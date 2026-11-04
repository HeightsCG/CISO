<?php
/**
 * Evidence Register: the evidence linked to an assessment, what each item
 * supports, and how far the applicable controls are covered. With no
 * evidence linked it becomes a short coverage report listing what is still
 * needed, family by family, instead of empty listings.
 */
class ReportEvidenceRegister extends ReportBuilder
{
    private $items = null;

    protected function title(): string
    {
        return 'Evidence Register';
    }

    protected function doc_options(): array
    {
        return array(
            'toc' => count($this->evidence()) > 0,
            'numbered' => true
        );
    }

    protected function blocks(): array
    {
        $evidence = $this->evidence();
        $blocks = $this->summary_blocks($evidence);

        if (count($evidence) === 0) {
            return array_merge($blocks, $this->why_blocks(), $this->outstanding_blocks(false));
        }

        return array_merge($blocks, $this->register_blocks($evidence), $this->trace_blocks($evidence), $this->outstanding_blocks($this->detail() !== 'executive'));
    }

    protected function sheets(): array
    {
        $evidence = $this->evidence();
        $evidence_rows = array();

        foreach ($evidence as $item) {
            $evidence_rows[] = array(
                $item['ref'],
                $item['title'],
                $item['description'],
                $item['type'],
                $item['file_name'],
                $item['date'],
                $item['uploaded_by'],
                implode(', ', $item['controls']),
                implode(', ', $item['objectives'])
            );
        }

        $coverage_rows = array();

        foreach ($this->applicable() as $control) {
            $refs = $this->refs_for_control($control, $evidence);
            $coverage_rows[] = array(
                $control['family'],
                $control['ref'],
                $control['title'],
                $control['result'],
                count($refs),
                implode(', ', $refs),
                count($refs) > 0 ? 'Evidence linked' : 'No evidence linked',
                count($refs) > 0 ? '' : $this->expected_evidence($control)
            );
        }

        return array(
            array(
                'name' => 'Evidence',
                'head' => array(
                    'Evidence ID',
                    'Title',
                    'Description',
                    'Type',
                    'File',
                    'Date added',
                    'Uploaded by',
                    'Linked '.$this->term('controls'),
                    'Linked '.$this->term('objectives')
                ),
                'widths' => array(
                    12,
                    36,
                    48,
                    18,
                    34,
                    14,
                    20,
                    30,
                    30
                ),
                'rows' => $evidence_rows
            ),
            array(
                'name' => 'Coverage',
                'head' => array(
                    ucfirst($this->term('family')),
                    'Identifier',
                    ucfirst($this->term('control')),
                    'Result',
                    'Evidence items',
                    'Evidence IDs',
                    'Coverage',
                    'Evidence that would demonstrate it'
                ),
                'widths' => array(
                    26,
                    14,
                    44,
                    20,
                    12,
                    20,
                    18,
                    70
                ),
                'rows' => $coverage_rows
            )
        );
    }

    /* ------------------------------------------------------------ sections */

