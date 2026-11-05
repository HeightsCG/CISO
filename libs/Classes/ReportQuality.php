<?php
/**
 * Deterministic quality checks over the AI-written narrative sections. No
 * network, no model: regular expressions and set arithmetic over the text and
 * the recorded results of a ReportAnalysis array.
 *
 * Detectors: unsupported assertions (named products, presumed client systems,
 * data flows, staff behaviour, verification and causal claims, unhedged risk
 * scenarios), contradictions with recorded results (by the refs near each
 * claim), cross-report consistency (counts, coverage, R-IDs, priorities),
 * repetition (shingle similarity between sentences) and overstatement
 * (absolute language where the related results are partial and unevidenced).
 *
 * Every issue: severity (High|Medium|Low), category, section, field,
 * description, refs, source 'rules'.
 */
class ReportQuality
{
    const SOURCE = 'rules';

    const SHINGLE = 3;

    const REPEAT_MIN_WORDS = 12;

    const REPEAT_THRESHOLD = 0.5;

    const REPEAT_MEDIUM = 0.75;

    const MAX_REPETITIONS = 15;

    const SEVERITY_RANK = array(
        'High' => 3,
        'Medium' => 2,
        'Low' => 1
    );

    const PRODUCTS = 'microsoft\s*365|office\s*365|o365|azure(?:\s+ad)?|entra(?:\s+id)?|active\s+directory|sharepoint|onedrive|exchange\s+online|microsoft\s+teams|microsoft\s+outlook|intune|defender\s+for|microsoft\s+sentinel|aws|amazon\s+web\s+services|google\s+workspace|g\s*suite|google\s+cloud|gcp|okta|ping\s*identity|duo\s+security|onelogin|salesforce|servicenow|jira|confluence|github|gitlab|bitbucket|splunk|crowdstrike|sentinelone|carbon\s+black|qualys|tenable|nessus|rapid7|palo\s+alto|fortinet|fortigate|cisco\s+(?:asa|umbrella|meraki)|sophos|mcafee|symantec|trend\s+micro|veeam|datto|dropbox|box\.com|workday|sap(?:\s+s4hana)?|oracle\s+(?:erp|cloud|database)|netsuite|quickbooks|amos|traxxeon|trax|camo\s*tools|ramco|lastpass|1password|bitwarden|cyberark|beyondtrust|knowbe4|proofpoint|mimecast|cloudflare|zscaler|vmware|citrix|kubernetes|docker|terraform|jamf|kaseya|connectwise|hubspot|zendesk|freshdesk';

    const PRODUCTS_CASED = 'Slack|Zoom|Outlook|Sentinel|Duo|Box|Teams|Check Point|Oracle|Google Drive|Gmail|Windows Server|Linux';

    const HEDGES = '\b(?:could|may|might|would|if|where|whether|should|unless|potentially|possibly|likely|consider|for example|for instance|such as|to be confirmed|confirm|can)\b';

    const SYSTEM_NOUNS = 'erp(?:\s+system)?|crm(?:\s+system)?|siem|hr\s+system|hris|payroll\s+system|identity\s+provider|identity\s+store|directory\s+service|active\s+directory|ticketing\s+(?:system|tool)|service\s+desk\s+(?:system|tool)|help\s*desk\s+(?:system|tool)|maintenance\s+(?:tracking|management|information)\s+system|mro\s+system|cloud\s+(?:environment|tenant|platform|provider)|tenant|data\s*cent(?:er|re)|production\s+(?:environment|network)|corporate\s+network|firewalls?|vpn|email\s+(?:system|platform|gateway)|file\s+(?:server|share)s?|databases?|data\s+warehouse|backup\s+(?:system|platform|solution|server)|endpoint\s+(?:protection|management)\s+(?:tool|platform)|edr\s+(?:tool|platform)|mdm\s+(?:tool|platform)|document\s+management\s+system|intranet';

    const RESULT_CLASS = array(
        'Implemented' => 'met',
        'Partially Implemented' => 'partial',
        'Not Implemented' => 'absent'
    );

    const NUMBERS = array(
        'one' => 1,
        'two' => 2,
        'three' => 3,
        'four' => 4,
        'five' => 5,
        'six' => 6,
        'seven' => 7,
        'eight' => 8,
        'nine' => 9,
        'ten' => 10,
        'eleven' => 11,
        'twelve' => 12,
        'thirteen' => 13,
        'fourteen' => 14,
        'fifteen' => 15,
        'sixteen' => 16,
        'seventeen' => 17,
        'eighteen' => 18,
        'nineteen' => 19,
        'twenty' => 20
    );

    /* ------------------------------------------------------------- public */

    /**
     * All issues for the given section contents (section key => content in
     * the stored shape), most severe first.
     */
    public static function check(array $analysis, array $sections): array
    {
        $facts = self::facts($analysis);
        $units = self::units($sections);
        $issues = array();

        foreach ($units as $unit) {
            foreach (self::sentences($unit['text']) as $sentence) {
                $found = self::contradictions($sentence, $unit, $facts);
                $issues = array_merge($issues, $found);
                $issues = array_merge($issues, self::assertions($sentence, $unit, $facts));
                if (count($found) === 0) {
                    $issues = array_merge($issues, self::overstatements($sentence, $unit, $facts));
                }
            }
            $issues = array_merge($issues, self::unhedged($unit));
        }

        $issues = array_merge($issues, self::consistency($sections, $units, $facts));
        $issues = array_merge($issues, self::repetitions($units));

        return self::sorted(self::unique($issues));
    }

    /** The issues with the shape report_center reads: counts by severity and the list. */
    public static function summary(array $issues): array
    {
        $counts = array(
            'High' => 0,
            'Medium' => 0,
            'Low' => 0
        );
        $categories = array();

        foreach ($issues as $issue) {
            $counts[$issue['severity']] = ($counts[$issue['severity']] ?? 0) + 1;
            $categories[$issue['category']] = ($categories[$issue['category']] ?? 0) + 1;
        }

        return array(
            'counts' => $counts,
            'categories' => $categories,
            'issues' => array_values($issues)
        );
    }

