<?php
/**
 * TSA Aviation Cybersecurity (HCG control set). The press releases are read
 * here with DOMDocument, independently of the parser's text extraction, to
 * confirm every public outcome TSA states has a control and every control
 * rests on a statement TSA actually made.
 */
$definition = function () {
    return json_decode(file_get_contents(ROOT.'/frameworks/tsa-aviation-cyber.json'), true);
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

// Text content of the elements matching an XPath query in a stored release.
$nodes = function (string $file, string $query): array {
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">'.file_get_contents(Source::path($file)));
    libxml_clear_errors();
    $out = array();

    foreach ((new DOMXPath($dom))->query($query) as $node) {
        $out[] = Source::normalise($node->textContent);
    }

    return $out;
};

return array(
    '8 control areas and 11 controls' => function () use ($definition) {
        $expected = array(
            'GOV' => 1,
            'CIP' => 2,
            'SEG' => 1,
            'ACC' => 1,
            'MON' => 1,
            'VUL' => 2,
            'CAP' => 1,
            'INC' => 2
        );
        $actual = array();

        foreach ($definition()['families'] as $family) {
            $actual[$family['code']] = count($family['controls']);
        }

        Assert::same($expected, $actual, 'controls per area');
        Assert::same(48, $definition()['counts']['objectives'], 'test points');
    },

    'every 2023 amendment outcome has a control built on it' => function () use ($controls, $nodes) {
        $items = $nodes('tsa/tsa-2023-03-07-aviation-cyber-amendment.html', '//div[contains(@class,"text-long")]//ol/li');
        Assert::same(4, count($items), 'numbered outcomes in the 7 March 2023 release');

        foreach ($items as $item) {
            $found = false;

            foreach ($controls() as $control) {
                if ($control['source'] === 'tsa2023' && strpos($item, $control['source_basis']) === 0) {
                    $found = true;
                }
            }

            Assert::true($found, 'no control for 2023 outcome: '.$item);
        }
    },

    'earlier aviation requirements named by TSA have controls' => function () use ($controls, $nodes) {
        $paragraphs = implode(' ', $nodes('tsa/tsa-2023-03-07-aviation-cyber-amendment.html', '//div[contains(@class,"text-long")]//p'));
        $previous = Source::between($paragraphs, 'Previous requirements for TSA-regulated airport and aircraft operators included measures such as', '.');
        $map = array(
            'reporting significant cybersecurity incidents' => 'TSA-INC-02',
            'establishing a cybersecurity point of contact' => 'TSA-GOV-01',
            'developing and adopting a cybersecurity incident response plan' => 'TSA-INC-01',
            'completing a cybersecurity vulnerability assessment' => 'TSA-VUL-02'
        );

        foreach ($map as $phrase => $id) {
            Assert::true(strpos($previous, $phrase) !== false, 'phrase not in the 2023 release: '.$phrase);
            Assert::true(isset($controls()[$id]), 'missing control '.$id);
        }

        $aviation = $nodes('tsa/tsa-2021-12-02-cyber-requirements.html', '//div[contains(@class,"text-long")]//li');
        $first_two = array_slice($aviation, 0, 2);
        Assert::same('designate a cybersecurity coordinator;', $first_two[0], 'first 2021 provision');
        Assert::same('report cybersecurity incidents to CISA within 24 hours;', $first_two[1], 'second 2021 provision');
        Assert::same('tsa2021', $controls()['TSA-GOV-01']['source'], 'coordinator control source');
        Assert::same('tsa2021', $controls()['TSA-INC-02']['source'], 'reporting control source');
    },

    'every control basis appears in its cited release' => function () use ($controls, $nodes) {
        $files = array(
            'tsa2023' => 'tsa/tsa-2023-03-07-aviation-cyber-amendment.html',
            'tsa2021' => 'tsa/tsa-2021-12-02-cyber-requirements.html'
        );

        foreach ($controls() as $id => $control) {
            $text = implode(' ', $nodes($files[$control['source']], '//body'));
            Assert::true(strpos($text, $control['source_basis']) !== false, $id.' basis not in '.$control['source']);
        }
    },

    'controls are HCG-authored with test points' => function () use ($controls) {
        foreach ($controls() as $id => $control) {
            Assert::true(preg_match('/^TSA-[A-Z]{3}-\d{2}$/', $id) === 1, 'ID format '.$id);
            Assert::same('', $control['text'], $id.' carries no official text');
            Assert::true(trim($control['title']) !== '' && trim($control['guidance']) !== '', $id.' title and guidance');
            Assert::true(count($control['objectives']) >= 3, $id.' has at least three test points');

            foreach ($control['objectives'] as $objective) {
                Assert::same('hcg', $objective['source'], $objective['id'].' source');
            }
        }
    },

    'notice, SSI field and deliverables' => function () use ($definition) {
        $d = $definition();
        Assert::same('HCG control set based on public TSA outcomes', $d['notice'], 'cover notice');
        Assert::same('hcg_authored', $d['content_licence'], 'licence');
        Assert::same('percentage', $d['scoring']['model'], 'scoring model');
        Assert::same(array(
            'results_report',
            'executive_briefing',
            'remediation_plan'
        ), $d['deliverables'], 'deliverables');
        $fields = array_column($d['fields'], null, 'key');
        Assert::same('boolean_with_note', $fields['ssi_reviewed']['type'] ?? null, 'SSI review field');
    },

    'incident reporting deadline matches the release' => function () use ($definition) {
        $rules = array_column($definition()['rules'], null, 'id');
        Assert::same(24, $rules['incident_reporting_deadline']['hours'], 'reporting hours');
        Assert::true(strpos($rules['incident_reporting_deadline']['quote'], 'within 24 hours') !== false, 'quote states 24 hours');
    }
);
