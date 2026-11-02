<?php
/**
 * NIST SP 800-171 Rev 2: structure derived from the official sources. CMMC
 * Level 2 reuses this control set (see cmmc-l2.php).
 *
 * - Requirement numbers, text, family and basic/derived type: NIST SP 800-171 r2 (CSRC export).
 * - Domain abbreviations (used by CMMC IDs): the requirement IDs listed in 32 CFR 170.24.
 * - Point values: DoD Assessment Methodology v1.2.1, Annex A (cross-checked against
 *   32 CFR 170.24 by the reference test).
 * - Assessment objectives: NIST SP 800-171A (June 2018), from the pdftotext -raw extract.
 */
return function (): array {
    $rows = Source::xlsx_rows('cmmc/sp800-171r2.xlsx');
    $families = array();
    $family = null;

    foreach (array_slice($rows, 1) as $row) {
        $a = trim($row['A'] ?? '');
        $b = trim($row['B'] ?? '');

        if ($a !== '' && preg_match('/^\((3\.\d+)\):\s*(.+)$/', $a, $m)) {
            $family = $m[1];
            $families[$family] = array(
                'number' => $m[1],
                'code' => '',
                'name' => Source::title_case($m[2]),
                'controls' => array()
            );
        }

        if (!preg_match('/^\((3\.\d+\.\d+)\):\s*(.+)$/s', $b, $m)) {
            continue;
        }

        $families[$family]['controls'][$m[1]] = array(
            'id' => '',
            'cmmc_id' => '',
            'number' => $m[1],
            'text' => Source::normalise($m[2]),
            'type' => stripos($row['D'] ?? '', 'Derived') !== false ? 'derived' : 'basic',
            'weight' => null,
            'partial_weight' => null,
            'objectives' => array()
        );
    }

    $cfr = Source::normalise(Source::xml_text('cmmc/32-cfr-170.xml'));
    $scoring = Source::between($cfr, '170.24 CMMC Scoring Methodology', '(3) CMMC Level 3 assessment scoring methodology');
    preg_match_all('/\b([A-Z]{2})[.-]L2-\s?(3\.\d+)\.\d+\b/', $scoring, $ids, PREG_SET_ORDER);

    foreach ($ids as $id) {
        $code = $id[1];
        $number = $id[2];

        if ($families[$number]['code'] !== '' && $families[$number]['code'] !== $code) {
            throw new RuntimeException('Two domain codes for family '.$number);
        }

        $families[$number]['code'] = $code;
    }

    $annex = Source::pdf_text('cmmc/dod-assessment-methodology-1.2.1.pdf');
    $annex = substr($annex, strrpos($annex, 'NIST SP 800-171 DoD Assessment Scoring Template'));
    $weights = array();

    foreach (preg_split('/\R/', $annex) as $line) {
        if (preg_match('/^(3\.\d+\.\d+)\*?\s+.*?\s{2,}(3 to 5|NA|\d)(\s|$)/', $line, $m)) {
            $weights[$m[1]] = $m[2];
        }
    }

    $objectives = array();
    $lines = preg_split('/\R/', Source::pdf_text('cmmc/sp800-171a-2018.pdf'));
    $current = null;
    $collect = false;
    $entries = array();

    foreach ($lines as $line) {
        $trim = trim($line);

        if ($trim === '' || preg_match('/^(CHAPTER THREE|NIST SP 800-171A|_{5,}|This publication is available)/', $trim)) {
            continue;
        }

        if (preg_match('/^(3\.\d+\.\d+)\s+SECURITY REQUIREMENT$/', $trim, $m)) {
            $current = $m[1];
            $collect = false;
            continue;
        }

        if ($current !== null && $trim === 'ASSESSMENT OBJECTIVE') {
            $collect = true;
            $entries = array();
            continue;
        }

        if ($collect && strpos($trim, 'POTENTIAL ASSESSMENT METHODS') === 0) {
            $objectives[$current] = $entries;
            $collect = false;
            $current = null;
            continue;
        }

        if (!$collect) {
            continue;
        }

        if (preg_match('/^(3\.\d+\.\d+\[[a-z]+\])\s*(.*)$/', $trim, $m)) {
            $entry = array(
                'id' => $m[1],
                'lines' => array()
            );

            if ($m[2] !== '') {
                $entry['lines'][] = $m[2];
            }

            $entries[] = $entry;
        } elseif (count($entries) === 0) {
            $entries[] = array(
                'id' => null,
                'lines' => array(
                    $trim
                )
            );
        } else {
            $entries[count($entries) - 1]['lines'][] = $trim;
        }
    }

    $out = array();

    foreach ($families as $number => $f) {
        if ($f['code'] === '') {
            throw new RuntimeException('No domain code found for family '.$number);
        }

        foreach ($f['controls'] as $n => $c) {
            if (!isset($weights[$n])) {
                throw new RuntimeException('No Annex A value for '.$n);
            }

            if (!isset($objectives[$n])) {
                throw new RuntimeException('No 800-171A objectives for '.$n);
            }

            $c['id'] = $n;
            $c['cmmc_id'] = $f['code'].'.L2-'.$n;
            $value = $weights[$n];

            if ($value === '3 to 5') {
                $c['weight'] = 5;
                $c['partial_weight'] = 3;
            } elseif ($value === 'NA') {
                $c['weight'] = 0;
            } else {
                $c['weight'] = (int) $value;
            }

            $entries = $objectives[$n];

            if (count($entries) === 1 && $entries[0]['id'] === null) {
                $c['objectives'][] = array(
                    'id' => $n,
                    'text' => Source::normalise(implode(' ', $entries[0]['lines']))
                );
            } else {
                foreach ($entries as $i => $entry) {
                    if ($entry['id'] === null) {
                        if (Source::normalise(implode(' ', $entry['lines'])) !== 'Determine if:') {
                            throw new RuntimeException('Unexpected objective preamble for '.$n);
                        }

                        continue;
                    }

                    $c['objectives'][] = array(
                        'id' => $entry['id'],
                        'text' => Source::normalise(implode(' ', $entry['lines']))
                    );
                }
            }

            $f['controls'][$n] = $c;
        }

        $f['controls'] = array_values($f['controls']);
        $out[] = $f;
    }

    return $out;
};
