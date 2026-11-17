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
                    if (isset($objective['description']) && (!is_string($objective['description']) || trim($objective['description']) === '')) {
                        throw new RuntimeException($key.' objective '.$objective['id'].' has an empty description');
                    }

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

    /**
     * The public catalogue: a small summary of every built definition, holding only what the
     * public framework pages and the home page need, so they never decode the full
     * definitions (which run to several megabytes). Section names and counts only.
     */
    public static function catalogue(): array
    {
        $catalogue = array();

        foreach (self::keys() as $key) {
            $definition = json_decode(file_get_contents(dirname(__DIR__).'/'.$key.'.json'), true);
            $families = array();
            $applies = array();

            foreach ($definition['families'] as $family) {
                $families[] = array(
                    'code' => (string) $family['code'],
                    'name' => (string) $family['name'],
                    'count' => count($family['controls'])
                );
            }

            foreach ($definition['rules'] as $rule) {
                if (($rule['type'] ?? '') === 'applicability' && !empty($rule['summary'])) {
                    $applies[] = $rule['summary'];
                }
            }

            $catalogue[$key] = array(
                'key' => $key,
                'name' => $definition['name'],
                'version' => $definition['version'],
                'full_name' => $definition['full_name'],
                'issuing_body' => $definition['issuing_body'],
                'catalogue' => $definition['catalogue'],
                'control_term' => $definition['terminology']['controls'] ?? 'controls',
                'family_term' => $definition['terminology']['families'] ?? 'families',
                'scoring' => $definition['scoring']['summary'] ?? '',
                'notice' => $definition['notice'] ?? '',
                'applies' => $applies,
                'glossary' => $definition['glossary'] ?? array(),
                'controls' => $definition['counts']['controls'],
                'objectives' => $definition['counts']['objectives'],
                'families' => $families
            );
            unset($definition);
        }

        return $catalogue;
    }

    public static function encode(array $definition): string
    {
        return json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
    }

}