    private function summary_blocks(array $evidence): array
    {
        $applicable = $this->applicable();
        $with = count(array_filter($applicable, function ($control) {
            return $control['evidence_count'] > 0;
        }));
        $objectives = $this->objectives_of($applicable);
        $objectives_with = count(array_filter($objectives, function ($objective) {
            return count($objective['evidence']) > 0;
        }));
        $controls = $this->term('controls');
        $client = $this->a['client'] !== '' ? ' for '.$this->a['client'] : '';

        $metrics = array(
            array(
                'value' => (string) count($evidence),
                'label' => 'Evidence items linked',
                'tone' => count($evidence) > 0 ? 'brand' : 'warning'
            ),
            array(
                'value' => $with.' of '.count($applicable),
                'label' => 'Applicable '.$controls.' with evidence',
                'sub' => self::share($with, count($applicable)).' coverage',
                'tone' => $with === count($applicable) ? 'positive' : ($with === 0 ? 'critical' : 'warning')
            )
        );

        if (count($objectives) > 0) {
            $metrics[] = array(
                'value' => $objectives_with.' of '.count($objectives),
                'label' => ucfirst($this->term('objectives')).' with evidence',
                'sub' => self::share($objectives_with, count($objectives)).' coverage',
                'tone' => $objectives_with === 0 ? 'critical' : 'neutral'
            );
        }

        $metrics[] = array(
            'value' => (string) (count($applicable) - $with),
            'label' => ucfirst($controls).' without evidence',
            'tone' => count($applicable) - $with > 0 ? 'warning' : 'positive'
        );

        if (count($evidence) === 0) {
            $intro = 'No evidence is linked to the '.$this->a['standard'].' assessment'.$client.'. None of the '.count($applicable).' applicable '.$controls.' is supported by a document, record or file in the system, so every result in this assessment rests on the answers recorded by the assessor alone.'
                ."\n\n".'This register therefore summarizes coverage and sets out the evidence that remains outstanding, grouped by '.$this->term('family').'. It is replaced by a full evidence listing and traceability matrix once evidence is linked.';
        } else {
            $intro = count($evidence).' evidence item'.(count($evidence) === 1 ? ' is' : 's are').' linked to the '.$this->a['standard'].' assessment'.$client.', supporting '.$with.' of the '.count($applicable).' applicable '.$controls.' ('.self::share($with, count($applicable)).')'
                .(count($objectives) > 0 ? ' and '.$objectives_with.' of '.count($objectives).' '.$this->term('objectives') : '').'.'
                ."\n\n".'The register lists each item with its metadata, traces it to the '.$controls.' and '.$this->term('objectives').' it supports, and identifies the '.$controls.' that still have no evidence.';
        }

        $blocks = array(
            self::h1('Evidence summary'),
            self::text($intro),
            array(
                'kind' => 'metrics',
                'value' => array(
                    'items' => $metrics
                )
            ),
            array(
                'kind' => 'panel',
                'value' => array(
                    'title' => 'Uploaded, reviewed and sufficient',
                    'tone' => 'neutral',
                    'body' => 'This register records evidence that has been uploaded and linked to the assessment. The system does not record a review status, a validation outcome or assessor comments for individual evidence items. A link therefore shows that an item was submitted in support of a '.$this->term('control').', not that it has been examined or judged sufficient; sufficiency requires confirmation by the assessor.'
                )
            ),
        );

        if (count($evidence) > 0) {
            $blocks[] = self::h2('Coverage by '.$this->term('family'));
            $blocks[] = array(
                'kind' => 'bars',
                'value' => array(
                    'items' => $this->family_bars(),
                    'max_label' => 'Share of applicable '.$controls.' with at least one evidence item'
                )
            );
        }

        return $blocks;
    }

    private function why_blocks(): array
    {
        $controls = $this->term('controls');

        return array(
            self::h1('Why evidence matters'),
            self::text('Evidence is what allows an assessment result to be relied on by someone other than the person who recorded it. Without it, an "Implemented" result is an assertion: a reviewer, auditor or customer cannot confirm that the '.$this->term('control').' operates as described, or that it operated throughout the period under review.'
                ."\n\n".'Linking evidence to each '.$this->term('control').' and, where the framework defines them, to individual '.$this->term('objectives').' also shortens any later audit, because the supporting material is already organized against the requirement it demonstrates. Gaps in evidence are often the first sign that a '.$this->term('control').' is informal or depends on one person.'
                ."\n\n".'Results recorded for '.count($this->applicable()).' applicable '.$controls.' should be treated as unverified until supporting evidence is linked and reviewed.')
        );
    }

    private function register_blocks(array $evidence): array
    {
        $rows = array();

        foreach ($evidence as $item) {
            $rows[] = array(
                $item['ref'],
                $item['title'].($item['description'] !== '' && $item['description'] !== $item['title'] ? '. '.$item['description'] : ''),
                $item['type'],
                $item['date'],
                $item['uploaded_by'] !== '' ? $item['uploaded_by'] : 'Not recorded',
                self::join_list(array_merge($item['controls'], $item['objectives']))
            );
        }

        return array(
            self::h1('Evidence register'),
            self::text('Each linked item is given a register identifier for reference in this report. Type is taken from the file format; date is the date the item was added to the system.'),
            self::table(array(
                'ID',
                'Title and description',
                'Type',
                'Date added',
                'Uploaded by',
                'Supports'
            ), array(
                7,
                33,
                14,
                13,
                14,
                19
            ), $rows)
        );
    }

