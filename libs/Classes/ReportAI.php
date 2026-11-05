<?php
use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\RateLimitException;

/**
 * AI-written report narratives on top of ReportAnalysis. The analysis stays
 * the source of every fact, count and score. Per gap the model keeps four
 * kinds of statement apart: the recorded condition (only what the responses
 * say), potential risk scenarios (conditional, generic to the gap type),
 * suggested approaches (options) and facts to confirm. It also writes family
 * narratives, consolidated remediation initiatives, cross-control patterns,
 * the executive summary and a quality review that reads ReportQuality's
 * deterministic findings.
 *
 * Each section is generated only through an explicit action, validated here
 * (unknown refs dropped, figures not found in the facts rejected, markdown
 * stripped, list lengths bounded) and stored with a hash of its inputs, so an
 * unchanged section is never paid for twice and a changed one shows as stale.
 */
class ReportAI
{
    const PROMPT_VERSIONS = array(
        'domain' => '2026-11-26.1',
        'remediation' => '2026-11-26.1',
        'patterns' => '2026-11-26.1',
        'executive' => '2026-11-26.1',
        'review' => '2026-11-26.1'
    );
    const MAX_TOKENS = 32000;
    const TIMEOUT = 600;
    const MAX_SECTIONS_PER_RUN = 24;
    const DOMAIN_CHUNK = 5;

    const KINDS = array(
        'domain',
        'remediation',
        'patterns',
        'executive',
        'review'
    );

    const LABELS = array(
        'remediation' => 'Remediation initiatives',
        'patterns' => 'Cross-cutting patterns',
        'executive' => 'Executive summary',
        'review' => 'Quality review'
    );

    const SEVERITIES = array(
        'High',
        'Medium',
        'Low'
    );

    const CATEGORIES = array(
        'unsupported_assertion',
        'contradiction',
        'cross_report_inconsistency',
        'repetition',
        'overstatement',
        'other'
    );

    const PATTERN_KINDS = array(
        'governance',
        'technical',
        'operational',
        'documentation'
    );

