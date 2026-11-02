<?php
/**
 * NIST SP 800-171 Rev 3. The definition is built from the OSCAL catalog; these
 * checks compare it with the published PDFs of SP 800-171 r3 and SP 800-171A r3.
 */
$definition = function () {
    return json_decode(file_get_contents(ROOT.'/frameworks/nist-800-171-r3.json'), true);
};

$controls = function () use ($definition) {
    $all = array();

    foreach ($definition()['families'] as $family) {
        foreach ($family['controls'] as $control) {
            $control['family'] = $family['number'];
            $all[$control['id']] = $control;
        }
    }

    return $all;
};

// PDF text with running headers/footers removed, for word-for-word checks.
$pdf = function (string $file, string $footer) {
    $text = Source::pdf_text($file);
    $text = preg_replace('/'.preg_quote($footer, '/').'\s*May 2024\s*\d+\s*/', "\n", $text);

    return $text;
};

// pdftotext drops some spaces ("potentialsecurityimpacts"), so word-for-word
// checks compare text with all whitespace removed.
$squash = function (string $text): string {
    return preg_replace('/\s+/u', '', Source::normalise($text));
};

$r3_text = function () use ($pdf) {
    return $pdf('nist-800-171r3/sp800-171r3.pdf', 'NIST SP 800-171r3 Protecting Controlled Unclassified Information');
};

$a_text = function () use ($pdf) {
    return $pdf('nist-800-171r3/sp800-171a-r3.pdf', 'NIST SP 800-171Ar3 Assessing CUI Security Requirements');
};

// Requirement headings in chapter 3 of a PDF: "03.01.01 Account Management" / "03.01.13 Withdrawn".
$headings = function (string $text, string $from, string $to) {
    $chapter = Source::between($text, $from, $to);
    preg_match_all('/^(03\.\d{2}\.\d{2}) ([A-Z][^\n]*)$/m', $chapter, $m, PREG_SET_ORDER);
    $out = array();

    foreach ($m as $row) {
        if (!isset($out[$row[1]])) {
            $out[$row[1]] = trim($row[2]);
        }
    }

    return $out;
};

$r3_headings = function () use ($headings, $r3_text) {
    return $headings($r3_text(), "\n3.1. Access Control\n", "\nReferences\n");
};

$a_headings = function () use ($headings, $a_text) {
    return $headings($a_text(), "\n3.1. Access Control\n", "\nReferences\n");
};