    /** The issues raised on one section. */
    public static function for_section(array $issues, string $section_key): array
    {
        return array_values(array_filter($issues, function ($issue) use ($section_key) {
            return $issue['section'] === $section_key;
        }));
    }

    /** The usable (current, not rejected) content of each section from ReportNarratives::states(). */
    public static function usable_sections(array $states): array
    {
        $sections = array();

        foreach ($states as $key => $state) {
            if (!empty($state['usable']) && is_array($state['content'])) {
                $sections[$key] = $state['content'];
            }
        }

        return $sections;
    }

    /** AI review issues in the rules issue shape, so both lists render alike. */
    public static function review_issues(array $review_content): array
    {
        $out = array();

        foreach ($review_content['issues'] ?? array() as $issue) {
            if (!is_array($issue) || !is_string($issue['description'] ?? null)) {
                continue;
            }
            $out[] = array(
                'severity' => in_array($issue['severity'] ?? '', array_keys(self::SEVERITY_RANK), true) ? $issue['severity'] : 'Low',
                'category' => in_array($issue['category'] ?? '', ReportAI::CATEGORIES, true) ? $issue['category'] : 'other',
                'section' => (string) ($issue['section'] ?? 'general'),
                'field' => null,
                'description' => $issue['description'],
                'refs' => array_values(array_filter((array) ($issue['refs'] ?? array()), 'is_string')),
                'source' => 'review'
            );
        }

        return $out;
    }

    /** Product and vendor names in a text, lower-cased. */
    public static function named_products(string $text): array
    {
        preg_match_all('~(?<![\w.-])(?:'.self::PRODUCTS.')(?![\w-])~i', $text, $match);
        preg_match_all('~(?<![\w.-])(?:'.self::PRODUCTS_CASED.')(?![\w-])~', $text, $cased);
        $match[0] = array_merge($match[0], $cased[0]);

        return array_values(array_unique(array_map(function ($name) {
            return strtolower(preg_replace('/\s+/', ' ', $name));
        }, $match[0])));
    }

    public static function hedged(string $text): bool
    {
        return preg_match('/'.self::HEDGES.'/i', $text) === 1;
    }

    /* -------------------------------------------------------------- facts */

    /** Results, evidence and recorded assessor text, indexed for the detectors. */
    public static function facts(array $analysis): array
    {
        $results = array();
        $evidence = array();
        $parent = array();
        $short = array();
        $recorded = array();
        $gaps = array();
        $priority = array();

        foreach ($analysis['assessor'] ?? array() as $value) {
            if (is_string($value)) {
                $recorded[] = $value;
            }
        }

        foreach ($analysis['controls'] ?? array() as $control) {
            $ref = $control['ref'];
            $results[$ref] = $control['result'];
            $evidence[$ref] = (int) ($control['evidence_count'] ?? count($control['evidence'] ?? array()));
            foreach ($control['assessor'] ?? array() as $value) {
                if (is_string($value)) {
                    $recorded[] = $value;
                }
            }
            foreach ($control['evidence'] ?? array() as $title) {
                if (is_string($title)) {
                    $recorded[] = $title;
                }
            }
            foreach ($control['objectives'] ?? array() as $objective) {
                $results[$objective['ref']] = $objective['result'];
                $evidence[$objective['ref']] = count($objective['evidence'] ?? array());
                $parent[$objective['ref']] = $ref;
                if (($objective['short'] ?? '') !== '') {
                    $short[strtolower($ref).'|'.strtolower($objective['short'])] = $objective['ref'];
                }
                foreach ($objective['assessor'] ?? array() as $value) {
                    if (is_string($value)) {
                        $recorded[] = $value;
                    }
                }
            }
            if (!empty($control['is_gap'])) {
                $gaps[$ref] = $control['family'];
                $priority[$ref] = $control['priority'] ?? null;
            }
        }

        $refs = array_keys($results);
        usort($refs, function ($x, $y) {
            return strlen($y) <=> strlen($x);
        });
        $shorts = array();
        foreach (array_keys($short) as $key) {
            $shorts[substr($key, strpos($key, '|') + 1)] = true;
        }
        $short_list = array_keys($shorts);
        usort($short_list, function ($x, $y) {
            return strlen($y) <=> strlen($x);
        });

        $families = array();
        foreach ($analysis['families'] ?? array() as $name => $family) {
            $families[$name] = array(
                'counts' => $family['counts'] ?? array(),
                'gaps' => count($family['gaps'] ?? array()),
                'score' => $family['score'] ?? null
            );
        }

        return array(
            'results' => $results,
            'evidence' => $evidence,
            'parent' => $parent,
            'short' => $short,
            'gaps' => $gaps,
            'priority' => $priority,
            'recorded' => strtolower(implode("\n", $recorded)),
            'counts' => $analysis['counts'] ?? array(),
            'score' => $analysis['score'] ?? null,
            'families' => $families,
            'with_evidence' => (int) ($analysis['with_evidence'] ?? 0),
            'ref_pattern' => count($refs) > 0 ? '(?<![\w-])('.implode('|', array_map(function ($ref) {
                return preg_quote($ref, '/');
            }, $refs)).')(?![\w-]|\.\d)' : null,
            'short_pattern' => count($short_list) > 0 ? '(?<![\w.-])('.implode('|', array_map(function ($value) {
                return preg_quote($value, '/');
            }, $short_list)).')(?![\w-])' : null
        );
    }

    /* -------------------------------------------------------------- units */

