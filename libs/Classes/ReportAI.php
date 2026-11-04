<?php
use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\RateLimitException;

/**
 * AI-written report narratives on top of ReportAnalysis. The analysis stays
 * the source of every fact, count and score; the model adds interpretation:
 * control-specific observations, risk scenarios and corrective actions per
 * family, consolidated remediation initiatives, cross-control patterns, the
 * executive summary and a quality review of the assembled text.
 *
 * Each section is generated only through an explicit action, validated here
 * (unknown refs dropped, figures not found in the facts rejected, markdown
 * stripped, list lengths bounded) and stored with a hash of its inputs, so an
 * unchanged section is never paid for twice and a changed one shows as stale.
 */
class ReportAI
{
    const PROMPT_VERSIONS = array(
        'domain' => '2026-11-24.2',
        'remediation' => '2026-11-24.2',
        'patterns' => '2026-11-24.2',
        'executive' => '2026-11-24.3',
        'review' => '2026-11-24.3'
    );
    const MAX_TOKENS = 32000;
    const TIMEOUT = 600;
    const MAX_SECTIONS_PER_RUN = 24;
    const DOMAIN_CHUNK = 6;

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

    const PATTERN_KINDS = array(
        'governance',
        'technical',
        'operational',
        'documentation'
    );

    const SYSTEM_PROMPT = <<<'PROMPT'
You write sections of a formal cybersecurity assessment report prepared by an independent assessor for an organization that operates in, or serves, the aviation sector. The report must read like a senior consultant's deliverable: precise, specific to each requirement, and useful to the people who must close the gaps. You receive a JSON object of FACTS and return JSON that matches the required schema.

SOURCES AND CERTAINTY
- The FACTS are the only source of information about the organization. Recorded results, objective results, assessor fields and evidence titles are facts. Everything else you write is professional interpretation or general security knowledge and must read as such ("is intended to", "typically", "could", "would").
- Interpret what the recorded results mean. A requirement recorded as Partially Implemented, with some objectives met and others not met or partially met, is missing specific elements: name those elements by what they require, not by repeating the title.
- Where the FACTS do not show why a result was recorded (no assessor fields), do not speculate about causes or about what exists. State what the recorded results indicate and, where useful, what should be confirmed.
- Evidence: only titles are given. Never describe what a document contains. If no evidence is listed, say that no evidence is linked; do not imply that any was reviewed.
- Write for the report reader: never refer to "the FACTS", "the input", "titles", field names or the data structure.
- Never state or imply that an incident, breach, audit finding, regulatory action or loss has occurred.
- Do not name the organization, individuals, vendors or products. Refer to "the organization".

NUMBERS AND RATINGS
- Do not assign or imply likelihood, impact, severity, risk ratings, priorities, scores, maturity levels, costs, effort, timeframes or deadlines. Those are calculated elsewhere.
- Do not write any digit that does not already appear in the FACTS. That includes durations ("within 72 hours"), counts you computed yourself, percentages, years and version numbers of external standards. Use words such as "promptly", "periodically" or "at a defined frequency" instead of invented values. Any figure not found in the FACTS will be removed together with the sentence's field.

REFERENCES
- Cite requirements and objectives only by the exact refs in the FACTS ("ref" values, or an objective's "short" value when writing about its own requirement). Never invent refs, clause numbers or citations to other frameworks.
- Use the framework's own terminology from FACTS.terms for what it calls a control, an objective and a family.

WRITING
- Specific beats generic. Every sentence should tell the reader something tied to this requirement or these objectives. Avoid filler such as "it is important to", "robust", "comprehensive", "best practice", "holistic", "leverage", and never explain a requirement by restating its title.
- Do not reuse the same scenario, action or sentence across requirements; vary according to each requirement's actual purpose.
- Plain text only: no markdown, bullet characters, headings or numbering inside strings. Each array item is one complete sentence or a short group of sentences.
- US English, formal third person, present tense for observations.
- Aviation context: where a requirement plausibly touches flight or maintenance operations, airworthiness and maintenance records, passenger or crew data, safety reporting, operational technology, or aviation authority oversight, you may say so as a conditional consideration, never as a fact about the organization.

SECTION GUIDES

[domain] The FACTS describe one family and its gap requirements (gap_controls). Return:
- narrative: one to three paragraphs on the family as a whole: what it is intended to achieve, what the recorded results show is in place (implemented_controls) and what is missing, and the combined operational significance of the gaps. Quote counts only from FACTS.
- controls: exactly one entry per item in gap_controls, in the same order:
  - ref: the gap requirement's ref.
  - observation: two to four sentences (under 120 words) interpreting the recorded condition. Name the deficient objectives by short ref and say in substance what each missing element means (for example: "PF03 is not met: access is not removed promptly when employment ends, so former staff could retain working credentials"). Distinguish not met from partially met, and mention met objectives where that clarifies the gap. Attribute assessor fields when present ("The assessor noted ..."). If the requirement has no objectives, describe the gap from the result and expectations and state that the specific deficiency needs confirmation.
  - control_purpose: one or two sentences on what this requirement is meant to achieve, faithful to its description and expectations.
  - risk_scenarios: two or three entries specific to the deficient objectives. scenario: one conditional sentence describing how the missing element could be exploited or fail ("If ..., ... could ..."). consequence: the realistic operational, security, regulatory or customer consequence. No ratings.
  - recommended_actions: two to six concrete actions that close the specific deficient objectives, each starting with a verb and naming the process, artifact or technical control involved. Never "implement the control".
  - expected_outcome: one sentence describing what an assessor would observe once remediated.
  - validation_criteria: two to four statements of what an assessor would inspect, sample or test to confirm closure.
  - considerations: one or two sentences of aviation, operational or security considerations specific to this requirement, or an empty string. Leave it empty unless something specific to this requirement applies; never repeat a generic aviation sentence across requirements.
  - source_refs: the requirement and objective refs your text relies on.
- When FACTS.all_gap_controls is present, the family has more gaps than are detailed in gap_controls: write the narrative about the whole family using all_gap_controls, and write controls entries only for the requirements detailed in gap_controls.

[remediation] Consolidate all gap requirements into four to ten remediation initiatives a program manager could run. deterministic_initiatives groups the gaps by topic as a starting point: merge, split or retitle where a different grouping gives more coherent work. Every gap ref must appear in exactly one initiative. For each initiative:
- title: action-oriented and specific, no numbering.
- phase: one of FACTS.phases. Immediate priorities for direct-exposure gaps (access, privileged access, monitoring, incident response, vulnerability handling, data protection) and not implemented requirements with high derived priority; Foundational improvements for governance, policy, risk and inventory foundations that other work depends on; Operational control enhancements for operating processes; Program maturity for review cycles, metrics and refinement. Use the derived priority as a guide but do not state it.
- problem: two or three sentences on what is missing across these requirements, naming the deficient objectives by substance, and why it matters taken together.
- activities: three to seven concrete activities in a sensible order.
- deliverables: two to five tangible artifacts.
- implementation_considerations: one or two sentences on sequencing, ownership and operational constraints (for example shift operations, maintenance windows, dependence on service providers). No durations.
- validation: two to four statements of how closure will be verified.
- controls: the gap refs covered.
- points: the open objective refs this initiative closes.
- depends_on: exact titles of other initiatives in your output that should come first; empty if none.

[patterns] Identify three to seven cross-cutting patterns: a root cause or recurring weakness visible in two or more gap requirements, often across families (for example requirements designed but not evidenced as operating, reviews defined but not performed, third parties left outside several processes). For each: label (short noun phrase), kind (governance, technical, operational or documentation), description (two or three sentences: what the pattern is, which deficient objectives show it, and why addressing it once is more effective than fixing requirements one by one), refs (the requirement refs, and objective refs where helpful, that support it; at least two requirements). Do not restate one family's results as a pattern. themes and keyword_patterns are keyword groupings offered as hints only.

[executive] The executive summary for senior management and the board:
- overview: two to four paragraphs. First, what was assessed and the overall result in plain language, using the score and counts from FACTS and explaining that the score measures implementation, not certification. Then the shape of the gaps (absent versus partial, where they concentrate, what they have in common) and what that means for the organization's security and assurance position. Then the state of evidence and what it means for reliance on the results. Do not walk through every family.
- key_findings: four to seven management-level findings, each with title (a short statement of the issue), explanation (two or three sentences on what is missing and why it matters to the business) and refs (supporting requirement refs). Cover the gaps with the highest derived priority.
- Precision: describe a Partially Implemented requirement or objective as incomplete, inconsistent or not yet formalized, never as absent ("has no", "does not"); only Not Implemented elements may be described as missing. Do not contradict objectives recorded as Implemented.
- strengths: two to five specific statements of what is in place, based on implemented requirements and stronger families; no praise.
- improvements: two to five statements of the most important areas needing improvement, worded differently from key_findings.
- strategic_recommendations: three to five, each with title, rationale (why this, why now, what it enables) and refs. They must align with the initiatives in FACTS.
- management_considerations: three to six decisions or actions for management (ownership, resourcing, risk acceptance, evidence, follow-up assessment). No dates or budgets.

[review] Act as the engagement quality reviewer. FACTS contain the key results and the assembled AI-written sections. Return issues, each with severity (High: inconsistent with the recorded results, an unsupported claim, or a recommendation that does not address its finding; Medium: incomplete coverage, contradictions between sections, misaligned emphasis; Low: repetition, vague wording, style), section (the section key, for example "executive" or "domain:Privacy") and description (what is wrong, where, and how to fix it, citing refs). Check consistency between sections, repeated scenarios or actions across requirements, completeness (every gap covered by a finding and an initiative), alignment between findings and recommendations, and statements presented as fact without support in the results. Return an empty list only when there is genuinely nothing to improve.
PROMPT;

    private $client;
    private $narratives_model;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client(
            apiKey: AiAssistant::api_key(),
            requestOptions: array(
                'maxRetries' => 1,
                'transporter' => new \GuzzleHttp\Client(array(
                    'timeout' => self::TIMEOUT,
                    'connect_timeout' => 15
                ))
            )
        );
        $this->narratives_model = new ReportNarrativesModel();
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
                'open_objectives' => array_column($gap['open'], 'title')
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
            $gaps[] = array(
                'ref' => $gap['ref'],
                'title' => $gap['title'],
                'family' => $gap['family'],
                'result' => $gap['result'],
                'derived_priority' => $gap['priority'],
                'open_objectives' => array_column($gap['open'], 'ref')
            );
        }

