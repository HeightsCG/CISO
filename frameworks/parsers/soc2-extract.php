<?php
/**
 * Derives the 2017 Trust Services Criteria list (criterion IDs, groups and the
 * short names of each point of focus, with the engagement type it applies to)
 * from the AICPA PDF's text extract. The PDF and extract stay local
 * (gitignored); the derived list is committed as sources/soc2/tsc-2017-ids.json.
 *
 *     php frameworks/parsers/soc2-extract.php    rewrite the committed list
 */
function soc2_groups(): array
{
    return array(
        'CC1' => 'Control Environment',
        'CC2' => 'Information and Communication',
        'CC3' => 'Risk Assessment',
        'CC4' => 'Monitoring Activities',
        'CC5' => 'Control Activities',
        'CC6' => 'Logical and Physical Access Controls',
        'CC7' => 'System Operations',
        'CC8' => 'Change Management',
        'CC9' => 'Risk Mitigation',
        'A1' => 'Availability',
        'C1' => 'Confidentiality',
        'PI1' => 'Processing Integrity',
        'P' => 'Privacy'
    );
}

function soc2_extract(string $text): array
{
    $lines = array();

    foreach (preg_split('/\R/', $text) as $line) {
        $line = preg_replace('/Page \d+$/', '', rtrim($line));

        if (in_array(trim($line), array('TSP', 'Ref. #', 'TRUST SERVICES CRITERIA AND POINTS OF FOCUS'), true)) {
            continue;
        }

        $lines[] = $line;
    }

    $start = null;

    foreach ($lines as $i => $line) {
        if (strpos($line, 'CC1.1 COSO Principle 1:') === 0) {
            $start = $i;
            break;
        }
    }

    $criteria = array();
    $current = null;
    $buffer = array();
    $flush = function () use (&$criteria, &$current, &$buffer) {
        if ($current !== null) {
            $criteria[$current] = $buffer;
        }

        $buffer = array();
    };

    for ($i = $start; $i < count($lines); $i++) {
        $line = $lines[$i];

        if (preg_match('/^(CC\d\.\d+|A1\.\d|C1\.\d|PI1\.\d|P\d\.\d) /', $line, $m)) {
            $flush();
            $current = preg_match('/^P\d\.0$/', $m[1]) ? null : $m[1];
            continue;
        }

        if (preg_match('/^(ADDITIONAL CRITERIA FOR|Appendix|APPENDIX)/', $line) && $current !== null && strpos($current, 'PI1.5') !== 0) {
            $flush();
            $current = null;
            continue;
        }

        if (preg_match('/^(Appendix|APPENDIX)/', $line)) {
            $flush();
            $current = null;
            break;
        }

        if ($current !== null) {
            $buffer[] = $line;
        }
    }

    $flush();
    $groups = soc2_groups();
    $out = array();

    foreach ($criteria as $id => $block) {
        $scope = 'all';
        $points = array();
        $joined = '';

        foreach ($block as $line) {
            $trim = trim($line);

            if (preg_match('/^Points of focus specified in the COSO framework/', $trim)) {
                $joined .= "\n@scope coso\n";
                continue;
            }

            if (preg_match('/^(Additional )?points? of focus (when using|that apply)[^:]*?(for (availability|confidentiality|privacy|processing integrity)|at the system level|for all engagements)?:?$/i', $trim, $s)) {
                $label = strtolower($s[4] ?? '');

                if ($label === '' && stripos($trim, 'system level') !== false) {
                    $label = 'system level';
                }

                $joined .= "\n@scope ".($label === '' ? 'all' : $label)."\n";
                continue;
            }

            $trim = preg_replace('/fn \d+$/', '', $trim);

            if (substr($joined, -1) === '-' && preg_match('/^[a-z]/', $trim)) {
                $joined = substr($joined, 0, -1).$trim;
            } else {
                $joined .= ' '.$trim;
            }
        }

        foreach (preg_split('/\n/', $joined) as $segment) {
            if (preg_match('/^@scope (.+)$/', $segment, $s)) {
                $scope = $s[1];
                continue;
            }

            preg_match_all('/•\s*([^•]+?)\s*—/u', $segment, $m);

            foreach ($m[1] as $title) {
                $title = preg_replace('/\s+/', ' ', trim($title));
                $point = array(
                    'title' => trim(preg_replace('/(\s*\[[CP]\])+$/', '', $title)),
                    'scope' => $scope
                );

                if (preg_match('/\[C\]/', $title)) {
                    $point['controller'] = true;
                }

                if (preg_match('/\[P\]/', $title)) {
                    $point['processor'] = true;
                }

                $points[] = $point;
            }
        }

        $prefix = preg_match('/^P\d/', $id) ? 'P' : preg_replace('/\.\d+$/', '', $id);
        $out[] = array(
            'id' => $id,
            'group' => $groups[$prefix],
            'points_of_focus' => $points
        );
    }

    return $out;
}

if (PHP_SAPI === 'cli' && realpath($argv[0] ?? '') === realpath(__FILE__)) {
    $dir = dirname(__DIR__).'/sources/soc2/';
    $list = array(
        'pdf_sha256' => hash_file('sha256', $dir.'Trust-services-criteria.pdf'),
        'criteria' => soc2_extract(file_get_contents($dir.'Trust-services-criteria.txt'))
    );
    file_put_contents($dir.'tsc-2017-ids.json', json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
    echo "written\n";
}