    private function trace_blocks(array $evidence): array
    {
        $rows = array();

        foreach ($this->applicable() as $control) {
            $refs = $this->refs_for_control($control, $evidence);
            if (count($refs) === 0) {
                continue;
            }
            $covered = array();
            foreach ($control['objectives'] as $objective) {
                if (count($objective['evidence']) > 0) {
                    $covered[] = $objective['short'];
                }
            }
            $rows[] = array(
                $control['ref'],
                $control['title'],
                $control['result'],
                implode(', ', $refs),
                count($control['objectives']) > 0 ? count($covered).' of '.count($control['objectives']).(count($covered) > 0 ? ' ('.implode(', ', $covered).')' : '') : 'None defined'
            );
        }

        $has_objectives = count($this->objectives_of($this->applicable())) > 0;
        $head = array(
            'ID',
            ucfirst($this->term('control')),
            'Result',
            'Evidence'
        );
        $widths = array(
            11,
            40,
            17,
            14
        );

        if ($has_objectives) {
            $head[] = ucfirst($this->term('objectives')).' evidenced';
            $widths[] = 18;
        } else {
            $rows = array_map(function ($row) {
                return array_slice($row, 0, 4);
            }, $rows);
            $widths[1] = 58;
        }

        return array(
            self::h1('Traceability'),
            self::text(count($rows).' applicable '.$this->term(count($rows) === 1 ? 'control' : 'controls').' '.(count($rows) === 1 ? 'has' : 'have').' at least one evidence item linked, either directly or through '.(count($rows) === 1 ? 'its' : 'their').' '.$this->term('objectives').'. The result shown is the assessor\'s recorded result; the presence of evidence does not change it.'),
            self::table($head, $widths, $rows)
        );
    }

    private function outstanding_blocks(bool $per_control): array
    {
        $by_family = array();

        foreach ($this->applicable() as $control) {
            if ($control['evidence_count'] === 0) {
                $by_family[$control['family']][] = $control;
            }
        }

        $controls = $this->term('controls');

        if (count($by_family) === 0) {
            return array(
                self::h1('Outstanding evidence requirements'),
                self::text('Every applicable '.$this->term('control').' has at least one evidence item linked. The remaining step is assessor review of each item for relevance, period coverage and sufficiency.')
            );
        }

        $rows = array();

        foreach ($by_family as $family => $list) {
            $rows[] = array(
                $family,
                count($list).': '.implode(', ', array_column($list, 'ref')),
                implode('; ', $this->typical_deliverables($list))
            );
        }

        $blocks = array(
            self::h1('Outstanding evidence requirements'),
            self::text(array_sum(array_map('count', $by_family)).' applicable '.$controls.' across '.count($by_family).' '.$this->term(count($by_family) === 1 ? 'family' : 'families').' have no evidence linked. The table lists, for each '.$this->term('family').', the '.$controls.' concerned and the kinds of evidence that would typically demonstrate them.'),
            self::table(array(
                ucfirst($this->term('family')),
                ucfirst($controls).' without evidence',
                'Evidence that would typically demonstrate them'
            ), array(
                22,
                30,
                48
            ), $rows),
            self::note('The evidence types are derived from the subject matter of each '.$this->term('control').' and are indicative. The assessor should confirm what is required, the period it must cover and who will provide it.')
        );

        $without = array_sum(array_map('count', $by_family));

        if ($this->detail() !== 'comprehensive' && (!$per_control || $without > count($this->applicable()) / 2)) {
            $blocks[count($blocks) - 1] = self::note('The evidence types are derived from the subject matter of each '.$this->term('control').' and are indicative; the assessor should confirm what is required, the period it must cover and who will provide it.'.($per_control ? ' The full '.$this->term('control').' list is in the Coverage sheet of the Excel version.' : ''));
            return $blocks;
        }

        $blocks[] = self::h2(ucfirst($controls).' without evidence');

        foreach ($by_family as $family => $list) {
            $rows = array();
            foreach ($list as $control) {
                $rows[] = array(
                    $control['ref'],
                    $control['title'],
                    $control['result'],
                    $this->expected_evidence($control)
                );
            }
            $blocks[] = self::h3($family);
            $blocks[] = self::table(array(
                'ID',
                ucfirst($this->term('control')),
                'Result',
                'Evidence that would demonstrate it'
            ), array(
                11,
                27,
                16,
                46
            ), $rows);
        }

        return $blocks;
    }

