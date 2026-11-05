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
            return array_merge($blocks, $this->outstanding_blocks(false));
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
                count($refs) > 0 ? 'Provided; review not recorded' : 'Outstanding',
                count($refs) > 0 ? '' : implode('; ', $control['suggested_evidence'] ?? array())
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
                    'Evidence status',
                    'Suggested evidence (not yet provided)'
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
            $intro = 'No evidence is linked to the '.$this->a['standard'].' assessment'.$client.'. None of the '.count($applicable).' applicable '.$controls.' is supported by a document, record or file in the system, so every recorded result rests on the assessor\'s answers alone and cannot yet be confirmed by a reviewer.'
                ."\n\n".'This register is therefore limited to the evidence status and the evidence suggested for collection, by '.$this->term('family').'. A full evidence listing and traceability matrix replace it once evidence is linked.';
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
            self::h2('Evidence status'),
            self::table(array(
                'Status',
                'Recorded for this assessment'
            ), array(
                24,
                76
            ), $this->status_rows($evidence, $with, count($applicable)))
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

    /** Provided, reviewed, validated, outstanding and suggested, stating plainly what the system does not record. */
    private function status_rows(array $evidence, int $with, int $applicable): array
    {
        $controls = $this->term('controls');
        $outstanding = $applicable - $with;
        $gaps = count(array_filter($this->applicable(), function ($control) {
            return $control['evidence_count'] === 0 && $control['is_gap'];
        }));

        return array(
            array(
                'Provided',
                count($evidence) === 0 ? 'None. No evidence item is linked to any '.$this->term('control').' or '.$this->term('objective').'.' : count($evidence).' '.(count($evidence) === 1 ? 'item' : 'items').' linked, supporting '.$with.' of '.$applicable.' applicable '.$controls.'.'
            ),
            array(
                'Reviewed',
                'Not recorded. The system stores no review status or assessor comment for evidence items, so this report cannot show whether any item has been examined.'
            ),
            array(
                'Validated',
                'Not recorded. No validation outcome is stored, so no item is shown as sufficient; sufficiency requires confirmation by the assessor.'
            ),
            array(
                'Outstanding',
                $outstanding === 0 ? 'None. Every applicable '.$this->term('control').' has at least one item linked.' : $outstanding.' applicable '.($outstanding === 1 ? $this->term('control').' has' : $controls.' have').' no evidence linked'.($gaps > 0 ? ', '.$gaps.' of them not fully implemented' : '').'.'
            ),
            array(
                'Suggested for collection',
                $outstanding === 0 ? 'None needed for coverage.' : 'Examples of records that would typically demonstrate the outstanding '.$controls.' are listed under Outstanding evidence. They are suggestions only and are not evidence held.'
            )
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
                self::h1('Outstanding evidence'),
                self::text('Every applicable '.$this->term('control').' has at least one evidence item linked. The remaining step is assessor review of each item for relevance, period coverage and sufficiency.')
            );
        }

        $rows = array();

        foreach ($by_family as $family => $list) {
            $rows[] = array(
                $family,
                implode(', ', array_column($list, 'ref')),
                implode('; ', $this->family_suggestions($list)).'.'
            );
        }

        $without = array_sum(array_map('count', $by_family));

        $blocks = array(
            self::h1('Outstanding evidence'),
            self::text($without.' applicable '.$controls.' across '.count($by_family).' '.$this->term(count($by_family) === 1 ? 'family' : 'families').' have no evidence linked. For each '.$this->term('family').', the table lists those '.$controls.' and examples of evidence suggested for collection, drawn from the wording of the '.$controls.' concerned. The examples are not evidence held; the assessor should confirm what is required, the period it must cover and who will provide it.'),
            self::table(array(
                ucfirst($this->term('family')),
                ucfirst($controls).' without evidence',
                'Suggested evidence (not yet provided)'
            ), array(
                20,
                28,
                52
            ), $rows)
        );

        if ($this->detail() !== 'comprehensive' && (!$per_control || $without > count($this->applicable()) / 2)) {
            $blocks[] = self::note('Suggested evidence for each individual '.$this->term('control').' is in the Coverage sheet of the Excel version.');
            return $blocks;
        }

        $blocks[] = self::h2('Suggested evidence by '.$this->term('control'));

        foreach ($by_family as $family => $list) {
            $rows = array();
            foreach ($list as $control) {
                $rows[] = array(
                    $control['ref'],
                    $control['title'],
                    $control['result'],
                    implode('; ', $control['suggested_evidence'] ?? array()).'.'
                );
            }
            $blocks[] = self::h3($family);
            $blocks[] = self::table(array(
                'ID',
                ucfirst($this->term('control')),
                'Result',
                'Suggested evidence (not yet provided)'
            ), array(
                11,
                29,
                16,
                44
            ), $rows);
        }

        return $blocks;
    }

    /** The suggested evidence most often proposed for the controls of one family. */
    private function family_suggestions(array $controls): array
    {
        $weight = array();
        $order = 0;

        foreach ($controls as $control) {
            foreach (array_values($control['suggested_evidence'] ?? array()) as $rank => $line) {
                if (!isset($weight[$line])) {
                    $weight[$line] = array(
                        0,
                        $order++
                    );
                }
                $weight[$line][0] += 4 - min($rank, 3);
            }
        }

        uasort($weight, function ($x, $y) {
            return $y[0] <=> $x[0] ?: $x[1] <=> $y[1];
        });

        return array_slice(array_keys($weight), 0, count($controls) > 3 ? 5 : 4);
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
