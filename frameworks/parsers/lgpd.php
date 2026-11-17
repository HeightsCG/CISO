<?php
/**
 * Lei Geral de Proteção de Dados Pessoais (LGPD), Lei nº 13.709/2018, in Portuguese, from the
 * updated text (norma atualizada) published by the Câmara dos Deputados' Centro de
 * Documentação e Informação, which incorporates every amendment to date.
 *
 * - Groups: the chapters and sections whose articles bind processing agents (controlador and
 *   operador): Art. 6 (princípios), Arts. 7 to 16 (tratamento de dados pessoais, dados
 *   sensíveis, crianças e adolescentes, término), 17 to 22 (direitos do titular), 33 to 36
 *   (transferência internacional), 37 to 45 (agentes de tratamento, encarregado,
 *   responsabilidade) and 46 to 51 (segurança e boas práticas).
 * - Controls: each article's caput and each of its paragraphs (§ or parágrafo único).
 * - Objectives: the incisos of a caput or paragraph, with their alíneas.
 * - Left out: paragraphs that only empower the ANPD or a court ("A autoridade nacional
 *   poderá...", "O juiz..."); Arts. 1 to 5 (scope and definitions), 23 to 32 (processing by public bodies),
 *   52 onwards (sanctions, the ANPD and the national council, final provisions), and every
 *   vetoed or revoked provision. Amendment notes in parentheses are removed; the text is
 *   otherwise word for word.
 */
return function (): array {
    $html = file_get_contents(Source::path('lgpd/lei-13709-2018-norma-atualizada-camara.html'));
    preg_match_all('/<p\b[^>]*>(.*?)<\/p>/s', $html, $m);
    $paragraphs = array();

    foreach ($m[1] as $p) {
        $p = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($p), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        if ($p !== '') {
            $paragraphs[] = $p;
        }
    }

    $note = '/\s*\((?:[^()]|\([^()]*\))*?(?:acrescid|redação dada|Vide|Revogad|incluíd|renumerad|convertida|VETAD)(?:[^()]|\([^()]*\))*\)\s*$/ui';
    $strip = function (string $text) use ($note): string {
        do {
            $before = $text;
            $text = preg_replace($note, '', $text);
        } while ($text !== $before);

        return trim($text);
    };
    $dead = function (string $text): bool {
        return preg_match('/^(?:Art\. \d+(?:-[A-Z])?\.|§ \d+º(?:-[A-Z])?\.?|Parágrafo único\.|[IVXL]+(?:-[A-Z])? -|[a-z]\))\s*\((?:VETAD|Revogad)/ui', $text) === 1;
    };

    $in_scope = array_merge(array(6), range(7, 22), range(33, 51));
    $chapter = '';
    $section = '';
    $families = array();
    $article = null;
    $unit = null;

    $close = function () use (&$unit, &$families, &$family_key) {
        if ($unit === null) {
            return;
        }

        $families[$family_key]['controls'][] = $unit;
        $unit = null;
    };

    $family_key = null;
    $sentence_case = function (string $text): string {
        $lower = mb_strtolower($text, 'UTF-8');

        return mb_strtoupper(mb_substr($lower, 0, 1), 'UTF-8').mb_substr($lower, 1);
    };

    for ($i = 0; $i < count($paragraphs); $i++) {
        $p = $paragraphs[$i];

        if (preg_match('/^CAPÍTULO ([IVXL]+(?:-[A-Z])?)$/u', $p, $c)) {
            $close();
            $chapter = 'Capítulo '.$c[1].' – '.$sentence_case($paragraphs[$i + 1]);
            $section = '';
            $i++;
            continue;
        }

        if (preg_match('/^Seção ([IVXL]+(?:-[A-Z])?)$/u', $p, $s)) {
            $close();
            $section = 'Seção '.$s[1].' – '.$paragraphs[$i + 1];
            $i++;
            continue;
        }

        if (strpos($p, '(') === 0) {
            continue;
        }

        if (preg_match('/^Art\. (\d+)(?:º|\.)?(-[A-Z])?\.?\s+(.*)$/u', $p, $a)) {
            $close();
            $number = (int) $a[1];
            $article = in_array($number, $in_scope, true) && $a[2] === '' && !$dead($p) ? 'Art. '.$number : null;

            if ($article === null) {
                continue;
            }

            $family_key = $chapter.($section === '' ? '' : ' | '.$section);

            if (!isset($families[$family_key])) {
                $families[$family_key] = array(
                    'number' => '',
                    'code' => '',
                    'name' => $section === '' ? $chapter : $chapter.', '.$section,
                    'controls' => array()
                );
            }

            $unit = array(
                'id' => $article,
                'text' => $strip($a[3]),
                'items' => array()
            );
            continue;
        }

        if ($article === null) {
            continue;
        }

        if (preg_match('/^(§ \d+º(?:-[A-Z])?|Parágrafo único)\.?\s+(.*)$/u', $p, $s)) {
            $close();

            if ($dead($p)) {
                continue;
            }

            $unit = array(
                'id' => $article.', '.($s[1] === 'Parágrafo único' ? 'parágrafo único' : $s[1]),
                'text' => $strip($s[2]),
                'items' => array()
            );
            continue;
        }

        if ($unit === null) {
            continue;
        }

        if (preg_match('/^([IVXL]+(?:-[A-Z])?) - (.*)$/u', $p, $inc)) {
            if (!$dead($p)) {
                $unit['items'][] = array(
                    'label' => $inc[1],
                    'text' => $strip($inc[0])
                );
            }

            continue;
        }

        if (preg_match('/^[a-z]\) /u', $p) && count($unit['items']) > 0) {
            if (!$dead($p)) {
                $unit['items'][count($unit['items']) - 1]['text'] .= ' '.$strip($p);
            }

            continue;
        }
    }

    $close();

    // Families get codes in order; controls take their final shape.
    $out = array();
    $n = 0;

    foreach ($families as $family) {
        $n++;
        $controls = array();

        foreach ($family['controls'] as $unit) {
            // Provisions that empower the ANPD or a court place no duty on processing agents.
            if ($unit['text'] === '' || preg_match('/^(?:A autoridade nacional|O juiz)\b/u', $unit['text'])) {
                continue;
            }

            $objectives = array();

            foreach ($unit['items'] as $item) {
                $objectives[] = array(
                    'id' => $unit['id'].', '.$item['label'],
                    'text' => $item['text'],
                    'source' => 'lgpd'
                );
            }

            $controls[] = array(
                'id' => $unit['id'],
                'number' => $unit['id'],
                'text' => $unit['text'],
                'type' => strpos($unit['id'], ',') === false ? 'caput' : 'paragraph',
                'weight' => null,
                'partial_weight' => null,
                'objectives' => $objectives,
                'source' => 'lgpd'
            );
        }

        $out[] = array(
            'number' => 'G'.$n,
            'code' => 'G'.$n,
            'name' => $family['name'],
            'controls' => $controls
        );
    }

    return $out;
};
