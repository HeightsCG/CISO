<?php
/**
 * ISO/IEC 27001:2022: clause and Annex A structure derived from the publicly
 * distributed sample previews of the standard. The licence allows IDs and
 * official short titles only, so `text` is always ''.
 *
 * - Clauses 4-10 (requirement-carrying sub-clauses): table of contents of the
 *   SIST EN ISO/IEC 27001:2023 sample (identical to ISO/IEC 27001:2022).
 *   The published contents list omits 6.3, so its title is read from the
 *   clause heading in the ISO/IEC 27001:2022 redline sample.
 * - Annex A controls and themes: table of contents of the SIST EN ISO/IEC
 *   27002:2022 sample. Annex A of 27001 lists the 27002 controls under the
 *   same numbers and titles.
 * - Descriptions and guidance: HCG-written, frameworks/meta/controls/iso-27001-2022.json.
 */
return function (): array {
    // Contents-page entries: "6.1.2 Information security risk assessment......4"
    $toc = function (string $file): array {
        $text = str_replace(array("\u{200B}", "\t"), array('', ' '), Source::pdf_text($file));
        $entries = array();

        foreach (preg_split('/\R/', $text) as $line) {
            if (preg_match('/^(\d+(?:\.\d+)*)\s+(\S.*?)\s*\.{3,}\s*[ivx\d]+$/', trim($line), $m)) {
                $entries[$m[1]] = Source::normalise($m[2]);
            }
        }

        return $entries;
    };

    $hcg = json_decode(file_get_contents(dirname(__DIR__).'/meta/controls/iso-27001-2022.json'), true);

    if (!is_array($hcg)) {
        throw new RuntimeException('meta/controls/iso-27001-2022.json is not valid JSON');
    }

    $used = array();

    $control = function (string $id, string $number, string $title, string $type, string $source, bool $annex) use ($hcg, &$used): array {
        if (!isset($hcg[$id]['description'], $hcg[$id]['guidance'])) {
            throw new RuntimeException('No HCG description and guidance for '.$id);
        }

        $used[$id] = true;
        $c = array(
            'id' => $id,
            'number' => $number,
            'title' => $title,
            'text' => '',
            'description' => $hcg[$id]['description'],
            'guidance' => $hcg[$id]['guidance'],
            'type' => $type,
            'weight' => null,
            'partial_weight' => null,
            'objectives' => array(),
            'source' => $source
        );

        if ($annex) {
            $c['applicability'] = true;
        }

        return $c;
    };

    // Clauses 4-10: keep the entries that have no sub-entries (6.1 -> 6.1.1-6.1.3 etc.)
    $clauses = array_filter($toc('iso27001/sist-en-iso-iec-27001-2023-sample.pdf'), function ($n) {
        return preg_match('/^(4|5|6|7|8|9|10)\./', (string) $n) === 1;
    }, ARRAY_FILTER_USE_KEY);

    if (isset($clauses['6.3'])) {
        throw new RuntimeException('The 27001 contents list now includes 6.3; drop the redline lookup');
    }

    $redline = str_replace("\u{200B}", '', Source::pdf_text('iso27001/iso-iec-27001-2022-redline-sample.pdf'));

    if (!preg_match('/^6\.3 (Planning of changes)$/m', $redline, $m)) {
        throw new RuntimeException('Clause 6.3 heading not found in the redline sample');
    }

    // Insert 6.3 after 6.2 to keep document order
    $ordered = array();

    foreach ($clauses as $n => $title) {
        $ordered[(string) $n] = $title;

        if ((string) $n === '6.2') {
            $ordered['6.3'] = $m[1];
        }
    }

    $isms = array(
        'number' => '4-10',
        'code' => 'ISMS',
        'name' => 'Management system requirements',
        'controls' => array()
    );

    foreach ($ordered as $n => $title) {
        $has_children = false;

        foreach (array_keys($ordered) as $other) {
            if (strpos((string) $other, $n.'.') === 0) {
                $has_children = true;
                break;
            }
        }

        if ($has_children) {
            continue;
        }

        $source = $n === '6.3' ? 'iso27001redline' : 'iso27001';
        $c = $control($n, $n, $title, 'requirement', $source, false);
        $parent = substr($n, 0, strrpos($n, '.'));

        // "6.1.1 General" reads better with its parent heading alongside
        if (substr_count($n, '.') === 2) {
            $c['parent_title'] = $ordered[$parent];
        }

        $isms['controls'][] = $c;
    }

    // Annex A themes (5-8) and controls from the 27002 contents list
    $families = array(
        $isms
    );
    $entries = $toc('iso27001/sist-en-iso-iec-27002-2022-sample.pdf');

    foreach (array('5', '6', '7', '8') as $theme) {
        if (!isset($entries[$theme])) {
            throw new RuntimeException('Theme '.$theme.' not found in the 27002 contents list');
        }

        $family = array(
            'number' => 'A.'.$theme,
            'code' => 'A.'.$theme,
            'name' => $entries[$theme],
            'controls' => array()
        );

        foreach ($entries as $n => $title) {
            if (preg_match('/^'.$theme.'\.\d+$/', (string) $n)) {
                $family['controls'][] = $control('A.'.$n, (string) $n, $title, 'control', 'iso27002', true);
            }
        }

        $families[] = $family;
    }

    $unused = array_diff(array_keys($hcg), array_keys($used));

    if (count($unused) > 0) {
        throw new RuntimeException('HCG descriptions for unknown IDs: '.implode(', ', $unused));
    }

    return $families;
};