    /**
     * Every piece of prose in the sections: section, field path, role
     * (recorded, exposure, approach, confirm, other), the control it belongs
     * to (or null) and the text.
     */
    public static function units(array $sections): array
    {
        $units = array();

        foreach ($sections as $key => $content) {
            if (!is_array($content)) {
                continue;
            }
            switch (ReportAI::kind((string) $key)) {
                case 'domain':
                    self::add_list($units, $key, 'narrative', 'recorded', null, $content['narrative'] ?? array());
                    foreach ($content['controls'] ?? array() as $entry) {
                        if (!is_array($entry) || !is_string($entry['ref'] ?? null)) {
                            continue;
                        }
                        $ref = $entry['ref'];
                        $path = 'controls.'.$ref.'.';
                        self::add($units, $key, $path.'recorded_condition', 'recorded', $ref, $entry['recorded_condition'] ?? ($entry['observation'] ?? null));
                        self::add($units, $key, $path.'control_purpose', 'other', $ref, $entry['control_purpose'] ?? null);
                        foreach ($entry['risk_scenarios'] ?? array() as $j => $pair) {
                            self::add($units, $key, $path.'risk_scenarios['.$j.'].scenario', 'exposure', $ref, $pair['scenario'] ?? null);
                            self::add($units, $key, $path.'risk_scenarios['.$j.'].consequence', 'exposure', $ref, $pair['consequence'] ?? null);
                        }
                        self::add_list($units, $key, $path.'suggested_approach', 'approach', $ref, $entry['suggested_approach'] ?? ($entry['recommended_actions'] ?? array()));
                        self::add_list($units, $key, $path.'confirm', 'confirm', $ref, $entry['confirm'] ?? array());
                        self::add($units, $key, $path.'expected_outcome', 'other', $ref, $entry['expected_outcome'] ?? null);
                        self::add_list($units, $key, $path.'validation_criteria', 'other', $ref, $entry['validation_criteria'] ?? array());
                        self::add($units, $key, $path.'considerations', 'exposure', $ref, $entry['considerations'] ?? null);
                    }
                    break;
                case 'remediation':
                    foreach ($content['initiatives'] ?? array() as $n => $entry) {
                        if (!is_array($entry)) {
                            continue;
                        }
                        $path = 'initiatives['.$n.'].';
                        self::add($units, $key, $path.'problem', 'recorded', null, $entry['problem'] ?? null);
                        self::add_list($units, $key, $path.'activities', 'approach', null, $entry['activities'] ?? array());
                        self::add_list($units, $key, $path.'deliverables', 'other', null, $entry['deliverables'] ?? array());
                        self::add($units, $key, $path.'implementation_considerations', 'approach', null, $entry['implementation_considerations'] ?? null);
                        self::add_list($units, $key, $path.'validation', 'other', null, $entry['validation'] ?? array());
                    }
                    break;
                case 'patterns':
                    foreach ($content['patterns'] ?? array() as $n => $entry) {
                        if (is_array($entry)) {
                            self::add($units, $key, 'patterns['.$n.'].description', 'recorded', null, $entry['description'] ?? null);
                        }
                    }
                    break;
                case 'executive':
                    self::add_list($units, $key, 'overview', 'recorded', null, $content['overview'] ?? array());
                    self::add_list($units, $key, 'exposure_overview', 'exposure', null, $content['exposure_overview'] ?? array());
                    foreach ($content['key_findings'] ?? array() as $n => $entry) {
                        if (is_array($entry)) {
                            self::add($units, $key, 'key_findings['.$n.'].explanation', 'recorded', null, $entry['explanation'] ?? null);
                        }
                    }
                    self::add_list($units, $key, 'strengths', 'recorded', null, $content['strengths'] ?? array());
                    self::add_list($units, $key, 'improvements', 'recorded', null, $content['improvements'] ?? array());
                    foreach ($content['strategic_recommendations'] ?? array() as $n => $entry) {
                        if (is_array($entry)) {
                            self::add($units, $key, 'strategic_recommendations['.$n.'].rationale', 'approach', null, $entry['rationale'] ?? null);
                        }
                    }
                    self::add_list($units, $key, 'management_considerations', 'approach', null, $content['management_considerations'] ?? array());
                    break;
            }
        }

        return $units;
    }

    private static function add(array &$units, string $section, string $field, string $role, ?string $ref, $text): void
    {
        if (!is_string($text) || trim($text) === '') {
            return;
        }

        $units[] = array(
            'section' => $section,
            'field' => $field,
            'role' => $role,
            'ref' => $ref,
            'text' => trim($text)
        );
    }

    private static function add_list(array &$units, string $section, string $field, string $role, ?string $ref, $list): void
    {
        foreach (is_array($list) ? array_values($list) : array() as $i => $text) {
            self::add($units, $section, $field.'['.$i.']', $role, $ref, $text);
        }
    }

    public static function sentences(string $text): array
    {
        $parts = preg_split('/(?<=[.!?])\s+(?=[A-Z(])|\n+/', trim($text));

        return array_values(array_filter(array_map('trim', $parts), function ($part) {
            return $part !== '';
        }));
    }

    /* --------------------------------------------------------------- refs */

    /**
     * Ref mentions in order: full refs, and short objective ids resolved
     * against the control in context (the last control named before it, else
     * the unit's own control). Each: ref, start, end.
     */
    public static function mentions(string $text, ?string $context, array $facts): array
    {
        $found = array();

        if ($facts['ref_pattern'] !== null && preg_match_all('/'.$facts['ref_pattern'].'/', $text, $match, PREG_OFFSET_CAPTURE)) {
            foreach ($match[1] as $hit) {
                $found[$hit[1]] = array(
                    'ref' => $hit[0],
                    'start' => $hit[1],
                    'end' => $hit[1] + strlen($hit[0]),
                    'short' => false
                );
            }
        }

        if ($facts['short_pattern'] !== null && preg_match_all('/'.$facts['short_pattern'].'/i', $text, $match, PREG_OFFSET_CAPTURE)) {
            foreach ($match[1] as $hit) {
                $inside = false;
                foreach ($found as $mention) {
                    if ($hit[1] >= $mention['start'] && $hit[1] < $mention['end']) {
                        $inside = true;
                        break;
                    }
                }
                if (!$inside) {
                    $found[$hit[1]] = array(
                        'ref' => null,
                        'token' => strtolower($hit[0]),
                        'start' => $hit[1],
                        'end' => $hit[1] + strlen($hit[0]),
                        'short' => true
                    );
                }
            }
        }

        ksort($found);
        $control = $context;
        $out = array();

        foreach ($found as $mention) {
            if (!$mention['short']) {
                $control = $facts['parent'][$mention['ref']] ?? $mention['ref'];
                $out[] = $mention;
                continue;
            }
            $ref = $control !== null ? ($facts['short'][strtolower($control).'|'.$mention['token']] ?? null) : null;
            if ($ref !== null) {
                $mention['ref'] = $ref;
                $out[] = $mention;
            }
        }

        return $out;
    }

