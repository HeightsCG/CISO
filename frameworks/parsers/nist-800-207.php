<?php
/**
 * NIST SP 800-207 Zero Trust Architecture. The document sets out tenets and architecture
 * rather than requirements, so the assessable items are taken from its own structure:
 * - 2.1: the seven tenets of zero trust;
 * - 2.2: the six network assumptions;
 * - 3: the three core logical components and the eight data sources feeding the policy engine;
 * - 7.3: the seven steps for introducing ZTA.
 * Control text is NIST's, word for word from the committed pdftotext -raw extract. Test
 * points are HCG-written, in frameworks/meta/controls/nist-800-207.json keyed by control ID.
 */
return function (): array {
    $text = str_replace(array("\r", "\f"), '', Source::pdf_text('nist800207/NIST.SP.800-207.pdf'));
    $text = preg_replace('/NIST SP 800-207 ZERO TRUST ARCHITECTURE\n\d+\nThis publication is available free of charge from: https:\/\/doi\.org\/10\.6028\/NIST\.SP\.800-207\n/', "\n", $text);
    $set = json_decode(file_get_contents(dirname(__DIR__).'/meta/controls/nist-800-207.json'), true);

    // The body follows the last occurrence of the table of contents' final heading.
    $body = substr($text, strpos($text, "\n2.1 Tenets of Zero Trust\n", strpos($text, "\n2.1 Tenets of Zero Trust") + 30));

    $between = function (string $from, string $to) use ($body): string {
        $start = strpos($body, $from);
        $end = strpos($body, $to, $start + strlen($from));

        if ($start === false || $end === false) {
            throw new RuntimeException('800-207: section not found between "'.$from.'" and "'.$to.'"');
        }

        return substr($body, $start + strlen($from), $end - $start - strlen($from));
    };

    $flat = function (string $text): string {
        $text = preg_replace('/(?<=[a-z])-\n(?=[a-z])/', '-', $text);

        return trim(preg_replace('/\s+/', ' ', $text));
    };

    $numbered = function (string $section, int $count) use ($flat): array {
        preg_match_all('/^(\d)\. (.+?)(?=^\d\. |\z)/ms', $section, $m, PREG_SET_ORDER);

        if (count($m) !== $count) {
            throw new RuntimeException('800-207: expected '.$count.' numbered items, found '.count($m));
        }

        $items = array();

        foreach ($m as $item) {
            $items[(int) $item[1]] = $flat($item[2]);
        }

        return $items;
    };

    $objectives = function (string $id) use ($set): array {
        if (!isset($set['objectives'][$id])) {
            throw new RuntimeException('800-207: no HCG test points for '.$id);
        }

        $out = array();

        foreach ($set['objectives'][$id] as $o => $text) {
            $out[] = array(
                'id' => $id.'.'.chr(97 + $o),
                'text' => $text,
                'source' => 'hcg'
            );
        }

        return $out;
    };

    $control = function (string $id, string $title, string $text, string $type) use ($objectives): array {
        return array(
            'id' => $id,
            'number' => $id,
            'title' => $title,
            'text' => $text,
            'type' => $type,
            'weight' => null,
            'partial_weight' => null,
            'objectives' => $objectives($id),
            'source' => 'sp800207'
        );
    };

    $families = array();

    // 2.1 tenets and 2.2 assumptions: the first sentence of each item is its statement.
    $sections = array(
        array('2.1', 'Tenets of Zero Trust', 'Tenet', 'tenet', "basic tenets:\n", "\nThe above tenets", 7),
        array('2.2', 'A Zero Trust View of a Network', 'Assumption', 'assumption', "following assumptions.\n", "\n3 Logical Components of Zero Trust Architecture\n", 6)
    );

    foreach ($sections as $section) {
        list($code, $name, $label, $type, $from, $to, $count) = $section;
        $controls = array();

        foreach ($numbered($between($from, $to), $count) as $n => $item) {
            $statement = preg_split('/(?<=\.)\s/', $item, 2)[0];
            $controls[] = $control($code.'.'.$n, $label.' '.$n.': '.rtrim($statement, '.'), $item, $type);
        }

        $families[] = array(
            'number' => $code,
            'code' => $code,
            'name' => $name,
            'controls' => $controls
        );
    }

    // 3: core components and data sources, one bullet each.
    $components = array(
        'PE' => 'Policy engine (PE)',
        'PA' => 'Policy administrator (PA)',
        'PEP' => 'Policy enforcement point (PEP)',
        'CDM' => 'Continuous diagnostics and mitigation (CDM) system',
        'ICS' => 'Industry compliance system',
        'TIF' => 'Threat intelligence feed(s)',
        'LOG' => 'Network and system activity logs',
        'DAP' => 'Data access policies',
        'PKI' => 'Enterprise public key infrastructure (PKI)',
        'IDM' => 'ID management system',
        'SIEM' => 'Security information and event management (SIEM) system'
    );
    $section = $between("The component descriptions:\n", "\n3.1 Variations of Zero Trust Architecture Approaches\n");
    $section = preg_replace('/\nIn addition to the core components.*?These can include:\n/s', "\n", $section);
    $bullets = preg_split('/^• /m', $section, -1, PREG_SPLIT_NO_EMPTY);
    $controls = array();

    foreach ($bullets as $bullet) {
        $bullet = $flat($bullet);
        $matched = null;

        foreach ($components as $id => $name) {
            if (strpos($bullet, $name.':') === 0) {
                $matched = $id;
            }
        }

        if ($matched === null) {
            throw new RuntimeException('800-207: unknown component bullet: '.substr($bullet, 0, 60));
        }

        $controls[] = $control('3.'.$matched, $components[$matched], trim(substr($bullet, strlen($components[$matched]) + 1)), 'component');
    }

    if (count($controls) !== count($components)) {
        throw new RuntimeException('800-207: expected '.count($components).' components, found '.count($controls));
    }

    $families[] = array(
        'number' => '3',
        'code' => '3',
        'name' => 'Logical Components of Zero Trust Architecture',
        'controls' => $controls
    );

    // 7.3: migration steps, each sub-section's text.
    $steps = array(
        '7.3.1' => 'Identify Actors on the Enterprise',
        '7.3.2' => 'Identify Assets Owned by the Enterprise',
        '7.3.3' => 'Identify Key Processes and Evaluate Risks Associated with Executing Process',
        '7.3.4' => 'Formulating Policies for the ZTA Candidate',
        '7.3.5' => 'Identifying Candidate Solutions',
        '7.3.6' => 'Initial Deployment and Monitoring',
        '7.3.7' => 'Expanding the ZTA'
    );
    $ids = array_keys($steps);
    $controls = array();

    foreach ($ids as $i => $id) {
        $to = isset($ids[$i + 1]) ? "\n".$ids[$i + 1].' '.$steps[$ids[$i + 1]]."\n" : "\nReferences\n";
        $controls[] = $control($id, $steps[$id], $flat($between("\n".$id.' '.$steps[$id]."\n", $to)), 'step');
    }

    $families[] = array(
        'number' => '7.3',
        'code' => '7.3',
        'name' => 'Steps to Introducing ZTA to a Perimeter-Based Architected Network',
        'controls' => $controls
    );

    return $families;
};
