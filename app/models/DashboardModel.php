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
            'c6' => $company_id
        );
        $sql = "SELECT
                    (SELECT COUNT(*) FROM clients c
                        WHERE c.company_id = :c1 and c.deleted = 0) AS client_count,
                    (SELECT COUNT(*) FROM assessments a
                        WHERE a.company_id = :c2 and a.deleted = 0) AS assessment_count,
                    (SELECT COUNT(*) FROM assessments a
                        WHERE a.company_id = :c3 and a.deleted = 0
                        and a.assessment_status <> 'Complete'
                        and (a.end_date is null or a.end_date >= CURDATE())) AS open_assessment_count,
                    (SELECT COUNT(*) FROM evidence e
                        WHERE e.company_id = :c4 and e.deleted = 0) AS evidence_count,
                    (SELECT COUNT(*) FROM evidence e
                        WHERE e.company_id = :c5 and e.deleted = 0
                        and e.evidence_private = 1) AS private_count,
                    (SELECT COUNT(*) FROM standards s
                        WHERE s.company_id = :c6 and s.deleted = 0) AS standard_count";
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
                    and
                    ai.control_kind = 'control'
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
                    a.client_id,
                    a.assessment_name,
                    a.short_code,
                    a.assessment_status,
                    cl.company_name AS client_name,
                    COUNT(ai.id) AS item_count,
                    SUM(CASE WHEN ai.item_result <> 'Not Assessed' THEN 1 ELSE 0 END) AS assessed_count,
                    SUM(CASE WHEN ai.item_result = 'Not Implemented' THEN 1 ELSE 0 END) AS gap_count
                FROM
                    assessments a
                    JOIN clients cl ON cl.id = a.client_id and cl.deleted = 0
                    LEFT JOIN assessment_items ai ON ai.assessment_id = a.id and ai.deleted = 0 and ai.control_kind = 'control'
                WHERE
                    a.company_id = :company_id
                    and
                    a.deleted = 0
                GROUP BY
                    a.id,
                    a.client_id,
                    a.assessment_name,
                    a.short_code,
                    a.assessment_status,
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
                    COUNT(DISTINCT a.id) AS assessment_count,
                    COUNT(ai.id) AS item_count,
                    SUM(CASE WHEN ai.item_result <> 'Not Assessed' THEN 1 ELSE 0 END) AS assessed_count,
                    SUM(CASE WHEN ai.item_result = 'Not Implemented' THEN 1 ELSE 0 END) AS gap_count,
                    (SELECT COUNT(*)
                        FROM evidence e
                        WHERE e.client_id = c.id
                        and e.company_id = :company_id2
                        and e.deleted = 0) AS evidence_count
                FROM
                    clients c
                    LEFT JOIN assessments a ON a.client_id = c.id and a.company_id = c.company_id and a.deleted = 0
                    LEFT JOIN assessment_items ai ON ai.assessment_id = a.id and ai.deleted = 0 and ai.control_kind = 'control'
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

    /**
     * Dated assessments that are late or falling behind. Late: past the end date
     * and not complete. Behind: the share of controls assessed trails the share
     * of the assessment's time already used by more than ten points, so an
     * assessment that is on pace never appears however close its end date is.
     */
    public function attention($company_id)
    {
        $where = array(
            'company_id' => $company_id
        );
        $sql = "SELECT
                    t.id,
                    t.assessment_name,
                    t.client_id,
                    t.client_name,
                    t.days_left,
                    ROUND(t.assessed_pct) AS assessed_pct,
                    ROUND(t.elapsed_pct) AS elapsed_pct
                FROM
                    (
                        SELECT
                            a.id,
                            a.assessment_name,
                            a.client_id,
                            cl.company_name AS client_name,
                            DATEDIFF(a.end_date, CURDATE()) AS days_left,
                            CASE
                                WHEN COUNT(ai.id) = 0 THEN 0
                                ELSE SUM(CASE WHEN ai.item_result <> 'Not Assessed' THEN 1 ELSE 0 END) / COUNT(ai.id) * 100
                            END AS assessed_pct,
                            LEAST(100, GREATEST(0,
                                DATEDIFF(CURDATE(), COALESCE(a.start_date, DATE(a.date_created)))
                                / GREATEST(DATEDIFF(a.end_date, COALESCE(a.start_date, DATE(a.date_created))), 1) * 100
                            )) AS elapsed_pct
                        FROM
                            assessments a
                            JOIN clients cl ON cl.id = a.client_id and cl.deleted = 0
                            LEFT JOIN assessment_items ai ON ai.assessment_id = a.id and ai.deleted = 0 and ai.control_kind = 'control'
                        WHERE
                            a.company_id = :company_id
                            and
                            a.deleted = 0
                            and
                            a.assessment_status <> 'Complete'
                            and
                            a.end_date is not null
                        GROUP BY
                            a.id,
                            a.assessment_name,
                            a.client_id,
                            cl.company_name,
                            a.end_date,
                            a.start_date,
                            a.date_created
                    ) t
                WHERE
                    t.assessed_pct < 100
                    and
                    (
                        t.days_left < 0
                        or
                        t.assessed_pct + 10 < t.elapsed_pct
                    )
                ORDER BY
                    t.days_left";
        return parent::select($sql, $where);
    }

}