    /* ------------------------------------------------------ contradiction */

    /**
     * A status claim ("is not met", "is partially implemented", "is in
     * place", "has no ...") compared with the recorded result of the refs it
     * is attached to: the run of refs just before it, or a parenthesis of
     * refs just after it.
     */
    private static function contradictions(string $sentence, array $unit, array $facts): array
    {
        if ($unit['role'] === 'confirm' || $unit['role'] === 'exposure') {
            return array();
        }

        $mentions = self::mentions($sentence, $unit['ref'], $facts);
        $issues = array();

        foreach (self::groups($sentence, $mentions) as $group) {
            if ($group['governed']) {
                continue;
            }
            $claim = self::claim_after($group['tail']);
            if ($claim === null && $group['before'] !== null) {
                $claim = self::claim_before($group['before']);
            }
            if ($claim === null) {
                continue;
            }

            $wrong = array();
            $levels = array();
            foreach ($group['refs'] as $ref) {
                $class = self::RESULT_CLASS[$facts['results'][$ref] ?? ''] ?? null;
                if ($class === null) {
                    continue;
                }
                $levels[] = $class;
                if ($class !== $claim) {
                    $wrong[] = $ref;
                }
            }

            if (count($wrong) === 0 || ($group['before'] !== null && in_array($claim, $levels, true))) {
                continue;
            }

            $recorded = array();
            foreach ($wrong as $ref) {
                $recorded[] = $ref.' is recorded as '.$facts['results'][$ref];
            }
            $severity = ($claim === 'partial' && self::all_class($wrong, 'absent', $facts)) ? 'Medium' : 'High';

            $issues[] = self::issue(
                $severity,
                'contradiction',
                $unit,
                'The text describes '.implode(', ', $wrong).' as '.self::claim_words($claim).', but '.implode('; ', $recorded).': "'.self::excerpt($sentence).'". Describe the result as recorded.',
                $wrong
            );
        }

        return $issues;
    }

    /** Runs of adjacent ref mentions with the text after them (to the next ref) and, for a parenthesis, the clause before it. */
    private static function groups(string $sentence, array $mentions): array
    {
        $groups = array();
        $count = count($mentions);
        $i = 0;

        while ($i < $count) {
            $start = $mentions[$i]['start'];
            $refs = array(
                $mentions[$i]['ref']
            );
            $j = $i;
            while ($j + 1 < $count && preg_match('/^[\s,()\/]*(?:and|or|as well as|through|to)?[\s,()\/]*$/i', substr($sentence, $mentions[$j]['end'], $mentions[$j + 1]['start'] - $mentions[$j]['end'])) === 1) {
                $j++;
                $refs[] = $mentions[$j]['ref'];
            }

            $end = $mentions[$j]['end'];
            $next = $j + 1 < $count ? $mentions[$j + 1]['start'] : strlen($sentence);
            $tail = substr($sentence, $end, $next - $end);

            $objectives = array();
            foreach ($refs as $ref) {
                if (strpos($ref, '-') !== false) {
                    $objectives[] = $ref;
                }
            }

            $prefix = substr($sentence, 0, $start);
            $before = null;
            if (preg_match('/\(\s*$/', $prefix) === 1) {
                $previous = $i > 0 ? $mentions[$i - 1]['end'] : 0;
                $clauses = preg_split('/[;:]|,\s*(?:and|but|while|whereas)?\s*|\s+(?:and|but|while|whereas)\s+/i', rtrim(substr($sentence, $previous, $start - $previous), '( '));
                $before = (string) end($clauses);
            }

            $groups[] = array(
                'refs' => array_values(array_unique(count($objectives) > 0 ? $objectives : $refs)),
                'tail' => $tail,
                'before' => $before,
                'governed' => $before === null && preg_match('/\b(?:under|of|in|within|for|across|from|by|to|with|at|on)\s*$/i', $prefix) === 1
            );

            $i = $j + 1;
        }

        return $groups;
    }

    /** The status stated right after a ref run, allowing a parenthesis or a "which ..." clause first. */
    private static function claim_after(string $tail): ?string
    {
        $tail = preg_split('/[;:]|(?<=[.!?])\s/', $tail)[0];

        if (preg_match('/^\s*\)?\s*(?:\([^)]{0,200}\)\s*)?(?:,[^,]{0,160},\s*)?(?:(?:each|both|all)\s+)?(?:is|are|was|were|remains?|stays?)\s+(?:also\s+|only\s+|still\s+|each\s+|both\s+)?(?:recorded|rated|assessed|marked|shown)?\s*(?:as\s+)?(.{0,40})/i', $tail, $match) !== 1) {
            return null;
        }

        return self::classify_status($match[1]);
    }

    private static function classify_status(string $words): ?string
    {
        $words = strtolower($words);

        if (preg_match('/^(?:not\s+(?:met|implemented|in\s+place|present|performed|defined|established)|absent|missing|unmet|lacking|non-existent|nonexistent)\b/', $words) === 1) {
            return 'absent';
        }
        if (preg_match('/^(?:partially\s+(?:met|implemented|in\s+place)|partial\b|incomplete|inconsistent|only\s+partially)/', $words) === 1) {
            return 'partial';
        }
        if (preg_match('/^(?:met|implemented|fully\s+(?:met|implemented)|in\s+place)\b/', $words) === 1) {
            return 'met';
        }

        return null;
    }

