<?php
/**
 * PCI DSS checks. The licensed PDF is only on HCG machines: when it is present
 * the committed requirement list is re-derived from it and the rule quotes are
 * checked; everywhere else the committed list is checked on its own.
 */
require_once ROOT.'/frameworks/parsers/pci-dss-4-extract.php';

$pdf = ROOT.'/frameworks/sources/pci/PCI-DSS-v4_0_1.pdf';
$txt = ROOT.'/frameworks/sources/pci/PCI-DSS-v4_0_1.txt';

$definition = function () {
    return json_decode(file_get_contents(ROOT.'/frameworks/pci-dss-4.json'), true);
};

$controls = function () use ($definition) {
    $all = array();

    foreach ($definition()['families'] as $family) {
        foreach ($family['controls'] as $control) {
            $all[$control['id']] = $control;
        }
    }

    return $all;
};

return array(
    '12 principal requirements and 250 requirements' => function () use ($definition, $controls) {
        $expected = array(
            '1' => 19,
            '2' => 11,
            '3' => 29,
            '4' => 6,
            '5' => 13,
            '6' => 19,
            '7' => 12,
            '8' => 29,
            '9' => 27,
            '10' => 27,
            '11' => 21,
            '12' => 37
        );
        $actual = array();

        foreach ($definition()['families'] as $family) {
            $actual[$family['number']] = count($family['controls']);
        }

        Assert::same($expected, $actual, 'requirements per principal requirement');
        Assert::same(250, count($controls()), 'requirement count');
    },

    'future-dated and service-provider flags' => function () use ($controls) {
        $future = 0;
        $provider = 0;

        foreach ($controls() as $control) {
            $future += isset($control['future_dated_until']) ? 1 : 0;
            $provider += empty($control['service_provider_only']) ? 0 : 1;
        }

        Assert::same(46, $future, 'requirements future-dated until 31 March 2025');
        Assert::same(16, $provider, 'service-provider-only requirements');
        Assert::same('2025-03-31', $controls()['8.4.2']['future_dated_until'] ?? '', '8.4.2 (MFA into the CDE) is future-dated');
        Assert::true(!empty($controls()['12.9.2']['service_provider_only']), '12.9.2 is for service providers');
    },

    'no PCI requirement text is stored and every requirement has HCG content' => function () use ($controls) {
        foreach ($controls() as $id => $control) {
            Assert::same('', $control['text'], $id.' stores requirement text');
            Assert::true(trim($control['title']) !== '' && mb_strlen($control['title']) <= 80, $id.' title missing or longer than 80 characters');
            Assert::true(trim($control['description']) !== '', $id.' has no description');
            Assert::true(trim($control['guidance']) !== '', $id.' has no guidance');
        }
    },

    'licensed copy: committed list matches a fresh extract' => function () use ($pdf, $txt) {
        if (!is_file($pdf) || !is_file($txt)) {
            return;
        }

        $list = json_decode(file_get_contents(ROOT.'/frameworks/sources/pci/pci-dss-4.0.1-ids.json'), true);
        Assert::same($list['pdf_sha256'], hash_file('sha256', $pdf), 'licensed PDF checksum');
        Assert::same($list['requirements'], pci_dss_extract(file_get_contents($txt)), 'requirement list');
    },

    'licensed copy: rule quotes appear in the standard' => function () use ($txt) {
        if (!is_file($txt)) {
            return;
        }

        $text = Source::normalise(file_get_contents($txt));

        foreach (FrameworkBuilder::meta('pci-dss-4')['rules'] as $rule) {
            if (isset($rule['quote'])) {
                Assert::true(strpos($text, Source::normalise($rule['quote'])) !== false, $rule['id'].' quote not found');
            }
        }
    },

    'licensed copy: HCG wording does not copy the standard' => function () use ($txt, $controls) {
        if (!is_file($txt)) {
            return;
        }

        $text = ' '.strtolower(preg_replace('/[^a-z0-9]+/i', ' ', file_get_contents($txt))).' ';

        foreach ($controls() as $id => $control) {
            foreach (array('title', 'description', 'guidance') as $field) {
                $words = preg_split('/\s+/', trim(strtolower(preg_replace('/[^a-z0-9]+/i', ' ', $control[$field]))));

                for ($i = 0; $i + 8 <= count($words); $i++) {
                    $run = ' '.implode(' ', array_slice($words, $i, 8)).' ';
                    Assert::true(strpos($text, $run) === false, $id.' '.$field.' copies the standard: "'.trim($run).'"');
                }
            }
        }
    }
);
