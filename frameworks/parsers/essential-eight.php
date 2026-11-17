<?php
/**
 * ASD Essential Eight Maturity Model (November 2023). Appendices A, B and C list the full set
 * of requirements for Maturity Level One, Two and Three, each grouped by the eight mitigation
 * strategies; each level's list is complete in itself.
 *
 * - Families: the eight mitigation strategies, in the publication's order.
 * - Controls: one per strategy per maturity level (E8-<strategy>-ML1..ML3).
 * - Objectives: that level's requirements for the strategy, word for word.
 *
 * Read from the committed pdftotext -layout extract: a strategy's name sits in the left
 * column (it may wrap), requirements in the right column separated by blank lines. A
 * requirement broken by a page footer is rejoined.
 */
return function (): array {
    $lines = explode("\n", str_replace(array("\r", "\f"), '', file_get_contents(Source::path('essential-eight/essential-eight-maturity-model-november-2023.layout.txt'))));
    $strategies = array(
        'PA' => 'Patch applications',
        'PO' => 'Patch operating systems',
        'MF' => 'Multi-factor authentication',
        'RA' => 'Restrict administrative privileges',
        'AC' => 'Application control',
        'RM' => 'Restrict Microsoft Office macros',
        'UH' => 'User application hardening',
        'RB' => 'Regular backups'
    );
    $levels = array(
        'ML1' => array('Appendix A: Maturity Level One', 'Maturity Level One'),
        'ML2' => array('Appendix B: Maturity Level Two', 'Maturity Level Two'),
        'ML3' => array('Appendix C: Maturity Level Three', 'Maturity Level Three')
    );
    $ends = array(
        'ML1' => 'Appendix B: Maturity Level Two',
        'ML2' => 'Appendix C: Maturity Level Three',
        'ML3' => 'Appendix D: Comparison of maturity levels'
    );

    $find = function (string $heading, int $from = 0) use ($lines): int {
        for ($i = $from; $i < count($lines); $i++) {
            if (trim($lines[$i]) === $heading) {
                $last = $i;
            }
        }

        if (!isset($last)) {
            throw new RuntimeException('Essential Eight: "'.$heading.'" not found');
        }

        return $last;
    };

    $requirements = array();

    foreach ($levels as $level => $level_info) {
        $start = $find($level_info[0]);
        $end = $find($ends[$level]);
        $split = null;
        $strategy_name = '';
        $current = null;
        $block = '';
        $flush = function () use (&$block, &$current, &$requirements, $level) {
            $block = trim(preg_replace('/\s+/', ' ', $block));

            if ($block !== '' && $current !== null) {
                $requirements[$current][$level][] = $block;
            }

            $block = '';
        };

        for ($i = $start + 1; $i < $end; $i++) {
            $line = $lines[$i];

            if (preg_match('/^\s*Mitigation Strategy\s+Description\s*$/', $line)) {
                $split = strpos($line, 'Description');
                continue;
            }

            // Page footer: a requirement cut by it continues on the next page.
            if (preg_match('/^Essential Eight maturity model\s+\d+\s*$/', $line)) {
                continue;
            }

            if ($split === null) {
                continue;
            }

            if (trim($line) === '') {
                // A blank line ends a requirement only when it ends a sentence.
                if (preg_match('/\.$/', trim($block))) {
                    $flush();
                }

                continue;
            }

            $left = trim(substr($line, 0, $split - 1));
            $right = trim((string) substr($line, $split - 1));

            if ($left !== '') {
                $joined = trim($strategy_name.' '.$left);
                $continues = $strategy_name !== '' && !in_array($strategy_name, $strategies, true) && count(array_filter($strategies, function ($s) use ($joined) {
                    return strpos($s, $joined) === 0;
                })) > 0;

                if ($continues) {
                    $strategy_name = $joined;
                } else {
                    // A new strategy's name starts: the previous requirement is complete.
                    $flush();
                    $strategy_name = $left;
                }

                $code = array_search($strategy_name, $strategies, true);

                if ($code !== false) {
                    $current = $code;
                }
            }

            // A line with only the strategy name's continuation also closes a finished requirement.
            if ($right === '') {
                if (preg_match('/\.$/', trim($block))) {
                    $flush();
                }

                continue;
            }

            $block = preg_match('/[a-z]-$/', $block) ? $block.$right : $block.' '.$right;
        }

        $flush();
    }

    $families = array();

    foreach ($strategies as $code => $name) {
        $controls = array();

        foreach ($levels as $level => $level_info) {
            if (empty($requirements[$code][$level])) {
                throw new RuntimeException('Essential Eight: no '.$level.' requirements for '.$name);
            }

            $id = 'E8-'.$code.'-'.$level;
            $objectives = array();

            foreach ($requirements[$code][$level] as $n => $text) {
                $objectives[] = array(
                    'id' => $id.'.'.($n + 1),
                    'text' => $text,
                    'source' => 'e8'
                );
            }

            $controls[] = array(
                'id' => $id,
                'number' => $id,
                'title' => $name.': '.$level_info[1],
                'text' => '',
                'description' => 'The '.count($objectives).' requirements for '.$name.' at '.$level_info[1].' (Appendix '.substr($level_info[0], 9, 1).'). Assess only the level the organisation is targeting.',
                'type' => strtolower($level),
                'weight' => null,
                'partial_weight' => null,
                'objectives' => $objectives,
                'source' => 'e8'
            );
        }

        $families[] = array(
            'number' => $code,
            'code' => $code,
            'name' => $name,
            'controls' => $controls
        );
    }

    return $families;
};
