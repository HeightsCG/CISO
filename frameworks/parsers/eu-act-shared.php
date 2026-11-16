<?php
/**
 * Reads articles of an EU act from the Official Journal XHTML the Publications Office
 * serves (http://publications.europa.eu/resource/celex/<number>).
 *
 * In that markup an article is <div id="art_N">, its title <div id="art_N.tit_1">, and
 * each numbered paragraph <div id="NNN.PPP">. Inside a paragraph, subparagraphs are <p>
 * elements and each lettered point is a two-cell <table>: label, then text (with nested
 * tables for sub-points). An article without numbered paragraphs has its <p> and
 * <table> elements directly under the article <div>.
 */
class EuAct {

    private DOMXPath $xpath;

    public function __construct(string $file)
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.file_get_contents(Source::path($file)));
        libxml_clear_errors();
        $this->xpath = new DOMXPath($dom);
    }

    public static function clean(string $text): string
    {
        return Source::normalise(str_replace("\u{a0}", ' ', $text));
    }

    public function title(int $article): string
    {
        $node = $this->xpath->query('//*[@id="art_'.$article.'.tit_1"]')->item(0);

        if ($node === null) {
            throw new RuntimeException('Article '.$article.' has no title');
        }

        return self::clean($node->textContent);
    }

    /**
     * Paragraphs of an article, keyed by paragraph number (0 for an article that has none).
     * Each is a list of parts in document order: ['text' => subparagraph] or
     * ['label' => '(a)', 'text' => point, with sub-points inline behind their labels].
     */
    public function paragraphs(int $article): array
    {
        $root = $this->xpath->query('//*[@id="art_'.$article.'"]')->item(0);

        if ($root === null) {
            throw new RuntimeException('Article '.$article.' not found');
        }

        $found = array();
        $prefix = sprintf('%03d.', $article);

        foreach ($this->xpath->query('./div[starts-with(@id, "'.$prefix.'")]', $root) as $div) {
            $number = (int) substr($div->getAttribute('id'), strlen($prefix));
            $parts = $this->parts($div);

            if (count($parts) === 0 || !isset($parts[0]['text']) || isset($parts[0]['label']) || !preg_match('/^'.$number.'\. (.+)$/s', $parts[0]['text'], $m)) {
                throw new RuntimeException('Article '.$article.'('.$number.') does not open with its number');
            }

            $parts[0]['text'] = $m[1];
            $found[$number] = $parts;
        }

        if (count($found) === 0) {
            $found[0] = $this->parts($root, true);
        }

        return $found;
    }

    private function parts(DOMNode $node, bool $skip_heading = false): array
    {
        $parts = array();

        foreach ($node->childNodes as $child) {
            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            if ($child->nodeName === 'table') {
                $cells = $this->xpath->query('./tbody/tr/td | ./tr/td', $child);
                $parts[] = array(
                    'label' => self::clean($cells->item(0)->textContent),
                    'text' => $this->cell($cells->item(1))
                );
                continue;
            }

            $class = $child->getAttribute('class');

            if ($skip_heading && ($class === 'oj-ti-art' || $class === 'eli-title')) {
                continue;
            }

            $text = self::clean($child->textContent);

            if ($text !== '') {
                $parts[] = array(
                    'text' => $text
                );
            }
        }

        return $parts;
    }

    private function cell(DOMNode $node): string
    {
        $pieces = array();

        foreach ($node->childNodes as $child) {
            if ($child->nodeName === 'table') {
                $cells = $this->xpath->query('./tbody/tr/td | ./tr/td', $child);
                $pieces[] = self::clean($cells->item(0)->textContent).' '.$this->cell($cells->item(1));
            } elseif ($child->nodeType === XML_ELEMENT_NODE) {
                $pieces[] = self::clean($child->textContent);
            }
        }

        return self::clean(implode(' ', array_filter($pieces, 'strlen')));
    }

    /**
     * One control per paragraph: the full text, and each lettered point as an objective
     * whose description joins it to the subparagraph that introduces it.
     */
    public static function control(string $id, array $parts): array
    {
        $text = array();
        $objectives = array();
        $lead = '';

        foreach ($parts as $part) {
            if (!isset($part['label'])) {
                $text[] = $part['text'];
                $lead = $part['text'];
                continue;
            }

            $text[] = $part['label'].' '.$part['text'];
            $objective = array(
                'id' => $id.$part['label'],
                'text' => $part['text']
            );

            if (ObjectiveText::introduces_list($lead)) {
                $objective['description'] = ObjectiveText::sentence(ObjectiveText::stem($lead).' '.ObjectiveText::item($part['text']));
            }

            foreach ($objectives as $earlier) {
                if ($earlier['id'] === $objective['id']) {
                    throw new RuntimeException($objective['id'].' appears twice: the paragraph holds two lists');
                }
            }

            $objectives[] = $objective;
        }

        return array(
            'id' => $id,
            'number' => $id,
            'text' => self::clean(implode(' ', $text)),
            'type' => 'paragraph',
            'weight' => null,
            'partial_weight' => null,
            'objectives' => $objectives
        );
    }

}
