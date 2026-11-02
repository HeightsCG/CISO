<?php
/**
 * Checks shared by both EASA Part-IS variants. Each check reads the source
 * snapshots its own way (raw markup, table of contents) rather than through
 * the parser, so a parser mistake shows up as a mismatch.
 */
return function (string $key, string $letter, string $regulation_file, array $expected_points, string $toc_start): array {
    $prefix = 'IS.'.$letter.'.OR.';
    $quoted = preg_quote($prefix, '/');

    $definition = function () use ($key) {
        return json_decode(file_get_contents(ROOT.'/frameworks/'.$key.'.json'), true);
    };

    $controls = function () use ($definition) {
        $all = array();

        foreach ($definition()['families'] as $family) {
            foreach ($family['controls'] as $control) {
                $all[$control['number']] = $control;
            }
        }

        return $all;
    };

    $rules = function () use ($key) {
        $all = array();

        foreach (FrameworkBuilder::meta($key)['rules'] as $rule) {
            $all[$rule['id']] = $rule;
        }

        return $all;
    };

    // Raw markup of each requirement, split on its bold heading; the last one ends at the next separator or annex title.
    $segments = function () use ($regulation_file, $quoted) {
        $html = file_get_contents(Source::path($regulation_file));
        $parts = preg_split('/<span class="boldface">\s*'.$quoted.'(\d{3})\b/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        $out = array();

        for ($i = 1; $i < count($parts); $i += 2) {
            $out[$parts[$i]] = $parts[$i + 1];
        }

        $last = array_key_last($out);
        $cut = strlen($out[$last]);

        $markers = array(
            '<hr',
            'class="title-annex-1"',
            'class="footnote"'
        );

        foreach ($markers as $marker) {
            $at = strpos($out[$last], $marker);

            if ($at !== false && $at < $cut) {
                $cut = $at;
            }
        }

        $out[$last] = substr($out[$last], 0, $cut);

        return $out;
    };

    return array(
        'fourteen requirements match the annex table of contents' => function () use ($regulation_file, $quoted, $controls) {
            $html = file_get_contents(Source::path($regulation_file));
            preg_match_all('/<p class="toc-item">('.$quoted.'\d{3})<\/p>\s*<\/td>\s*<td[^>]*>\s*<p class="toc-item">([^<]+)<\/p>/', $html, $m, PREG_SET_ORDER);
            $toc = array();

            foreach ($m as $row) {
                $toc[$row[1]] = Source::normalise(html_entity_decode($row[2], ENT_QUOTES | ENT_XML1, 'UTF-8'));
            }

            $built = array();

            foreach ($controls() as $control) {
                $built[$control['id']] = $control['title'];
            }

            ksort($built);
            Assert::same(14, count($toc), 'requirements in the table of contents');
            Assert::same(array_keys($toc), array_keys($built), 'requirement IDs');

            // Known differences between the contents table and the headings in the text, which are used as titles:
            // the table writes "detection, response, and recovery", and in 2022/1645 omits "(ISMS)" from point 200.
            $differences = array(
                'response, and' => 'response and',
                'Information security management system' => 'Information security management system (ISMS)'
            );

            foreach ($toc as $id => $title) {
                if ($title !== $built[$id]) {
                    Assert::same(strtr($title, $differences), $built[$id], $id.' title');
                }
            }
        },

        'requirements are grouped as documented' => function () use ($definition, $prefix) {
            $expected = array(
                'Management system' => array(
                    '100',
                    '200',
                    '250',
                    '255',
                    '260'
                ),
                'Risk assessment and treatment' => array(
                    '205',
                    '210'
                ),
                'Incidents, reporting and findings' => array(
                    '215',
                    '220',
                    '225',
                    '230'
                ),
                'Contracting, personnel and records' => array(
                    '235',
                    '240',
                    '245'
                )
            );
            $actual = array();

            foreach ($definition()['families'] as $family) {
                foreach ($family['controls'] as $control) {
                    $actual[$family['name']][] = $control['number'];
                    Assert::same($prefix.$control['number'], $control['id'], 'control id');
                }
            }

            Assert::same($expected, $actual, 'groups');
        },

        'points per requirement match a count of point labels in the source markup' => function () use ($controls, $segments, $expected_points) {
            $actual = array();
            $raw = array();

            foreach ($controls() as $number => $control) {
                $actual[$number] = count($control['objectives']);
            }

            foreach ($segments() as $number => $segment) {
                preg_match_all('/<span>\(([a-z0-9]+)\)[\s\x{00A0}]*<\/span>/u', $segment, $m);
                $raw[$number] = max(1, count($m[1]));
            }

            ksort($actual);
            ksort($raw);
            ksort($expected_points);
            Assert::same($expected_points, $actual, 'points per requirement (built)');
            Assert::same($expected_points, $raw, 'point labels per requirement (source markup)');
        },

        'point IDs are well formed and their text is in the source' => function () use ($controls, $regulation_file, $quoted) {
            $text = Source::normalise(html_entity_decode(strip_tags(file_get_contents(Source::path($regulation_file))), ENT_QUOTES | ENT_XML1, 'UTF-8'));
            $seen = array();

            foreach ($controls() as $control) {
                foreach ($control['objectives'] as $objective) {
                    Assert::true(preg_match('/^'.$quoted.'\d{3}(\([a-z0-9]+\))*$/', $objective['id']) === 1, 'malformed point id '.$objective['id']);
                    Assert::true(strpos($objective['id'], $control['id']) === 0, $objective['id'].' is not under '.$control['id']);
                    Assert::true(!isset($seen[$objective['id']]), 'repeated point '.$objective['id']);
                    $seen[$objective['id']] = true;
                    $start = mb_substr($objective['text'], 0, 80);
                    Assert::true(strpos($text, $start) !== false, $objective['id'].' text not found in source: '.$start);
                }
            }
        },

        'guidance holds every AMC and GM item listed in the Easy Access Rules contents' => function () use ($controls, $quoted, $toc_start) {
            $lines = preg_split('/\R/', Source::pdf_text('easa/ear-information-security-2025-12.pdf'));
            $listed = array();
            $in = false;

            foreach ($lines as $line) {
                if (strpos($line, $toc_start) === 0 && strpos($line, '....') !== false) {
                    $in = true;
                    continue;
                }

                if ($in && strpos($line, 'Appendix I ') === 0) {
                    break;
                }

                if ($in && preg_match('/^((AMC|GM)\d+ '.$quoted.'\s?\d{3}\S*)/', $line, $m)) {
                    $listed[] = str_replace(' ', '', $m[1]);
                }
            }

            $found = array();

            foreach ($controls() as $number => $control) {
                preg_match_all('/^((AMC|GM)\d+ '.$quoted.'\s?(\d{3})\S*) .*\(ED Decision \d{4}\/\d{3}\/R/m', $control['guidance'], $m, PREG_SET_ORDER);

                foreach ($m as $item) {
                    Assert::same((string) $number, $item[3], $item[1].' filed under the wrong requirement');
                    $found[] = str_replace(' ', '', $item[1]);
                }
            }

            Assert::true(count($listed) > 80, 'too few AMC/GM items read from the contents ('.count($listed).')');
            sort($listed);
            sort($found);
            Assert::same($listed, $found, 'AMC/GM items');
        },

        'reporting and retention rules match the requirement text' => function () use ($rules, $controls, $prefix) {
            $r = $rules();
            $c = $controls();
            $points = array();

            foreach ($c as $control) {
                foreach ($control['objectives'] as $objective) {
                    $points[$objective['id']] = $objective['text'];
                }
            }

            Assert::same(72, $r['incident_report_72h']['hours'], 'report deadline hours');
            Assert::true(strpos($points[$prefix.'230(c)(2)'], 'not exceeding 72 hours') !== false, '230(c)(2) says 72 hours');
            Assert::same(5, $r['retention_approval_records']['years'], 'approval record retention');
            Assert::true(strpos($points[$prefix.'245(a)(2)'], '5 years after the approval') !== false, '245(a)(2) says 5 years');
            Assert::same(5, $r['retention_contracts']['years'], 'contract retention');
            Assert::true(strpos($points[$prefix.'245(a)(3)'], '5 years after the contract') !== false, '245(a)(3) says 5 years');
            Assert::same(5, $r['retention_isms_records']['years'], 'ISMS record retention');
            Assert::true(strpos($points[$prefix.'245(a)(4)'], 'period of 5 years') !== false, '245(a)(4) says 5 years');
            Assert::same(3, $r['retention_staff_records']['years'], 'staff record retention');
            Assert::true(strpos($points[$prefix.'245(b)(1)'], 'at least 3 years after the person has left') !== false, '245(b)(1) says 3 years');
            Assert::true(strpos($points[$prefix.'225(a)(2)'], 'corrective action plan') !== false, '225(a)(2) corrective action plan');

            foreach ($r as $rule) {
                if (isset($rule['control'])) {
                    Assert::true(isset($c[substr($rule['control'], -3)]), $rule['id'].' names an unknown requirement');
                }
            }
        },

        'effective dates match the dates written in their quotes' => function () use ($rules) {
            foreach ($rules() as $rule) {
                if ($rule['type'] !== 'effective_date') {
                    continue;
                }

                Assert::true(preg_match('/(\d{1,2} [A-Z][a-z]+ \d{4})/', $rule['quote'], $m) === 1, $rule['id'].' quote has no date');
                Assert::same($rule['date'], DateTime::createFromFormat('!j F Y', $m[1])->format('Y-m-d'), $rule['id'].' date');
            }
        }
    );
};
