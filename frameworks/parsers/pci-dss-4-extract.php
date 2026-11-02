<?php
/**
 * IDs are taken where a line starts with them, and also where a requirement
 * statement wraps mid-line at a page break (10.2.1.4).
 *
 * Derives the PCI DSS v4.0.1 requirement list (IDs, principal requirement
 * names, future-dated and service-provider flags) from the licensed PDF's text
 * extract. The PDF and extract stay local (gitignored); the derived list is
 * committed as sources/pci/pci-dss-4.0.1-ids.json.
 *
 *     php frameworks/parsers/pci-dss-4-extract.php    rewrite the committed list
 */
function pci_dss_extract(string $text): array
{
    preg_match_all('/^Requirement (\d{1,2}):\s?(.+)$/m', $text, $headings, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
    $sections = array();

    foreach ($headings as $heading) {
        $number = (int) $heading[1][0];

        if ($heading[0][1] > 20000 && !isset($sections[$number])) {
            $name = trim($heading[2][0]);
            $after = substr($text, $heading[0][1] + strlen($heading[0][0]), 40);

            if (preg_match('/^\n([A-Z][a-z]+)\nSections\n/', $after, $wrap)) {
                $name .= ' '.$wrap[1];
            }

            $sections[$number] = array(
                $heading[0][1],
                $name
            );
        }
    }

    if (count($sections) !== 12) {
        throw new RuntimeException('Expected 12 principal requirements, found '.count($sections));
    }

    $appendix = strpos($text, "\nAppendix A1:", $sections[12][0]);
    $out = array();

    foreach ($sections as $number => $section) {
        $stop = $sections[$number + 1][0] ?? $appendix;
        $body = substr($text, $section[0], $stop - $section[0]);
        preg_match_all('/^('.$number.'\.\d+\.\d+(?:\.\d+)*)(?: |$)/m', $body, $m, PREG_OFFSET_CAPTURE);
        $first = array();

        foreach ($m[1] as $match) {
            if (!isset($first[$match[0]])) {
                $first[$match[0]] = $match[1];
            }
        }

        preg_match_all('/(?<![\d.v])('.$number.'\.\d+\.\d+(?:\.\d+)*)(?!\d)/', $body, $anywhere, PREG_OFFSET_CAPTURE);

        foreach ($anywhere[1] as $match) {
            if (!isset($first[$match[0]])) {
                $first[$match[0]] = $match[1];
            }
        }

        uksort($first, 'version_compare');
        $ids = array_keys($first);
        $controls = array();

        foreach ($ids as $i => $id) {
            $later = array_filter($first, function ($offset) use ($first, $id) {
                return $offset > $first[$id];
            });
            $end = count($later) > 0 ? min($later) : strlen($body);
            $block = preg_replace('/\s+/', ' ', substr($body, $first[$id], $end - $first[$id]));
            $control = array(
                'id' => $id
            );

            if (stripos($block, 'best practice until 31 March 2025') !== false) {
                $control['future_dated_until'] = '2025-03-31';
            }

            if (stripos($block, 'Additional requirement for service providers only') !== false) {
                $control['service_provider_only'] = true;
            }

            if (stripos($block, 'Additional requirement for multi-tenant service providers only') !== false) {
                $control['service_provider_only'] = true;
                $control['multi_tenant_only'] = true;
            }

            $controls[] = $control;
        }

        $out[] = array(
            'number' => (string) $number,
            'name' => preg_replace('/\s+/', ' ', $section[1]),
            'controls' => $controls
        );
    }

    return $out;
}

if (PHP_SAPI === 'cli' && realpath($argv[0] ?? '') === realpath(__FILE__)) {
    $text = file_get_contents(dirname(__DIR__).'/sources/pci/PCI-DSS-v4_0_1.txt');
    $list = array(
        'pdf_sha256' => hash_file('sha256', dirname(__DIR__).'/sources/pci/PCI-DSS-v4_0_1.pdf'),
        'requirements' => pci_dss_extract($text)
    );
    file_put_contents(dirname(__DIR__).'/sources/pci/pci-dss-4.0.1-ids.json', json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
    echo "written\n";
}
