<?php
/**
 * Cyber Essentials checks. The parser splits the layout extract into paragraphs; these
 * hold the result against a reading-order extract of the same PDF.
 */
$definition = function () {
    return json_decode(file_get_contents(ROOT.'/frameworks/cyber-essentials.json'), true);
};

$flat = function (string $text): string {
    return strtolower(preg_replace('/[^a-z0-9]+/i', '', Source::normalise($text)));
};

// Reading-order text of section E, page furniture removed, letters and digits only.
$section = function () use ($flat) {
    $raw = file_get_contents(Source::path('cyber-essentials/cyber-essentials-requirements-for-it-infrastructure-v3-3.raw.txt'));
    // The headings also appear in the contents list; section E runs between their last copies.
    $raw = substr($raw, strrpos($raw, 'Requirements by technical control theme'));
    $raw = substr($raw, 0, strrpos($raw, 'Further guidance'));
    $raw = preg_replace('/Cyber Essentials: Requirements for IT Infrastructure v3\.3|April 2026|All material is UK Crown Copyright ©|\n\d+\n/u', ' ', $raw);

    return $flat(str_replace(array('•', ' o '), ' ', $raw));
};

return array(
    'five control themes in order' => function () use ($definition) {
        Assert::same(array(
            'Firewalls',
            'Secure Configuration',
            'Security Update Management',
            'User Access Control',
            'Malware protection'
        ), array_column($definition()['families'], 'name'), 'themes');

        foreach ($definition()['families'] as $family) {
            Assert::true($family['applies_to'] !== '', $family['name'].' says what it applies to');
        }
    },

    'every requirement and element is printed in section E' => function () use ($definition, $section, $flat) {
        $text = $section();
        $total = 0;

        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $i => $control) {
                Assert::same($family['number'].'.'.($i + 1), $control['id'], $control['id'].' numbering');
                $opening = $control['text'];
                $opening = substr($opening, 0, strpos($opening.' •', ' •'));
                Assert::true(strpos($text, $flat($opening)) !== false, $control['id'].' text');

                foreach ($control['objectives'] as $objective) {
                    $first = explode(' - ', $objective['text'])[0];
                    Assert::true(strpos($text, $flat($first)) !== false, $objective['id'].' text');
                }

                $total++;
            }
        }

        Assert::same(14, $total, 'requirements');
    },

    'only the password measures are "should" requirements' => function () use ($definition) {
        $should = array();

        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                if ($control['force'] === 'should') {
                    $should[] = $control['title'];
                }
            }
        }

        Assert::same(array('Password-based authentication'), $should, 'should requirements');
    },

    'lists keep their sub-bullets inside their item' => function () use ($definition) {
        $firewalls = $definition()['families'][0]['controls'][1];

        Assert::same(5, count($firewalls['objectives']), 'firewall configuration items');
        Assert::true(strpos($firewalls['objectives'][1]['text'], 'multi-factor authentication') !== false, 'admin interface item carries its options');
    }
);