    /** A strong claim in the clause before a parenthesis of refs. */
    private static function claim_before(string $before): ?string
    {
        $before = strtolower($before);

        if (preg_match('/\b(?:partially\s+(?:met|implemented|in place)|incomplete|inconsistent(?:ly)?|not\s+consistently|not\s+fully|in part)\b/', $before) === 1) {
            return 'partial';
        }
        if (preg_match('/\b(?:has no|have no|there is no|there are no|lacks?|lacking|is absent|are absent|is missing|are missing|does not exist|do not exist|not in place|not implemented|not met|no formal|no documented|no defined|without any)\b/', $before) === 1) {
            return 'absent';
        }
        if (preg_match('/\b(?:is in place|are in place|is implemented|are implemented|fully implemented|is met|are met)\b/', $before) === 1) {
            return 'met';
        }

        return null;
    }

    private static function claim_words(string $claim): string
    {
        switch ($claim) {
            case 'absent':
                return 'absent or not met';
            case 'partial':
                return 'partially in place';
        }

        return 'met or in place';
    }

    private static function all_class(array $refs, string $class, array $facts): bool
    {
        foreach ($refs as $ref) {
            if ((self::RESULT_CLASS[$facts['results'][$ref] ?? ''] ?? null) !== $class) {
                return false;
            }
        }

        return true;
    }

    /* ------------------------------------------------ unsupported claims */

    private static function assertions(string $sentence, array $unit, array $facts): array
    {
        if ($unit['role'] === 'confirm') {
            return array();
        }

        $issues = array();
        $hedged = self::hedged($sentence);

        foreach (self::named_products($sentence) as $product) {
            if (strpos($facts['recorded'], $product) !== false) {
                continue;
            }
            $issues[] = self::issue(
                $unit['role'] === 'approach' ? 'Medium' : 'High',
                'unsupported_assertion',
                $unit,
                'Names a product or vendor ("'.$product.'") that does not appear in any recorded response or evidence title: "'.self::excerpt($sentence).'". Remove the name or refer to a generic category.',
                self::refs_in($sentence, $unit, $facts)
            );
        }

        $presumed = '/\b(?:the\s+organi[sz]ation\'s|its|the)\s+(?:[a-z-]+\s+){0,2}(?:'.self::SYSTEM_NOUNS.')\b/i';
        if (preg_match($presumed, $sentence, $match) === 1
            && preg_match('/\b(?:if one exists|if any|where used|if used|where one exists|where applicable|if present|where present|such as|for example|in scope|whether|which)\b/i', $sentence) !== 1
            && strpos($facts['recorded'], strtolower(preg_replace('/^(?:the\s+organi[sz]ation\'s|its|the)\s+/i', '', $match[0]))) === false) {
            $issues[] = self::issue(
                $unit['role'] === 'recorded' ? 'Medium' : 'Low',
                'unsupported_assertion',
                $unit,
                'Presumes a specific client system ("'.$match[0].'") that the recorded responses do not describe: "'.self::excerpt($sentence).'". Refer to it generically or conditionally ("where used"), or move it to To be confirmed.',
                self::refs_in($sentence, $unit, $facts)
            );
        }

        if ($unit['role'] !== 'recorded' || $hedged) {
            return $issues;
        }

        $patterns = array(
            'a data flow or storage location' => '/\b(?:data|information|records?|files?|backups?)\s+(?:[a-z-]+\s+){0,3}(?:is|are)\s+(?:currently\s+|routinely\s+)?(?:sent|transferred|transmitted|shared|stored|hosted|replicated|synchroni[sz]ed|exported|uploaded|held|kept|processed)\s+(?:to|in|on|with|from|between|into|outside|by)\b|\bflows?\s+(?:from|to|between|into)\b/i',
            'what staff currently do' => '/\b(?:staff|employees|personnel|users|administrators|admins|managers|engineers|developers|technicians|contractors)\s+(?:currently\s+|routinely\s+|often\s+|usually\s+|commonly\s+|regularly\s+|still\s+|informally\s+)?(?:use|share|rely on|store|bypass|reuse|log in|send|email|keep|handle|approve|grant|copy|download|access)\b/i',
            'the tools the organization uses' => '/\b(?:the\s+organi[sz]ation|it)\s+(?:currently\s+|already\s+)?(?:uses|relies on|operates|runs|hosts|outsources|depends on|employs|licenses|subscribes to)\b/i',
            'verification that is not recorded' => '/\b(?:assessor|assessment team|we)\s+(?:observed|tested|verified|inspected|reviewed|sampled|confirmed|found|walked through)\b|\b(?:evidence|testing|inspection|sampling)\s+(?:shows?|showed|indicates?|indicated|demonstrates?|confirms?|confirmed)\b|\b(?:was|were|has been|have been)\s+(?:observed|verified|tested|inspected|sampled)\b/i',
            'a cause' => '/\b(?:due to|stems? from|root cause(?!\s+analys)|as a result of|owing to|attributable to|caused by|reflects? a (?:culture|lack|tendency|reliance))\b/i'
        );

        foreach ($patterns as $label => $pattern) {
            if (preg_match($pattern, $sentence, $match) !== 1) {
                continue;
            }
            if ($label === 'verification that is not recorded' && $facts['recorded'] !== '' && self::evidence_for($sentence, $unit, $facts) > 0) {
                continue;
            }
            $issues[] = self::issue(
                'Medium',
                'unsupported_assertion',
                $unit,
                'States '.$label.' as fact ("'.trim($match[0]).'") without support in the recorded responses: "'.self::excerpt($sentence).'". Keep to the recorded result, make it conditional, or move it to To be confirmed.',
                self::refs_in($sentence, $unit, $facts)
            );
        }

        return $issues;
    }

    /** A scenario or consequence field with no conditional or modal word reads as an observed event. */
    private static function unhedged(array $unit): array
    {
        if ($unit['role'] !== 'exposure' || self::hedged($unit['text'])) {
            return array();
        }

        return array(
            self::issue(
                'Medium',
                'unsupported_assertion',
                $unit,
                'A potential exposure is stated as fact, with no condition or modal ("if", "could", "may"): "'.self::excerpt($unit['text']).'". Scenarios are possibilities, not observed events.',
                $unit['ref'] !== null ? array(
                    $unit['ref']
                ) : array()
            )
        );
    }

