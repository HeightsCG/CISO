<?php
/**
 * NYDFS 23 NYCRR 500 checks. Section and subsection IDs are re-derived from the
 * raw extract by splitting on headings (the parser walks lines with a nesting
 * state); rule parameters and transition dates are computed from the text.
 */
$text = function () {
    // Page breaks are a form feed plus the next page number; drop them so sentences rejoin.
    return preg_replace('/\f\d*/', "\n", Source::pdf_text('nydfs/23-nycrr-500.pdf'));
};

$flat = function () use ($text) {
    return Source::normalise($text());
};

$definition = function () {
    return json_decode(file_get_contents(ROOT.'/frameworks/nydfs-500.json'), true);
};

$rules = function () {
    return array_column(FrameworkBuilder::meta('nydfs-500')['rules'], null, 'id');
};

// Section number => body text, split on heading lines.
$bodies = function () use ($text) {
    $parts = preg_split('/^(500\.\d+)\s*[A-Z][^\n]*\.$/m', $text(), -1, PREG_SPLIT_DELIM_CAPTURE);
    $out = array();

    for ($i = 1; $i < count($parts); $i += 2) {
        $out[$parts[$i]] = $parts[$i + 1];
    }

    return $out;
};

return array(
    'sections 500.2 to 500.17 with their published subsection letters' => function () use ($definition, $bodies) {
        $expected = array(
            '500.2' => 'e',
            '500.3' => 'o',
            '500.4' => 'd',
            '500.5' => 'c',
            '500.6' => 'b',
            '500.7' => 'c',
            '500.8' => 'b',
            '500.9' => 'b',
            '500.10' => 'b',
            '500.11' => 'b',
            '500.12' => 'b',
            '500.13' => 'b',
            '500.14' => 'b',
            '500.15' => 'b',
            '500.16' => 'e',
            '500.17' => 'c'
        );
        $b = $bodies();
        $built = array();

        foreach ($definition()['families'][0]['controls'] as $control) {
            $built[$control['id']] = array_column($control['objectives'], 'id');
        }

        Assert::same(array_keys($expected), array_keys($built), 'sections');

        foreach ($expected as $section => $last) {
            $ids = array();

            foreach (range('a', $last) as $letter) {
                // Each subsection opens a line in the source.
                Assert::true(preg_match('/^\('.$letter.'\) /m', $b[$section]) === 1, $section.'('.$letter.') opens a line');
                $ids[] = $section.'('.$letter.')';
            }

            Assert::same($ids, $built[$section], $section.' subsections');
        }

        Assert::same(56, $definition()['counts']['objectives'], 'subsection count');
    },

    'Class A subsections are exactly those addressed to class A companies' => function () use ($definition, $flat) {
        $source = $flat();
        $flagged = array();

        foreach ($definition()['families'][0]['controls'] as $control) {
            $own = array();

            foreach ($control['objectives'] as $objective) {
                if (!empty($objective['class_a_only'])) {
                    $own[] = $objective['id'];
                    Assert::true(strpos($source, Source::normalise($objective['text'])) !== false, $objective['id'].' text is verbatim');
                }
            }

            // The control-level list must mirror the objective flags (only control flags survive loading).
            Assert::same($own, $control['class_a_objectives'], $control['id'].' class_a_objectives');
            $flagged = array_merge($flagged, $own);
        }

        $expected = array(
            '500.2(c)',
            '500.7(c)',
            '500.14(b)'
        );
        Assert::same($expected, $flagged, 'class A subsections');
        Assert::same(3, substr_count($source, 'Each class A company'), 'class A requirements in the text');
    },

    'exemption parameters match 500.19' => function () use ($bodies, $rules) {
        $b = Source::normalise($bodies()['500.19']);
        $r = $rules();
        preg_match('/\(1\) fewer than (\d+) employees.*?less than \$([\d,]+) in gross annual revenue in each of the last three fiscal years.*?less than \$([\d,]+) in year-end total assets/', $b, $m);
        Assert::same((int) $m[1], $r['limited_exemption']['employees_under'], 'employees');
        Assert::same((int) str_replace(',', '', $m[2]), $r['limited_exemption']['revenue_under'], 'revenue');
        Assert::same((int) str_replace(',', '', $m[3]), $r['limited_exemption']['assets_under'], 'assets');

        // Expand "500.14(a)(1), (a)(2), and (b)" into full citations.
        preg_match('/shall be exempt from the requirements of sections (.+?) of this Part\. \(b\)/', $b, $list);
        $cites = array();
        $base = null;

        foreach (preg_split('/,\s*(?:and\s+)?|\s+and\s+/', $list[1]) as $item) {
            if (preg_match('/^(500\.\d+)(.*)$/', $item, $p)) {
                $base = $p[1];
                $cites[] = $p[1].$p[2];
            } else {
                $cites[] = $base.$item;
            }
        }

        Assert::same($cites, $r['limited_exemption']['exempt'], '500.19(a) exempt sections');

        preg_match_all('/exempt from the requirements of sections (500\.2, .+?) of this Part/', $b, $lists);
        Assert::same(2, count($lists[1]), '500.19(c) and (d) lists');

        foreach (array('no_systems_exemption', 'captive_insurer_exemption') as $i => $id) {
            $items = preg_split('/,\s*|\s+and\s+/', $lists[1][$i]);
            Assert::same($items, $r[$id]['exempt'], $id.' exempt sections');
        }

        Assert::true(strpos($b, 'within 30 days of the determination that the covered entity is exempt') !== false, 'notice of exemption days');
        Assert::same(30, $r['notice_of_exemption']['days'], 'notice of exemption');
        Assert::true(strpos($b, 'shall have 180 days from the date that it ceases to so qualify') !== false, 'lapse days');
        Assert::same(180, $r['exemption_lapse']['days'], 'exemption lapse');
    },

    'notice deadlines match 500.17' => function () use ($bodies, $rules) {
        $b = Source::normalise($bodies()['500.17']);
        $r = $rules();
        preg_match('/no event later than (\d+) hours after determining that a cybersecurity incident/', $b, $m);
        Assert::same((int) $m[1], $r['incident_notification']['hours'], 'incident hours');
        preg_match('/electronically by (\w+) (\d+) either/', $b, $m);
        Assert::same('April', $m[1], 'certification month');
        Assert::same(sprintf('04-%02d', (int) $m[2]), $r['annual_compliance_notice']['date'], 'certification date');
        preg_match('/within (\d+) hours of the extortion payment, notice of the payment; and \(2\) within (\d+) days of the extortion payment/', $b, $m);
        Assert::same((int) $m[1], $r['extortion_payment_notice']['hours'], 'extortion hours');
        Assert::same((int) $m[2], $r['extortion_payment_notice']['days'], 'extortion days');
    },

    'transition dates are computed from 500.21(b) and 500.22' => function () use ($bodies, $rules, $flat) {
        preg_match('/The second amendment to this Part shall become effective (\w+ \d+, \d{4})\./', $flat(), $m);
        $start = new DateTimeImmutable($m[1]);
        Assert::same('2023-11-01', $start->format('Y-m-d'), 'second amendment effective date');
        $b = Source::normalise($bodies()['500.22']);
        $words = array(
            '30 days' => 'P30D',
            '180 days' => 'P180D',
            'one year' => 'P1Y',
            '18 months' => 'P18M',
            'two years' => 'P2Y'
        );
        $r = $rules();
        $checks = array(
            'transition_general' => '/have (180 days) from the effective date of the second amendment to this Part to comply with the new requirements set forth/',
            'transition_notices' => '/\(1\) (30 days) from the effective date of the second amendment to this Part to comply with the new requirements specified in section (500\.17)/',
            'transition_one_year' => '/\(2\) (one year) from the effective date of the second amendment to this Part to comply with the new requirements specified in sections (.+?) of this Part/',
            'transition_eighteen_months' => '/\(3\) (18 months) from the effective date of the second amendment to this Part to comply with the new requirements specified in sections (.+?) of this Part/',
            'transition_two_years' => '/\(4\) (two years) from the effective date of the second amendment to this Part to comply with the new requirements specified in sections (.+?) of this Part/'
        );

        foreach ($checks as $id => $pattern) {
            Assert::true(preg_match($pattern, $b, $m) === 1, $id.' wording');
            $due = $start->add(new DateInterval($words[$m[1]]))->format('Y-m-d');
            Assert::same($due, $r[$id]['date'], $id.' date');

            if (isset($m[2])) {
                Assert::same(preg_split('/,\s*|\s+and\s+/', $m[2]), $r[$id]['controls'], $id.' sections');
            }
        }

        Assert::same('2025-11-01', $r['transition_two_years']['date'], 'MFA and asset inventory date');
    },

    'section text is verbatim from the source' => function () use ($definition, $flat) {
        $source = str_replace('- ', '-', $flat());

        foreach ($definition()['families'][0]['controls'] as $control) {
            Assert::true(strpos($source, Source::normalise($control['text'])) !== false, $control['id'].' text');
        }
    }
);
