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
 *
 * Consolidated texts (celex numbers that start with 0) use other markup: a numbered
 * paragraph is <div class="norm"> holding <span class="no-parag">1.</span> and a content
 * <div>; a point is <div class="grid-container"> with a label column and a text column.
 * Both forms give the same result here.
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

        if (count($found) > 0) {
            return $found;
        }

        foreach ($this->xpath->query('./div[contains(concat(" ", @class, " "), " norm ")][./span[@class="no-parag"]]', $root) as $div) {
            $number = (int) $this->xpath->query('./span[@class="no-parag"]', $div)->item(0)->textContent;
            $content = $this->xpath->query('./div[contains(@class, "inline-element")]', $div)->item(0);

            if ($number < 1 || $content === null || isset($found[$number])) {
                throw new RuntimeException('Article '.$article.': unreadable paragraph number');
            }

            $found[$number] = $this->consolidated($content);
        }

        if (count($found) === 0) {
            $found[0] = $this->xpath->query('./p[@class="title-article-norm"]', $root)->length > 0 ? $this->consolidated($root) : $this->parts($root, true);
        }

        return $found;
    }

    // Consolidation marks ("►C1 ... ◄") are not part of the legal text.
    private static function unmarked(string $text): string
    {
        return self::clean(preg_replace('/►[A-Z]\d+|◄/u', ' ', $text));
    }

    private function consolidated(DOMNode $node): array
    {
        $parts = array();
        $inline = '';

        // Short paragraphs hold their text directly, without a <p>; gather it up to the next block.
        $flush = function () use (&$parts, &$inline) {
            $text = self::unmarked($inline);
            $inline = '';

            if ($text !== '') {
                $parts[] = array(
                    'text' => $text
                );
            }
        };

        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $inline .= $child->textContent;
                continue;
            }

            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            $class = ' '.$child->getAttribute('class').' ';

            if (strpos($class, ' grid-container ') !== false) {
                $flush();
                $parts[] = array(
                    'label' => self::unmarked($this->xpath->query('./div[contains(@class, "grid-list-column-1")]', $child)->item(0)->textContent),
                    'text' => $this->consolidated_cell($this->xpath->query('./div[contains(@class, "grid-list-column-2")]', $child)->item(0))
                );
                continue;
            }

            if (strpos($class, ' title-article-norm ') !== false || strpos($class, ' eli-title ') !== false || strpos($class, ' modref ') !== false) {
                continue;
            }

            if ($child->nodeName === 'p' || $child->nodeName === 'div') {
                $flush();
                $inline = $child->textContent;
                $flush();
                continue;
            }

            $inline .= $child->textContent;
        }

        $flush();

        return $parts;
    }

    private function consolidated_cell(DOMNode $node): string
    {
        $pieces = array();

        foreach ($this->consolidated($node) as $part) {
            $pieces[] = isset($part['label']) ? $part['label'].' '.$part['text'] : $part['text'];
        }

        return self::clean(implode(' ', $pieces));
    }

    /**
     * An annex (<div id="anx_I">) as a list of parts. A part has a `title` (the "Part I ..."
     * heading, or '' when the annex is not divided), a `lead` (the sentence that
     * introduces its items) and `items`: ['label' => '(2)' or '1.', 'text' => the item's
     * own words, 'points' => its lettered sub-items as ['label', 'text']].
     */
    public function annex(string $id): array
    {
        $root = $this->xpath->query('//*[@id="'.$id.'"]')->item(0);

        if ($root === null) {
            throw new RuntimeException('Annex '.$id.' not found');
        }

        $parts = array();
        $current = null;

        foreach ($root->childNodes as $child) {
            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            $class = $child->getAttribute('class');

            if ($class === 'oj-doc-ti') {
                continue;
            }

            if ($class === 'oj-ti-grseq-1' || $current === null) {
                $parts[] = array(
                    'title' => $class === 'oj-ti-grseq-1' ? self::clean($child->textContent) : '',
                    'lead' => '',
                    'items' => array()
                );
                $current = count($parts) - 1;

                if ($class === 'oj-ti-grseq-1') {
                    continue;
                }
            }

            if ($child->nodeName !== 'table') {
                $parts[$current]['lead'] = trim($parts[$current]['lead'].' '.self::clean($child->textContent));
                continue;
            }

            // The label is the cell before the last; the last cell holds the text and any nested points.
            $cells = $this->xpath->query('./tbody/tr/td | ./tr/td', $child);
            $content = $cells->item($cells->length - 1);
            $own = array();
            $points = array();

            foreach ($content->childNodes as $piece) {
                if ($piece->nodeName === 'table') {
                    $inner = $this->xpath->query('./tbody/tr/td | ./tr/td', $piece);
                    $points[] = array(
                        'label' => self::clean($inner->item($inner->length - 2)->textContent),
                        'text' => $this->cell($inner->item($inner->length - 1))
                    );
                } elseif ($piece->nodeType === XML_ELEMENT_NODE || $piece->nodeType === XML_TEXT_NODE) {
                    $own[] = self::clean($piece->textContent);
                }
            }

            $parts[$current]['items'][] = array(
                'label' => self::clean($cells->item($cells->length - 2)->textContent),
                'text' => self::clean(implode(' ', array_filter($own, 'strlen'))),
                'points' => $points
            );
        }

        return $parts;
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
     * whose description joins it to the subparagraph that introduces it. A paragraph
     * with two lists has no objectives.
     */
    public static function control(string $id, array $parts): array
    {
        $text = array();
        $objectives = array();
        $lead = '';
        $two_lists = false;

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
                    $two_lists = true;
                }
            }

            $objectives[] = $objective;
        }

        // A paragraph that holds two lists would give two points the same citation: it stays one control.
        if ($two_lists) {
            $objectives = array();
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
