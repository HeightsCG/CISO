<?php
/**
 * Reference-data tests for every framework definition. Each definition is
 * rebuilt from the official source snapshots and must match the committed
 * file exactly; every rule quote must appear in its source; framework-specific
 * checks live in tests/frameworks/<key>.php.
 */
$tests = array();
$load = function (string $file) {
    return require $file;
};

$source_text = function (array $source): string {
    static $cache = array();

    if (!isset($cache[$source['file']])) {
        $file = $source['file'];

        if (substr($file, -4) === '.xml') {
            $text = Source::xml_text($file);
        } elseif (substr($file, -4) === '.pdf') {
            $text = Source::pdf_text($file);
        } elseif (substr($file, -5) === '.xlsx') {
            $text = '';

            foreach (Source::xlsx_rows($file) as $row) {
                $text .= implode(' ', $row)."\n";
            }
        } else {
            $text = file_get_contents(Source::path($file));
        }

        $cache[$file] = Source::normalise($text);
    }

    return $cache[$source['file']];
};

foreach (FrameworkBuilder::keys() as $key) {
    $tests[$key.': committed definition matches a rebuild from source'] = function () use ($key) {
        $path = ROOT.'/frameworks/'.$key.'.json';
        Assert::true(is_file($path), 'frameworks/'.$key.'.json is missing; run php frameworks/build.php '.$key);
        $built = FrameworkBuilder::encode(FrameworkBuilder::build($key));
        Assert::true($built === file_get_contents($path), 'frameworks/'.$key.'.json differs from a rebuild; run php frameworks/build.php '.$key.' and review the diff');
    };

    $tests[$key.': source snapshots match their recorded checksums'] = function () use ($key) {
        foreach (FrameworkBuilder::meta($key)['sources'] as $source) {
            if (!isset($source['file'])) {
                continue;
            }

            Assert::same($source['sha256'], hash_file('sha256', Source::path($source['file'])), $source['file'].' checksum');

            if (substr($source['file'], -4) === '.pdf') {
                Assert::true(is_file(Source::path(preg_replace('/\.pdf$/', '.txt', $source['file']))), $source['file'].' needs its committed text extract');
            }
        }
    };

    $tests[$key.': required fields are present'] = function () use ($key) {
        $d = json_decode(file_get_contents(ROOT.'/frameworks/'.$key.'.json'), true);
        $required = array(
            'key',
            'name',
            'version',
            'display_name',
            'full_name',
            'issuing_body',
            'standard_codes',
            'content_licence',
            'sources',
            'terminology',
            'result_scale',
            'scoring',
            'rules',
            'deliverables',
            'families'
        );

        foreach ($required as $field) {
            Assert::true(isset($d[$field]) && $d[$field] !== '' && $d[$field] !== array(), $key.' is missing '.$field);
        }

        Assert::true(in_array($d['scoring']['model'], array('points', 'percentage', 'maturity'), true), $key.' has an unknown scoring model');
        Assert::true(strpos($d['name'], $d['version']) === false, $key.' repeats its version inside its name');
        Assert::true(strpos($d['version'], '(') === false && strpos($d['name'], '(') === false, $key.' display name would nest brackets: '.$d['display_name']);
        Assert::true(in_array($d['content_licence'], array('public_domain', 'open', 'ids_titles_only', 'hcg_authored'), true), $key.' has an unknown content licence');
        $deliverables = array('results_report', 'executive_briefing', 'remediation_plan', 'score_summary', 'governing_document');
        Assert::same(array(), array_values(array_diff($d['deliverables'], $deliverables)), $key.' lists unknown deliverables');

        if (in_array('governing_document', $d['deliverables'], true)) {
            Assert::true(isset($d['governing_document']['title']), $key.' offers a governing document but does not describe it');
        }

        $source_ids = array_column($d['sources'], 'id');
        $ids = array();

        foreach ($d['families'] as $family) {
            Assert::true($family['name'] !== '', $key.' has a group with no name');
            Assert::true(count($family['controls']) > 0, $key.' group '.$family['name'].' has no controls');

            foreach ($family['controls'] as $control) {
                Assert::true(!isset($ids[$control['id']]), $key.' repeats control '.$control['id']);
                $ids[$control['id']] = true;
                Assert::true(in_array($control['source'], $source_ids, true), $key.' '.$control['id'].' cites an unknown source');
                Assert::true(trim($control['text'] ?? '') !== '' || trim($control['description'] ?? '') !== '', $key.' '.$control['id'].' has no text or description');

                foreach ($control['objectives'] as $objective) {
                    Assert::true(trim($objective['text']) !== '', $key.' objective '.$objective['id'].' is empty');
                    Assert::true(in_array($objective['source'], $source_ids, true), $key.' objective '.$objective['id'].' cites an unknown source');
                }
            }
        }
    };

    $tests[$key.': every rule and consequence quote appears in its source'] = function () use ($key, $source_text) {
        $meta = FrameworkBuilder::meta($key);
        $sources = array();

        foreach ($meta['sources'] as $source) {
            $sources[$source['id']] = $source;
        }

        $items = array_merge($meta['rules'], $meta['consequences'] ?? array());

        foreach ($items as $item) {
            if (!isset($item['quote'])) {
                continue;
            }

            Assert::true(isset($sources[$item['source']]), $key.' '.($item['id'] ?? $item['summary']).' cites unknown source '.$item['source']);

            if (!isset($sources[$item['source']]['file'])) {
                continue;
            }

            $haystack = $source_text($sources[$item['source']]);
            Assert::true(strpos($haystack, Source::normalise($item['quote'])) !== false, $key.' '.($item['id'] ?? 'consequence').': quote not found in '.$item['source'].': "'.$item['quote'].'"');
        }
    };

    $specific = __DIR__.'/frameworks/'.$key.'.php';

    if (is_file($specific)) {
        foreach ($load($specific) as $name => $test) {
            $tests[$key.': '.$name] = $test;
        }
    }
}

return $tests;