    /* ------------------------------------------------------ overstatement */

    private static function overstatements(string $sentence, array $unit, array $facts): array
    {
        if ($unit['role'] !== 'recorded' || self::hedged($sentence)) {
            return array();
        }

        $absolute = '/\b(?:never|whatsoever|at all)\b|\b(?:entirely|completely|wholly|totally)\s+(?:absent|missing|lacking|without|informal|ad hoc|manual|reactive|undocumented|unmanaged|uncontrolled|unaddressed)\b|\b(?:has|have) no\b|\bthere (?:is|are) no\b|\b(?:does|do) not (?:exist|have)\b|\bnone of (?:its|the organi[sz]ation\'s)\b|\bno (?:formal |documented |defined |effective )?(?:process|procedure|policy|policies|control|controls|inventory|monitoring|review|reviews|training|plan|program|programme|mechanism|logs?|oversight|governance|testing|encryption|approval|ownership)\b/i';

        if (preg_match($absolute, $sentence, $match) !== 1) {
            return array();
        }

        $rest = substr($sentence, strpos($sentence, $match[0]) + strlen($match[0]));
        if (preg_match('/^\s*(?:[a-z]+\s+)?(?:evidence|assessor|record|objectives?|points?|criteri|requirements?|controls? (?:is|are) recorded)/i', $rest) === 1 || preg_match('/\bevidence\b|\bnot recorded\b|\bnot assessed\b/i', $sentence) === 1) {
            return array();
        }

        $refs = self::refs_in($sentence, $unit, $facts);
        $partial = false;
        $absent = false;
        $evidence = 0;

        foreach ($refs as $ref) {
            $class = self::RESULT_CLASS[$facts['results'][$ref] ?? ''] ?? null;
            $partial = $partial || $class === 'partial' || $class === 'met';
            $absent = $absent || $class === 'absent';
            $evidence += $facts['evidence'][$ref] ?? 0;
        }

        if (count($refs) === 0) {
            $partial = ($facts['counts']['Partially Implemented'] ?? 0) > ($facts['counts']['Not Implemented'] ?? 0);
            $evidence = $facts['with_evidence'];
        }

        if (!$partial || $absent || $evidence > 0) {
            return array();
        }

        return array(
            self::issue(
                'Medium',
                'overstatement',
                $unit,
                'Absolute language ("'.trim($match[0]).'") where the related results are partially implemented and no evidence is linked: "'.self::excerpt($sentence).'". Describe what is recorded as incomplete instead.',
                $refs
            )
        );
    }

    /* -------------------------------------------------------- consistency */

