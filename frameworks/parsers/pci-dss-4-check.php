<?php
/**
 * Checks one HCG content file for PCI DSS before the full build:
 *     php frameworks/parsers/pci-dss-4-check.php frameworks/meta/controls/pci-dss-4/<file>.json <first req> <last req>
 * Every ID in the requirement range must be present with a title (max 80
 * characters), description and guidance, and no field may copy eight or more
 * consecutive words from the licensed standard.
 */
$file = $argv[1] ?? '';
$from = (int) ($argv[2] ?? 0);
$to = (int) ($argv[3] ?? 0);
$root = dirname(__DIR__);
$content = json_decode(file_get_contents($file), true);

if (!is_array($content)) {
    exit("Not valid JSON: ".$file."\n");
}

$list = json_decode(file_get_contents($root.'/sources/pci/pci-dss-4.0.1-ids.json'), true);
$text = ' '.strtolower(preg_replace('/[^a-z0-9]+/i', ' ', file_get_contents($root.'/sources/pci/PCI-DSS-v4_0_1.txt'))).' ';
$expected = array();
$problems = array();

foreach ($list['requirements'] as $requirement) {
    if ((int) $requirement['number'] >= $from && (int) $requirement['number'] <= $to) {
        foreach ($requirement['controls'] as $control) {
            $expected[] = $control['id'];
        }
    }
}

foreach ($expected as $id) {
    if (!isset($content[$id])) {
        $problems[] = $id.': missing';
        continue;
    }

    foreach (array('title', 'description', 'guidance') as $field) {
        $value = trim($content[$id][$field] ?? '');

        if ($value === '') {
            $problems[] = $id.': no '.$field;
            continue;
        }

        $words = preg_split('/\s+/', trim(strtolower(preg_replace('/[^a-z0-9]+/i', ' ', $value))));

        for ($i = 0; $i + 8 <= count($words); $i++) {
            $run = ' '.implode(' ', array_slice($words, $i, 8)).' ';

            if (strpos($text, $run) !== false) {
                $problems[] = $id.' '.$field.' copies the standard: "'.trim($run).'"';
                break;
            }
        }
    }

    if (mb_strlen($content[$id]['title'] ?? '') > 80) {
        $problems[] = $id.': title over 80 characters';
    }
}

foreach (array_diff(array_keys($content), $expected) as $extra) {
    $problems[] = $extra.': not a requirement in this range';
}

echo count($expected).' requirements expected, '.count($content).' in file, '.count($problems)." problems\n";
echo implode("\n", $problems).(count($problems) > 0 ? "\n" : '');
exit(count($problems) > 0 ? 1 : 0);
