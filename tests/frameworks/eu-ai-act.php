<?php
/**
 * EU AI Act checks. The parser walks the article markup; these read the same articles as
 * plain text and count paragraphs a second way.
 */
$file = 'eu-ai-act/reg-2024-1689.html';

$definition = function () {
    return json_decode(file_get_contents(ROOT.'/frameworks/eu-ai-act.json'), true);
};

$html = function () use ($file) {
    static $html = null;
    $html = $html ?? file_get_contents(Source::path($file));

    return $html;
};

// Markup of one article, from its heading to the next article's.
$markup = function (int $number) use ($html) {
    $start = strpos($html(), 'id="art_'.$number.'"');
    $end = strpos($html(), 'id="art_'.($number + 1).'"');

    return substr($html(), $start, $end - $start);
};

$article = function (int $number) use ($markup) {
    return Source::normalise(str_replace("\u{a0}", ' ', html_entity_decode(strip_tags($markup($number)), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
};

$excluded = array(
    '5(2)',
    '5(3)',
    '5(4)',
    '5(5)',
    '5(6)',
    '5(7)',
    '5(8)',
    '9(7)',
    '11(3)',
    '15(2)',
    '18(2)',
    '21(3)',
    '22(3)',
    '22(4)',
    '25(5)',
    '26(3)',
    '27(5)',
    '43(5)',
    '43(6)',
    '47(5)',
    '50(6)',
    '50(7)',
    '53(2)',
    '53(4)',
    '53(5)',
    '53(6)',
    '53(7)',
    '54(3)',
    '54(4)',
    '54(5)',
    '54(6)',
    '55(2)',
    '55(3)',
    '73(5)',
    '73(7)',
    '73(8)',
    '73(11)'
);

return array(
    'thirty-two articles, each with a role and a system type' => function () use ($definition) {
        $d = $definition();
        $roles = array();

        Assert::same(32, count($d['families']), 'articles');

        foreach ($d['families'] as $family) {
            Assert::true(in_array($family['system_type'], array('all', 'prohibited', 'high_risk', 'certain', 'general_purpose', 'general_purpose_systemic'), true), $family['code'].' system type');

            foreach (explode(', ', $family['role']) as $role) {
                $roles[$role] = true;
            }
        }

        ksort($roles);
        Assert::same(array(
            'any',
            'deployer',
            'distributor',
            'importer',
            'provider'
        ), array_keys($roles), 'roles');
    },

    'role comes from the article itself' => function () use ($definition, $article) {
        $subject = array(
            16 => 'Providers of high-risk AI systems shall',
            23 => 'importers shall',
            24 => 'distributors shall',
            26 => 'Deployers of high-risk AI systems shall',
            53 => 'Providers of general-purpose AI models shall',
            55 => 'providers of general-purpose AI models with systemic risk shall'
        );
        $roles = array();

        foreach ($definition()['families'] as $family) {
            $roles[(int) $family['number']] = $family['role'];
        }

        foreach ($subject as $n => $phrase) {
            Assert::true(strpos($article($n), $phrase) !== false, 'Article '.$n.' names its operator');
        }

        Assert::same('importer', $roles[23], 'Article 23');
        Assert::same('distributor', $roles[24], 'Article 24');
        Assert::same('deployer', $roles[26], 'Article 26');
        Assert::same('provider', $roles[16], 'Article 16');
    },

    'every numbered paragraph is an obligation or a listed exclusion' => function () use ($definition, $markup, $excluded) {
        $total = 0;

        foreach ($definition()['families'] as $family) {
            $n = (int) $family['number'];
            preg_match_all('/id="'.sprintf('%03d', $n).'\.\d{3}"/', $markup($n), $ids);
            $printed = count(array_unique($ids[0]));
            $built = count($family['controls']);
            $left_out = count(array_filter($excluded, function ($ref) use ($n) {
                return strpos($ref, $n.'(') === 0;
            }));

            Assert::same(max($printed, 1), $built + $left_out, 'Article '.$n.' paragraphs');
            $total += $built;
        }

        Assert::same(122, $total, 'obligations');
    },

    'excluded paragraphs are addressed to someone else or grant an option' => function () use ($article, $excluded) {
        $openings = array(
            '5(5)' => 'A Member State may decide',
            '9(7)' => 'Testing procedures may include',
            '11(3)' => 'The Commission is empowered to adopt delegated acts',
            '18(2)' => 'Each Member State shall determine',
            '22(3)' => 'The authorised representative shall perform',
            '27(5)' => 'The AI Office shall develop a template',
            '53(4)' => 'Providers of general-purpose AI models may rely on codes of practice',
            '73(8)' => 'The market surveillance authority shall take appropriate measures'
        );

        foreach ($openings as $ref => $opening) {
            Assert::true(in_array($ref, $excluded, true), $ref.' is listed');
            Assert::true(strpos($article((int) $ref), $opening) !== false, 'Article '.$ref.': '.$opening);
        }
    },

    'every obligation and element is printed in its article' => function () use ($definition, $article) {
        foreach ($definition()['families'] as $family) {
            $text = $article((int) $family['number']);
            Assert::true(strpos($text, $family['name']) !== false, $family['code'].' title');

            foreach ($family['controls'] as $control) {
                $opening = implode(' ', array_slice(explode(' ', $control['text']), 0, 10));
                Assert::true(strpos($text, $opening) !== false, $control['id'].' text');

                foreach ($control['objectives'] as $i => $objective) {
                    Assert::same($control['id'].'('.chr(ord('a') + $i).')', $objective['id'], $control['id'].' points run in order');
                    $opening = implode(' ', array_slice(explode(' ', $objective['text']), 0, 6));
                    Assert::true(strpos($text, $opening) !== false, $objective['id'].' text');
                }
            }
        }
    },

    'well-known lists have their published length' => function () use ($definition) {
        $points = array();

        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                $points[$control['id']] = count($control['objectives']);
            }
        }

        Assert::same(8, $points['Art. 5(1)'], 'prohibited practices');
        Assert::same(12, $points['Art. 16'], 'provider obligations');
        Assert::same(13, $points['Art. 17(1)'], 'quality management system aspects');
        Assert::same(4, $points['Art. 53(1)'], 'general-purpose model provider obligations');
        Assert::same(4, $points['Art. 55(1)'], 'systemic risk obligations');
        Assert::same(0, $points['Art. 43(1)'], 'a paragraph with two lists is assessed whole');
    }
);