    private static function consistency(array $sections, array $units, array $facts): array
    {
        $issues = array();
        $remediation = $sections['remediation'] ?? null;
        $executive = $sections['executive'] ?? null;
        $patterns = $sections['patterns'] ?? null;
        $ids = array();

        if (is_array($remediation)) {
            $covered = array();
            $number = 0;
            foreach ($remediation['initiatives'] ?? array() as $n => $initiative) {
                $controls = array_values(array_filter((array) ($initiative['controls'] ?? array()), function ($ref) use ($facts) {
                    return is_string($ref) && isset($facts['gaps'][$ref]);
                }));
                if (count($controls) === 0) {
                    continue;
                }
                $number++;
                $ids['R-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT)] = true;
                $unit = self::unit_for('remediation', 'initiatives['.$n.']');
                $phase = (string) ($initiative['phase'] ?? '');
                $priorities = array();
                foreach ($controls as $ref) {
                    if (isset($covered[$ref])) {
                        $issues[] = self::issue('High', 'cross_report_inconsistency', $unit, $ref.' is covered by more than one remediation initiative.', array(
                            $ref
                        ));
                    }
                    $covered[$ref] = true;
                    $priorities[] = $facts['priority'][$ref] ?? null;
                }
                if ($phase === ReportAnalysis::PHASES[3] && in_array('High', $priorities, true)) {
                    $issues[] = self::issue('Low', 'cross_report_inconsistency', $unit, 'The initiative "'.(string) ($initiative['title'] ?? '').'" is placed in '.$phase.' but covers gaps with High derived priority ('.implode(', ', array_keys(array_filter(array_combine($controls, $priorities), function ($value) {
                        return $value === 'High';
                    }))).'); the remediation plan and the findings rank them differently.', $controls);
                }
                if ($phase === ReportAnalysis::PHASES[0] && !in_array('High', $priorities, true) && !in_array('Medium', $priorities, true)) {
                    $issues[] = self::issue('Low', 'cross_report_inconsistency', $unit, 'The initiative "'.(string) ($initiative['title'] ?? '').'" is an Immediate priority although every gap it covers has Low derived priority.', $controls);
                }
            }
            $missing = array_values(array_diff(array_keys($facts['gaps']), array_keys($covered)));
            if (count($missing) > 0) {
                $issues[] = self::issue('High', 'cross_report_inconsistency', self::unit_for('remediation', 'initiatives'), 'No remediation initiative covers '.implode(', ', $missing).', so these gaps have a finding but no planned remediation.', $missing);
            }
        }

        foreach ($sections as $key => $content) {
            if (ReportAI::kind((string) $key) !== 'domain' || !is_array($content)) {
                continue;
            }
            $family = substr((string) $key, 7);
            $entries = array();
            foreach ($content['controls'] ?? array() as $entry) {
                if (is_string($entry['ref'] ?? null) && trim((string) ($entry['recorded_condition'] ?? ($entry['observation'] ?? ''))) !== '') {
                    $entries[$entry['ref']] = true;
                }
            }
            $missing = array();
            foreach ($facts['gaps'] as $ref => $gap_family) {
                if ($gap_family === $family && !isset($entries[$ref])) {
                    $missing[] = $ref;
                }
            }
            if (count($missing) > 0) {
                $issues[] = self::issue('Medium', 'cross_report_inconsistency', self::unit_for((string) $key, 'controls'), 'No recorded condition is written for '.implode(', ', $missing).'; the findings for these gaps fall back to the calculated text.', $missing);
            }
        }

        if (is_array($executive)) {
            $cited = array();
            foreach (array(
                'key_findings',
                'strategic_recommendations'
            ) as $list) {
                foreach ($executive[$list] ?? array() as $n => $entry) {
                    foreach ((array) ($entry['refs'] ?? array()) as $ref) {
                        if (!is_string($ref)) {
                            continue;
                        }
                        $control = $facts['parent'][$ref] ?? $ref;
                        $cited[$control] = true;
                        if ($list === 'key_findings' && isset($facts['results'][$control]) && !isset($facts['gaps'][$control])) {
                            $issues[] = self::issue('High', 'cross_report_inconsistency', self::unit_for('executive', 'key_findings['.$n.']'), 'Key finding "'.(string) ($entry['title'] ?? '').'" cites '.$control.', which is recorded as '.$facts['results'][$control].' and is not a gap.', array(
                                $control
                            ));
                        }
                    }
                }
            }
            $uncited = array();
            foreach ($facts['priority'] as $ref => $priority) {
                if ($priority === 'High' && !isset($cited[$ref])) {
                    $uncited[] = $ref;
                }
            }
            if (count($uncited) > 0 && count($uncited) <= 12) {
                $issues[] = self::issue('Low', 'cross_report_inconsistency', self::unit_for('executive', 'key_findings'), 'Gaps with High derived priority are not cited by any key finding or strategic recommendation: '.implode(', ', $uncited).'.', $uncited);
            }
        }

        if (is_array($patterns)) {
            foreach ($patterns['patterns'] ?? array() as $n => $pattern) {
                $listed = array();
                foreach ((array) ($pattern['refs'] ?? array()) as $ref) {
                    if (is_string($ref)) {
                        $listed[$facts['parent'][$ref] ?? $ref] = true;
                    }
                }
                $named = array();
                foreach (self::mentions((string) ($pattern['description'] ?? ''), null, $facts) as $mention) {
                    $control = $facts['parent'][$mention['ref']] ?? $mention['ref'];
                    if (!isset($listed[$control])) {
                        $named[$control] = true;
                    }
                }
                if (count($named) > 0) {
                    $issues[] = self::issue('Low', 'cross_report_inconsistency', self::unit_for('patterns', 'patterns['.$n.']'), 'The pattern "'.(string) ($pattern['label'] ?? '').'" names '.implode(', ', array_keys($named)).' in its description but not in its supporting refs.', array_keys($named));
                }
            }
        }

        foreach ($units as $unit) {
            if (preg_match_all('/\bR-\d{2}\b/', $unit['text'], $match) && is_array($remediation)) {
                foreach (array_unique($match[0]) as $id) {
                    if (!isset($ids[$id])) {
                        $issues[] = self::issue('Medium', 'cross_report_inconsistency', $unit, 'Refers to initiative '.$id.', which does not exist in the remediation plan.', array());
                    }
                }
            }
            if ($unit['role'] === 'recorded') {
                $issues = array_merge($issues, self::count_checks($unit, $facts));
            }
        }

        return $issues;
    }

    /** Counts and scores quoted in recorded text must match the results for the section's scope. */
    private static function count_checks(array $unit, array $facts): array
    {
        $issues = array();
        $kind = ReportAI::kind($unit['section']);
        $family = $kind === 'domain' ? ($facts['families'][substr($unit['section'], 7)] ?? null) : null;
        $number = '(\d+|'.implode('|', array_keys(self::NUMBERS)).')';

        $allowed = function (string $result) use ($facts, $family): array {
            $values = array(
                (int) ($facts['counts'][$result] ?? 0)
            );
            if ($family !== null) {
                $values[] = (int) ($family['counts'][$result] ?? 0);
            } else {
                foreach ($facts['families'] as $row) {
                    $values[] = (int) ($row['counts'][$result] ?? 0);
                }
            }
            return $values;
        };

        $nouns = '(?:criteria|criterion|controls?|requirements?|clauses?|practices?|safeguards?|subcategor(?:y|ies)|categor(?:y|ies)\s+criteria)';
        if (preg_match_all('/(?<![\w.\-])'.$number.'\s+(?:of\s+(?:the\s+|its\s+)?(?:\d+|[a-z]+)\s+)?(?:'.$nouns.'\s+)?(?:(?:are|is|were|was)\s+)?(?:(?:recorded|rated|assessed)\s+)?(?:as\s+)?(not implemented|partially implemented|implemented)\b/i', $unit['text'], $match, PREG_SET_ORDER)) {
            foreach ($match as $hit) {
                $value = self::number($hit[1]);
                $result = ucwords(strtolower($hit[2]));
                if ($value === null || in_array($value, $allowed($result), true)) {
                    continue;
                }
                $issues[] = self::issue('High', 'cross_report_inconsistency', $unit, 'States '.$value.' '.strtolower($result).' ("'.trim($hit[0]).'"), which does not match the recorded counts ('.(int) ($facts['counts'][$result] ?? 0).' overall'.($family !== null ? ', '.(int) ($family['counts'][$result] ?? 0).' in this family' : '').').', array());
            }
        }

        if (preg_match_all('/\bscore\s+(?:of\s+|is\s+|was\s+|stands at\s+|at\s+)?(\d+)\b|\b(\d+)\s*(?:%|percent)\s+(?:implementation\s+)?score\b/i', $unit['text'], $match, PREG_SET_ORDER)) {
            $scores = array(
                $facts['score']
            );
            foreach ($facts['families'] as $row) {
                $scores[] = $row['score'];
            }
            foreach ($match as $hit) {
                $value = (int) ($hit[1] !== '' ? $hit[1] : $hit[2]);
                if (!in_array($value, $scores, true)) {
                    $issues[] = self::issue('High', 'cross_report_inconsistency', $unit, 'Quotes a score of '.$value.', which matches neither the overall implementation score ('.$facts['score'].') nor any family score.', array());
                }
            }
        }

        return $issues;
    }

    private static function number(string $value): ?int
    {
        if (ctype_digit($value)) {
            return (int) $value;
        }

        return self::NUMBERS[strtolower($value)] ?? null;
    }

    /* --------------------------------------------------------- repetition */

    /** Sentence pairs in different places whose word shingles overlap above the threshold; the most similar first. */
    private static function repetitions(array $units): array
    {
        $items = array();

        foreach ($units as $u => $unit) {
            if ($unit['role'] === 'confirm') {
                continue;
            }
            foreach (self::sentences($unit['text']) as $sentence) {
                $words = self::words($sentence);
                if (count($words) < self::REPEAT_MIN_WORDS) {
                    continue;
                }
                $shingles = array();
                for ($i = 0; $i + self::SHINGLE <= count($words); $i++) {
                    $shingles[implode(' ', array_slice($words, $i, self::SHINGLE))] = true;
                }
                $items[] = array(
                    'unit' => $u,
                    'sentence' => $sentence,
                    'shingles' => $shingles
                );
            }
        }

        $index = array();
        foreach ($items as $n => $item) {
            foreach (array_keys($item['shingles']) as $shingle) {
                $index[$shingle][] = $n;
            }
        }

        $shared = array();
        foreach ($index as $list) {
            if (count($list) < 2 || count($list) > 40) {
                continue;
            }
            $count = count($list);
            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    if ($list[$i] !== $list[$j]) {
                        $key = $list[$i].':'.$list[$j];
                        $shared[$key] = ($shared[$key] ?? 0) + 1;
                    }
                }
            }
        }

