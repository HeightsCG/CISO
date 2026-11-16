<?php
/**
 * Reads provisions from the XML legislation.gov.uk publishes (.../data.xml, Crown
 * Legislation Markup Language).
 *
 * A provision is <P1 id="article-32"> under a <P1group> that holds its title. Numbered
 * paragraphs are <P2>, lettered points <P3>, their sub-points <P4>; each carries its
 * number in <Pnumber> and its words in <Text>. Amendments are wrapped in <Addition>,
 * <Substitution> or <Repeal> inside the text, and footnote anchors are <CommentaryRef>;
 * a repealed provision is printed as a row of dots.
 */
class UkLegislation {

    const NS = 'http://www.legislation.gov.uk/namespaces/legislation';

    private DOMXPath $xpath;

    public function __construct(string $file)
    {
        $dom = new DOMDocument();
        $dom->load(Source::path($file));
        $this->xpath = new DOMXPath($dom);
        $this->xpath->registerNamespace('l', self::NS);
    }

    // Words repealed from the middle of a provision are printed as dots; they are not part of the text.
    public static function clean(string $text): string
    {
        $text = Source::normalise(str_replace("\u{a0}", ' ', $text));
        $text = preg_replace('/(?<=\s)(?:\.\s?){3,}|…/u', ' ', ' '.$text);

        return trim(preg_replace('/\s+([,;:.)])/', '$1', preg_replace('/\s+/', ' ', $text)));
    }

    /** True for text that is only the dots printed in place of repealed words. */
    public static function repealed(string $text): bool
    {
        return preg_match('/^[.\s…]*$/u', $text) === 1;
    }

    /** Ids of every provision in document order ("article-5", "article-8A", ...). */
    public function ids(): array
    {
        $ids = array();

        foreach ($this->xpath->query('//l:P1[@id]') as $node) {
            $ids[] = $node->getAttribute('id');
        }

        return $ids;
    }

    public function title(string $id): string
    {
        $node = $this->xpath->query('//l:P1[@id="'.$id.'"]/ancestor::l:P1group[1]/l:Title')->item(0);

        if ($node === null) {
            throw new RuntimeException($id.' has no title');
        }

        return self::clean($node->textContent);
    }

    /**
     * Paragraphs of a provision keyed by paragraph number ('0' for a provision without
     * numbered paragraphs), each a list of parts in document order: ['text' => words] or
     * ['label' => '(a)', 'text' => point, with sub-points inline behind their labels].
     * Repealed paragraphs and points are left out.
     */
    public function paragraphs(string $id): array
    {
        $root = $this->xpath->query('//l:P1[@id="'.$id.'"]')->item(0);

        if ($root === null) {
            throw new RuntimeException($id.' not found');
        }

        $found = array();
        $numbered = $this->xpath->query('./l:P1para/l:P2', $root);

        foreach ($numbered as $p2) {
            $number = rtrim(self::clean($this->xpath->query('./l:Pnumber', $p2)->item(0)->textContent), '.');
            $parts = $this->parts($this->xpath->query('./l:P2para', $p2));

            if (count($parts) > 0) {
                $found[$number] = $parts;
            }
        }

        if ($numbered->length === 0) {
            $parts = $this->parts($this->xpath->query('./l:P1para', $root));

            if (count($parts) > 0) {
                $found['0'] = $parts;
            }
        }

        return $found;
    }

    private function parts(DOMNodeList $containers): array
    {
        $parts = array();

        foreach ($containers as $container) {
            foreach ($container->childNodes as $child) {
                if ($child->nodeType !== XML_ELEMENT_NODE) {
                    continue;
                }

                if ($child->localName === 'P3') {
                    $text = $this->nested($child, 'P3para');

                    if (!self::repealed($text)) {
                        $parts[] = array(
                            'label' => $this->label($child),
                            'text' => $text
                        );
                    }

                    continue;
                }

                $text = $this->words($child);

                if (!self::repealed($text)) {
                    $parts[] = array(
                        'text' => $text
                    );
                }
            }
        }

        return $parts;
    }

    private function label(DOMNode $node): string
    {
        $number = $this->xpath->query('./l:Pnumber', $node)->item(0);

        return ($number->getAttribute('PuncBefore') ?: '(').self::clean($number->textContent).($number->getAttribute('PuncAfter') ?: ')');
    }

    // A point's words with deeper points kept inline behind their labels.
    private function nested(DOMNode $node, string $para): string
    {
        $pieces = array();

        foreach ($this->xpath->query('./l:'.$para, $node) as $container) {
            foreach ($container->childNodes as $child) {
                if ($child->nodeType !== XML_ELEMENT_NODE) {
                    continue;
                }

                if (preg_match('/^P(\d)$/', $child->localName, $m)) {
                    $inner = $this->nested($child, $child->localName.'para');

                    if (!self::repealed($inner)) {
                        $pieces[] = $this->label($child).' '.$inner;
                    }
                } else {
                    $pieces[] = $this->words($child);
                }
            }
        }

        return self::clean(implode(' ', array_filter($pieces, 'strlen')));
    }

    // Text of an element without its footnote anchors.
    private function words(DOMNode $node): string
    {
        $copy = $node->cloneNode(true);
        $xpath = new DOMXPath($copy->ownerDocument);
        $xpath->registerNamespace('l', self::NS);

        foreach (iterator_to_array($xpath->query('.//l:CommentaryRef', $copy)) as $ref) {
            $ref->parentNode->removeChild($ref);
        }

        return self::clean($copy->textContent);
    }

}
