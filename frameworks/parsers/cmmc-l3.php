<?php
/**
 * CMMC Level 3: the 24 NIST SP 800-172 requirements selected in Table 1 to
 * 32 CFR 170.14(c)(4), numbered DD.L3-3.x.ye.
 *
 * - Requirement text: Table 1 as printed in the rule, i.e. SP 800-172 text with
 *   the DoD organization-defined parameters (ODPs) filled in. The italicised
 *   DoD values are also kept as dod_parameters[].
 * - Objectives: the lettered determination statements of NIST SP 800-172A
 *   (March 2022), from the pdftotext -raw extract. Its ODP statements ("... are
 *   defined") are left out: at Level 3 the DoD assigns those values in Table 1.
 * - Domains: the SP 800-171 r2 family of the same number, with its CMMC code.
 */
require_once dirname(__DIR__).'/lib/ObjectiveText.php';

return function (): array {
    $r2 = (require __DIR__.'/nist-800-171-r2.php')();
    $by_number = array();

    foreach ($r2 as $family) {
        $by_number[$family['number']] = $family;
    }

    $decode = function (string $xml): string {
        return Source::normalise(html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8'));
    };

    $cfr = simplexml_load_file(Source::path('cmmc/32-cfr-170.xml'));
    $table = null;

    foreach ($cfr->xpath('//TABLE') as $t) {
        if (strpos($decode($t->CAPTION->asXML()), 'Table 1 to') === 0) {
            $table = $t;
        }
    }

    if ($table === null) {
        throw new RuntimeException('Table 1 to 32 CFR 170.14(c)(4) not found');
    }

    $rows = array();

    foreach ($table->TBODY->TR as $tr) {
        if (!preg_match('/^\(([ivx]+)\) ([A-Z]{2})\.L3-((3\.\d+)\.\d+e)$/', $decode($tr->TD[0]->asXML()), $m)) {
            throw new RuntimeException('Unexpected Table 1 row');
        }

        // Adjacent italic runs (e.g. a value followed by an italic comma) are one parameter.
        $cell = preg_replace('#</E>\s*<E T="03">#', '', $tr->TD[1]->asXML());
        preg_match_all('#<E T="03">(.*?)</E>#s', $cell, $italics);

        $rows[] = array(
            'id' => $m[2].'.L3-'.$m[3],
            'code' => $m[2],
            'number' => $m[3],
            'family' => $m[4],
            'text' => $decode($tr->TD[1]->asXML()),
            'dod_parameters' => array_map($decode, $italics[1])
        );
    }

    // SP 800-172A chapter 3: "3.1.2e ENHANCED SECURITY REQUIREMENT" ... "ASSESSMENT OBJECTIVE"
    // ... "POTENTIAL ASSESSMENT METHODS AND OBJECTS".
    $text = Source::pdf_text('nist-800-172/sp800-172a.pdf');
    $text = substr($text, strpos($text, "\nCHAPTER THREE\n"));
    $objectives = array();
    $current = null;
    $collect = false;

    foreach (preg_split('/\R/', $text) as $line) {
        $line = trim(preg_replace('/NISTSP800-172A ASSESSING ENHANCED SECURITY REQUIREMENTS FOR CUI.*$/', '', $line));

        if ($line === '' || preg_match('/^(_{5,}$|\d+$|3\.\d+ [A-Z]+$|This publication is available free of charge|There are no enhanced security requirements)/', $line)) {
            continue;
        }

        if (preg_match('/^(3\.\d+\.\d+e) ENHANCED SECURITY REQUIREMENT$/', $line, $m)) {
            $current = $m[1];
            $collect = false;
            continue;
        }

        if ($current !== null && ($line === 'ASSESSMENT OBJECTIVE' || $line === 'Determine if:')) {
            $collect = true;
            $objectives[$current] = $objectives[$current] ?? array();
            continue;
        }

        if (strpos($line, 'POTENTIAL ASSESSMENT METHODS') === 0) {
            $collect = false;
            $current = null;
            continue;
        }

        if (!$collect) {
            continue;
        }

        if (preg_match('/^(3\.\d+\.\d+e(?:\[[a-z]+\]|_ODP\[\d+\])) (.*)$/', $line, $m)) {
            $objectives[$current][] = array(
                'id' => $m[1],
                'lines' => array(
                    $m[2]
                )
            );
        } elseif (count($objectives[$current]) > 0) {
            $objectives[$current][count($objectives[$current]) - 1]['lines'][] = $line;
        } else {
            throw new RuntimeException('Unexpected text before the first objective of '.$current.': '.$line);
        }
    }

    $families = array();

    foreach ($rows as $row) {
        if (!isset($objectives[$row['number']])) {
            throw new RuntimeException('No SP 800-172A objectives for '.$row['number']);
        }

        if (!isset($by_number[$row['family']]) || $by_number[$row['family']]['code'] !== $row['code']) {
            throw new RuntimeException('Domain code of '.$row['id'].' does not match SP 800-171 family '.$row['family']);
        }

        $list = array();
        $placeholders = array();

        foreach ($objectives[$row['number']] as $entry) {
            if (strpos($entry['id'], '_ODP[') !== false) {
                continue;
            }

            // A line ending in a hyphen continues the same word ("replay-" + "resistant").
            $joined = preg_replace('/(?<=[a-z])-\n/', '-', implode("\n", $entry['lines']));
            $list[] = array(
                'id' => $entry['id'],
                'text' => Source::normalise($joined)
            );
            preg_match_all('/<[^>]+>/', end($list)['text'], $found);
            $placeholders = array_values(array_unique(array_merge($placeholders, $found[0])));
        }

        // Each 172A placeholder, in order of first use, is the DoD value Table 1 prints
        // in italics at the same place; a verb-phrase placeholder keeps 172A's wording.
        if (count($placeholders) > 0 && count($placeholders) !== count($row['dod_parameters'])) {
            throw new RuntimeException('Placeholders and DoD values do not pair up in '.$row['id']);
        }

        $values = array();

        foreach ($placeholders as $i => $placeholder) {
            $label = trim(preg_replace('/^<[^\s:]+:?\s*|>$/', '', $placeholder));
            $values[$placeholder] = preg_match('/^\w+ed\b/', $label) === 1 ? $label : rtrim($row['dod_parameters'][$i], ' .');
        }

        foreach ($list as $o => $objective) {
            if (strpos($objective['text'], '<') !== false) {
                $list[$o]['description'] = ObjectiveText::sentence(preg_replace('/,(?=[.;]|$)/', '', strtr($objective['text'], $values)));
            }
        }

        if (!isset($families[$row['code']])) {
            $families[$row['code']] = array(
                'number' => $row['family'],
                'code' => $row['code'],
                'name' => $by_number[$row['family']]['name'],
                'controls' => array()
            );
        }

        $families[$row['code']]['controls'][] = array(
            'id' => $row['id'],
            'number' => $row['number'],
            'text' => $row['text'],
            'weight' => 1,
            'dod_parameters' => $row['dod_parameters'],
            'objectives' => $list
        );
    }

    return array_values($families);
};
