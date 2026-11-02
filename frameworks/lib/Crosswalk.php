<?php
/**
 * Builds frameworks/crosswalk/crosswalk.json: links between controls of
 * different frameworks, from official mapping files and from control sets
 * frameworks share. Links let evidence and results from one assessment be
 * offered in another; results are still reported per framework.
 */
class Crosswalk {

    public static function config(): array
    {
        return json_decode(file_get_contents(dirname(__DIR__).'/crosswalk/sources.json'), true);
    }

    /** Control IDs per framework, from the built definitions. */
    private static function ids(string $key): array
    {
        static $cache = array();

        if (!isset($cache[$key])) {
            $definition = json_decode(file_get_contents(dirname(__DIR__).'/'.$key.'.json'), true);
            $cache[$key] = array();

            foreach ($definition['families'] as $family) {
                foreach ($family['controls'] as $control) {
                    $cache[$key][$control['id']] = $control;
                }
            }
        }

        return $cache[$key];
    }

    /** "IR-04" -> "IR-4", "CP-02(08)" -> "CP-2(8)". */
    private static function sp80053(string $id): string
    {
        $id = trim($id);

        if (!preg_match('/^([A-Z]{2})-0*(\d+)(?:\(0*(\d+)\))?$/', $id, $m)) {
            return $id;
        }

        return $m[1].'-'.$m[2].(isset($m[3]) ? '('.$m[3].')' : '');
    }

    /** ISO references in their various source spellings, as definition IDs. */
    private static function iso(string $reference): array
    {
        $reference = trim(preg_replace('/\s+/', ' ', $reference));
        $annex = false;

        if (preg_match('/^(Annex A Controls|Control):?\s*(.*)$/', $reference, $m)) {
            $annex = true;
            $reference = $m[2];
        } elseif (preg_match('/^Mandatory Clause:?\s*(.*)$/', $reference, $m)) {
            $reference = $m[1];
        }

        $out = array();

        foreach (preg_split('/\s*,\s*/', trim($reference, " ,")) as $part) {
            $part = preg_replace('/\s*\([a-z]\)$/', '', trim($part));

            if (preg_match('/^A\.(\d+\.\d+)$/', $part, $m)) {
                $annex = true;
                $part = $m[1];
            }

            $out[] = ($annex && preg_match('/^[5-8]\.\d+$/', $part)) ? 'A.'.$part : $part;
        }

        return $out;
    }

    /**
     * Resolves a source ID to definition IDs: exact match, or a clause whose
     * sub-clauses are the controls (ISO 6.1 -> 6.1.1, 6.1.2, 6.1.3).
     */
    private static function resolve(string $key, string $id): array
    {
        $ids = self::ids($key);

        if (isset($ids[$id])) {
            return array(
                $id
            );
        }

        $children = array();

        foreach (array_keys($ids) as $candidate) {
            if ($id !== '' && strpos($candidate, $id.'.') === 0) {
                $children[] = $candidate;
            }
        }

        return $children;
    }

    public static function build(): array
    {
        $config = self::config();
        $links = array();
        $unresolved = array();

        $corrections = array();

        foreach ($config['corrections'] as $correction) {
            $corrections[$correction['set'].'|'.$correction['source_id']] = $correction['id'];
        }

        foreach ($config['sets'] as $set) {
            $pairs = self::pairs($set);

            foreach ($pairs as $pair) {
                $pair[1] = $corrections[$set['id'].'|'.$pair[1]] ?? $pair[1];
                $from = self::resolve($set['from'], $pair[0]);
                $to = self::resolve($set['to'], $pair[1]);

                if (count($from) === 0) {
                    $unresolved[$set['from'].':'.$pair[0]] = true;
                }

                if (count($to) === 0) {
                    $unresolved[$set['to'].':'.$pair[1]] = true;
                }

                foreach ($from as $a) {
                    foreach ($to as $b) {
                        $links[$set['id'].'|'.$a.'|'.$b] = array(
                            $set['id'],
                            $a,
                            $b,
                            $pair[2]
                        );
                    }
                }
            }
        }

        $unresolved = array_keys($unresolved);
        sort($unresolved);
        $bad = array_values(array_diff($unresolved, $config['unresolved_allowed']));

        if (count($bad) > 0) {
            throw new RuntimeException('Crosswalk IDs not found in the definitions: '.implode(', ', array_slice($bad, 0, 20)));
        }

        ksort($links);
        $counts = array();

        foreach ($links as $link) {
            $counts[$link[0]] = ($counts[$link[0]] ?? 0) + 1;
        }

        return array(
            'sources' => $config['sources'],
            'sets' => $config['sets'],
            'counts' => $counts,
            'unresolved' => $unresolved,
            'links' => array_values($links)
        );
    }

