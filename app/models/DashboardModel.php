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
     * Open and overdue POA&M items across live assessments. Overdue needs a
     * scheduled completion date in the past; an item without one is never
     * counted as overdue.
     */
    public function poam_totals($company_id)
    {
        $where = array(
            'company_id' => $company_id
        );
        $sql = "SELECT
                    SUM(p.poam_status <> 'Closed') AS open_count,
                    SUM(p.poam_status <> 'Closed' and p.scheduled_completion_date < CURDATE()) AS overdue_count
                FROM
                    poam_items p
                    INNER JOIN assessments a ON a.id = p.assessment_id and a.deleted = 0
                WHERE
                    p.company_id = :company_id
                    and
                    p.deleted = 0";
        return parent::select($sql, $where);
    }

    /** Milestones not yet completed that are due today or within the next 30 days. */
    public function upcoming_milestones($company_id)
    {
        $where = array(
            'company_id' => $company_id
        );
        $sql = "SELECT
                    m.id,
                    m.milestone_text,
                    m.due_date,
                    p.poam_ref,
                    a.id AS assessment_id,
                    a.assessment_name
                FROM
                    poam_milestones m
                    INNER JOIN poam_items p ON p.id = m.poam_item_id and p.deleted = 0 and p.poam_status <> 'Closed'
                    INNER JOIN assessments a ON a.id = p.assessment_id and a.deleted = 0
                WHERE
                    m.company_id = :company_id
                    and
                    m.deleted = 0
                    and
                    m.milestone_status <> 'Completed'
                    and
                    m.due_date BETWEEN CURDATE() and CURDATE() + INTERVAL 30 DAY
                ORDER BY
                    m.due_date,
                    p.poam_ref
                LIMIT 50";
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
