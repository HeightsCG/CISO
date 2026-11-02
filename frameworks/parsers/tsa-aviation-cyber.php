<?php
/**
 * TSA Aviation Cybersecurity: HCG-authored control set. The detailed
 * requirements in airport and aircraft operator security program amendments
 * are SSI, so each control is built around an outcome TSA has stated publicly.
 *
 * - Control areas, controls, descriptions, guidance and test points:
 *   frameworks/meta/controls/tsa-aviation-cyber.json (HCG-written; kept outside
 *   meta/*.json so FrameworkBuilder does not treat it as a framework).
 * - Each control's `basis` must appear word for word in the TSA press release
 *   it cites; the build fails otherwise.
 */
return function (): array {
    $meta = FrameworkBuilder::meta('tsa-aviation-cyber');
    $set = json_decode(file_get_contents(dirname(__DIR__).'/meta/controls/tsa-aviation-cyber.json'), true);

    if (!is_array($set)) {
        throw new RuntimeException('meta/controls/tsa-aviation-cyber.json is not valid JSON');
    }

    // Plain text of each stored press release, keyed by source id.
    $texts = array();

    foreach ($meta['sources'] as $source) {
        if (!isset($source['file'])) {
            continue;
        }

        $html = file_get_contents(Source::path($source['file']));
        $html = preg_replace('/<(script|style)\b.*?<\/\1>/s', ' ', $html);
        $html = preg_replace('/<\/?(p|li|div|br|h\d)\b[^>]*>/i', "\n", $html);
        $texts[$source['id']] = Source::normalise(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    $families = array();

    foreach ($set['families'] as $i => $family) {
        $controls = array();

        foreach ($family['controls'] as $control) {
            if (!preg_match('/^TSA-'.$family['code'].'-\d{2}$/', $control['id'])) {
                throw new RuntimeException('Control ID does not match its area: '.$control['id']);
            }

            if (!isset($texts[$control['source']])) {
                throw new RuntimeException($control['id'].' cites a source with no stored file: '.$control['source']);
            }

            if (strpos($texts[$control['source']], Source::normalise($control['basis'])) === false) {
                throw new RuntimeException($control['id'].' basis not found in '.$control['source']);
            }

            $objectives = array();

            foreach ($control['objectives'] as $o => $text) {
                $objectives[] = array(
                    'id' => $control['id'].'.'.chr(97 + $o),
                    'text' => $text
                );
            }

            $controls[] = array(
                'id' => $control['id'],
                'number' => substr($control['id'], 4),
                'title' => $control['title'],
                'text' => '',
                'description' => $control['description'],
                'guidance' => $control['guidance'],
                'type' => 'hcg',
                'weight' => null,
                'partial_weight' => null,
                'objectives' => $objectives,
                'source' => $control['source'],
                'source_basis' => Source::normalise($control['basis'])
            );
        }

        $families[] = array(
            'number' => (string) ($i + 1),
            'code' => $family['code'],
            'name' => $family['name'],
            'controls' => $controls
        );
    }

    return $families;
};