    /** [from id, to id, relationship] pairs for one set, as written in its source. */
    private static function pairs(array $set): array
    {
        $pairs = array();

        if ($set['source'] === 'csf2refs') {
            $prefix = array(
                'nist-800-53-r5' => 'SP 800-53 Rev 5.2.0',
                'nist-800-171-r3' => 'SP 800-171 Rev 3',
                'iso-27001-2022' => 'ISO/IEC 27001:2022'
            )[$set['to']];

            foreach (Source::xlsx_sheet('crosswalk/csf2-informative-references-all.xlsx', 'CSF 2.0') as $row) {
                if (!preg_match('/^([A-Z]{2}\.[A-Z]{2}-\d{2}):/', trim($row['C'] ?? ''), $m)) {
                    continue;
                }

                foreach (preg_split('/\R/', $row['E'] ?? '') as $line) {
                    $line = trim($line);

                    if (strpos($line, $prefix.':') !== 0) {
                        continue;
                    }

                    $reference = trim(substr($line, strlen($prefix) + 1));
                    $targets = $set['to'] === 'iso-27001-2022' ? self::iso($reference) : array(
                        $set['to'] === 'nist-800-53-r5' ? self::sp80053($reference) : $reference
                    );

                    foreach ($targets as $target) {
                        $pairs[] = array(
                            $m[1],
                            $target,
                            'informative'
                        );
                    }
                }
            }
        } elseif ($set['source'] === 'cuioverlay') {
            $control = null;

            foreach (Source::xlsx_sheet('crosswalk/sp800-171r3-cui-overlay.xlsx', 'CUI Overlay') as $row) {
                if (preg_match('/^([A-Z]{2}-\d{2}(?:\(\d{2}\))?)\s/', trim($row['B'] ?? ''), $b)) {
                    $control = self::sp80053($b[1]);
                }

                if ($control === null || !preg_match('/^(\d{2})-(\d{2})-(\d{2})/', trim($row['D'] ?? ''), $d)) {
                    continue;
                }

                $pairs[] = array(
                    $control,
                    $d[1].'.'.$d[2].'.'.$d[3],
                    'source'
                );
            }
        } elseif ($set['source'] === 'r2r3') {
            $by_text = array();

            foreach (self::ids('nist-800-171-r2') as $id => $control) {
                $by_text[Source::normalise($control['text'])] = $id;
            }

            foreach (Source::xlsx_sheet('crosswalk/sp800-171r2-to-r3-analysis.xlsx', 'Change Analysis 800-171 R2-R3') as $row) {
                $text = Source::normalise($row['D'] ?? '');

                $listed = trim($row['C'] ?? '');

                if (!preg_match('/^3\.\d+\.\d+$/', $listed)) {
                    continue;
                }

                $r2 = $by_text[$text] ?? (isset(self::ids('nist-800-171-r2')[$listed]) ? $listed : null);

                if ($r2 === null) {
                    continue;
                }
                $relationship = trim($row['J'] ?? '') !== '' ? 'equivalent' : 'revised';
                $targets = array(
                    trim($row['G'] ?? '')
                );

                if (trim($row['H'] ?? '') === 'Withdrawn') {
                    preg_match_all('/03\.\d{2}[. ]\s?\d{2}/', $row['I'] ?? '', $m);
                    $targets = preg_replace('/^(03\.\d{2})[. ]\s?(\d{2})$/', '$1.$2', $m[0]);
                    $relationship = 'incorporated';
                }

                foreach ($targets as $target) {
                    if (preg_match('/^03\.\d{2}\.\d{2}$/', $target)) {
                        $pairs[] = array(
                            $r2,
                            $target,
                            $relationship
                        );
                    }
                }
            }
        } elseif ($set['source'] === 'olir155') {
            $zip = new ZipArchive();
            $zip->open(Source::path('iso27001/olir-155-sp800-53r5-to-iso-27001-2022.xlsx'));
            $workbook = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
            $zip->close();

            foreach ($workbook->sheets->sheet as $sheet) {
                if ((string) $sheet['state'] === 'hidden') {
                    continue;
                }

                foreach (Source::xlsx_sheet('iso27001/olir-155-sp800-53r5-to-iso-27001-2022.xlsx', (string) $sheet['name']) as $row) {
                    $control = self::sp80053($row['A'] ?? '');
                    $reference = trim($row['D'] ?? '');

                    if (preg_match('/^\d+\.\d{6,}$/', $reference)) {
                        $reference = (string) round((float) $reference, 3);
                    }

                    if (!preg_match('/^[A-Z]{2}-\d/', $control) || $reference === '') {
                        continue;
                    }

                    foreach (self::iso($reference) as $target) {
                        $pairs[] = array(
                            $control,
                            $target,
                            strtolower(trim($row['G'] ?? '')) !== '' ? strtolower(trim($row['G'])) : 'related'
                        );
                    }
                }
            }
        } elseif ($set['id'] === 'cmmcl2-171r2') {
            foreach (self::ids('cmmc-l2') as $id => $control) {
                $pairs[] = array(
                    $id,
                    $control['number'],
                    'equivalent'
                );
            }
        } elseif ($set['id'] === 'cmmcl1-171r2') {
            foreach (self::ids('cmmc-l1') as $id => $control) {
                foreach ($control['nist_800_171'] as $number) {
                    $pairs[] = array(
                        $id,
                        $number,
                        count($control['nist_800_171']) === 1 ? 'equivalent' : 'subset'
                    );
                }
            }
        } elseif (strpos($set['id'], 'fedramp') === 0) {
            foreach (array_keys(self::ids($set['from'])) as $id) {
                $pairs[] = array(
                    $id,
                    $id,
                    'equivalent'
                );
            }
        } else {
            throw new RuntimeException('No reader for crosswalk set '.$set['id']);
        }

        return $pairs;
    }

}
