<?php
class DashboardModel extends Model {

    public function __construct(){
        parent::__construct();
    }

    /**
     * Headline counts. Native prepares are on, so a placeholder cannot be reused
     * inside one statement and each subquery binds its own copy of company_id.
     */
    public function totals($company_id)
    {
        $where = array(
            'c1' => $company_id,
            'c2' => $company_id,
            'c3' => $company_id,
            'c4' => $company_id,
            'c5' => $company_id,
            'c6' => $company_id,
            'c7' => $company_id
        );
        $sql = "SELECT
                    (SELECT COUNT(*) FROM clients c
                        WHERE c.company_id = :c1 and c.deleted = 0) AS client_count,
                    (SELECT COUNT(*) FROM projects p
                        WHERE p.company_id = :c2 and p.deleted = 0) AS project_count,
                    (SELECT COUNT(*) FROM projects p
                        WHERE p.company_id = :c3 and p.deleted = 0
                        and p.project_status <> 'Complete') AS open_project_count,
                    (SELECT COUNT(*) FROM assessments a
                        WHERE a.company_id = :c4 and a.deleted = 0) AS assessment_count,
                    (SELECT COUNT(*) FROM evidence e
                        WHERE e.company_id = :c5 and e.deleted = 0) AS evidence_count,
                    (SELECT COUNT(*) FROM evidence e
                        WHERE e.company_id = :c6 and e.deleted = 0
                        and e.evidence_private = 1) AS private_count,
                    (SELECT COUNT(*) FROM standards s
                        WHERE s.company_id = :c7 and s.deleted = 0) AS standard_count";
        return parent::select($sql, $where);
    }

    /** Every control in the organisation, grouped by the result it was given. */
    public function result_totals($company_id)
    {
        $where = array(
            'company_id' => $company_id
        );
        $sql = "SELECT
                    ai.item_result,
                    COUNT(*) AS item_count
                FROM
                    assessment_items ai
                    JOIN assessments a ON a.id = ai.assessment_id
                WHERE
                    a.company_id = :company_id
                    and
                    a.deleted = 0
                    and
                    ai.deleted = 0
                GROUP BY
                    ai.item_result";
        return parent::select($sql, $where);
    }

    public function assessment_status($company_id)
    {
        $where = array(
            'company_id' => $company_id
        );
        $sql = "SELECT
                    a.assessment_status,
                    COUNT(*) AS assessment_count
                FROM
                    assessments a
                WHERE
                    a.company_id = :company_id
                    and
                    a.deleted = 0
                GROUP BY
                    a.assessment_status";
        return parent::select($sql, $where);
    }

    /**
     * Every assessment in flight, with how far the assessing has got and how many
     * gaps it has turned up. Ordered least-finished first, so the work still to do
     * is at the top of the list rather than buried under finished assessments.
     */
    public function assessments($company_id)
    {
        $where = array(
            'company_id' => $company_id
        );
        $sql = "SELECT
                    a.id,
                    a.project_id,
                    a.assessment_name,
                    a.short_code,
                    a.assessment_status,
                    p.project_name,
                    cl.company_name AS client_name,
                    COUNT(ai.id) AS item_count,
                    SUM(CASE WHEN ai.item_result <> 'Not Assessed' THEN 1 ELSE 0 END) AS assessed_count,
                    SUM(CASE WHEN ai.item_result = 'Not Implemented' THEN 1 ELSE 0 END) AS gap_count
                FROM
                    assessments a
                    JOIN projects p ON p.id = a.project_id and p.deleted = 0
                    LEFT JOIN clients cl ON cl.id = p.client_id and cl.deleted = 0
                    LEFT JOIN assessment_items ai ON ai.assessment_id = a.id and ai.deleted = 0
                WHERE
                    a.company_id = :company_id
                    and
                    a.deleted = 0
                GROUP BY
                    a.id,
                    a.project_id,
                    a.assessment_name,
                    a.short_code,
                    a.assessment_status,
                    p.project_name,
                    cl.company_name
                ORDER BY
                    assessed_count / GREATEST(COUNT(ai.id), 1),
                    a.assessment_name";
        return parent::select($sql, $where);
    }

    /**
     * Per client. The evidence count is a subquery rather than another join, or it
     * would multiply against the assessment item rows.
     */
    public function by_client($company_id)
    {
        $where = array(
            'company_id' => $company_id,
            'company_id2' => $company_id
        );
        $sql = "SELECT
                    c.id,
                    c.company_name,
                    COUNT(DISTINCT p.id) AS project_count,
                    COUNT(DISTINCT a.id) AS assessment_count,
                    COUNT(ai.id) AS item_count,
                    SUM(CASE WHEN ai.item_result <> 'Not Assessed' THEN 1 ELSE 0 END) AS assessed_count,
                    SUM(CASE WHEN ai.item_result = 'Not Implemented' THEN 1 ELSE 0 END) AS gap_count,
                    (SELECT COUNT(*)
                        FROM evidence e
                        JOIN projects ep ON ep.id = e.project_id
                        WHERE ep.client_id = c.id
                        and e.company_id = :company_id2
                        and e.deleted = 0
                        and ep.deleted = 0) AS evidence_count
                FROM
                    clients c
                    LEFT JOIN projects p ON p.client_id = c.id and p.deleted = 0
                    LEFT JOIN assessments a ON a.project_id = p.id and a.deleted = 0
                    LEFT JOIN assessment_items ai ON ai.assessment_id = a.id and ai.deleted = 0
                WHERE
                    c.company_id = :company_id
                    and
                    c.deleted = 0
                GROUP BY
                    c.id,
                    c.company_name
                ORDER BY
                    c.company_name";
        return parent::select($sql, $where);
    }

    /** Open projects already past their end date, or reaching it within 30 days. */
    public function attention($company_id)
    {
        $where = array(
            'company_id' => $company_id
        );
        $sql = "SELECT
                    p.id,
                    p.project_name,
                    p.end_date,
                    DATE_FORMAT(p.end_date, df.sql_format) AS end_date_display,
                    DATEDIFF(p.end_date, CURDATE()) AS days_left,
                    cl.company_name AS client_name
                FROM
                    projects p
                    JOIN companies co ON co.id = p.company_id
                    JOIN date_formats df ON df.id = co.date_format_id
                    LEFT JOIN clients cl ON cl.id = p.client_id and cl.deleted = 0
                WHERE
                    p.company_id = :company_id
                    and
                    p.deleted = 0
                    and
                    p.project_status <> 'Complete'
                    and
                    p.end_date is not null
                    and
                    p.end_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                ORDER BY
                    p.end_date";
        return parent::select($sql, $where);
    }

}