    const SYSTEM_PROMPT = <<<'PROMPT'
You write sections of a formal cybersecurity assessment report prepared by an independent assessor for an organization that operates in, or serves, the aviation sector. The standard is a senior consultant's deliverable: precise, traceable to the recorded results, and useful to the people who must close the gaps. Precision and traceability matter more than length. You receive a JSON object of FACTS and return JSON that matches the required schema.

WHAT IS KNOWN
- The FACTS are the only source of information about the organization. Known facts are: the result recorded for each requirement and objective, assessor fields (notes, gap description, business impact, recommendation, owner, target date, implementation description), evidence titles, and counts and scores.
- A requirement's or objective's title, description and expectations are the framework's expectation, not a description of the organization. "PF10 requires encryption of sensitive data at rest" is the framework; "PF10 is recorded as not met" is the fact. Neither tells you which systems, data stores, tools, vendors, people or data flows the organization has.
- The assessor fields are empty unless present in FACTS. When they are empty, the reason for a result is unknown.

FOUR KINDS OF STATEMENT. Every statement you write is exactly one of these, and each goes only in the fields meant for it. The report labels them "Recorded condition", "Potential exposure", "Suggested approach" and "To be confirmed".
(a) Recorded condition: only what the recorded results and assessor fields say. Write it in the language of the record: "PF03 (removal of access when employment ends) is recorded as partially met", "the assessor noted ...", "no evidence is linked". You may name what an objective covers so the reader understands it, but attribute the expectation to the requirement ("which requires ...", "covering ...") and the status to the record ("is recorded as ...").
(b) Potential exposure: risk scenarios that follow from the type of gap, written as possibilities: "If ..., ... could ...", "Where ..., ... may ...". They describe what could happen to any organization with this kind of gap. They are never observed events and never describe the organization's actual systems, data or people.
(c) Suggested approach: options for closing the gap, phrased as suggestions ("Consider ...", "One option is ...", "For example, ..."). They describe what could be done, never what the organization currently has or does. Refer to generic categories ("the system of record for personnel changes, if one exists", "an identity provider or directory service, where used"), never to a specific client system as if it exists.
(d) To be confirmed: specific facts the assessor must confirm with the organization before the statements above can be relied on, such as which systems are in scope, whether a process exists informally, or what the partial result covers. Phrase each as an open question of fact ("Whether ...", "Which ...").

PROHIBITED unless the exact fact appears in an assessor field or evidence title:
- Naming or implying a product, vendor, platform or tool ("the organization uses Microsoft 365", "its Okta tenant", "AWS", "the SIEM").
- Presuming a system exists: "the organization's ERP / HR system / maintenance tracking system / identity provider / ticketing system / cloud environment / data center ...".
- Describing data flows or storage: "data flows from X to Y", "personal data is stored in ...", "records are sent to ...", "customer data is shared with ...".
- Describing what people do: "staff currently ...", "administrators share accounts", "users routinely ...", "managers approve informally".
- Claiming verification that did not happen: "the assessor observed / tested / verified / inspected / reviewed ...", "evidence shows ...", "was confirmed".
- Causal claims: "because ...", "due to ...", "stems from ...", "reflects a culture of ...", "the root cause is ..." about the organization. Without assessor text the cause is unknown; you may say a result "could reflect" one of several possibilities only inside a To be confirmed item.
- Absolute language about the organization: "no", "never", "none", "entirely", "completely", "all", "at all". The record shows results against requirements, not the full state of practice. Prefer "is recorded as not met".
- Describing a Partially Implemented requirement or objective as absent or missing ("has no", "does not", "lacks", "is missing", "not in place"). Partial means some elements are recorded as in place and others are not, or the element is incomplete or inconsistent. Only Not Implemented elements may be described as not in place, and still as recorded ("is recorded as not implemented").
- Describing anything recorded as Implemented as deficient.
- Stating or implying that an incident, breach, audit finding, regulatory action or loss has occurred.
- Referring to "the FACTS", "the input", "titles", field names or the data structure. Write for the report reader.

REQUIRED HEDGING
- Every scenario sentence contains a condition ("if", "where", "should", "unless") and a modal ("could", "may", "might", "would"). Every consequence uses "could", "may" or "would".
- Aviation context (flight or maintenance operations, airworthiness and maintenance records, passenger or crew data, safety reporting, operational technology, aviation authority oversight) may appear only as a condition: "Where the organization holds maintenance records, ...". Use it only where it is specific to the requirement, at most once per requirement, and never in a recorded condition.

NUMBERS AND RATINGS
- Do not assign or imply likelihood, impact, severity, risk ratings, priorities, scores, maturity levels, costs, effort, timeframes or deadlines. Those are calculated elsewhere.
- Do not write any digit that does not already appear in the FACTS: no durations ("within 72 hours"), counts you computed yourself, percentages, years or version numbers of external standards. Use "promptly", "periodically" or "at a defined frequency". Any figure not found in the FACTS is removed together with its field. When you quote a count, use the exact count from FACTS for the same scope (family counts for a family, overall counts for the assessment).

REFERENCES
- Cite requirements and objectives only by the exact refs in the FACTS ("ref" values, or an objective's "short" value when writing about its own requirement). Never invent refs, clause numbers or citations to other frameworks.
- Use the framework's own terminology from FACTS.terms for what it calls a control, an objective and a family.

WRITING
- Specific beats generic: tie each sentence to the requirement or objectives at hand. Avoid filler such as "it is important to", "robust", "comprehensive", "best practice", "holistic", "leverage", and never explain a requirement by restating its title.
- Do not reuse the same scenario, approach or sentence across requirements; vary them according to each requirement's purpose. Prefer fewer, sharper statements over more.
- Plain text only: no markdown, bullet characters, headings or numbering inside strings. Each array item is one complete sentence or a short group of sentences.
- US English, formal third person.

SECTION GUIDES

[domain] The FACTS describe one family and its gap requirements (gap_controls). Return:
- narrative: one or two paragraphs. First the recorded position: what the family is intended to achieve (as the framework's intent), which requirements are recorded as implemented (implemented_controls) and which are gaps, with counts from family_results only, and the state of evidence. Optionally a second paragraph on the potential significance of the gaps taken together, written entirely in conditional language. No statements about the organization's systems, staff or data.
- controls: exactly one entry per item in gap_controls, in the same order:
  - ref: the gap requirement's ref.
  - recorded_condition: two to four sentences (under 100 words), strictly the recorded condition: the requirement's result, which objectives are recorded as not met and which as partially met (by short ref, each with a few words on what it covers), met objectives where that clarifies the gap, assessor fields when present ("The assessor noted ..."), and whether evidence is linked. No inference about systems, causes or practices. If the requirement has no objectives, state the result and that the specific deficiency is not recorded.
  - control_purpose: one or two sentences on what this requirement is intended to achieve, faithful to its description and expectations.
  - risk_scenarios: two entries (three only when the deficient objectives clearly call for it), each tied to a deficient objective. scenario: one conditional sentence on how the missing or incomplete element could be exploited or fail, generic to the gap type. consequence: the potential operational, security, regulatory or customer consequence, conditional. No ratings.
  - suggested_approach: two to four options that would close the specific deficient objectives, phrased as suggestions ("Consider ...", "One option is ...", "For example, ..."), each naming the kind of process, artifact or technical control involved. Never "implement the control". Never presume a specific system exists.
  - confirm: one to three specific facts to confirm with the organization before the condition and approach can be relied on (for example, what the partially met element covers today, which systems are in scope, whether an informal practice exists). Each must be specific to this requirement.
  - expected_outcome: one sentence describing what an assessor would observe once the gap is closed.
  - validation_criteria: two or three statements of what an assessor would inspect, sample or test to confirm closure.
  - source_refs: the requirement and objective refs your text relies on.
- When FACTS.all_gap_controls is present, the family has more gaps than are detailed in gap_controls: write the narrative about the whole family using all_gap_controls, and write controls entries only for the requirements detailed in gap_controls.

[remediation] Consolidate all gap requirements into four to eight remediation initiatives a program manager could run. deterministic_initiatives groups the gaps by topic as a starting point: merge, split or retitle where a different grouping gives more coherent work. Every gap ref must appear in exactly one initiative. For each initiative:
- title: action-oriented and specific, no numbering.
- phase: one of FACTS.phases. Immediate priorities for direct-exposure gaps (access, privileged access, monitoring, incident response, vulnerability handling, data protection) and not implemented requirements with high derived priority; Foundational improvements for governance, policy, risk and inventory foundations that other work depends on; Operational control enhancements for operating processes; Program maturity for review cycles, metrics and refinement. Use the derived priority as a guide but do not state it.
- problem: two or three sentences stating the recorded gaps this initiative addresses: which requirements and objectives are recorded as not met or partially met (by ref and substance), then, conditionally, why they matter together. Recorded condition first; no description of the organization's environment.
- activities: three to five suggested activities in a sensible order, phrased as work to be done ("Define ...", "Establish ..."), never as a description of current practice.
- deliverables: two to four tangible artifacts.
- implementation_considerations: one or two sentences of suggestions on sequencing, ownership and constraints, conditional where they depend on the organization ("Where shift operations apply, ..."). No durations, no presumed systems.
- validation: two or three statements of how closure will be verified.
- controls: the gap refs covered.
- points: the open objective refs this initiative closes.
- depends_on: exact titles of other initiatives in your output that should come first; empty if none.

[patterns] Identify three to six cross-cutting patterns: a recurring kind of recorded result visible in two or more gap requirements, often across families (for example, review or monitoring objectives recorded as partially met across several requirements, documentation objectives recorded as not met, third-party elements recorded as open in several processes). For each: label (short noun phrase describing the recorded pattern, not a diagnosis), kind (governance, technical, operational or documentation), description (two or three sentences: which objectives are recorded with this result, what they have in common, and why addressing them together could be more effective than one by one), refs (the requirement refs, and objective refs, that show the pattern; at least two requirements; every requirement you name in the description must be in refs). Describe what the results have in common, not why they happened: no causal claims ("because", "due to", "stems from", "root cause") unless an assessor field states the cause. Do not restate one family's results as a pattern. themes and keyword_patterns are keyword groupings offered as hints only.

[executive] The executive summary for senior management and the board:
- overview: two or three paragraphs. First, what was assessed and the overall result in plain language, using the score and counts from FACTS and explaining that the score measures recorded implementation, not certification. Then the shape of the gaps as recorded (not implemented versus partially implemented, where they concentrate, what the recorded results have in common). Then the state of evidence and what it means for reliance on the results. Do not walk through every family.
- exposure_overview: one or two paragraphs for the Risk Assessment's own summary: the potential exposure and consequences that the recorded gaps could create for the organization's operations, data, customers and oversight, grouped by the main exposure areas and written entirely in conditional language. Use different wording from overview; do not repeat counts already given there.
- key_findings: four to six management-level findings, each with title (a short statement of the recorded issue), explanation (two or three sentences: what is recorded as missing or incomplete, then conditionally why it matters to the business) and refs (supporting gap requirement refs only). Cover the gaps with the highest derived priority.
- strengths: two to four specific statements of what is recorded as in place, based on implemented requirements and objectives recorded as met; no praise and no inference beyond the record.
- improvements: two to four statements of the most important areas needing improvement, worded differently from key_findings.
- strategic_recommendations: three to five, each with title, rationale (why this, why now, what it would enable) and refs. They must align with the initiatives in FACTS.
- management_considerations: three to five decisions or actions for management (ownership, resourcing, risk acceptance, evidence, follow-up assessment, facts to confirm). No dates or budgets.

[review] Act as the engagement quality reviewer. FACTS contain the recorded results per requirement and objective, the scoring method, the readiness issues, the assembled AI-written sections, and rule_findings: issues found by deterministic checks. Return issues, each with:
- severity: High (inconsistent with the recorded results, an unsupported client-specific claim, or a recommendation that does not address its finding), Medium (incomplete coverage, inconsistency between sections, overstatement, misaligned emphasis), Low (repetition, vague wording, style).
- category: unsupported_assertion (a statement about the organization's systems, tools, vendors, data flows, staff behaviour, causes or verification that the recorded results do not support), contradiction (text that conflicts with a recorded result, for example a partially met objective described as absent), cross_report_inconsistency (counts, priorities, initiative coverage or emphasis that disagree between sections), repetition (the same scenario, approach or paragraph repeated across requirements or sections), overstatement (conclusions stronger than the evidence and results allow, absolute language), other.
- section: the section key, for example "executive" or "domain:Privacy".
- description: what is wrong, where, and how to fix it, citing refs and quoting the problem words briefly.
- refs: the requirement or objective refs concerned (may be empty).
Check every recorded condition against the recorded results, scenarios for hedging, suggested approaches for presumed systems, consistency between sections, completeness (every gap covered by a finding and an initiative), and alignment between findings and recommendations. rule_findings are shown to the assessor separately: do not restate them; use them as leads and report similar problems they missed, or a rule finding you judge to be more serious than its severity. Return an empty list only when there is genuinely nothing to improve.
PROMPT;

    private $client;
    private $narratives_model;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? self::client();
        $this->narratives_model = new ReportNarrativesModel();
    }

    /**
     * The API client. $progress, when given, is called about once a second
     * while a request is in flight, so a background job can keep its
     * heartbeat during a long answer.
     */
    public static function client(?callable $progress = null): Client
    {
        $transport = array(
            'timeout' => self::TIMEOUT,
            'connect_timeout' => 15
        );

        if ($progress !== null) {
            $transport['progress'] = function () use ($progress) {
                $progress();
            };
        }

        return new Client(
            apiKey: AiAssistant::api_key(),
            requestOptions: array(
                'maxRetries' => 1,
                'transporter' => new \GuzzleHttp\Client($transport)
            )
        );
    }

    /* ----------------------------------------------------------- sections */

    /** The sections an assessment can have, in generation order: later sections read earlier ones. */
    public static function section_keys(array $analysis): array
    {
        $keys = array();

        foreach ($analysis['families'] as $name => $family) {
            if (count($family['gaps']) > 0) {
                $keys[] = 'domain:'.$name;
            }
        }

        if (count($analysis['gaps']) > 0) {
            $keys[] = 'remediation';
            $keys[] = 'patterns';
        }

        $keys[] = 'executive';

        if (count($analysis['gaps']) > 0) {
            $keys[] = 'review';
        }

        return $keys;
    }

    public static function kind(string $section_key): string
    {
        return strpos($section_key, 'domain:') === 0 ? 'domain' : $section_key;
    }

    public static function label(string $section_key): string
    {
        if (self::kind($section_key) === 'domain') {
            return substr($section_key, 7);
        }

        return self::LABELS[$section_key] ?? $section_key;
    }

    /* ------------------------------------------------------------- inputs */

    /**
     * The facts sent for one section. $current holds the stored content of
     * sections that are current and not rejected, keyed by section key; the
     * executive summary and the review read it, so their inputs (and hashes)
     * change when the sections they build on change.
     */
    public static function input(array $analysis, string $section_key, array $current): ?array
    {
        $base = array(
            'section' => $section_key,
            'framework' => $analysis['standard'],
            'terms' => $analysis['terms']
        );

        switch (self::kind($section_key)) {
            case 'domain':
                $facts = self::domain_facts($analysis, substr($section_key, 7));
                break;
            case 'remediation':
                $facts = self::remediation_facts($analysis);
                break;
            case 'patterns':
                $facts = self::pattern_facts($analysis);
                break;
            case 'executive':
                $facts = self::executive_facts($analysis, $current);
                break;
            case 'review':
                $facts = self::review_facts($analysis, $current);
                break;
            default:
                $facts = null;
        }

        return $facts === null ? null : array_merge($base, $facts);
    }

    private static function domain_facts(array $analysis, string $name): ?array
    {
        $family = $analysis['families'][$name] ?? null;

        if ($family === null || count($family['gaps']) === 0) {
            return null;
        }

        $implemented = array();
        $other = array();

        foreach ($family['controls'] as $control) {
            if ($control['result'] === 'Implemented') {
                $implemented[] = array(
                    'ref' => $control['ref'],
                    'title' => $control['title']
                );
            } elseif (!$control['is_gap']) {
                $other[] = array(
                    'ref' => $control['ref'],
                    'title' => $control['title'],
                    'result' => $control['result']
                );
            }
        }

        $counts = array_filter($family['counts']);

        return array(
            'family' => $name,
            'family_results' => array(
                'controls' => count($family['controls']),
                'gap_count' => count($family['gaps']),
                'counts' => $counts,
                'score' => $family['score']
            ),
            'implemented_controls' => $implemented,
            'other_controls' => $other,
            'gap_controls' => array_map(function ($control) {
                return self::control_facts($control);
            }, $family['gaps'])
        );
    }

    private static function control_facts(array $control): array
    {
        $expectations = array();
        foreach ($control['expectations'] as $pair) {
            $expectations[] = ($pair[0] !== '' ? $pair[0].': ' : '').$pair[1];
        }

        $objectives = array();
        foreach ($control['objectives'] as $objective) {
            $row = array(
                'ref' => $objective['ref'],
                'short' => $objective['short'],
                'title' => $objective['title'],
                'description' => $objective['description'],
                'result' => $objective['result']
            );
            if (count($objective['assessor']) > 0) {
                $row['assessor'] = $objective['assessor'];
            }
            if (count($objective['evidence']) > 0) {
                $row['evidence'] = $objective['evidence'];
            }
            $objectives[] = $row;
        }

        $facts = array(
            'ref' => $control['ref'],
            'title' => $control['title'],
            'description' => $control['description'],
            'result' => $control['result'],
            'topic' => ReportKnowledge::topic($control['topic'])['name'],
            'expectations' => $expectations,
            'objectives' => $objectives,
            'evidence' => $control['evidence']
        );

        if (count($control['assessor']) > 0) {
            $facts['assessor'] = $control['assessor'];
        }

        if ($control['risk_rating'] !== null) {
            $facts['assessor_risk_rating'] = $control['risk_rating'];
        }

        return $facts;
    }

    private static function open_objectives(array $control, bool $with_description): array
    {
        $out = array();

        foreach ($control['open'] as $objective) {
            $row = array(
                'ref' => $objective['ref'],
                'title' => $objective['title'],
                'result' => $objective['result']
            );
            if ($with_description) {
                $row['description'] = $objective['description'];
            }
            $out[] = $row;
        }

        return $out;
    }

    private static function remediation_facts(array $analysis): ?array
    {
        if (count($analysis['gaps']) === 0) {
            return null;
        }

        $gaps = array();
        foreach ($analysis['gaps'] as $gap) {
            $row = array(
                'ref' => $gap['ref'],
                'title' => $gap['title'],
                'family' => $gap['family'],
                'result' => $gap['result'],
                'derived_priority' => $gap['priority'],
                'topic' => ReportKnowledge::topic($gap['topic'])['name'],
                'open_objectives' => self::open_objectives($gap, false)
            );
            if (count($gap['assessor']) > 0) {
                $row['assessor'] = $gap['assessor'];
            }
            $gaps[] = $row;
        }

        $initiatives = array();
        foreach ($analysis['initiatives'] as $initiative) {
            $initiatives[] = array(
                'title' => $initiative['title'],
                'phase' => $initiative['phase'],
                'derived_priority' => $initiative['priority'],
                'controls' => $initiative['controls']
            );
        }

        return array(
            'phases' => ReportAnalysis::PHASES,
            'gaps' => $gaps,
            'deterministic_initiatives' => $initiatives
        );
    }

    private static function pattern_facts(array $analysis): ?array
    {
        if (count($analysis['gaps']) === 0) {
            return null;
        }

        $gaps = array();
        foreach ($analysis['gaps'] as $gap) {
            $gaps[] = array(
                'ref' => $gap['ref'],
                'title' => $gap['title'],
                'family' => $gap['family'],
                'result' => $gap['result'],
                'open_objectives' => self::open_objectives($gap, true)
            );
        }

        $themes = array();
        foreach ($analysis['themes'] as $theme) {
            $themes[] = array(
                'name' => $theme['name'],
                'refs' => $theme['refs']
            );
        }

        $patterns = array();
        foreach ($analysis['patterns'] as $pattern) {
            $patterns[] = array(
                'label' => $pattern['label'],
                'controls' => $pattern['controls']
            );
        }

        return array(
            'gaps' => $gaps,
            'themes' => $themes,
            'keyword_patterns' => $patterns
        );
    }

    private static function executive_facts(array $analysis, array $current): array
    {
        $c = $analysis['counts'];

        $families = array();
        foreach ($analysis['families'] as $name => $family) {
            $families[] = array(
                'name' => $name,
                'controls' => count($family['controls']),
                'score' => $family['score'],
                'counts' => array_filter($family['counts']),
                'gap_refs' => array_column($family['gaps'], 'ref')
            );
        }

        $implemented = array();
        foreach ($analysis['controls'] as $control) {
            if ($control['result'] === 'Implemented') {
                $implemented[] = array(
                    'ref' => $control['ref'],
                    'title' => $control['title'],
                    'family' => $control['family']
                );
            }
        }

        $gaps = array();
        foreach ($analysis['gaps'] as $gap) {
            $gaps[] = array(
                'ref' => $gap['ref'],
                'title' => $gap['title'],
                'family' => $gap['family'],
                'result' => $gap['result'],
                'derived_priority' => $gap['priority'],
                'objectives' => count($gap['objectives']),
                'open_objectives' => array_map(function ($objective) {
                    return array(
                        'ref' => $objective['ref'],
                        'title' => $objective['title'],
                        'result' => $objective['result']
                    );
                }, $gap['open'])
            );
        }

        $themes = array();
        foreach ($analysis['themes'] as $theme) {
            $themes[] = array(
                'name' => $theme['name'],
                'refs' => $theme['refs']
            );
        }

        $patterns = array();
        if (isset($current['patterns']['patterns'])) {
            foreach ($current['patterns']['patterns'] as $pattern) {
                $patterns[] = array(
                    'label' => (string) ($pattern['label'] ?? ''),
                    'refs' => $pattern['refs'] ?? array()
                );
            }
        } else {
            foreach ($analysis['patterns'] as $pattern) {
                $patterns[] = array(
                    'label' => $pattern['label'],
                    'refs' => $pattern['controls']
                );
            }
        }

        $initiatives = array();
        if (isset($current['remediation']['initiatives'])) {
            foreach ($current['remediation']['initiatives'] as $initiative) {
                $initiatives[] = array(
                    'title' => (string) ($initiative['title'] ?? ''),
                    'phase' => (string) ($initiative['phase'] ?? ''),
                    'controls' => $initiative['controls'] ?? array()
                );
            }
        } else {
            foreach ($analysis['initiatives'] as $initiative) {
                $initiatives[] = array(
                    'title' => $initiative['title'],
                    'phase' => $initiative['phase'],
                    'controls' => $initiative['controls']
                );
            }
        }

        $facts = array(
            'results' => array(
                'controls' => $analysis['total'],
                'assessed' => $analysis['assessed'],
                'applicable' => $analysis['applicable'],
                'counts' => $c,
                'gap_total' => $c['Not Implemented'] + $c['Partially Implemented'],
                'implementation_score' => $analysis['score'],
                'score_basis' => 'implemented counts in full, partially implemented counts half, over applicable assessed controls',
                'controls_with_evidence' => $analysis['with_evidence'],
                'evidence_files' => count($analysis['evidence_files'])
            ),
            'families' => $families,
            'implemented' => $implemented,
            'gaps' => $gaps,
            'themes' => $themes,
            'patterns' => $patterns,
            'initiatives' => $initiatives,
            'readiness_issues' => array_map(function ($issue) {
                return $issue[1];
            }, $analysis['readiness'])
        );

        if (count($analysis['assessor']) > 0) {
            $facts['assessor'] = $analysis['assessor'];
        }

        return $facts;
    }

    private static function review_facts(array $analysis, array $current): ?array
    {
        if (count($analysis['gaps']) === 0) {
            return null;
        }

        $c = $analysis['counts'];
        $gaps = array();
        foreach ($analysis['gaps'] as $gap) {
            $row = array(
                'ref' => $gap['ref'],
                'title' => $gap['title'],
                'family' => $gap['family'],
                'result' => $gap['result'],
                'derived_priority' => $gap['priority'],
                'evidence_count' => $gap['evidence_count'],
                'objectives' => array_map(function ($objective) {
                    return array(
                        'ref' => $objective['ref'],
                        'title' => $objective['title'],
                        'result' => $objective['result']
                    );
                }, $gap['objectives'])
            );
            if (count($gap['assessor']) > 0) {
                $row['assessor'] = $gap['assessor'];
            }
            $gaps[] = $row;
        }

        $implemented = array();
        foreach ($analysis['controls'] as $control) {
            if (!$control['is_gap']) {
                $implemented[] = array(
                    'ref' => $control['ref'],
                    'result' => $control['result']
                );
            }
        }

        $sections = array();
        foreach ($current as $key => $content) {
            if ($key !== 'review') {
                $sections[$key] = $content;
            }
        }

        $rule_findings = array();
        foreach (ReportQuality::check($analysis, $sections) as $issue) {
            $rule_findings[] = array(
                'severity' => $issue['severity'],
                'category' => $issue['category'],
                'section' => $issue['section'],
                'description' => $issue['description']
            );
        }

        return array(
            'results' => array(
                'counts' => $c,
                'gap_total' => $c['Not Implemented'] + $c['Partially Implemented'],
                'implementation_score' => $analysis['score'],
                'scoring_method' => 'Implementation score = (implemented + 0.5 x partially implemented) / (implemented + partially implemented + not implemented), as a percentage; not applicable and not assessed criteria are excluded. It measures recorded implementation, not a compliance opinion.',
                'controls_with_evidence' => $analysis['with_evidence'],
                'evidence_files' => count($analysis['evidence_files'])
            ),
            'gaps' => $gaps,
            'other_controls' => $implemented,
            'readiness_issues' => array_map(function ($issue) {
                return $issue[1];
            }, $analysis['readiness']),
            'sections' => $sections,
            'rule_findings' => array_slice($rule_findings, 0, 60)
        );
    }

    /* --------------------------------------------------------------- hash */

    /** sha256 over the canonical input, the model and the prompt version of the section's kind. */
    public static function hash(?array $input, string $section_key): string
    {
        return hash('sha256', self::canonical(array(
            'section' => $section_key,
            'model' => AiAssistant::MODEL,
            'prompt' => self::PROMPT_VERSIONS[self::kind($section_key)] ?? '',
            'input' => $input
        )));
    }

    public static function canonical($value): string
    {
        return json_encode(self::sorted($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function sorted($value)
    {
        if (!is_array($value)) {
            return $value;
        }

        $out = array();
        foreach ($value as $key => $item) {
            $out[$key] = self::sorted($item);
        }

        if (!array_is_list($out)) {
            ksort($out, SORT_STRING);
        }

        return $out;
    }

    /* ------------------------------------------------------------- schema */

    public static function schema(string $kind): array
    {
        switch ($kind) {
            case 'domain':
                return self::object(array(
                    'narrative' => self::strings('One to three paragraphs on the family as a whole.'),
                    'controls' => array(
                        'type' => 'array',
                        'items' => self::object(array(
                            'ref' => self::text_field('The gap requirement ref.'),
                            'recorded_condition' => self::text_field('Strictly what the recorded results and assessor fields say.'),
                            'control_purpose' => self::text_field('What the requirement is intended to achieve.'),
                            'risk_scenarios' => array(
                                'type' => 'array',
                                'items' => self::object(array(
                                    'scenario' => self::text_field('Conditional sentence: how the missing element could be exploited or fail.'),
                                    'consequence' => self::text_field('The potential consequence, conditional.')
                                ))
                            ),
                            'suggested_approach' => self::strings('Options for closing the deficient objectives, phrased as suggestions.'),
                            'confirm' => self::strings('Facts to confirm with the organization before relying on the text.'),
                            'expected_outcome' => self::text_field('What an assessor would observe once remediated.'),
                            'validation_criteria' => self::strings('What an assessor would inspect, sample or test.'),
                            'source_refs' => self::strings('Requirement and objective refs relied on.')
                        ))
                    )
                ));
            case 'remediation':
                return self::object(array(
                    'initiatives' => array(
                        'type' => 'array',
                        'items' => self::object(array(
                            'title' => self::text_field('Action-oriented initiative title.'),
                            'phase' => array(
                                'type' => 'string',
                                'enum' => ReportAnalysis::PHASES
                            ),
                            'problem' => self::text_field('The recorded gaps addressed, then conditionally why they matter together.'),
                            'activities' => self::strings('Suggested activities in order.'),
                            'deliverables' => self::strings('Tangible artifacts.'),
                            'implementation_considerations' => self::text_field('Suggestions on sequencing, ownership and constraints.'),
                            'validation' => self::strings('How closure will be verified.'),
                            'controls' => self::strings('Gap refs covered.'),
                            'points' => self::strings('Open objective refs closed.'),
                            'depends_on' => self::strings('Titles of initiatives that should come first.')
                        ))
                    )
                ));
            case 'patterns':
                return self::object(array(
                    'patterns' => array(
                        'type' => 'array',
                        'items' => self::object(array(
                            'label' => self::text_field('Short noun phrase describing the recorded pattern.'),
                            'kind' => array(
                                'type' => 'string',
                                'enum' => self::PATTERN_KINDS
                            ),
                            'description' => self::text_field('Which objectives show it, what they share, why address them together.'),
                            'refs' => self::strings('Supporting requirement and objective refs.')
                        ))
                    )
                ));
            case 'executive':
                return self::object(array(
                    'overview' => self::strings('Two or three paragraphs on the recorded result.'),
                    'exposure_overview' => self::strings('One or two conditional paragraphs on potential exposure and consequences.'),
                    'key_findings' => array(
                        'type' => 'array',
                        'items' => self::object(array(
                            'title' => self::text_field('Short statement of the issue.'),
                            'explanation' => self::text_field('What is missing and why it matters.'),
                            'refs' => self::strings('Supporting requirement refs.')
                        ))
                    ),
                    'strengths' => self::strings('Specific strengths.'),
                    'improvements' => self::strings('Most important improvement areas.'),
                    'strategic_recommendations' => array(
                        'type' => 'array',
                        'items' => self::object(array(
                            'title' => self::text_field('Recommendation title.'),
                            'rationale' => self::text_field('Why this, why now, what it enables.'),
                            'refs' => self::strings('Supporting requirement refs.')
                        ))
                    ),
                    'management_considerations' => self::strings('Decisions or actions for management.')
                ));
            case 'review':
                return self::object(array(
                    'issues' => array(
                        'type' => 'array',
                        'items' => self::object(array(
                            'severity' => array(
                                'type' => 'string',
                                'enum' => self::SEVERITIES
                            ),
                            'category' => array(
                                'type' => 'string',
                                'enum' => self::CATEGORIES
                            ),
                            'section' => self::text_field('Section key.'),
                            'description' => self::text_field('What is wrong, where, and how to fix it.'),
                            'refs' => self::strings('Requirement or objective refs concerned.')
                        ))
                    )
                ));
        }

        throw new InvalidArgumentException('Unknown section kind: '.$kind);
    }

    private static function object(array $properties): array
    {
        return array(
            'type' => 'object',
            'properties' => $properties,
            'required' => array_keys($properties),
            'additionalProperties' => false
        );
    }

    private static function text_field(string $description): array
    {
        return array(
            'type' => 'string',
            'description' => $description
        );
    }

    private static function strings(string $description): array
    {
        return array(
            'type' => 'array',
            'description' => $description,
            'items' => array(
                'type' => 'string'
            )
        );
    }

    /* ---------------------------------------------------------- generation */

    /**
     * Generate one section. An existing row written from the same inputs is
     * kept without calling the API, unless the assessor rejected it or
     * $force is set. $reviewer_notes are quality-review issues the new text
     * must resolve; they go to the model beside the facts but do not change
     * the input hash, so the section stays current for the same facts.
     * A failure carries 'retryable' (rate limit, connection, server error,
     * refusal, truncated or unreadable answer).
     */
    public function generate(int $assessment_id, int $company_id, string $section_key, int $user_id, bool $force = false, array $reviewer_notes = array()): array
    {
        $analysis = (new ReportAnalysis($assessment_id, $company_id))->build();

        if ($analysis === null) {
            return array(
                'success' => false,
                'message' => 'That assessment could not be found'
            );
        }

        $states = ReportNarratives::states($analysis, $this->narratives_model->load_narratives($assessment_id, $company_id));

        if (!isset($states[$section_key]) || $states[$section_key]['input'] === null) {
            return array(
                'success' => false,
                'message' => 'That section does not apply to this assessment',
                'retryable' => false,
                'not_applicable' => true
            );
        }

        $state = $states[$section_key];
        $reviewer_notes = array_values(array_filter(array_map('strval', $reviewer_notes), function ($note) {
            return trim($note) !== '';
        }));

        if (!$force && count($reviewer_notes) === 0 && $state['state'] === 'current' && $state['narrative_status'] !== 'Rejected') {
            return array(
                'success' => true,
                'message' => 'ok',
                'cached' => true,
                'input_tokens' => 0,
                'output_tokens' => 0,
                'rejected' => $state['rejected']
            );
        }

        $kind = self::kind($section_key);
        $answer = $kind === 'domain' && count($state['input']['gap_controls']) > self::DOMAIN_CHUNK ? $this->call_domain_chunks($state['input'], $reviewer_notes) : $this->call($kind, $state['input'], $reviewer_notes);

        if (!$answer['success']) {
            return $answer;
        }

        $checked = self::validate($kind, $answer['output'], $state['input']);

        $this->narratives_model->save_generated(
            $company_id,
            $assessment_id,
            $section_key,
            $state['hash'],
            json_encode($checked['content'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            json_encode($checked['rejected'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            count($reviewer_notes) > 0 ? json_encode($reviewer_notes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            AiAssistant::MODEL,
            $answer['input_tokens'],
            $answer['output_tokens'],
            $user_id
        );

        error_log('[report-ai] assessment '.$assessment_id.' '.$section_key.(count($reviewer_notes) > 0 ? ' (revised, '.count($reviewer_notes).' notes)' : '').': in '.$answer['input_tokens'].' (cache read '.$answer['cache_read'].', cache write '.$answer['cache_write'].'), out '.$answer['output_tokens'].', rejected '.count($checked['rejected']));

        return array(
            'success' => true,
            'message' => 'ok',
            'cached' => false,
            'input_tokens' => $answer['input_tokens'],
            'output_tokens' => $answer['output_tokens'],
            'rejected' => $checked['rejected']
        );
    }

    /** Generate every missing or stale section in order, up to the per-run cap. */
    public function generate_all(int $assessment_id, int $company_id, int $user_id): array
    {
        $analysis = (new ReportAnalysis($assessment_id, $company_id))->build();

        if ($analysis === null) {
            return array();
        }

        $results = array();
        $calls = 0;

        foreach (self::section_keys($analysis) as $section_key) {
            if ($calls >= self::MAX_SECTIONS_PER_RUN) {
                $results[$section_key] = array(
                    'success' => false,
                    'message' => 'Skipped: the per-run limit was reached'
                );
                continue;
            }
            $result = $this->generate($assessment_id, $company_id, $section_key, $user_id);
            if (empty($result['cached'])) {
                $calls++;
            }
            $results[$section_key] = $result;
        }

        return $results;
    }

    private function call(string $kind, array $input, array $reviewer_notes = array()): array
    {
        try {
            $response = $this->client->beta->messages->create(
                model: AiAssistant::MODEL,
                maxTokens: self::MAX_TOKENS,
                system: array(
                    array(
                        'type' => 'text',
                        'text' => self::SYSTEM_PROMPT,
                        'cacheControl' => array(
                            'type' => 'ephemeral'
                        )
                    )
                ),
                messages: array(
                    array(
                        'role' => 'user',
                        'content' => self::user_message($kind, $input, $reviewer_notes)
                    )
                ),
                outputConfig: array(
                    'effort' => 'high',
                    'format' => array(
                        'type' => 'json_schema',
                        'schema' => self::schema($kind)
                    )
                ),
                fallbacks: 'default',
                betas: array(
                    'server-side-fallback-2026-07-01'
                )
            );
        } catch (RateLimitException $e) {
            return self::failure('The AI service is busy right now. Try again in a minute.', true);
        } catch (APIStatusException $e) {
            error_log('[report-ai] '.$e->getMessage());
            return self::failure('The AI service could not write this section. Try again.', self::retryable_status($e->status));
        } catch (APIConnectionException $e) {
            error_log('[report-ai] '.$e->getMessage());
            return self::failure('The AI service could not be reached. Try again.', true);
        } catch (\JsonException $e) {
            error_log('[report-ai] unreadable response: '.$e->getMessage());
            return self::failure('The AI service sent an answer that could not be read. Try again.', true);
        }

        if ($response->stopReason === 'refusal') {
            return self::failure('The AI service declined to write this section; the calculated text will be used.', true);
        }

        if ($response->stopReason === 'max_tokens') {
            return self::failure('The section was too long to complete; the calculated text will be used.', true);
        }

        $text = '';
        foreach ($response->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        $output = json_decode($text, true);

        if (!is_array($output)) {
            return self::failure('The AI service sent an answer that could not be read. Try again.', true);
        }

        return array(
            'success' => true,
            'output' => $output,
            'input_tokens' => (int) $response->usage->inputTokens + (int) $response->usage->cacheReadInputTokens + (int) $response->usage->cacheCreationInputTokens,
            'output_tokens' => (int) $response->usage->outputTokens,
            'cache_read' => (int) $response->usage->cacheReadInputTokens,
            'cache_write' => (int) $response->usage->cacheCreationInputTokens
        );
    }

    /**
     * A family with many gaps is written in parts so no single answer runs
     * past the token limit. Every part sees the whole family's gap list; the
     * first part's narrative is kept and the control entries are joined.
     */
    private function call_domain_chunks(array $input, array $reviewer_notes = array()): array
    {
        $all = array();
        foreach ($input['gap_controls'] as $control) {
            $all[] = array(
                'ref' => $control['ref'],
                'title' => $control['title'],
                'result' => $control['result'],
                'open_objectives' => $control['open_objectives'] ?? ($control['objectives'] ?? array())
            );
        }

        $merged = array(
            'success' => true,
            'output' => array(
                'narrative' => array(),
                'controls' => array()
            ),
            'input_tokens' => 0,
            'output_tokens' => 0,
            'cache_read' => 0,
            'cache_write' => 0
        );

        foreach (array_chunk($input['gap_controls'], self::DOMAIN_CHUNK) as $n => $chunk) {
            $part = $input;
            $part['all_gap_controls'] = $all;
            $part['gap_controls'] = $chunk;
            $part['part_note'] = 'This family is written in several parts. Write control entries only for gap_controls, but write the narrative for the whole family using all_gap_controls, which lists every gap in the family with its open points. Never say that any control or its points are missing from or not detailed in this section.';

            $answer = $this->call('domain', $part, $reviewer_notes);

            if (!$answer['success']) {
                return $answer;
            }

            if ($n === 0) {
                $merged['output']['narrative'] = $answer['output']['narrative'] ?? array();
            }
            foreach (self::items($answer['output']['controls'] ?? null) as $entry) {
                $merged['output']['controls'][] = $entry;
            }
            foreach (array(
                'input_tokens',
                'output_tokens',
                'cache_read',
                'cache_write'
            ) as $key) {
                $merged[$key] += $answer[$key];
            }
        }

        return $merged;
    }

    private static function failure(string $message, bool $retryable = false): array
    {
        return array(
            'success' => false,
            'message' => $message,
            'retryable' => $retryable
        );
    }

    /** Rate limits, overload and server errors are worth another try; other client errors are not. */
    public static function retryable_status(?int $status): bool
    {
        return $status === null || $status === 408 || $status === 409 || $status === 429 || $status >= 500;
    }

    /**
     * The user turn: the facts, and when the section is being revised, the
     * quality reviewer's notes it must resolve without leaving the facts.
     */
    public static function user_message(string $kind, array $input, array $reviewer_notes = array()): string
    {
        $message = 'Write the ['.$kind.'] section following its section guide.'."\n\n".'FACTS:'."\n".json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (count($reviewer_notes) > 0) {
            $message .= "\n\n".'REVIEWER NOTES TO RESOLVE:'."\n".'The engagement quality reviewer raised these issues with the previous version of this section. Rewrite the whole section so each one is resolved. Use only the FACTS above; where a note asks for something the facts do not support, leave it out rather than invent it.'."\n".implode("\n", array_map(function ($note) {
                return '- '.$note;
            }, $reviewer_notes));
        }

        return $message;
    }

    /* ---------------------------------------------------------- validation */

    /**
     * Clean a decoded model answer against the facts it was written from.
     * Returns the content in the schema's shape, with rejected fields set to
     * null (or dropped from lists), and a list of what was rejected and why.
     */
    public static function validate(string $kind, array $output, array $input): array
    {
        $known = self::known_tokens($input);
        $index = self::ref_index($input);
        $rejected = array();

        if ($kind !== 'review') {
            $known['#products'] = ReportQuality::named_products(json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        switch ($kind) {
            case 'domain':
                $content = self::validate_domain($output, $input, $known, $index, $rejected);
                break;
            case 'remediation':
                $content = self::validate_remediation($output, $input, $known, $index, $rejected);
                break;
            case 'patterns':
                $content = self::validate_patterns($output, $known, $index, $rejected);
                break;
            case 'executive':
                $content = self::validate_executive($output, $known, $index, $rejected);
                break;
            case 'review':
                $content = self::validate_review($output, $input, $known, $index, $rejected);
                break;
            default:
                throw new InvalidArgumentException('Unknown section kind: '.$kind);
        }

        return array(
            'content' => $content,
            'rejected' => $rejected
        );
    }

    private static function validate_domain(array $output, array $input, array $known, array $index, array &$rejected): array
    {
        $gap_refs = array();
        foreach ($input['gap_controls'] ?? array() as $control) {
            $gap_refs[strtolower($control['ref'])] = $control['ref'];
        }

        $controls = array();
        $seen = array();

        foreach (self::items($output['controls'] ?? null) as $i => $entry) {
            $ref = $gap_refs[strtolower(trim((string) ($entry['ref'] ?? '')))] ?? null;
            if ($ref === null || isset($seen[$ref])) {
                $rejected[] = 'controls['.$i.'].ref: unknown or repeated "'.(string) ($entry['ref'] ?? '').'"';
                continue;
            }
            $seen[$ref] = true;
            $path = 'controls.'.$ref;

            $scenarios = array();
            foreach (self::items($entry['risk_scenarios'] ?? null) as $j => $pair) {
                $scenario = self::clean_text($pair['scenario'] ?? null, $path.'.risk_scenarios['.$j.'].scenario', $known, $rejected);
                $consequence = self::clean_text($pair['consequence'] ?? null, $path.'.risk_scenarios['.$j.'].consequence', $known, $rejected);
                if ($scenario === null || $scenario === '' || $consequence === null || $consequence === '') {
                    continue;
                }
                if (!ReportQuality::hedged($scenario) || !ReportQuality::hedged($consequence)) {
                    $rejected[] = $path.'.risk_scenarios['.$j.']: dropped, not written as a possibility';
                    continue;
                }
                $scenarios[] = array(
                    'scenario' => $scenario,
                    'consequence' => $consequence
                );
            }

            $controls[] = array(
                'ref' => $ref,
                'recorded_condition' => self::clean_text($entry['recorded_condition'] ?? null, $path.'.recorded_condition', $known, $rejected),
                'control_purpose' => self::clean_text($entry['control_purpose'] ?? null, $path.'.control_purpose', $known, $rejected),
                'risk_scenarios' => self::bounded($scenarios, 3, $path.'.risk_scenarios', $rejected),
                'suggested_approach' => self::clean_list($entry['suggested_approach'] ?? null, 4, $path.'.suggested_approach', $known, $rejected),
                'confirm' => self::clean_list($entry['confirm'] ?? null, 3, $path.'.confirm', $known, $rejected),
                'expected_outcome' => self::clean_text($entry['expected_outcome'] ?? null, $path.'.expected_outcome', $known, $rejected),
                'validation_criteria' => self::clean_list($entry['validation_criteria'] ?? null, 3, $path.'.validation_criteria', $known, $rejected),
                'source_refs' => self::clean_refs($entry['source_refs'] ?? null, $index, $ref, $path.'.source_refs', $rejected)
            );
        }

        foreach ($gap_refs as $ref) {
            if (!isset($seen[$ref])) {
                $rejected[] = 'controls: no entry for '.$ref;
            }
        }

        return array(
            'narrative' => self::clean_list($output['narrative'] ?? null, 2, 'narrative', $known, $rejected),
            'controls' => $controls
        );
    }

    private static function validate_remediation(array $output, array $input, array $known, array $index, array &$rejected): array
    {
        $gap_refs = array();
        foreach ($input['gaps'] ?? array() as $gap) {
            $gap_refs[strtolower($gap['ref'])] = $gap['ref'];
        }

        $initiatives = array();
        $covered = array();

        foreach (self::items($output['initiatives'] ?? null) as $i => $entry) {
            $title = self::clean_text($entry['title'] ?? null, 'initiatives['.$i.'].title', $known, $rejected);
            if ($title === null || $title === '') {
                continue;
            }
            $path = 'initiatives.'.$title;

            $controls = array();
            foreach (self::items($entry['controls'] ?? null) as $value) {
                $ref = $gap_refs[strtolower(trim((string) $value))] ?? null;
                if ($ref === null) {
                    $rejected[] = $path.'.controls: unknown ref "'.(string) $value.'"';
                } elseif (isset($covered[$ref])) {
                    $rejected[] = $path.'.controls: '.$ref.' already covered by "'.$covered[$ref].'"';
                } else {
                    $covered[$ref] = $title;
                    $controls[] = $ref;
                }
            }

            if (count($controls) === 0) {
                $rejected[] = $path.': dropped, no valid controls';
                continue;
            }

            $points = array();
            foreach (self::clean_refs($entry['points'] ?? null, $index, null, $path.'.points', $rejected) as $ref) {
                if (in_array($index['parent'][$ref] ?? null, $controls, true)) {
                    $points[] = $ref;
                } else {
                    $rejected[] = $path.'.points: '.$ref.' is not an objective of the covered controls';
                }
            }

            $phase = in_array($entry['phase'] ?? '', ReportAnalysis::PHASES, true) ? $entry['phase'] : ReportAnalysis::PHASES[1];

            $initiatives[] = array(
                'title' => $title,
                'phase' => $phase,
                'problem' => self::clean_text($entry['problem'] ?? null, $path.'.problem', $known, $rejected),
                'activities' => self::clean_list($entry['activities'] ?? null, 5, $path.'.activities', $known, $rejected),
                'deliverables' => self::clean_list($entry['deliverables'] ?? null, 4, $path.'.deliverables', $known, $rejected),
                'implementation_considerations' => self::clean_text($entry['implementation_considerations'] ?? null, $path.'.implementation_considerations', $known, $rejected),
                'validation' => self::clean_list($entry['validation'] ?? null, 3, $path.'.validation', $known, $rejected),
                'controls' => $controls,
                'points' => $points,
                'depends_on' => self::items($entry['depends_on'] ?? null)
            );
        }

        if (count($initiatives) > 10) {
            $rejected[] = 'initiatives: more than ten returned';
        }

        $titles = array_column($initiatives, 'title');
        foreach ($initiatives as $n => $initiative) {
            $depends = array();
            foreach ($initiative['depends_on'] as $value) {
                $value = trim((string) $value);
                if (in_array($value, $titles, true) && $value !== $initiative['title']) {
                    $depends[] = $value;
                } else {
                    $rejected[] = 'initiatives.'.$initiative['title'].'.depends_on: unknown "'.$value.'"';
                }
            }
            $initiatives[$n]['depends_on'] = array_values(array_unique($depends));
        }

        usort($initiatives, function ($x, $y) {
            return array_search($x['phase'], ReportAnalysis::PHASES, true) <=> array_search($y['phase'], ReportAnalysis::PHASES, true);
        });

        $uncovered = array_values(array_diff(array_values($gap_refs), array_keys($covered)));
        if (count($uncovered) > 0) {
            $rejected[] = 'initiatives: not covered '.implode(', ', $uncovered);
        }

        return array(
            'initiatives' => $initiatives,
            'uncovered' => $uncovered
        );
    }

    private static function validate_patterns(array $output, array $known, array $index, array &$rejected): array
    {
        $patterns = array();

        foreach (self::items($output['patterns'] ?? null) as $i => $entry) {
            $label = self::clean_text($entry['label'] ?? null, 'patterns['.$i.'].label', $known, $rejected);
            $description = self::clean_text($entry['description'] ?? null, 'patterns['.$i.'].description', $known, $rejected);
            $refs = self::clean_refs($entry['refs'] ?? null, $index, null, 'patterns['.$i.'].refs', $rejected);
            $controls = array();
            foreach ($refs as $ref) {
                $controls[$index['parent'][$ref] ?? $ref] = true;
            }

            if ($label === null || $label === '' || $description === null || $description === '' || count($controls) < 2) {
                $rejected[] = 'patterns['.$i.']: dropped (needs a label, a description and at least two controls)';
                continue;
            }

            $patterns[] = array(
                'label' => $label,
                'kind' => in_array($entry['kind'] ?? '', self::PATTERN_KINDS, true) ? $entry['kind'] : 'governance',
                'description' => $description,
                'refs' => $refs
            );
        }

        return array(
            'patterns' => self::bounded($patterns, 6, 'patterns', $rejected)
        );
    }

    private static function validate_executive(array $output, array $known, array $index, array &$rejected): array
    {
        $findings = array();
        foreach (self::items($output['key_findings'] ?? null) as $i => $entry) {
            $title = self::clean_text($entry['title'] ?? null, 'key_findings['.$i.'].title', $known, $rejected);
            $explanation = self::clean_text($entry['explanation'] ?? null, 'key_findings['.$i.'].explanation', $known, $rejected);
            if ($title === null || $title === '' || $explanation === null || $explanation === '') {
                continue;
            }
            $findings[] = array(
                'title' => $title,
                'explanation' => $explanation,
                'refs' => self::control_refs($entry['refs'] ?? null, $index, 'key_findings['.$i.'].refs', $rejected)
            );
        }

        $recommendations = array();
        foreach (self::items($output['strategic_recommendations'] ?? null) as $i => $entry) {
            $title = self::clean_text($entry['title'] ?? null, 'strategic_recommendations['.$i.'].title', $known, $rejected);
            $rationale = self::clean_text($entry['rationale'] ?? null, 'strategic_recommendations['.$i.'].rationale', $known, $rejected);
            if ($title === null || $title === '' || $rationale === null || $rationale === '') {
                continue;
            }
            $recommendations[] = array(
                'title' => $title,
                'rationale' => $rationale,
                'refs' => self::control_refs($entry['refs'] ?? null, $index, 'strategic_recommendations['.$i.'].refs', $rejected)
            );
        }

        return array(
            'overview' => self::clean_list($output['overview'] ?? null, 3, 'overview', $known, $rejected),
            'exposure_overview' => self::clean_list($output['exposure_overview'] ?? null, 2, 'exposure_overview', $known, $rejected),
            'key_findings' => self::bounded($findings, 6, 'key_findings', $rejected),
            'strengths' => self::clean_list($output['strengths'] ?? null, 4, 'strengths', $known, $rejected),
            'improvements' => self::clean_list($output['improvements'] ?? null, 4, 'improvements', $known, $rejected),
            'strategic_recommendations' => self::bounded($recommendations, 5, 'strategic_recommendations', $rejected),
            'management_considerations' => self::clean_list($output['management_considerations'] ?? null, 5, 'management_considerations', $known, $rejected)
        );
    }

    private static function validate_review(array $output, array $input, array $known, array $index, array &$rejected): array
    {
        $sections = array_keys($input['sections'] ?? array());
        $issues = array();

        foreach (self::items($output['issues'] ?? null) as $i => $entry) {
            $description = self::clean_text($entry['description'] ?? null, 'issues['.$i.'].description', $known, $rejected);
            if ($description === null || $description === '') {
                continue;
            }
            $section = trim((string) ($entry['section'] ?? ''));
            $issues[] = array(
                'severity' => in_array($entry['severity'] ?? '', self::SEVERITIES, true) ? $entry['severity'] : 'Low',
                'category' => in_array($entry['category'] ?? '', self::CATEGORIES, true) ? $entry['category'] : 'other',
                'section' => in_array($section, $sections, true) ? $section : 'general',
                'description' => $description,
                'refs' => self::clean_refs($entry['refs'] ?? null, $index, null, 'issues['.$i.'].refs', $rejected)
            );
        }

        return array(
            'issues' => self::bounded($issues, 25, 'issues', $rejected)
        );
    }

    private static function items($value): array
    {
        return is_array($value) ? array_values($value) : array();
    }

    private static function bounded(array $list, int $max, string $path, array &$rejected): array
    {
        if (count($list) > $max) {
            $rejected[] = $path.': trimmed from '.count($list).' to '.$max;
            return array_slice($list, 0, $max);
        }

        return $list;
    }

    /**
     * One prose field: markdown stripped; null when missing, when it carries
     * a figure the facts do not, or when it names a product or vendor the
     * facts do not.
     */
    public static function clean_text($value, string $path, array $known, array &$rejected): ?string
    {
        if (!is_string($value)) {
            $rejected[] = $path.': missing';
            return null;
        }

        $text = self::strip_markdown($value);
        $figure = self::unsupported_figure($text, $known);

        if ($figure !== null) {
            $rejected[] = $path.': unsupported figure "'.$figure.'"';
            return null;
        }

        if (isset($known['#products'])) {
            $products = array_values(array_diff(ReportQuality::named_products($text), $known['#products']));
            if (count($products) > 0) {
                $rejected[] = $path.': unsupported product or vendor name "'.$products[0].'"';
                return null;
            }
        }

        return $text;
    }

    private static function clean_list($value, int $max, string $path, array $known, array &$rejected): array
    {
        $out = array();

        foreach (self::items($value) as $i => $item) {
            $text = self::clean_text($item, $path.'['.$i.']', $known, $rejected);
            if ($text !== null && $text !== '') {
                $out[] = $text;
            }
        }

        return self::bounded($out, $max, $path, $rejected);
    }

    /** Refs kept only when they exist in the facts; an objective's short id is repaired within its own control. */
    private static function clean_refs($value, array $index, ?string $control, string $path, array &$rejected): array
    {
        $out = array();

        foreach (self::items($value) as $item) {
            $token = strtolower(trim((string) $item));
            $ref = $index['refs'][$token] ?? null;
            if ($ref === null && $control !== null) {
                $ref = $index['short'][strtolower($control).'|'.$token] ?? null;
            }
            if ($ref === null) {
                $rejected[] = $path.': unknown ref "'.(string) $item.'"';
                continue;
            }
            $out[] = $ref;
        }

        return array_values(array_unique($out));
    }

    private static function control_refs($value, array $index, string $path, array &$rejected): array
    {
        $out = array();

        foreach (self::clean_refs($value, $index, null, $path, $rejected) as $ref) {
            $out[] = $index['parent'][$ref] ?? $ref;
        }

        return array_values(array_unique($out));
    }

    public static function strip_markdown(string $text): string
    {
        $text = str_replace(array(
            "\r\n",
            "\r"
        ), "\n", $text);
        $text = preg_replace('/\*\*|__|`/', '', $text);
        $text = preg_replace('/(?<![\w*])\*(\S[^*\n]*?\S|\S)\*(?![\w*])/', '$1', $text);
        $text = preg_replace('/^[ \t]*#{1,6}[ \t]*/m', '', $text);
        $text = preg_replace('/^[ \t]*(?:[-*\x{2022}]|\d+[.)])[ \t]+/mu', '', $text);
        $text = preg_replace('/\[([^\]]+)\]\([^)]*\)/', '$1', $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/ *\n */', "\n", $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    /** The first digit-bearing token in the text that does not appear in the facts, or null. */
    public static function unsupported_figure(string $text, array $known): ?string
    {
        foreach (self::tokens($text) as $token) {
            if (isset($known[strtolower($token)])) {
                continue;
            }
            foreach (preg_split('/[\/\-]/', $token) as $part) {
                $part = rtrim($part, '.');
                if (preg_match('/\d/', $part) && !isset($known[strtolower($part)])) {
                    return $token;
                }
            }
        }

        return null;
    }

    private static function tokens(string $text): array
    {
        preg_match_all('/[A-Za-z0-9][A-Za-z0-9.\/\-]*/', $text, $match);
        $out = array();

        foreach ($match[0] as $token) {
            $token = rtrim($token, '.-/');
            if ($token !== '' && preg_match('/\d/', $token)) {
                $out[] = $token;
            }
        }

        return $out;
    }

    /** Every digit-bearing token in the facts, with its hyphen and slash parts, lower-cased. */
    public static function known_tokens(array $input): array
    {
        $known = array();

        array_walk_recursive($input, function ($value) use (&$known) {
            if (is_bool($value) || $value === null) {
                return;
            }
            foreach (self::tokens((string) $value) as $token) {
                $known[strtolower($token)] = true;
                foreach (preg_split('/[\/\-]/', $token) as $part) {
                    $part = rtrim($part, '.');
                    if ($part !== '') {
                        $known[strtolower($part)] = true;
                    }
                }
            }
        });

        return $known;
    }

    /**
     * Refs present in the facts: every record with a "ref" is a control,
     * except those inside an objectives list, which are objectives of the
     * enclosing control. Returns refs (lower => ref), parent (objective =>
     * control) and short (control|short => objective ref).
     */
    public static function ref_index(array $input): array
    {
        $index = array(
            'refs' => array(),
            'parent' => array(),
            'short' => array()
        );

        self::walk_refs($input, null, $index);

        return $index;
    }

    private static function walk_refs(array $node, ?string $parent, array &$index): void
    {
        $control = null;

        if (isset($node['ref']) && is_string($node['ref'])) {
            $ref = $node['ref'];
            $index['refs'][strtolower($ref)] = $ref;
            if ($parent !== null) {
                $index['parent'][$ref] = $parent;
                if (isset($node['short']) && is_string($node['short'])) {
                    $index['short'][strtolower($parent).'|'.strtolower($node['short'])] = $ref;
                }
                $prefix = strtolower($parent).'-';
                if (strpos(strtolower($ref), $prefix) === 0) {
                    $index['short'][strtolower($parent).'|'.substr(strtolower($ref), strlen($prefix))] = $ref;
                }
            } else {
                $control = $ref;
            }
        }

        foreach ($node as $key => $child) {
            if (!is_array($child)) {
                continue;
            }
            if ($control !== null && ($key === 'objectives' || $key === 'open_objectives')) {
                foreach ($child as $objective) {
                    if (is_array($objective)) {
                        self::walk_refs($objective, $control, $index);
                    }
                }
                continue;
            }
            if ($parent === null) {
                self::walk_refs($child, null, $index);
            }
        }
    }

    /* ------------------------------------------------------- assessor edits */

    /** Problems with an edited section's shape against the section schema; empty when it fits. */
    public static function shape_errors(string $kind, $value): array
    {
        $errors = array();
        self::check_shape(self::schema($kind), $value, $kind, $errors);

        return $errors;
    }

    private static function check_shape(array $schema, $value, string $path, array &$errors): void
    {
        if (count($errors) >= 10) {
            return;
        }

        switch ($schema['type']) {
            case 'object':
                if (!is_array($value) || (count($value) > 0 && array_is_list($value))) {
                    $errors[] = $path.' must be an object';
                    return;
                }
                foreach ($schema['required'] as $key) {
                    if (!array_key_exists($key, $value)) {
                        $errors[] = $path.'.'.$key.' is missing';
                    }
                }
                foreach ($value as $key => $child) {
                    if (!isset($schema['properties'][$key])) {
                        if (!($path === 'remediation' && $key === 'uncovered')) {
                            $errors[] = $path.'.'.$key.' is not expected';
                        }
                        continue;
                    }
                    self::check_shape($schema['properties'][$key], $child, $path.'.'.$key, $errors);
                }
                return;
            case 'array':
                if (!is_array($value) || !array_is_list($value)) {
                    $errors[] = $path.' must be a list';
                    return;
                }
                foreach ($value as $i => $child) {
                    self::check_shape($schema['items'], $child, $path.'['.$i.']', $errors);
                }
                return;
            case 'string':
                if ($value === null) {
                    return;
                }
                if (!is_string($value)) {
                    $errors[] = $path.' must be text';
                    return;
                }
                if (isset($schema['enum']) && !in_array($value, $schema['enum'], true)) {
                    $errors[] = $path.' must be one of '.implode(', ', $schema['enum']);
                }
                return;
        }
    }
}
