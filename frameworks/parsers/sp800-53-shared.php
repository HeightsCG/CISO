<?php
/**
 * SP 800-53 Rev 5.2.0 from two official NIST artefacts of the same release:
 * - OSCAL catalog (usnistgov/oscal-content): families, controls, enhancements, parent
 *   relation, withdrawn status, and the SP 800-53A assessment objectives embedded in it.
 * - CPRT export: the control statement and discussion exactly as NIST renders them
 *   (parameters printed as [Assignment: ...] / [Selection: ...]).
 * Shared by nist-800-53-r5.php and the FedRAMP baselines, which select from it.
 *
 * Returns a closure that yields:
 *   families: code => array(code, name, ids[]) in catalog order
 *   controls: ID ("AC-2", "AC-2(1)") => control row, withdrawn ones excluded
 *   withdrawn: IDs the catalog marks withdrawn
 */
return function (): array {
    $catalog = json_decode(file_get_contents(Source::path('sp800-53/NIST_SP-800-53_rev5_catalog.json')), true)['catalog'];

    $prop = function (array $node, string $name, ?string $class = null): ?string {
        foreach ($node['props'] ?? array() as $p) {
            if ($p['name'] === $name && ($p['class'] ?? null) === $class) {
                return $p['value'];
            }
        }

        return null;
    };

    // Every parameter in the catalog, keyed by ID, for objectives that cite them.
    $params = array();
    $collect = function (array $node) use (&$collect, &$params) {
        foreach ($node['params'] ?? array() as $p) {
            $params[$p['id']] = $p;
        }

        foreach ($node['controls'] ?? array() as $child) {
            $collect($child);
        }
    };

    foreach ($catalog['groups'] as $group) {
        $collect($group);
    }

    // Parameters inside objectives are rendered the way SP 800-53A prints them:
    // <AC-02_ODP[01] prerequisites and criteria>, <AC-01_ODP[03] SELECTED PARAMETER VALUE(S)>.
    $render = function (string $prose) use (&$params, $prop): string {
        return preg_replace_callback('/\{\{\s*insert:\s*param,\s*([^\s}]+)\s*\}\}/', function ($m) use (&$params, $prop) {
            $p = $params[$m[1]] ?? null;
            $label = $p === null ? null : $prop($p, 'label', 'sp800-53a');

            if ($label === null) {
                throw new RuntimeException('Objective cites a parameter with no SP 800-53A label: '.$m[1]);
            }

            if (isset($p['select'])) {
                return '<'.$label.' SELECTED PARAMETER VALUE(S)>';
            }

            return '<'.$label.' '.$p['label'].'>';
        }, $prose);
    };

    // Statement and discussion as NIST renders them in its CPRT export of the same release.
    $cprt = array();

    foreach (array_slice(Source::xlsx_rows('sp800-53/cprt-sp800-53-5.2.0.xlsx'), 1) as $row) {
        $id = trim($row['B'] ?? '');

        if ($id === '') {
            continue;
        }

        // CPRT pads numbers (AC-02(01)); the publication cites AC-2(1).
        $id = preg_replace('/(?<=-)0(?=\d)|(?<=\()0(?=\d\))/', '', $id);
        $lines = array();

        foreach (preg_split('/\R/', $row['D'] ?? '') as $line) {
            $line = Source::normalise($line);

            if ($line !== '') {
                $lines[] = $line;
            }
        }

        $cprt[$id] = array(
            'text' => implode("\n", $lines),
            'discussion' => Source::normalise($row['E'] ?? '')
        );
    }

    // Every assessment-objective part with prose is a determination statement; parts
    // without prose only group their children, and "Determine if:" is a lead-in.
    $objectives = function (array $part) use (&$objectives, $render, $prop): array {
        $out = array();
        $prose = Source::normalise($part['prose'] ?? '');

        if ($prose !== '' && $prose !== 'Determine if:') {
            $out[] = array(
                'id' => $prop($part, 'label', 'sp800-53a'),
                'text' => Source::normalise($render($part['prose']))
            );
        }

        foreach ($part['parts'] ?? array() as $child) {
            if ($child['name'] === 'assessment-objective') {
                $out = array_merge($out, $objectives($child));
            }
        }

        return $out;
    };

    $families = array();
    $controls = array();
    $withdrawn = array();

    $add = function (array $node, string $family, ?string $parent) use (&$add, &$families, &$controls, &$withdrawn, $prop, $objectives, $cprt) {
        $id = $prop($node, 'label');

        if ($prop($node, 'status') === 'withdrawn') {
            $withdrawn[] = $id;
        } else {
            $objs = array();

            foreach ($node['parts'] ?? array() as $part) {
                if ($part['name'] === 'assessment-objective') {
                    $objs = $objectives($part);
                }
            }

            if (!isset($cprt[$id]) || $cprt[$id]['text'] === '' || count($objs) === 0) {
                throw new RuntimeException('No CPRT statement or no objectives for '.$id);
            }

            $controls[$id] = array(
                'id' => $id,
                'number' => $id,
                'title' => Source::normalise($node['title']),
                'text' => $cprt[$id]['text'],
                'guidance' => $cprt[$id]['discussion'],
                'type' => $parent === null ? 'control' : 'enhancement',
                'parent' => $parent,
                'weight' => null,
                'partial_weight' => null,
                'objectives' => $objs
            );
            $families[$family]['ids'][] = $id;
        }

        foreach ($node['controls'] ?? array() as $child) {
            $add($child, $family, $id);
        }
    };

    foreach ($catalog['groups'] as $group) {
        $code = strtoupper($group['id']);
        $families[$code] = array(
            'code' => $code,
            'name' => Source::normalise($group['title']),
            'ids' => array()
        );

        foreach ($group['controls'] as $control) {
            $add($control, $code, null);
        }
    }

    return array(
        'families' => $families,
        'controls' => $controls,
        'withdrawn' => $withdrawn
    );
};
