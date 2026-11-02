<?php
/**
 * NIST SP 800-171 Rev 3: structure derived from NIST's OSCAL catalog, which
 * carries both the SP 800-171 r3 requirements and the SP 800-171A r3
 * determination statements (assessment objectives).
 *
 * - Withdrawn requirements (status "withdrawn" in the catalog) are skipped.
 * - Requirement text renders organization-defined parameters the way the
 *   SP 800-171 r3 PDF prints them: [Assignment: ...] / [Selection (one or more): ...].
 * - Objective IDs follow SP 800-171A r3: the OSCAL id is the statement item it
 *   assesses plus a decomposition suffix, which 171A prints in square brackets
 *   (OSCAL A.03.01.01.a.01 for item a is 171A A.03.01.01.a[01]).
 * - Objective text renders parameters the way 171A prints them: <A.03.01.01.ODP[01]: time period>.
 * - The ODP definitions themselves are kept on each requirement as parameters[].
 * - Two catalog typos are tidied (a space before closing punctuation, one doubled
 *   full stop in 03.01.06); the reference test checks the result against the PDFs.
 */
return function (): array {
    $catalog = json_decode(file_get_contents(Source::path('nist-800-171r3/NIST_SP800-171_rev3_catalog.json')), true)['catalog'];
    $params = array();

    foreach ($catalog['groups'] as $group) {
        foreach ($group['controls'] as $control) {
            foreach ($control['params'] ?? array() as $param) {
                $params[$param['id']] = $param;
            }
        }
    }

    $tidy = function (string $text): string {
        $text = preg_replace('/\s+([.,;])/', '$1', Source::normalise($text));

        return preg_replace('/\.\.$/', '.', $text);
    };

    $label = function (array $node): string {
        foreach ($node['props'] ?? array() as $prop) {
            if ($prop['name'] === 'label') {
                return $prop['value'];
            }
        }

        throw new RuntimeException('No label on '.($node['id'] ?? '?'));
    };

    $is_withdrawn = function (array $control): bool {
        foreach ($control['props'] ?? array() as $prop) {
            if ($prop['name'] === 'status' && $prop['value'] === 'withdrawn') {
                return true;
            }
        }

        return false;
    };

    $assignment = function (string $prose) use (&$assignment, $params): string {
        return preg_replace_callback('/\{\{\s*insert:\s*param,\s*([^\s}]+)\s*\}\}/', function ($m) use ($assignment, $params) {
            $param = $params[$m[1]];

            if (isset($param['select'])) {
                $choices = array();

                foreach ($param['select']['choice'] as $choice) {
                    $choices[] = $assignment($choice);
                }

                $how = str_replace('-', ' ', $param['select']['how-many'] ?? 'one');

                return '[Selection ('.$how.'): '.implode('; ', $choices).']';
            }

            return '[Assignment: '.$param['usage'].']';
        }, $prose);
    };

    $reference = function (string $prose) use ($params, $label): string {
        return preg_replace_callback('/\{\{\s*insert:\s*param,\s*([^\s}]+)\s*\}\}/', function ($m) use ($params, $label) {
            $param = $params[$m[1]];

            return '<'.$label($param).': '.$param['label'].'>';
        }, $prose);
    };

    // Statement items: "a. ...", nested "1. ..." as the PDF prints them.
    $items = function (array $part, int $depth) use (&$items, $assignment, $tidy): array {
        $lines = array();

        foreach ($part['parts'] ?? array() as $item) {
            if ($item['name'] !== 'item') {
                continue;
            }

            $tail = substr($item['id'], strrpos($item['id'], '.') + 1);
            $marker = $depth === 0 ? $tail : (string) (int) $tail;
            $lines[] = $marker.'. '.$tidy($assignment($item['prose'] ?? ''));

            foreach ($items($item, $depth + 1) as $line) {
                $lines[] = $line;
            }
        }

        return $lines;
    };

    $guidance = function (string $prose): string {
        $prose = preg_replace('/\[\]\([^)]*\)/', '', $prose);
        $paragraphs = array();

        foreach (preg_split('/\n\s*\n/', $prose) as $paragraph) {
            $paragraph = Source::normalise($paragraph);

            if ($paragraph !== '') {
                $paragraphs[] = $paragraph;
            }
        }

        return implode("\n\n", $paragraphs);
    };

    // Natural order of 171A IDs: a[01] < a[02] < b < c.01 < c.02 ...
    $sort_key = function (string $id): string {
        return preg_replace_callback('/\d+/', function ($m) {
            return str_pad($m[0], 4, '0', STR_PAD_LEFT);
        }, strtr($id, '[]', '. '));
    };

    $families = array();

    foreach ($catalog['groups'] as $group) {
        $number = substr($group['id'], strlen('SP_800_171_'));
        $family = array(
            'number' => $number,
            'code' => '',
            'name' => $group['title'],
            'controls' => array()
        );

        foreach ($group['controls'] as $control) {
            if ($is_withdrawn($control)) {
                continue;
            }

            $n = substr($control['id'], strlen('SP_800_171_'));
            $text = array();
            $objectives = array();
            $discussion = '';

            foreach ($control['parts'] as $part) {
                if ($part['name'] === 'statement') {
                    if (trim($part['prose'] ?? '') !== '') {
                        $text[] = $tidy($assignment($part['prose']));
                    }

                    $text = array_merge($text, $items($part, 0));
                } elseif ($part['name'] === 'guidance') {
                    $discussion = $guidance($part['prose']);
                } elseif ($part['name'] === 'assessment-objective') {
                    $links = array();

                    foreach ($part['links'] ?? array() as $link) {
                        if ($link['rel'] === 'assessment-for') {
                            $links[] = $link['href'];
                        }
                    }

                    if (count($links) !== 1) {
                        throw new RuntimeException('Objective '.$part['id'].' does not name one statement item');
                    }

                    $oid = preg_replace('/^assessment-objective_DS-/', '', $part['id']);
                    $item = 'A.'.preg_replace('/^#SR-/', '', $links[0]);

                    if (strpos($oid, $item) !== 0) {
                        throw new RuntimeException('Objective '.$oid.' is not under '.$item);
                    }

                    $id = $item.preg_replace('/\.(\d+)/', '[$1]', substr($oid, strlen($item)));
                    $objectives[$sort_key($id)] = array(
                        'id' => $id,
                        'text' => $tidy($reference($part['prose']))
                    );
                }
            }

            ksort($objectives);
            $parameters = array();

            foreach ($control['params'] ?? array() as $param) {
                if (isset($param['select'])) {
                    $definition = 'Selection ('.str_replace('-', ' ', $param['select']['how-many']).'): '.implode('; ', array_map($reference, $param['select']['choice']));
                } else {
                    $definition = $param['guidelines'][0]['prose'];
                }

                $parameters[$sort_key($label($param))] = array(
                    'id' => $label($param),
                    'label' => $param['label'],
                    'text' => Source::normalise($definition)
                );
            }

            ksort($parameters);

            if (count($text) === 0 || count($objectives) === 0) {
                throw new RuntimeException('Requirement '.$n.' has no statement or no objectives');
            }

            $family['controls'][] = array(
                'id' => $n,
                'number' => $n,
                'title' => $control['title'],
                'text' => implode("\n", $text),
                'guidance' => $discussion,
                'parameters' => array_values($parameters),
                'objectives' => array_values($objectives)
            );
        }

        $families[] = $family;
    }

    return $families;
};
