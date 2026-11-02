<?php
/**
 * Joins a framework's hand-written metadata with the structure its parser
 * derives from source. Used by build.php and by the reference tests, which
 * rebuild every definition and compare it with the committed file.
 */
class FrameworkBuilder {

    public static function keys(): array
    {
        $keys = array();

        foreach (glob(dirname(__DIR__).'/meta/*.json') as $file) {
            $keys[] = basename($file, '.json');
        }

        sort($keys);

        return $keys;
    }

    public static function meta(string $key): array
    {
        $meta = json_decode(file_get_contents(dirname(__DIR__).'/meta/'.$key.'.json'), true);

        if (!is_array($meta)) {
            throw new RuntimeException('meta/'.$key.'.json is not valid JSON');
        }

        return $meta;
    }

    public static function build(string $key): array
    {
        $meta = self::meta($key);
        $parser = require dirname(__DIR__).'/parsers/'.$key.'.php';
        $families = $parser();
        $sources = array();

        foreach ($meta['sources'] as $source) {
            $sources[$source['id']] = $source['title'];
        }

        $control_source = $meta['source_of']['controls'];
        $objective_source = $meta['source_of']['objectives'] ?? $control_source;
        $controls = 0;
        $objectives = 0;

        foreach ($families as $f => $family) {
            foreach ($family['controls'] as $c => $control) {
                $control['source'] = $control['source'] ?? $control_source;

                foreach ($control['objectives'] as $o => $objective) {
                    $control['objectives'][$o]['source'] = $objective['source'] ?? $objective_source;
                    $objectives++;
                }

                $families[$f]['controls'][$c] = $control;
                $controls++;
            }
        }

        $meta['display_name'] = $meta['name'].' ('.$meta['version'].')';
        $meta['counts'] = array(
            'families' => count($families),
            'controls' => $controls,
            'objectives' => $objectives
        );
        $meta['families'] = $families;

        return $meta;
    }

    public static function encode(array $definition): string
    {
        return json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
    }

}