        $sections = array();
        foreach ($current as $key => $content) {
            if ($key !== 'review') {
                $sections[$key] = $content;
            }
        }

        return array(
            'results' => array(
                'counts' => $c,
                'gap_total' => $c['Not Implemented'] + $c['Partially Implemented'],
                'implementation_score' => $analysis['score'],
                'controls_with_evidence' => $analysis['with_evidence']
            ),
            'gaps' => $gaps,
            'readiness_issues' => array_map(function ($issue) {
                return $issue[1];
            }, $analysis['readiness']),
            'sections' => $sections
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
                            'observation' => self::text_field('Interpretation of the recorded condition, citing deficient objectives.'),
                            'control_purpose' => self::text_field('What the requirement is meant to achieve.'),
                            'risk_scenarios' => array(
                                'type' => 'array',
                                'items' => self::object(array(
                                    'scenario' => self::text_field('Conditional sentence: how the missing element could be exploited or fail.'),
                                    'consequence' => self::text_field('The realistic consequence.')
                                ))
                            ),
                            'recommended_actions' => self::strings('Concrete actions closing the deficient objectives.'),
                            'expected_outcome' => self::text_field('What an assessor would observe once remediated.'),
                            'validation_criteria' => self::strings('What an assessor would inspect, sample or test.'),
                            'considerations' => self::text_field('Aviation, operational or security considerations, or an empty string.'),
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
                            'problem' => self::text_field('What is missing and why it matters together.'),
                            'activities' => self::strings('Concrete activities in order.'),
                            'deliverables' => self::strings('Tangible artifacts.'),
                            'implementation_considerations' => self::text_field('Sequencing, ownership and operational constraints.'),
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
                            'label' => self::text_field('Short noun phrase.'),
                            'kind' => array(
                                'type' => 'string',
                                'enum' => self::PATTERN_KINDS
                            ),
                            'description' => self::text_field('What the pattern is, where it shows, why fix it once.'),
                            'refs' => self::strings('Supporting requirement and objective refs.')
                        ))
                    )
                ));
            case 'executive':
                return self::object(array(
                    'overview' => self::strings('Two to four paragraphs.'),
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
                            'section' => self::text_field('Section key.'),
                            'description' => self::text_field('What is wrong, where, and how to fix it.')
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
     * kept without calling the API, unless the assessor rejected it.
     */
    public function generate(int $assessment_id, int $company_id, string $section_key, int $user_id): array
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
                'message' => 'That section does not apply to this assessment'
            );
        }

        $state = $states[$section_key];

        if ($state['state'] === 'current' && $state['narrative_status'] !== 'Rejected') {
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
        $answer = $kind === 'domain' && count($state['input']['gap_controls']) > self::DOMAIN_CHUNK ? $this->call_domain_chunks($state['input']) : $this->call($kind, $state['input']);

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
            AiAssistant::MODEL,
            $answer['input_tokens'],
            $answer['output_tokens'],
            $user_id
        );

        error_log('[report-ai] assessment '.$assessment_id.' '.$section_key.': in '.$answer['input_tokens'].' (cache read '.$answer['cache_read'].', cache write '.$answer['cache_write'].'), out '.$answer['output_tokens'].', rejected '.count($checked['rejected']));

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

    private function call(string $kind, array $input): array
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
                        'content' => self::user_message($kind, $input)
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
            return self::failure('The AI service is busy right now. Try again in a minute.');
        } catch (APIStatusException $e) {
            error_log('[report-ai] '.$e->getMessage());
            return self::failure('The AI service could not write this section. Try again.');
        } catch (APIConnectionException $e) {
            error_log('[report-ai] '.$e->getMessage());
            return self::failure('The AI service could not be reached. Try again.');
        } catch (\JsonException $e) {
            error_log('[report-ai] unreadable response: '.$e->getMessage());
            return self::failure('The AI service sent an answer that could not be read. Try again.');
        }

        if ($response->stopReason === 'refusal') {
            return self::failure('The AI service declined to write this section; the calculated text will be used.');
        }

        if ($response->stopReason === 'max_tokens') {
            return self::failure('The section was too long to complete; the calculated text will be used.');
        }

        $text = '';
        foreach ($response->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        $output = json_decode($text, true);

        if (!is_array($output)) {
            return self::failure('The AI service sent an answer that could not be read. Try again.');
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
    private function call_domain_chunks(array $input): array
    {
        $all = array();
        foreach ($input['gap_controls'] as $control) {
            $all[] = array(
                'ref' => $control['ref'],
                'title' => $control['title'],
                'result' => $control['result']
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

            $answer = $this->call('domain', $part);

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

    private static function failure(string $message): array
    {
        return array(
            'success' => false,
            'message' => $message
        );
    }

    public static function user_message(string $kind, array $input): string
    {
        return 'Write the ['.$kind.'] section following its section guide.'."\n\n".'FACTS:'."\n".json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
                $content = self::validate_review($output, $input, $known, $rejected);
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
                if ($scenario !== null && $scenario !== '' && $consequence !== null && $consequence !== '') {
                    $scenarios[] = array(
                        'scenario' => $scenario,
                        'consequence' => $consequence
                    );
                }
            }

            $controls[] = array(
                'ref' => $ref,
                'observation' => self::clean_text($entry['observation'] ?? null, $path.'.observation', $known, $rejected),
                'control_purpose' => self::clean_text($entry['control_purpose'] ?? null, $path.'.control_purpose', $known, $rejected),
                'risk_scenarios' => self::bounded($scenarios, 3, $path.'.risk_scenarios', $rejected),
                'recommended_actions' => self::clean_list($entry['recommended_actions'] ?? null, 6, $path.'.recommended_actions', $known, $rejected),
                'expected_outcome' => self::clean_text($entry['expected_outcome'] ?? null, $path.'.expected_outcome', $known, $rejected),
                'validation_criteria' => self::clean_list($entry['validation_criteria'] ?? null, 4, $path.'.validation_criteria', $known, $rejected),
                'considerations' => self::clean_text($entry['considerations'] ?? '', $path.'.considerations', $known, $rejected),
                'source_refs' => self::clean_refs($entry['source_refs'] ?? null, $index, $ref, $path.'.source_refs', $rejected)
            );
        }

        foreach ($gap_refs as $ref) {
            if (!isset($seen[$ref])) {
                $rejected[] = 'controls: no entry for '.$ref;
            }
        }

        return array(
            'narrative' => self::clean_list($output['narrative'] ?? null, 3, 'narrative', $known, $rejected),
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
                'activities' => self::clean_list($entry['activities'] ?? null, 7, $path.'.activities', $known, $rejected),
                'deliverables' => self::clean_list($entry['deliverables'] ?? null, 5, $path.'.deliverables', $known, $rejected),
                'implementation_considerations' => self::clean_text($entry['implementation_considerations'] ?? null, $path.'.implementation_considerations', $known, $rejected),
                'validation' => self::clean_list($entry['validation'] ?? null, 4, $path.'.validation', $known, $rejected),
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
            'patterns' => self::bounded($patterns, 7, 'patterns', $rejected)
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
            'overview' => self::clean_list($output['overview'] ?? null, 4, 'overview', $known, $rejected),
            'key_findings' => self::bounded($findings, 7, 'key_findings', $rejected),
            'strengths' => self::clean_list($output['strengths'] ?? null, 5, 'strengths', $known, $rejected),
            'improvements' => self::clean_list($output['improvements'] ?? null, 5, 'improvements', $known, $rejected),
            'strategic_recommendations' => self::bounded($recommendations, 5, 'strategic_recommendations', $rejected),
            'management_considerations' => self::clean_list($output['management_considerations'] ?? null, 6, 'management_considerations', $known, $rejected)
        );
    }

    private static function validate_review(array $output, array $input, array $known, array &$rejected): array
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
                'section' => in_array($section, $sections, true) ? $section : 'general',
                'description' => $description
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

    /** One prose field: markdown stripped; null when missing or when it carries a figure the facts do not. */
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