return array(
    '97 requirements in 17 families with the published counts' => function () use ($definition, $r3_headings, $a_headings) {
        $expected = array(
            '03.01' => 16,
            '03.02' => 2,
            '03.03' => 8,
            '03.04' => 10,
            '03.05' => 8,
            '03.06' => 5,
            '03.07' => 3,
            '03.08' => 7,
            '03.09' => 2,
            '03.10' => 5,
            '03.11' => 3,
            '03.12' => 4,
            '03.13' => 10,
            '03.14' => 5,
            '03.15' => 3,
            '03.16' => 3,
            '03.17' => 3
        );
        $actual = array();

        foreach ($definition()['families'] as $family) {
            $actual[$family['number']] = count($family['controls']);
        }

        Assert::same($expected, $actual, 'requirements per family');
        Assert::same(97, $definition()['counts']['controls'], 'requirement count');
        Assert::same(17, $definition()['counts']['families'], 'family count');

        $pdfs = array(
            'SP 800-171 r3' => $r3_headings(),
            'SP 800-171A r3' => $a_headings()
        );

        foreach ($pdfs as $name => $list) {
            $per_family = array();

            foreach ($list as $id => $title) {
                if ($title === 'Withdrawn') {
                    continue;
                }

                $f = substr($id, 0, 5);
                $per_family[$f] = ($per_family[$f] ?? 0) + 1;
            }

            Assert::same($expected, $per_family, 'requirements per family in the '.$name.' PDF');
        }
    },

    'requirement IDs and titles match the SP 800-171 r3 PDF' => function () use ($controls, $r3_headings) {
        $pdf = array();

        foreach ($r3_headings() as $id => $title) {
            if ($title !== 'Withdrawn') {
                $pdf[$id] = Source::normalise($title);
            }
        }

        $built = array();

        foreach ($controls() as $id => $control) {
            $built[$id] = Source::normalise($control['title']);
        }

        Assert::same($pdf, $built, 'requirement IDs and titles');
    },

    'withdrawn requirements are excluded (33 in the catalog and the PDF)' => function () use ($controls, $r3_headings) {
        $catalog = json_decode(file_get_contents(Source::path('nist-800-171r3/NIST_SP800-171_rev3_catalog.json')), true)['catalog'];
        $withdrawn = array();
        $all = 0;

        foreach ($catalog['groups'] as $group) {
            foreach ($group['controls'] as $control) {
                $all++;

                foreach ($control['props'] ?? array() as $prop) {
                    if ($prop['name'] === 'status' && $prop['value'] === 'withdrawn') {
                        $withdrawn[] = substr($control['id'], strlen('SP_800_171_'));
                    }
                }
            }
        }

        $pdf = array_keys(array_filter($r3_headings(), function ($title) {
            return $title === 'Withdrawn';
        }));

        Assert::same(130, $all, 'requirement numbers in the catalog');
        Assert::same(33, count($withdrawn), 'withdrawn requirements in the catalog');
        Assert::same($pdf, $withdrawn, 'withdrawn requirements in the catalog and the PDF');
        Assert::same(array(), array_values(array_intersect($withdrawn, array_keys($controls()))), 'withdrawn requirements loaded');
    },

    'requirement text matches the SP 800-171 r3 PDF' => function () use ($controls, $r3_text, $squash) {
        $haystack = $squash($r3_text());

        foreach ($controls() as $id => $control) {
            foreach (explode("\n", $control['text']) as $line) {
                $line = preg_replace('/^([a-z]|\d+)\. /', '', $line);
                Assert::true(strpos($haystack, $squash($line)) !== false, $id.' text not found in the PDF: "'.$line.'"');
            }
        }
    },

    '422 determination statements with the SP 800-171A r3 IDs, order and text' => function () use ($definition, $controls, $a_text, $squash) {
        Assert::same(422, $definition()['counts']['objectives'], 'objective count');
        $raw = $a_text();
        preg_match_all('/^(A\.(03\.\d{2}\.\d{2})(?:\.[a-z](?:\.\d{2})*)?(?:\[\d+\])?): /m', $raw, $m, PREG_SET_ORDER);
        $pdf = array();

        foreach ($m as $row) {
            if (!in_array($row[1], $pdf[$row[2]] ?? array(), true)) {
                $pdf[$row[2]][] = $row[1];
            }
        }

        $haystack = $squash($raw);

        // The catalog labels A.03.10.02.ODP[02] "events or potential indicators of
        // events"; the 171A PDF prints "indications". Only that label differs.
        $known = array(
            'A.03.10.02.b[02]' => array(
                'indicators of events>',
                'indications of events>'
            )
        );

        foreach ($controls() as $id => $control) {
            Assert::same($pdf[$id] ?? array(), array_column($control['objectives'], 'id'), 'determination statements of '.$id);

            foreach ($control['objectives'] as $objective) {
                $needle = $objective['id'].': '.$objective['text'];

                if (isset($known[$objective['id']])) {
                    $needle = str_replace($known[$objective['id']][0], $known[$objective['id']][1], $needle);
                }

                Assert::true(strpos($haystack, $squash($needle)) !== false, 'objective text not found in 171A: "'.$needle.'"');
            }
        }

        Assert::same(count($controls()), count($pdf), 'requirements with determination statements in 171A');
    },

    'organization-defined parameters match SP 800-171A r3' => function () use ($controls, $a_text) {
        preg_match_all('/^(A\.03\.\d{2}\.\d{2}\.ODP\[\d+\]): /m', Source::between($a_text(), "\n3.1. Access Control\n", "\nReferences\n"), $m);
        $pdf = array_values(array_unique($m[1]));
        $built = array();

        foreach ($controls() as $control) {
            foreach ($control['parameters'] as $parameter) {
                $built[] = $parameter['id'];
            }
        }

        Assert::same($pdf, $built, 'ODP IDs');
    },

    'family names match the SP 800-171 r3 PDF' => function () use ($definition, $r3_text) {
        preg_match_all('/^3\.(\d+)\. ([A-Z][A-Za-z ,]+)$/m', Source::between($r3_text(), "\n3.1. Access Control\n", "\nReferences\n"), $m, PREG_SET_ORDER);
        $pdf = array();

        foreach ($m as $row) {
            $pdf[sprintf('03.%02d', $row[1])] = $row[2];
        }

        foreach ($definition()['families'] as $family) {
            Assert::same($pdf[$family['number']] ?? null, $family['name'], 'family name for '.$family['number']);
        }
    },

    'system security plan is requirement 03.15.02' => function () use ($definition, $controls) {
        $all = $controls();
        Assert::same('System Security Plan', $all['03.15.02']['title'], '03.15.02 title');
        Assert::same('03.15.02', $definition()['governing_document']['required_by'], 'governing document requirement');
        Assert::same(8, preg_match_all('/^\d+\. /m', $all['03.15.02']['text']), 'SSP content items (a.1 to a.8)');
        Assert::same(count($definition()['governing_document']['sections']), 8, 'SSP sections follow 03.15.02.a');
    }
);
