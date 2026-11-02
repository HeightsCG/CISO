<?php
/**
 * Spell-check for consultant-written text. Accepts US and British spellings
 * (SCOWL size 60 word lists in libs/data/spelling), HCG's security vocabulary,
 * and any extra words passed in (framework glossary terms, client and project
 * names). Acronyms, words with digits, and capitalised words that do not start
 * a sentence (names) are not checked.
 */
class SpellCheck {

    private static $words = null;

    private static function load(): array
    {
        if (self::$words === null) {
            self::$words = array();
            $dir = dirname(__DIR__).'/data/spelling/';

            foreach (array('en_US-60.txt', 'en_GB-60.txt', 'hcg-terms.txt') as $file) {
                $body = file_get_contents($dir.$file);
                $start = strpos($body, "\n---\n");
                $body = $start === false ? $body : substr($body, $start + 5);

                foreach (preg_split('/\R/', $body) as $line) {
                    $line = trim($line);

                    if ($line !== '' && $line[0] !== '#') {
                        self::$words[mb_strtolower($line)] = true;
                    }
                }
            }
        }

        return self::$words;
    }

    /** Unknown words in $text, in order of first appearance, without repeats. */
    public static function unknown_words(string $text, array $extra = array()): array
    {
        $words = self::load();
        $known = array();

        foreach ($extra as $phrase) {
            foreach (preg_split('/[^\p{L}\']+/u', mb_strtolower($phrase)) as $part) {
                if ($part !== '') {
                    $known[$part] = true;
                }
            }
        }

        $unknown = array();
        preg_match_all('/[\p{L}][\p{L}\'’-]*/u', $text, $m, PREG_OFFSET_CAPTURE);

        foreach ($m[0] as $match) {
            $word = $match[0];
            $offset = $match[1];

            if (preg_match('/\d/', $word) || mb_strlen($word) < 3) {
                continue;
            }

            if (preg_match('/^\p{Lu}[\p{Lu}\'’-]*$/u', $word) || preg_match('/\p{Ll}\p{Lu}/u', $word)) {
                continue;
            }

            $before = rtrim(substr($text, 0, $offset));
            $sentence_start = $before === '' || preg_match('/[.!?:\n]$/', $before);

            if (!$sentence_start && preg_match('/^\p{Lu}/u', $word)) {
                continue;
            }

            foreach (preg_split('/-/', $word) as $part) {
                $part = mb_strtolower(preg_replace('/[\'’]s$/u', '', $part));
                $part = trim($part, "'’");

                if ($part === '' || mb_strlen($part) < 3 || isset($words[$part]) || isset($known[$part])) {
                    continue;
                }

                $unknown[$part] = true;
            }
        }

        return array_keys($unknown);
    }

    /** Words typed twice in a row ("the the"), case-insensitive. */
    public static function repeated_words(string $text): array
    {
        preg_match_all('/\b(\p{L}+)\s+\1\b/iu', $text, $m);

        return array_values(array_unique(array_map('mb_strtolower', $m[1])));
    }

}
