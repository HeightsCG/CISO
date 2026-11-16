<?php
/**
 * Australian Privacy Act checks. The parser reads paragraph styles in the Word file;
 * these read the same principles as plain text and find the duties a second way.
 */
$definition = function () {
    return json_decode(file_get_contents(ROOT.'/frameworks/au-privacy.json'), true);
};

// Plain text of the Word file, one paragraph per line.
$lines = function () {
    static $lines = null;

    if ($lines === null) {
        $zip = new ZipArchive();
        $zip->open(Source::path('au-privacy/C2026C00227.docx'));
        $xml = str_replace(array('<w:noBreakHyphen/>', '<w:tab/>'), array('-', ' '), $zip->getFromName('word/document.xml'));
        $zip->close();
        $text = html_entity_decode(strip_tags(preg_replace('/<w:p[ >]/', "\n$0", $xml)), ENT_QUOTES | ENT_XML1, 'UTF-8');
        $lines = array_map(array('Source', 'normalise'), explode("\n", $text));
    }

    return $lines;
};

// Lines of one principle, from its heading in Schedule 1 to the next one.
$principle = function (int $app) use ($lines) {
    $all = $lines();
    $start = null;
    $end = count($all);

    foreach ($all as $i => $line) {
        if (preg_match('/^'.$app.' Australian Privacy Principle '.$app.'-/', $line)) {
            $start = $i;
        }
    }

    foreach ($all as $i => $line) {
        if ($start !== null && $i > $start && preg_match('/^(\d+ Australian Privacy Principle \d+-|Schedule 2-)/', $line)) {
            $end = $i;
            break;
        }
    }

    Assert::true($start !== null, 'APP '.$app.' is in the text');

    return array_slice($all, $start, $end - $start);
};

return array(
    'thirteen principles and the data breach scheme' => function () use ($definition) {
        $codes = array_column($definition()['families'], 'code');

        Assert::same(14, count($codes), 'groups');
        Assert::same('APP 1', $codes[0], 'first');
        Assert::same('APP 13', $codes[12], 'thirteenth');
        Assert::same('Part IIIC', $codes[13], 'data breaches');
    },

    'a subclause is a requirement exactly when it says must' => function () use ($definition, $principle) {
        $built = array();

        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                $built[$control['id']] = $control;
            }
        }

        $kept = 0;

        for ($app = 1; $app <= 13; $app++) {
            $subclauses = array();
            $current = null;

            foreach ($principle($app) as $line) {
                if (preg_match('/^('.$app.'\.\d+)(.*)$/', $line, $m)) {
                    $current = $m[1];
                    $subclauses[$current] = $m[2];
                } elseif ($current !== null && strpos($line, 'Note:') !== 0) {
                    $subclauses[$current] .= ' '.$line;
                }
            }

            Assert::true(count($subclauses) >= 1, 'APP '.$app.' subclauses found');

            foreach ($subclauses as $number => $text) {
                $must = preg_match('/\bmust\b/', $text) === 1;
                Assert::same($must, isset($built['APP '.$number]), 'APP '.$number.' kept when it says must');
                $kept += $must ? 1 : 0;
            }
        }

        Assert::same(37, $kept, 'subclauses kept');
    },

    'every requirement and element is printed in the Act' => function () use ($definition, $lines) {
        $flat = function (string $text): string {
            return strtolower(preg_replace('/[^a-z0-9]+/i', '', $text));
        };
        $all = $flat(implode(' ', $lines()));

        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                $opening = implode(' ', array_slice(explode(' ', $control['text']), 0, 12));
                Assert::true(strpos($all, $flat($opening)) !== false, $control['id'].' text');

                foreach ($control['objectives'] as $objective) {
                    Assert::true(strpos($all, $flat($objective['text'])) !== false, $objective['id'].' text');
                }
            }
        }
    },

    'elements only where every one must be met' => function () use ($definition) {
        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                foreach ($control['objectives'] as $objective) {
                    Assert::true(preg_match('/[;,]\s*or$/', $objective['text']) === 0, $objective['id'].' is not an alternative');
                }

                if (strpos($control['text'], 'If:') === 0) {
                    Assert::same(0, count($control['objectives']), $control['id'].' lists conditions, not elements');
                }
            }
        }
    },

    'well-known contents have their published length' => function () use ($definition) {
        $points = array();

        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                $points[$control['id']] = count($control['objectives']);
            }
        }

        Assert::same(7, $points['APP 1.4'], 'APP privacy policy contents');
        Assert::same(2, $points['APP 11.1'], 'security of personal information');
        Assert::same(4, $points['s 26WK(3)'], 'data breach statement contents');
    }
);
