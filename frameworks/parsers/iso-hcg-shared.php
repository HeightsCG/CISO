<?php
/**
 * HCG control sets for licensed ISO management system standards (ISO/IEC 42001 and the
 * others built the same way). Shared by their parsers.
 *
 * The full text of these standards is licensed and HCG does not hold a copy, so:
 * - Families with a numeric code are the standard's clauses 4 to 10. Each control's id and
 *   title are the official clause number and heading, and the build checks that the pair is
 *   printed in the contents list of the public preview stored as $preview_source (in its
 *   layout extract or its reading-order extract, <file>.raw.txt). Text is
 *   not reproduced: descriptions, guidance and test points are HCG-written.
 * - Families whose code starts with "HCG-" are HCG-written controls for the standard's
 *   annex control areas, which the public preview does not show. They carry no official
 *   numbering and cite the HCG source.
 *
 * The controls live in frameworks/meta/controls/<key>.json, outside meta/*.json so
 * FrameworkBuilder does not treat them as a framework.
 */
return function (string $key, string $preview_source): array {
    $meta = FrameworkBuilder::meta($key);
    $set = json_decode(file_get_contents(dirname(__DIR__).'/meta/controls/'.$key.'.json'), true);

    if (!is_array($set)) {
        throw new RuntimeException('meta/controls/'.$key.'.json is not valid JSON');
    }

    $file = null;

    foreach ($meta['sources'] as $source) {
        if ($source['id'] === $preview_source) {
            $file = $source['file'];
        }
    }

    if ($file === null) {
        throw new RuntimeException($key.' has no source '.$preview_source);
    }

    // The contents list, letters and digits only: "4.1 Understanding the ..." becomes "41understandingthe".
    $flat = function (string $text): string {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '', Source::normalise($text)));
    };
    // The preview's watermark splits some contents lines in the layout extract; the reading-order extract (.raw.txt) keeps them whole.
    $raw = preg_replace('/\.pdf$/', '.raw.txt', $file);
    $contents = $flat(Source::pdf_text($file)).' '.$flat(file_get_contents(Source::path($raw)));
    $families = array();

    foreach ($set['families'] as $family) {
        $official = preg_match('/^\d+$/', $family['code']) === 1;

        if (!$official && strpos($family['code'], 'HCG-') !== 0) {
            throw new RuntimeException('Family code '.$family['code'].' is neither a clause nor an HCG area');
        }

        if ($official && strpos($contents, $flat($family['code'].' '.$family['name'])) === false) {
            throw new RuntimeException('Clause '.$family['code'].' '.$family['name'].' is not in the preview contents');
        }

        $controls = array();

        foreach ($family['controls'] as $control) {
            if ($official) {
                if (strpos($control['id'], $family['code'].'.') !== 0) {
                    throw new RuntimeException($control['id'].' is outside clause '.$family['code']);
                }

                if (strpos($contents, $flat($control['id'].' '.$control['title'])) === false) {
                    throw new RuntimeException('Clause '.$control['id'].' '.$control['title'].' is not in the preview contents');
                }
            } elseif (!preg_match('/^'.preg_quote($family['code'], '/').'-\d{2}$/', $control['id'])) {
                throw new RuntimeException('Control ID does not match its area: '.$control['id']);
            }

            $objectives = array();

            foreach ($control['objectives'] as $o => $text) {
                $objectives[] = array(
                    'id' => $control['id'].'.'.chr(97 + $o),
                    'text' => $text,
                    'source' => 'hcg'
                );
            }

            $controls[] = array(
                'id' => $control['id'],
                'number' => $control['id'],
                'title' => $control['title'],
                'text' => '',
                'description' => $control['description'],
                'guidance' => $control['guidance'],
                'type' => $official ? 'clause' : 'hcg',
                'weight' => null,
                'partial_weight' => null,
                'objectives' => $objectives,
                'source' => $official ? $preview_source : 'hcg'
            );
        }

        $families[] = array(
            'number' => $family['code'],
            'code' => $family['code'],
            'name' => $family['name'],
            'controls' => $controls
        );
    }

    return $families;
};