        $pairs = array();
        foreach ($shared as $key => $common) {
            if ($common < 3) {
                continue;
            }
            list($a, $b) = array_map('intval', explode(':', $key));
            if ($items[$a]['unit'] === $items[$b]['unit']) {
                continue;
            }
            $union = count($items[$a]['shingles']) + count($items[$b]['shingles']) - $common;
            $similarity = $union > 0 ? $common / $union : 0;
            if ($similarity >= self::REPEAT_THRESHOLD) {
                $pairs[] = array(
                    'a' => $a,
                    'b' => $b,
                    'similarity' => $similarity
                );
            }
        }

        usort($pairs, function ($x, $y) {
            return $y['similarity'] <=> $x['similarity'];
        });

        $issues = array();
        $seen = array();

        foreach ($pairs as $pair) {
            if (count($issues) >= self::MAX_REPETITIONS) {
                break;
            }
            $first = $units[$items[$pair['a']]['unit']];
            $second = $units[$items[$pair['b']]['unit']];
            $key = $first['field'].'|'.$second['field'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $where = $first['section'] === $second['section'] ? $first['field'] : $first['section'].' ('.$first['field'].')';
            $issues[] = self::issue(
                $pair['similarity'] >= self::REPEAT_MEDIUM && in_array($second['role'], array(
                    'exposure',
                    'approach'
                ), true) ? 'Medium' : 'Low',
                'repetition',
                $second,
                'Repeats, in substance ('.(int) round($pair['similarity'] * 100).'% word overlap), text already used in '.$where.': "'.self::excerpt($items[$pair['b']]['sentence']).'". Make each statement specific to its requirement or remove the repeat.',
                array_values(array_filter(array(
                    $first['ref'],
                    $second['ref']
                )))
            );
        }

        return $issues;
    }

    private static function words(string $sentence): array
    {
        $text = strtolower($sentence);
        $text = preg_replace('/\b[a-z]{1,4}\d+(?:\.\d+)*(?:-[a-z]{1,4}\d+)?\b/', 'ref', $text);
        $text = preg_replace('/[^a-z0-9 ]+/', ' ', $text);

        return array_values(array_filter(explode(' ', $text), function ($word) {
            return $word !== '';
        }));
    }

    /* ------------------------------------------------------------ helpers */

    private static function refs_in(string $sentence, array $unit, array $facts): array
    {
        $refs = array();

        foreach (self::mentions($sentence, $unit['ref'], $facts) as $mention) {
            $refs[] = $mention['ref'];
        }

        if (count($refs) === 0 && $unit['ref'] !== null) {
            $refs[] = $unit['ref'];
        }

        return array_values(array_unique($refs));
    }

    private static function evidence_for(string $sentence, array $unit, array $facts): int
    {
        $total = 0;

        foreach (self::refs_in($sentence, $unit, $facts) as $ref) {
            $total += $facts['evidence'][$ref] ?? 0;
        }

        return $total;
    }

    private static function unit_for(string $section, string $field): array
    {
        return array(
            'section' => $section,
            'field' => $field,
            'role' => 'other',
            'ref' => null,
            'text' => ''
        );
    }

    private static function issue(string $severity, string $category, array $unit, string $description, array $refs): array
    {
        return array(
            'severity' => $severity,
            'category' => $category,
            'section' => $unit['section'],
            'field' => $unit['field'],
            'description' => $description,
            'refs' => array_values(array_unique(array_filter($refs, 'is_string'))),
            'source' => self::SOURCE
        );
    }

    public static function excerpt(string $text, int $max = 160): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));

        return mb_strlen($text) > $max ? rtrim(mb_substr($text, 0, $max - 1)).'…' : $text;
    }

    private static function unique(array $issues): array
    {
        $out = array();

        foreach ($issues as $issue) {
            $out[$issue['section'].'|'.$issue['field'].'|'.$issue['category'].'|'.$issue['description']] = $issue;
        }

        return array_values($out);
    }

    private static function sorted(array $issues): array
    {
        usort($issues, function ($x, $y) {
            return self::SEVERITY_RANK[$y['severity']] <=> self::SEVERITY_RANK[$x['severity']] ?: strcmp($x['section'], $y['section']);
        });

        return $issues;
    }
}
