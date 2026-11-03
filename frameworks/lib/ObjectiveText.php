<?php
/**
 * Builds an objective's optional `description`: a standalone sentence for an
 * objective whose official `text` cannot be read on its own (a list item that
 * needs its lead-in, a trailing "; and", an unrendered parameter placeholder).
 * The official `text` is never changed.
 */
class ObjectiveText {

    // A lead-in ending in one of these words runs straight on into its items.
    const RUN_ON = array(
        'shall',
        'will',
        'must',
        'may',
        'should',
        'to',
        'by',
        'that',
        'of',
        'for',
        'which',
        'be',
        'include',
        'includes',
        'including',
        'contain',
        'contains',
        'containing',
        'ensure',
        'address',
        'addresses',
        'entities'
    );

    // A lead-in ending in one of these words keeps its colon before a lower-case item.
    const KEEPS_COLON = array(
        'follows',
        'furthermore'
    );

    // Words after which a frequency placeholder reads as a noun, not as "at a frequency".
    const BEFORE_NOUN = array(
        'a',
        'an',
        'the',
        'of',
        'at',
        'per',
        'every',
        'and',
        'or',
        'following',
        'by',
        'with',
        'to',
        'on',
        'in',
        'than',
        'defined'
    );

    // The sentence a list item hangs from: the lead-in's last sentence, plus the
    // one before it when it opens by pointing back ("These policies ...",
    // "With regard to these changes:").
    public static function stem(string $lead): string
    {
        $lead = trim($lead);
        $sentences = preg_split('/(?<=[a-z\)][.])\s+(?=[A-Z])/', $lead);
        $stem = array_pop($sentences);

        while (count($sentences) > 0 && preg_match('/^(?:\S+\s+){0,3}?(these|those|such|that|this|it|they|furthermore)\b/i', $stem)) {
            $stem = array_pop($sentences).' '.$stem;
        }

        return $stem;
    }

    // A list item without its list punctuation ("...; and", "...; or", "...:").
    public static function item(string $text): string
    {
        return preg_replace('/\s*(?:[;,]\s*(?:and|or)|[;:.,])\s*$/u', '', trim($text));
    }

    public static function trailing_or(string $text): bool
    {
        return preg_match('/[;,]\s*or\s*$/u', trim($text)) === 1;
    }

    public static function trailing_connector(string $text): bool
    {
        return preg_match('/(?:[;,]\s*(?:and|or)|[:—])\s*$/u', trim($text)) === 1;
    }

    // True when a lead-in introduces a list ("... shall:", "... will—").
    public static function introduces_list(string $lead): bool
    {
        return preg_match('/[:—]\s*$/u', trim($lead)) === 1;
    }

    // The lead-in and a list item joined into one sentence.
    public static function complete(string $lead, string $item): string
    {
        return self::sentence(self::join($lead, self::item($item)), self::trailing_or($item));
    }

    // The lead-in's stem and an item run together, the item's own punctuation kept
    // (so a nested lead-in still ends in its colon).
    public static function join(string $lead, string $item): string
    {
        $stem = rtrim(preg_replace('/[:—]\s*$/u', '', self::stem($lead)));
        $last = strtolower((string) preg_replace('/^.*\s/s', '', $stem));

        // "... address the following areas to the extent applicable: information security"
        // reads as "... address information security to the extent applicable".
        if (preg_match('/^(.*?)\bthe following [a-z]+\b(.*)$/s', $stem, $m) && !self::introduces_list($item) && preg_match('/^(there|where|if|when)\b|\bshall\b/i', $item) !== 1) {
            return $m[1].self::lower_first($item).$m[2];
        }

        // "including by: Identify ..." keeps its colon; "by: Providing ..." runs on, as does a
        // lower-case item that is not a clause of its own ("the organisation: establishes ...").
        $runs_on = in_array($last, self::RUN_ON, true) && ($last !== 'by' || preg_match('/^\w+ing\b/', $item) === 1);
        $continues = preg_match('/^[a-z]/', $item) === 1 && preg_match('/^(there|where|if|when)\b|\bshall\b/i', $item) !== 1 && !in_array($last, self::KEEPS_COLON, true);

        if ($runs_on || $continues || mb_substr(trim($lead), -1) === '—') {
            return $stem.' '.self::lower_first($item);
        }

        return $stem.': '.$item;
    }

    // Capital first letter, one closing full stop; an item that ends "; or" says it is an alternative.
    public static function sentence(string $text, bool $alternative = false): string
    {
        $alternative = $alternative || self::trailing_or($text);
        $text = self::item($text);
        $first = mb_substr($text, 0, 1);
        $text = mb_strtoupper($first).mb_substr($text, 1).'.';

        if ($alternative) {
            $text .= ' Alternatively, the requirement can be met as the objectives that follow describe.';
        }

        return $text;
    }

    public static function lower_first(string $text): string
    {
        if (preg_match('/^[A-Z][a-z]/', $text) !== 1) {
            return $text;
        }

        return strtolower(substr($text, 0, 1)).substr($text, 1);
    }

    // An assignment placeholder in words: "[organization-defined personnel or roles]",
    // or "at an organization-defined frequency" where the frequency stands alone.
    public static function assignment(string $label, string $before): string
    {
        $label = preg_replace('/\[([^\]]+)\]\([^)]*\)/', '$1', trim($label));

        if (strpos($label, 'organization-defined') !== 0) {
            $label = 'organization-defined '.$label;
        }

        $previous = strtolower((string) preg_replace('/^.*\s/s', '', rtrim($before)));

        if ($label === 'organization-defined frequency' && !in_array($previous, self::BEFORE_NOUN, true)) {
            return 'at an organization-defined frequency';
        }

        return '['.$label.']';
    }

    // A selection placeholder in words: "[one or more of: a; b; c]".
    public static function selection(array $choices, string $how_many): string
    {
        $how = $how_many === 'one-or-more' ? 'one or more of' : 'one of';

        return '['.$how.': '.implode('; ', $choices).']';
    }
}