    /* ------------------------------------------------------------- helpers */

    /** Evidence items linked to this assessment, numbered E-01..., with what each supports. */
    private function evidence(): array
    {
        if ($this->items !== null) {
            return $this->items;
        }

        $owner = array();

        foreach ($this->a['controls'] as $control) {
            $owner[$control['id']] = array(
                'control',
                $control['ref']
            );
            foreach ($control['objectives'] as $objective) {
                $owner[$objective['id']] = array(
                    'objective',
                    $objective['ref']
                );
            }
        }

        $rows = (new ReadyReportsModel())->evidence_by_item((int) $this->a['assessment']['id'], $this->company_id);
        $items = array();

        foreach ($rows as $row) {
            $id = (int) $row['evidence_id'];
            if (!isset($items[$id])) {
                $items[$id] = array(
                    'id' => $id,
                    'title' => ReportAnalysis::plain($row['evidence_title']),
                    'description' => ReportAnalysis::plain($row['description']),
                    'file_name' => ReportAnalysis::plain($row['file_name']),
                    'type' => self::file_type((string) $row['file_name'], (string) $row['file_type']),
                    'date' => empty($row['date_created']) ? '' : date('M j, Y', strtotime($row['date_created'])),
                    'sort' => (string) $row['date_created'],
                    'uploaded_by' => ReportAnalysis::plain($row['uploaded_by_name'] ?? ''),
                    'controls' => array(),
                    'objectives' => array()
                );
            }
            $link = $owner[(int) $row['assessment_item_id']] ?? null;
            if ($link !== null) {
                $items[$id][$link[0] === 'control' ? 'controls' : 'objectives'][] = $link[1];
            }
        }

        $items = array_values($items);
        usort($items, function ($x, $y) {
            return strcmp($x['sort'], $y['sort']) ?: strcasecmp($x['title'], $y['title']);
        });

        foreach ($items as $index => $item) {
            $items[$index]['ref'] = sprintf('E-%02d', $index + 1);
            $items[$index]['controls'] = array_values(array_unique($item['controls']));
            $items[$index]['objectives'] = array_values(array_unique($item['objectives']));
        }

        $this->items = $items;

        return $items;
    }

    private function applicable(): array
    {
        return array_values(array_filter($this->a['controls'], function ($control) {
            return $control['applicability'] !== 'Excluded' && $control['result'] !== 'Not Applicable';
        }));
    }

    private function objectives_of(array $controls): array
    {
        $out = array();

        foreach ($controls as $control) {
            foreach ($control['objectives'] as $objective) {
                $out[] = $objective;
            }
        }

        return $out;
    }

    /** Register ids of the evidence linked to a control or any of its objectives. */
    private function refs_for_control(array $control, array $evidence): array
    {
        $own = array(
            $control['ref']
        );

        foreach ($control['objectives'] as $objective) {
            $own[] = $objective['ref'];
        }

        $refs = array();

        foreach ($evidence as $item) {
            if (count(array_intersect($own, array_merge($item['controls'], $item['objectives']))) > 0) {
                $refs[] = $item['ref'];
            }
        }

        return $refs;
    }

