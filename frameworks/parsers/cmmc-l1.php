<?php
/**
 * CMMC Level 1: the 15 safeguarding requirements of FAR 52.204-21(b)(1)(i)-(xv)
 * (32 CFR 170.14(c)(2)), numbered DD.L1-b.1.<roman> (32 CFR 170.14(c)(1)).
 *
 * - Requirement text: FAR 52.204-21 from the eCFR.
 * - Identifiers and the mapping to SP 800-171 r2 requirements: Table 2 to
 *   32 CFR 170.15(c)(1)(ii). PE.L1-b.1.ix is split by phrase across 3.10.3-3.10.5.
 * - Objectives: the SP 800-171A (June 2018) objectives of the mapped
 *   requirement(s), taken from the 800-171 r2 parser, in table order.
 * - Domains: the 800-171 r2 family of the mapped requirement, with its code.
 */
return function (): array {
    $r2 = (require __DIR__.'/nist-800-171-r2.php')();
    $requirements = array();
    $domains = array();

    foreach ($r2 as $family) {
        foreach ($family['controls'] as $control) {
            $requirements[$control['number']] = $control;
            $domains[$control['number']] = $family;
        }
    }

    $far = simplexml_load_file(Source::path('cmmc/far-52.204-21.xml'));
    $clause = array();

    foreach ($far->xpath('//EXTRACT/P') as $p) {
        $text = Source::normalise(html_entity_decode(strip_tags($p->asXML()), ENT_QUOTES | ENT_XML1, 'UTF-8'));

        if (preg_match('/^\(([ivx]+)\) (.+)$/', $text, $m)) {
            $clause[$m[1]] = $m[2];
        }
    }

    $cfr = simplexml_load_file(Source::path('cmmc/32-cfr-170.xml'));
    $table = null;

    foreach ($cfr->xpath('//TABLE') as $t) {
        if (strpos(Source::normalise(strip_tags($t->CAPTION->asXML())), 'Table 2 to') === 0) {
            $table = $t;
        }
    }

    if ($table === null) {
        throw new RuntimeException('Table 2 to 32 CFR 170.15 not found');
    }

    $map = array();

    foreach ($table->TBODY->TR as $tr) {
        $cells = array();

        foreach ($tr->TD as $td) {
            $cells[] = Source::normalise(html_entity_decode(strip_tags($td->asXML()), ENT_QUOTES | ENT_XML1, 'UTF-8'));
        }

        if (!preg_match('/\b([A-Z]{2}\.L1-b\.1\.([ivx]+))\b/', $cells[0], $m)) {
            throw new RuntimeException('Unexpected Table 2 row: '.$cells[0]);
        }

        $map[$m[1]]['roman'] = $m[2];
        $map[$m[1]]['nist'][] = $cells[1];
    }

    $families = array();

    foreach ($map as $id => $row) {
        if (!isset($clause[$row['roman']])) {
            throw new RuntimeException('FAR 52.204-21(b)(1)('.$row['roman'].') not found for '.$id);
        }

        $code = substr($id, 0, 2);
        $objectives = array();

        foreach ($row['nist'] as $n) {
            if (!isset($requirements[$n])) {
                throw new RuntimeException('Table 2 maps '.$id.' to unknown requirement '.$n);
            }

            if ($domains[$n]['code'] !== $code) {
                throw new RuntimeException($id.' domain does not match the family of '.$n);
            }

            $objectives = array_merge($objectives, $requirements[$n]['objectives']);
        }

        $family = $domains[$row['nist'][0]];

        if (!isset($families[$code])) {
            $families[$code] = array(
                'number' => $family['number'],
                'code' => $code,
                'name' => $family['name'],
                'controls' => array()
            );
        }

        $families[$code]['controls'][] = array(
            'id' => $id,
            'number' => 'b.1.'.$row['roman'],
            'text' => $clause[$row['roman']],
            'nist_800_171' => $row['nist'],
            'objectives' => $objectives
        );
    }

    return array_values($families);
};
