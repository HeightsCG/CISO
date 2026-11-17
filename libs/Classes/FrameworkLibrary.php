<?php
/**
 * The framework definitions in frameworks/*.json, and the sync that loads one
 * into a company's standards library. Assessments copy controls when they are
 * created, so a sync never changes an existing assessment.
 */
class FrameworkLibrary {

    private static $definitions = null;

    public static function directory(): string
    {
        return dirname(__DIR__, 2).'/frameworks';
    }

    /** Every built definition, keyed by framework key. */
    public static function all(): array
    {
        if (self::$definitions === null) {
            self::$definitions = array();

            foreach (glob(self::directory().'/*.json') as $file) {
                $definition = json_decode(file_get_contents($file), true);

                if (is_array($definition) && isset($definition['key'])) {
                    self::$definitions[$definition['key']] = $definition;
                }
            }

            ksort(self::$definitions);
        }

        return self::$definitions;
    }

    /** Headings of the public framework catalogue, in display order. */
    const CATALOGUE_GROUPS = array(
        'Aviation',
        'US federal',
        'Security',
        'Industry',
        'Privacy',
        'AI',
        'NIST guidance'
    );

    /**
     * The library as a public catalogue: each heading with its framework names, one entry
     * per name (levels and revisions of a framework share one name), in catalogue order.
     */
    public static function catalogue(): array
    {
        $entries = array();

        foreach (self::summaries() as $definition) {
            $entry = $definition['catalogue'];
            $entries[$entry['group'].'|'.$entry['name']] = $entry;
        }

        uasort($entries, function ($a, $b) {
            return $a['order'] <=> $b['order'];
        });

        $groups = array();

        foreach (self::CATALOGUE_GROUPS as $group) {
            $names = array();

            foreach ($entries as $entry) {
                if ($entry['group'] === $group) {
                    $names[] = $entry['name'];
                }
            }

            if (count($names) > 0) {
                $groups[] = array(
                    'group' => $group,
                    'names' => $names
                );
            }
        }

        return $groups;
    }

