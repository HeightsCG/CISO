<?php
/**
 * CIS Controls v8.1 readiness HCG control set; see hcg-set-shared.php. Area CIS-NN must be
 * named exactly as "CIS Control N: <name>" on CIS's stored public list.
 */
return function (): array {
    $set = require __DIR__.'/hcg-set-shared.php';
    $families = $set('cis-controls');
    $html = file_get_contents(Source::path('cis/cis-controls-list.html'));
    $html = preg_replace('/<(script|style)\b.*?<\/\1>/s', ' ', $html);
    $text = Source::normalise(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    foreach ($families as $family) {
        $number = (int) substr($family['code'], 4);

        if (strpos($text, 'CIS Control '.$number.': '.$family['name']) === false) {
            throw new RuntimeException($family['code'].' '.$family['name'].' is not on the CIS list');
        }
    }

    return $families;
};
