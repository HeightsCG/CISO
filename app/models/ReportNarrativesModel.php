<?php
/**
 * AI-written report narratives, one row per assessment and report section.
 * Every query is scoped to the company. A section is written once and then
 * regenerated in place, so the row id stays stable across regenerations.
 */
class ReportNarrativesModel extends Model {

    public function __construct(){
        parent::__construct();
    }

    public function load_narratives($assessment_id, $company_id)
    {
        $where = array(
            'assessment_id' => $assessment_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    id,
                    section_key,
                    input_hash,
                    content,
                    edited_content,
                    rejected_fields,
                    revision_notes,
                    narrative_status,
                    model,
                    input_tokens,
                    output_tokens,
                    generated_by,
                    edited_by,
                    reviewed_by,
                    date_created,
                    date_updated
                FROM
                    report_narratives
                WHERE
                    assessment_id = :assessment_id
                    and
                    company_id = :company_id
                    and
                    deleted = 0
                ORDER BY
                    section_key";
        return parent::select($sql, $where);
    }

    /** The row for one section, deleted or not, so a regeneration reuses it. */
    public function get_any_narrative($assessment_id, $company_id, $section_key)
    {
        $where = array(
            'assessment_id' => $assessment_id,
            'company_id' => $company_id,
            'section_key' => $section_key
        );
        $sql = "SELECT
                    id,
                    deleted
                FROM
                    report_narratives
                WHERE
                    assessment_id = :assessment_id
                    and
                    company_id = :company_id
                    and
                    section_key = :section_key";
        return parent::select($sql, $where);
    }

    /** A freshly generated section replaces any earlier text, edit and review. */
    public function save_generated(
        $company_id,
        $assessment_id,
        $section_key,
        $input_hash,
        $content,
        $rejected_fields,
        $revision_notes,
        $model,
        $input_tokens,
        $output_tokens,
        $generated_by
    )
    {
        $data = array(
            'input_hash' => $input_hash,
            'content' => $content,
            'edited_content' => null,
            'rejected_fields' => $rejected_fields,
            'revision_notes' => $revision_notes,
            'narrative_status' => 'Draft',
            'model' => $model,
            'input_tokens' => $input_tokens,
            'output_tokens' => $output_tokens,
            'generated_by' => $generated_by,
            'edited_by' => null,
            'reviewed_by' => null,
            'date_updated' => date('Y-m-d H:i:s'),
            'deleted' => 0
        );

        $existing = $this->get_any_narrative($assessment_id, $company_id, $section_key);

        if (count($existing) === 1) {
            $where = array(
                'id' => $existing[0]['id'],
                'company_id' => $company_id
            );
            parent::update('report_narratives', $data, 'id = :id and company_id = :company_id', $where);
            return $existing[0]['id'];
        }

        $data['company_id'] = $company_id;
        $data['assessment_id'] = $assessment_id;
        $data['section_key'] = $section_key;
        $data['date_created'] = date('Y-m-d H:i:s');

        return parent::insert('report_narratives', $data);
    }

    /** An assessor's edit is kept beside the generated text and goes back to Draft for review. */
    public function save_edit($assessment_id, $company_id, $section_key, $edited_content, $edited_by)
    {
        $where = array(
            'assessment_id' => $assessment_id,
            'company_id' => $company_id,
            'section_key' => $section_key
        );
        $data = array(
            'edited_content' => $edited_content,
            'edited_by' => $edited_by,
            'narrative_status' => 'Draft',
            'reviewed_by' => null,
            'date_updated' => date('Y-m-d H:i:s')
        );
        return parent::update('report_narratives', $data, 'assessment_id = :assessment_id and company_id = :company_id and section_key = :section_key and deleted = 0', $where);
    }

    public function set_narrative_status($assessment_id, $company_id, $section_key, $narrative_status, $reviewed_by)
    {
        $where = array(
            'assessment_id' => $assessment_id,
            'company_id' => $company_id,
            'section_key' => $section_key
        );
        $data = array(
            'narrative_status' => $narrative_status,
            'reviewed_by' => $reviewed_by,
            'date_updated' => date('Y-m-d H:i:s')
        );
        return parent::update('report_narratives', $data, 'assessment_id = :assessment_id and company_id = :company_id and section_key = :section_key and deleted = 0', $where);
    }
}