    /** URL slug of a catalogue name: "ISO/IEC 27001" -> "iso-iec-27001". */
    public static function slug(string $name): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
    }

    /**
     * The public catalogue (frameworks/catalogue/catalogue.json, written by the build): a
     * small summary of every framework, so public pages never decode the full definitions.
     */
    public static function summaries(): array
    {
        static $summaries = null;

        if ($summaries === null) {
            $summaries = json_decode(file_get_contents(self::directory().'/catalogue/catalogue.json'), true);
        }

        return $summaries;
    }

    /**
     * The public page for one catalogue entry (a framework and its levels or revisions):
     * facts drawn only from the catalogue summaries and the crosswalk. Null for an unknown
     * slug. Pages carry section names and counts, never requirement text.
     */
    public static function page(string $slug): ?array
    {
        $members = array();
        $group = '';
        $name = '';
        $all = self::summaries();

        foreach ($all as $key => $summary) {
            if (self::slug($summary['catalogue']['name']) === $slug) {
                $members[$key] = $summary;
                $group = $summary['catalogue']['group'];
                $name = $summary['catalogue']['name'];
            }
        }

        if (count($members) === 0) {
            return null;
        }

        $parts = array();
        $applies = array();
        $glossary = array();
        $notices = array();
        $issuers = array();
        $requirements = 0;

        foreach ($members as $key => $summary) {
            $requirements += $summary['controls'];
            $issuers[$summary['issuing_body']] = true;
            $parts[] = $summary;

            foreach ($summary['applies'] as $line) {
                $applies[$line] = true;
            }

            foreach ($summary['glossary'] as $term) {
                $glossary[$term['term']] = $term['definition'];
            }

            if ($summary['notice'] !== '') {
                $notices[$summary['notice']] = true;
            }
        }

        // Related frameworks: other catalogue entries linked through the crosswalk.
        $crosswalk = json_decode(file_get_contents(self::directory().'/crosswalk/crosswalk.json'), true);
        $sources = array();

        foreach ($crosswalk['sources'] as $source) {
            $sources[$source['id']] = $source['title'];
        }

        $related = array();

        foreach ($crosswalk['sets'] as $set) {
            foreach (array(array($set['from'], $set['to']), array($set['to'], $set['from'])) as $pair) {
                if (!isset($members[$pair[0]]) || !isset($all[$pair[1]]) || isset($members[$pair[1]])) {
                    continue;
                }

                $other = $all[$pair[1]]['catalogue']['name'];
                $related[$other] = array(
                    'name' => $other,
                    'slug' => self::slug($other),
                    'links' => ($related[$other]['links'] ?? 0) + ($crosswalk['counts'][$set['id']] ?? 0),
                    'source' => $sources[$set['source']] ?? ''
                );
            }
        }

        unset($crosswalk);
        usort($related, function ($a, $b) {
            return $b['links'] <=> $a['links'];
        });

        $first = reset($members);
        $applies = array_keys($applies);
        $faq = array(
            array(
                'What is '.$name.'?',
                (count($parts) === 1 ? $first['full_name'] : $name.' has '.count($parts).' parts: '.implode('; ', array_column($parts, 'full_name'))).'. Issued by '.implode(' and ', array_keys($issuers)).'.'
            ),
            array(
                'How does CISO.aero assess '.$name.'?',
                'Each of the '.number_format($requirements).' '.$first['control_term'].' is broken into plain-English points the assessor marks met, not met or not applicable, and the result is calculated from those answers. '.$first['scoring']
            )
        );

        if (count($applies) > 0) {
            array_splice($faq, 1, 0, array(array(
                'What are the scope and applicability rules of '.$name.'?',
                implode(' ', $applies)
            )));
        }

        if (count($related) > 0) {
            $faq[] = array(
                'Which frameworks map to '.$name.'?',
                implode(', ', array_column(array_slice($related, 0, 6), 'name')).'. Evidence attached for '.$name.' is offered against the linked controls of those frameworks.'
            );
        }

        $faq[] = array(
            'What reports does CISO.aero produce for '.$name.'?',
            'Results, gap analysis, statement of applicability, remediation plan and executive summary for the '.$name.' assessment, downloadable as PDF, Word or Excel.'
        );

        return array(
            'slug' => $slug,
            'name' => $name,
            'group' => $group,
            'requirements' => $requirements,
            'parts' => $parts,
            'applies' => $applies,
            'related' => $related,
            'glossary' => $glossary,
            'notices' => array_keys($notices),
            'faq' => $faq,
            'description' => self::page_description($name, $requirements, $first['control_term'])
        );
    }

    /** Meta description for a framework page, kept to search-snippet length. */
    private static function page_description(string $name, int $requirements, string $control_term): string
    {
        $text = 'Assess '.$name.' with CISO.aero: '.number_format($requirements).' '.$control_term.' broken into plain-English points, evidence reused across frameworks, and reports for leadership.';

        return mb_strlen($text) <= 160 ? $text : 'Assess '.$name.' with CISO.aero: '.number_format($requirements).' '.$control_term.', evidence reused across frameworks, reports for leadership.';
    }

    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    /** The definition that serves a standards.short_code, if any. */
    public static function for_short_code(string $short_code): ?array
    {
        foreach (self::all() as $definition) {
            if (in_array($short_code, $definition['standard_codes'], true)) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * What the assessment screens need from a framework: optional scope
     * categories (SOC 2) and extra fields such as the TSA SSI review. Empty for standards not built from a definition.
     */
    public static function assessment_features(string $key): array
    {
        $definition = self::get($key);
        $features = array(
            'ssi_field' => null,
            'notice' => '',
            'partial_labels' => array()
        );

        if ($definition === null) {
            return $features;
        }

        foreach ($definition['fields'] ?? array() as $field) {
            if (($field['key'] ?? '') === 'ssi_reviewed') {
                $features['ssi_field'] = $field;
            }
        }

        $features['notice'] = (string) ($definition['notice'] ?? '');

        foreach ($definition['rules'] as $rule) {
            foreach (($rule['type'] ?? '') === 'partial_credit' ? ($rule['controls'] ?? array()) : array() as $id => $control) {
                $features['partial_labels'][$id] = (string) ($control['partial_label'] ?? '');
            }
        }

        return $features;
    }

    /** "Name (version)", without repeating a version already in the name. */
    public static function display_name(string $name, string $version): string
    {
        $name = trim($name);
        $version = trim($version);

        if ($version === '' || stripos($name, $version) !== false) {
            return $name;
        }

        return $name.' ('.$version.')';
    }

    /** company_id of the shared framework library, which every organisation reads and none may change. */
    const SHARED = 0;

    /**
     * Loads one definition into the shared library (company_id SHARED): updates the standard that
     * already serves it (matched by framework key, then short code) or adds it,
     * updates controls in place by identifier, adds new ones and removes ones
     * the definition no longer has. Returns the standard id.
     */
    public static function sync(int $company_id, string $key, int $user_id = 0): int
    {
        $definition = self::get($key);

        if ($definition === null) {
            throw new RuntimeException('Unknown framework '.$key);
        }

        $sources = array();

        foreach ($definition['sources'] as $source) {
            $sources[$source['id']] = array(
                'title' => $source['title'],
                'version' => $source['version'] ?? $definition['version']
            );
        }

        $primary = $sources[$definition['source_of']['controls']];
        $model = new StandardsModel();
        $existing = $model->framework_standard($company_id, $key, $definition['standard_codes'][0]);
        $model->begin();

        try {
            $standard_id = $model->save_framework_standard(
                $existing === null ? 0 : (int) $existing['id'],
                $company_id,
                $key,
                $definition['name'],
                $definition['standard_codes'][0],
                $definition['version'],
                $definition['full_name'],
                $definition['issuing_body'],
                $primary['title'],
                $primary['version'],
                $user_id
            );

            $ids = array();

            foreach ($model->framework_control_ids($standard_id) as $row) {
                $ids[$row['control_kind'].'|'.$row['control_identifier']] = (int) $row['id'];
            }

            $sort_order = 0;
            $kept = array();

            foreach (self::rows($definition) as $row) {
                $sort_order++;
                $source = $sources[$row['source']];
                $model->save_framework_control(
                    $ids[$row['kind'].'|'.$row['identifier']] ?? 0,
                    $standard_id,
                    $row['identifier'],
                    $row['parent'],
                    $row['kind'],
                    mb_substr($row['title'], 0, 400),
                    $row['description'],
                    $row['guidance'],
                    $row['family'],
                    $row['weight'],
                    $row['partial_weight'],
                    $row['flags'],
                    $source['title'],
                    $source['version'],
                    $sort_order,
                    $user_id
                );
                $kept[$row['kind'].'|'.$row['identifier']] = true;
            }

            $model->rename_assessments($standard_id, $company_id, $definition['name'], $definition['version'], $key);

            foreach ($ids as $kind_identifier => $control_id) {
                if (!isset($kept[$kind_identifier])) {
                    $model->delete_control($control_id, $standard_id, $user_id);
                }
            }

            $model->commit();
        } catch (Throwable $e) {
            $model->roll_back();
            throw $e;
        }

        return $standard_id;
    }

    /** Replaces framework_crosswalk with frameworks/crosswalk/crosswalk.json. Returns the link count. */
    public static function load_crosswalk(): int
    {
        $crosswalk = json_decode(file_get_contents(self::directory().'/crosswalk/crosswalk.json'), true);
        $sources = array();
        $sets = array();

        foreach ($crosswalk['sources'] as $source) {
            $sources[$source['id']] = $source;
        }

        foreach ($crosswalk['sets'] as $set) {
            $sets[$set['id']] = $set;
        }

        $model = new CrosswalkModel();
        $model->begin();

        try {
            $model->clear_links();

            foreach ($crosswalk['links'] as $link) {
                $set = $sets[$link[0]];
                $source = $sources[$set['source']];
                $model->add_link(
                    $set['id'],
                    $set['from'],
                    $link[1],
                    $set['to'],
                    $link[2],
                    $link[3],
                    $source['title'],
                    $source['version']
                );
            }

            $model->commit();
        } catch (Throwable $e) {
            $model->roll_back();
            throw $e;
        }

        return count($crosswalk['links']);
    }

    /** Control and objective rows in library order, ready to store. */
    public static function rows(array $definition): array
    {
        $rows = array();
        $skip = array(
            'id',
            'number',
            'title',
            'text',
            'description',
            'guidance',
            'type',
            'weight',
            'partial_weight',
            'objectives',
            'source',
            'cmmc_id'
        );

        foreach ($definition['families'] as $family) {
            $family_name = $family['name'];

            foreach ($family['controls'] as $control) {
                $text = trim($control['text'] ?? '');
                $title = trim($control['title'] ?? '');
                $flags = array();

                foreach ($control as $field => $value) {
                    if (!in_array($field, $skip, true) && $value !== null && $value !== false && $value !== '') {
                        $flags[$field] = $value;
                    }
                }

                $rows[] = array(
                    'identifier' => $control['id'],
                    'parent' => $control['parent'] ?? '',
                    'kind' => 'control',
                    'title' => $title !== '' ? $title : $text,
                    'description' => $text !== '' ? $text : trim($control['description'] ?? ''),
                    'guidance' => trim($control['guidance'] ?? ''),
                    'family' => $family_name,
                    'weight' => $control['weight'] ?? null,
                    'partial_weight' => $control['partial_weight'] ?? null,
                    'flags' => count($flags) === 0 ? '' : json_encode($flags, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'source' => $control['source']
                );

                foreach ($control['objectives'] as $objective) {
                    $objective_flags = array();

                    foreach ($objective as $field => $value) {
                        if (!in_array($field, array('id', 'text', 'description', 'source'), true) && $value !== null && $value !== false && $value !== '') {
                            $objective_flags[$field] = $value;
                        }
                    }

                    $rows[] = array(
                        'identifier' => $objective['id'],
                        'parent' => $control['id'],
                        'kind' => 'objective',
                        'title' => $objective['text'],
                        'description' => trim($objective['description'] ?? '') !== '' ? trim($objective['description']) : $objective['text'],
                        'guidance' => '',
                        'family' => $family_name,
                        'weight' => null,
                        'partial_weight' => null,
                        'flags' => count($objective_flags) === 0 ? '' : json_encode($objective_flags, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        'source' => $objective['source']
                    );
                }
            }
        }

        return $rows;
    }

}