    private function family_bars(): array
    {
        $families = array();

        foreach ($this->applicable() as $control) {
            if (!isset($families[$control['family']])) {
                $families[$control['family']] = array(
                    0,
                    0
                );
            }
            $families[$control['family']][1]++;
            if ($control['evidence_count'] > 0) {
                $families[$control['family']][0]++;
            }
        }

        $bars = array();

        foreach ($families as $name => $pair) {
            $bars[] = array(
                'label' => $name,
                'value' => $pair[0],
                'max' => $pair[1],
                'text' => $pair[0].' of '.$pair[1],
                'tone' => $pair[0] === $pair[1] ? 'positive' : ($pair[0] === 0 ? 'critical' : 'warning')
            );
        }

        return $bars;
    }

    /** Typical evidence for a group of controls, from the topic knowledge layer. */
    private function typical_deliverables(array $controls): array
    {
        $weight = array();

        foreach ($controls as $control) {
            $weight[$control['topic']] = ($weight[$control['topic']] ?? 0) + 1;
        }

        arsort($weight);
        $out = array();

        foreach (array_keys($weight) as $key) {
            $topic = ReportKnowledge::topic($key);
            foreach (array_slice($topic['deliverables'] ?? array(), 0, 2) as $deliverable) {
                if (!in_array($deliverable, $out, true)) {
                    $out[] = $deliverable;
                }
            }
            if (count($out) >= 5) {
                break;
            }
        }

        return array_slice($out, 0, 5);
    }

    /** What would demonstrate one control: its expectation topics, else its objectives, else typical artifacts. */
    private function expected_evidence(array $control): string
    {
        $topics = array();

        foreach ($control['expectations'] as $expectation) {
            if ($expectation[0] !== '' && !preg_match('/^ex\s*\d+$/i', $expectation[0])) {
                $topics[] = $expectation[0];
            }
        }

        if (count($topics) > 0) {
            return 'Records showing: '.strtolower(implode('; ', $topics)).'.';
        }

        if (count($control['objectives']) > 0) {
            $titles = array_map(function ($objective) {
                return rtrim($objective['title'], '.');
            }, array_slice($control['objectives'], 0, 4));
            return 'Evidence for each '.$this->term('objective').', e.g. '.implode('; ', $titles).(count($control['objectives']) > 4 ? '; and '.(count($control['objectives']) - 4).' more' : '').'.';
        }

        if (count($control['expectations']) > 0) {
            $text = rtrim($control['expectations'][0][1], '.');
            return 'Records showing that the organization does the following: '.(strlen($text) > 220 ? substr($text, 0, strrpos(substr($text, 0, 220), ' ')).'...' : $text).'.';
        }

        return implode('; ', $this->typical_deliverables(array(
            $control
        ))).'.';
    }

    private static function share(int $part, int $whole): string
    {
        if ($part > 0 && $whole > 0 && round(100 * $part / $whole) < 1) {
            return 'under 1%';
        }

        return self::pct($part, $whole);
    }

    private static function file_type(string $file_name, string $mime): string
    {
        $extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $names = array(
            'pdf' => 'PDF document',
            'doc' => 'Word document',
            'docx' => 'Word document',
            'odt' => 'Text document',
            'rtf' => 'Text document',
            'txt' => 'Text file',
            'md' => 'Text file',
            'xls' => 'Spreadsheet',
            'xlsx' => 'Spreadsheet',
            'ods' => 'Spreadsheet',
            'csv' => 'Spreadsheet (CSV)',
            'ppt' => 'Presentation',
            'pptx' => 'Presentation',
            'png' => 'Image',
            'jpg' => 'Image',
            'jpeg' => 'Image',
            'gif' => 'Image',
            'webp' => 'Image',
            'heic' => 'Image',
            'svg' => 'Image',
            'zip' => 'Archive',
            'json' => 'Data file',
            'xml' => 'Data file',
            'log' => 'Log file',
            'eml' => 'Email',
            'msg' => 'Email',
            'mp4' => 'Video',
            'mov' => 'Video'
        );

        if (isset($names[$extension])) {
            return $names[$extension];
        }

        if (strpos($mime, 'image/') === 0) {
            return 'Image';
        }

        if (strpos($mime, 'video/') === 0) {
            return 'Video';
        }

        return $extension !== '' ? strtoupper($extension).' file' : 'File';
    }
}
