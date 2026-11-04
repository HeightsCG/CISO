<?php

/**
 * Formatted text from the description editor. Only paragraphs, line breaks,
 * bold, italic, underline, lists and http(s)/mailto links survive; everything
 * else is unwrapped to its text, and scripts and styles are dropped whole.
 * Older descriptions are plain text, so anything without tags is treated as
 * plain text.
 */
class RichText
{
    private const TAGS = array(
        'p' => 'p',
        'br' => 'br',
        'strong' => 'strong',
        'b' => 'strong',
        'em' => 'em',
        'i' => 'em',
        'u' => 'u',
        'ul' => 'ul',
        'ol' => 'ol',
        'li' => 'li',
        'a' => 'a'
    );

    private const DROP = array(
        'script',
        'style',
        'iframe',
        'object',
        'embed',
        'template',
        'head',
        'title',
        'meta',
        'link'
    );

    /** What to store: cleaned HTML, plain text as it came, or '' when empty. */
    public static function clean($value): string
    {
        $value = trim((string) $value);

        if (!self::is_html($value)) {
            return $value;
        }

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="rich">'.$value.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $root = $doc->getElementById('rich');

        if ($root === null) {
            return '';
        }

        $out = new DOMDocument();
        $holder = $out->createElement('div');
        $out->appendChild($holder);

        foreach (iterator_to_array($root->childNodes) as $child) {
            self::copy($child, $holder, $out);
        }

        $html = '';

        foreach ($holder->childNodes as $child) {
            $html .= $out->saveHTML($child);
        }

        $html = trim($html);

        if (trim(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8')) === '') {
            return '';
        }

        return $html;
    }

    /** Safe HTML to show on a page. */
    public static function show($value): string
    {
        $value = (string) $value;

        if (!self::is_html($value)) {
            return nl2br(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
        }

        return self::clean($value);
    }

    /** HTML for the editor to start from. */
    public static function editable($value): string
    {
        $value = (string) $value;

        if (self::is_html($value)) {
            return self::clean($value);
        }

        $html = '';

        foreach (preg_split('/\R/', $value) as $line) {
            $html .= '<p>'.($line === '' ? '<br>' : htmlspecialchars($line, ENT_QUOTES, 'UTF-8')).'</p>';
        }

        return ($value === '' ? '' : $html);
    }

    /** Text only, for reports and the assistant. */
    public static function plain($value): string
    {
        $value = (string) $value;

        if (!self::is_html($value)) {
            return trim($value);
        }

        $text = preg_replace('#<li[^>]*>#i', '- ', self::clean($value));
        $text = preg_replace('#<br\s*/?>|</p>|</li>#i', "\n", $text);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8');

        return trim(preg_replace("/\n{3,}/", "\n\n", $text));
    }

    private static function is_html(string $value): bool
    {
        return preg_match('#</?[a-z][a-z0-9]*[\s>/]#i', $value) === 1;
    }

    private static function copy(DOMNode $node, DOMNode $parent, DOMDocument $out): void
    {
        if ($node instanceof DOMText) {
            $parent->appendChild($out->createTextNode($node->nodeValue));
            return;
        }

        if (!$node instanceof DOMElement) {
            return;
        }

        $name = strtolower($node->nodeName);

        if (in_array($name, self::DROP, true)) {
            return;
        }

        $target = $parent;

        if (isset(self::TAGS[$name])) {

            $target = $out->createElement(self::TAGS[$name]);

            if ($name === 'a') {

                $href = trim($node->getAttribute('href'));

                if (!preg_match('#^(https?://|mailto:)#i', $href)) {
                    $target = $parent;
                } else {
                    $target->setAttribute('href', $href);
                    $target->setAttribute('target', '_blank');
                    $target->setAttribute('rel', 'noopener noreferrer');
                }
            }

            if ($target !== $parent) {
                $parent->appendChild($target);
            }
        }

        foreach (iterator_to_array($node->childNodes) as $child) {
            self::copy($child, $target, $out);
        }
    }
}
