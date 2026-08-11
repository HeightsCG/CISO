<?php
class InvoicesModel extends Model {

    public function __construct(){
        parent::__construct();
    }

    /**
     * The label every surface shows. Computed here rather than in each view so a
     * list and a detail page cannot disagree about the same invoice.
     *
     * Payment processing outranks overdue deliberately: an ACH debit takes days
     * to settle, so an invoice paid on its due date would otherwise be shown as
     * late to the client who has already paid it.
     */
    private function status_case(): string
    {
        return "CASE
                    WHEN i.invoice_status = 'Draft' THEN 'Draft'
                    WHEN i.invoice_status = 'Finalizing' THEN 'Sending'
                    WHEN i.invoice_status = 'Paid' THEN 'Paid'
                    WHEN i.invoice_status = 'Void' THEN 'Void'
                    WHEN i.invoice_status = 'Uncollectible' THEN 'Written Off'
                    WHEN i.payment_state = 'Processing' THEN 'Payment Processing'
                    WHEN i.payment_state = 'Failed' THEN 'Payment Failed'
                    WHEN i.payment_state = 'Requires_Action' THEN 'Action Needed'
                    WHEN i.due_date is not null and i.due_date < UTC_DATE() THEN 'Overdue'
                    ELSE 'Awaiting Payment'
                END";
    }

    /**
     * Money is formatted in SQL for the same reason dates already are: the raw
     * column sorts and the display column renders, so a currency cell and a date
     * cell behave identically in DataTables. The code leads rather than a symbol,
     * because a list can hold USD and CHF at once.
     */
    private function money_columns(): string
    {
        return "CONCAT(UPPER(i.currency), ' ', FORMAT(i.total_cents / 100, 2)) AS total_display,
                    CONCAT(UPPER(i.currency), ' ', FORMAT(i.amount_due_cents / 100, 2)) AS amount_due_display,
                    CONCAT(UPPER(i.currency), ' ', FORMAT(i.amount_paid_cents / 100, 2)) AS amount_paid_display";
    }

    public function load_invoices($company_id)
    {
        $where = array(
            'company_id' => $company_id
        );
        $sql = "SELECT
                    i.id,
                    i.client_id,
                    i.project_id,
                    i.subscription_id,
                    i.invoice_origin,
                    i.invoice_status,
                    i.payment_state,
                    i.finalize_state,
                    i.invoice_number,
                    i.currency,
                    i.total_cents,
                    i.amount_due_cents,
                    i.amount_paid_cents,
                    i.due_date,
                    i.date_created,
                    c.company_name AS client_name,
                    p.project_name,
                    ".$this->money_columns().",
                    ".$this->status_case()." AS invoice_status_display,
                    DATE_FORMAT(i.date_created, df.sql_format) AS date_created_display,
                    DATE_FORMAT(i.due_date, df.sql_format) AS due_date_display
                FROM
                    invoices i
                    JOIN companies co ON co.id = i.company_id
                    JOIN date_formats df ON df.id = co.date_format_id
                    JOIN clients c ON c.id = i.client_id and c.deleted = 0
                    LEFT JOIN projects p ON p.id = i.project_id and p.deleted = 0
                WHERE
                    i.company_id = :company_id
                    and
                    i.deleted = 0
                ORDER BY
                    i.date_created DESC,
                    i.id DESC";
        return parent::select($sql, $where);
    }

    public function client_invoices($client_id, $company_id)
    {
        $where = array(
            'client_id'  => $client_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    i.id,
                    i.project_id,
                    i.subscription_id,
                    i.invoice_origin,
                    i.invoice_status,
                    i.payment_state,
                    i.invoice_number,
                    i.currency,
                    i.total_cents,
                    i.amount_due_cents,
                    i.due_date,
                    i.date_created,
                    p.project_name,
                    ".$this->money_columns().",
                    ".$this->status_case()." AS invoice_status_display,
                    DATE_FORMAT(i.date_created, df.sql_format) AS date_created_display,
                    DATE_FORMAT(i.due_date, df.sql_format) AS due_date_display
                FROM
                    invoices i
                    JOIN companies co ON co.id = i.company_id
                    JOIN date_formats df ON df.id = co.date_format_id
                    LEFT JOIN projects p ON p.id = i.project_id and p.deleted = 0
                WHERE
                    i.client_id = :client_id
                    and
                    i.company_id = :company_id
                    and
                    i.deleted = 0
                ORDER BY
                    i.date_created DESC,
                    i.id DESC";
        return parent::select($sql, $where);
    }

    /**
     * The portal list. A draft is staff work in progress that Stripe has not sent,
     * so it is excluded in the query rather than filtered in the view - a client
     * must not be able to reach one by any route.
     */
    public function portal_invoices($client_id, $company_id)
    {
        $where = array(
            'client_id'  => $client_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    i.id,
                    i.invoice_status,
                    i.payment_state,
                    i.invoice_number,
                    i.currency,
                    i.total_cents,
                    i.amount_due_cents,
                    i.due_date,
                    p.project_name,
                    ".$this->money_columns().",
                    ".$this->status_case()." AS invoice_status_display,
                    DATE_FORMAT(i.finalized_at, df.sql_format) AS issued_display,
                    DATE_FORMAT(i.due_date, df.sql_format) AS due_date_display
                FROM
                    invoices i
                    JOIN companies co ON co.id = i.company_id
                    JOIN date_formats df ON df.id = co.date_format_id
                    LEFT JOIN projects p ON p.id = i.project_id and p.deleted = 0
                WHERE
                    i.client_id = :client_id
                    and
                    i.company_id = :company_id
                    and
                    i.invoice_status not in ('Draft', 'Finalizing')
                    and
                    i.deleted = 0
                ORDER BY
                    i.finalized_at DESC,
                    i.id DESC";
        return parent::select($sql, $where);
    }

    public function get_invoice($invoice_id, $company_id)
    {
        $where = array(
            'id'         => $invoice_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    i.*,
                    c.company_name AS client_name,
                    c.contact_email AS client_contact_email,
                    c.billing_email AS client_billing_email,
                    c.stripe_customer_id AS client_stripe_customer_id,
                    c.stripe_livemode AS client_stripe_livemode,
                    p.project_name,
                    ".$this->money_columns().",
                    ".$this->status_case()." AS invoice_status_display,
                    DATE_FORMAT(i.date_created, df.sql_format) AS date_created_display,
                    DATE_FORMAT(i.finalized_at, df.sql_format) AS finalized_display,
                    DATE_FORMAT(i.due_date, df.sql_format) AS due_date_display
                FROM
                    invoices i
                    JOIN companies co ON co.id = i.company_id
                    JOIN date_formats df ON df.id = co.date_format_id
                    JOIN clients c ON c.id = i.client_id and c.deleted = 0
                    LEFT JOIN projects p ON p.id = i.project_id and p.deleted = 0
                WHERE
                    i.id = :id
                    and
                    i.company_id = :company_id
                    and
                    i.deleted = 0";
        return parent::select($sql, $where);
    }

    public function get_invoice_items($invoice_id)
    {
        $where = array(
            'invoice_id' => $invoice_id
        );
        $sql = "SELECT
                    id,
                    invoice_id,
                    service_id,
                    line_source,
                    item_description,
                    quantity_milli,
                    unit_amount_cents,
                    amount_cents,
                    sort_order
                FROM
                    invoice_items
                WHERE
                    invoice_id = :invoice_id
                    and
                    deleted = 0
                ORDER BY
                    sort_order ASC,
                    id ASC";
        return parent::select($sql, $where);
    }

    public function add_invoice($company_id, $client_id, $project_id, $currency, $invoice_memo, $invoice_footer, $due_days, $due_date, $created_by)
    {
        $data = array(
            'company_id'     => $company_id,
            'client_id'      => $client_id,
            'project_id'     => $project_id,
            'currency'       => $currency,
            'invoice_memo'   => $invoice_memo,
            'invoice_footer' => $invoice_footer,
            'due_days'       => $due_days,
            'due_date'       => $due_date,
            'invoice_origin' => 'Manual',
            'invoice_status' => 'Draft',
            'created_by'     => $created_by,
            'updated_by'     => $created_by,
            'date_created'   => date('Y-m-d H:i:s'),
            'date_updated'   => date('Y-m-d H:i:s')
        );
        return parent::insert('invoices', $data);
    }

    public function update_invoice($invoice_id, $company_id, $client_id, $project_id, $currency, $invoice_memo, $invoice_footer, $due_days, $due_date, $updated_by)
    {
        $where = array(
            'id'         => $invoice_id,
            'company_id' => $company_id
        );
        $data = array(
            'client_id'      => $client_id,
            'project_id'     => $project_id,
            'currency'       => $currency,
            'invoice_memo'   => $invoice_memo,
            'invoice_footer' => $invoice_footer,
            'due_days'       => $due_days,
            'due_date'       => $due_date,
            'updated_by'     => $updated_by,
            'date_updated'   => date('Y-m-d H:i:s')
        );
        return parent::update('invoices', $data, 'id = :id and company_id = :company_id', $where);
    }

    /**
     * Lines are replaced wholesale and the header total recomputed from what was
     * actually written, so the total can never disagree with the lines that add up
     * to it. The client's own total is never read.
     *
     * Model has no transaction helper, so the PDO handle is used directly - an
     * invoice whose lines were half replaced would bill the wrong amount.
     */
    public function save_invoice_lines($invoice_id, $lines, $updated_by)
    {
        $now = date('Y-m-d H:i:s');

        $this->db->beginTransaction();

        try {

            $clear = $this->db->prepare("UPDATE invoice_items SET deleted = 1, date_updated = :date_updated WHERE invoice_id = :invoice_id and deleted = 0");
            $clear->execute(array('date_updated' => $now, 'invoice_id' => $invoice_id));

            $insert = $this->db->prepare(
                "INSERT INTO invoice_items
                    (invoice_id, service_id, line_source, item_description, quantity_milli, unit_amount_cents, amount_cents, sort_order, updated_by, date_created, date_updated)
                 VALUES
                    (:invoice_id, :service_id, :line_source, :item_description, :quantity_milli, :unit_amount_cents, :amount_cents, :sort_order, :updated_by, :date_created, :date_updated)"
            );

            $subtotal = 0;
            $sort     = 0;

            foreach ($lines as $line) {

                $amount    = Money::line_amount($line['quantity_milli'], $line['unit_amount_cents']);
                $subtotal += $amount;

                $insert->execute(array(
                    'invoice_id'        => $invoice_id,
                    'service_id'        => (int) ($line['service_id'] ?? 0),
                    'line_source'       => 'Local',
                    'item_description'  => $line['item_description'],
                    'quantity_milli'    => $line['quantity_milli'],
                    'unit_amount_cents' => $line['unit_amount_cents'],
                    'amount_cents'      => $amount,
                    'sort_order'        => $sort,
                    'updated_by'        => $updated_by,
                    'date_created'      => $now,
                    'date_updated'      => $now
                ));

                $sort++;
            }

            $total = $this->db->prepare(
                "UPDATE invoices
                    SET subtotal_cents = :subtotal, total_cents = :total, amount_due_cents = :due,
                        updated_by = :updated_by, date_updated = :date_updated
                    WHERE id = :invoice_id"
            );

            $total->execute(array(
                'subtotal'     => $subtotal,
                'total'        => $subtotal,
                'due'          => $subtotal,
                'updated_by'   => $updated_by,
                'date_updated' => $now,
                'invoice_id'   => $invoice_id
            ));

            $this->db->commit();

        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $subtotal;
    }

    /**
     * Invoices Stripe is still chasing. Disconnecting while these are outstanding
     * would leave Stripe emailing and dunning for money this application could no
     * longer see settled.
     */
    public function count_open_invoices($company_id)
    {
        $where = array(
            'company_id' => $company_id
        );
        $sql = "SELECT
                    COUNT(*) AS open_count
                FROM
                    invoices
                WHERE
                    company_id = :company_id
                    and
                    invoice_status in ('Finalizing', 'Open')
                    and
                    deleted = 0";
        $rows = parent::select($sql, $where);

        return isset($rows[0]['open_count']) ? (int) $rows[0]['open_count'] : 0;
    }

    /** Stamp the Stripe draft onto the local row before anything irreversible happens. */
    public function set_stripe_invoice($invoice_id, $stripe_invoice_id, $stripe_account_id, $stripe_customer_id, $stripe_livemode)
    {
        $where = array(
            'id' => $invoice_id
        );
        $data = array(
            'stripe_invoice_id'  => $stripe_invoice_id,
            'stripe_account_id'  => $stripe_account_id,
            'stripe_customer_id' => $stripe_customer_id,
            'stripe_livemode'    => $stripe_livemode,
            'date_updated'       => date('Y-m-d H:i:s')
        );
        return parent::update('invoices', $data, 'id = :id', $where);
    }

    /**
     * Apply a Stripe invoice to the local mirror. The same method serves the send
     * path, the webhook and the reconciler, so the three cannot drift about what a
     * Stripe invoice means.
     *
     * Every field is written from the snapshot rather than as a delta, which is
     * what makes applying the same event twice harmless.
     */
    public function apply_stripe_invoice($invoice_id, array $stripe, $event_created)
    {
        $status_map = array(
            'draft'         => 'Draft',
            'open'          => 'Open',
            'paid'          => 'Paid',
            'void'          => 'Void',
            'uncollectible' => 'Uncollectible'
        );

        $status = $status_map[$stripe['status'] ?? ''] ?? 'Open';
        $now    = date('Y-m-d H:i:s');

        $data = array(
            'invoice_status'    => $status,
            'invoice_number'    => (string) ($stripe['number'] ?? ''),
            'currency'          => (string) ($stripe['currency'] ?? 'usd'),
            'subtotal_cents'    => (int) ($stripe['subtotal'] ?? 0),
            'total_cents'       => (int) ($stripe['total'] ?? 0),
            'amount_paid_cents' => (int) ($stripe['amount_paid'] ?? 0),
            'amount_due_cents'  => (int) ($stripe['amount_remaining'] ?? 0),
            'hosted_invoice_url'=> (string) ($stripe['hosted_invoice_url'] ?? ''),
            'invoice_pdf_url'   => (string) ($stripe['invoice_pdf'] ?? ''),
            'finalize_state'    => 'Idle',
            'finalize_error'    => '',
            'stripe_synced_at'  => $now,
            'date_updated'      => $now
        );

        if ((int) $event_created > 0) {
            $data['stripe_event_created'] = (int) $event_created;
        }

        if (!empty($stripe['due_date'])) {
            $data['due_date'] = date('Y-m-d', (int) $stripe['due_date']);
        }

        if (!empty($stripe['status_transitions']['finalized_at'])) {
            $data['finalized_at'] = date('Y-m-d H:i:s', (int) $stripe['status_transitions']['finalized_at']);
        }

        if (!empty($stripe['status_transitions']['paid_at'])) {
            $data['paid_at'] = date('Y-m-d H:i:s', (int) $stripe['status_transitions']['paid_at']);
        }

        if (!empty($stripe['status_transitions']['voided_at'])) {
            $data['voided_at'] = date('Y-m-d H:i:s', (int) $stripe['status_transitions']['voided_at']);
        }

        if ($status === 'Paid') {
            $data['payment_state'] = 'Succeeded';
        }

        $payment_intent = StripeService::invoice_payment_intent_id($stripe);

        if ($payment_intent !== '') {
            $data['stripe_payment_intent_id'] = $payment_intent;
        }

        return parent::update('invoices', $data, 'id = :id', array('id' => $invoice_id));
    }

    /**
     * Only a definitive refusal from Stripe may return an invoice to Draft. On a
     * timeout the row stays in Finalizing, because Stripe may well have created and
     * emailed the invoice - retrying past the idempotency window would bill the
     * client twice and set dunning chasing double the money.
     */
    public function fail_finalize($invoice_id, $finalize_error, $back_to_draft)
    {
        $data = array(
            'finalize_state' => 'Failed',
            'finalize_error' => substr((string) $finalize_error, 0, 480),
            'date_updated'   => date('Y-m-d H:i:s')
        );

        if ($back_to_draft) {
            $data['invoice_status'] = 'Draft';
        }

        return parent::update('invoices', $data, 'id = :id', array('id' => $invoice_id));
    }

    public function set_payment_state($invoice_id, $payment_state, $payment_error, $event_created)
    {
        $data = array(
            'payment_state'  => $payment_state,
            'payment_error'  => substr((string) $payment_error, 0, 240),
            'date_updated'   => date('Y-m-d H:i:s')
        );

        if ((int) $event_created > 0) {
            $data['stripe_event_created'] = (int) $event_created;
        }

        return parent::update('invoices', $data, 'id = :id', array('id' => $invoice_id));
    }

    /**
     * Invoices left mid-send. send_invoice deliberately does not roll these back to
     * Draft on a timeout, because Stripe may already have finalised and emailed
     * them - so something has to come along afterwards and find out which happened.
     * Nothing else resolves this state.
     */
    public function stuck_finalizing($company_id, $older_than_seconds)
    {
        $where = array(
            'company_id' => $company_id,
            'cutoff'     => date('Y-m-d H:i:s', time() - (int) $older_than_seconds)
        );
        $sql = "SELECT
                    id,
                    client_id,
                    stripe_invoice_id,
                    stripe_account_id,
                    stripe_customer_id,
                    finalize_state,
                    date_updated
                FROM
                    invoices
                WHERE
                    company_id = :company_id
                    and
                    invoice_status = 'Finalizing'
                    and
                    date_updated < :cutoff
                    and
                    deleted = 0
                ORDER BY
                    date_updated ASC
                LIMIT 50";
        return parent::select($sql, $where);
    }

    /** Open invoices whose mirror has aged, in case a webhook was never delivered. */
    public function stale_open($company_id, $older_than_seconds)
    {
        $where = array(
            'company_id' => $company_id,
            'cutoff'     => date('Y-m-d H:i:s', time() - (int) $older_than_seconds)
        );
        $sql = "SELECT
                    id,
                    stripe_invoice_id,
                    stripe_account_id
                FROM
                    invoices
                WHERE
                    company_id = :company_id
                    and
                    invoice_status = 'Open'
                    and
                    stripe_invoice_id is not null
                    and
                    (stripe_synced_at is null or stripe_synced_at < :cutoff)
                    and
                    deleted = 0
                ORDER BY
                    stripe_synced_at ASC
                LIMIT 100";
        return parent::select($sql, $where);
    }

    /** Invoices Stripe is still chasing for one client, guarding the client delete. */
    public function count_open_client_invoices($client_id, $company_id)
    {
        $where = array(
            'client_id'  => $client_id,
            'company_id' => $company_id
        );
        $sql = "SELECT
                    COUNT(*) AS open_count
                FROM
                    invoices
                WHERE
                    client_id = :client_id
                    and
                    company_id = :company_id
                    and
                    invoice_status in ('Finalizing', 'Open')
                    and
                    deleted = 0";
        $rows = parent::select($sql, $where);

        return isset($rows[0]['open_count']) ? (int) $rows[0]['open_count'] : 0;
    }

    public function delete_invoice($invoice_id, $company_id, $updated_by)
    {
        $where = array(
            'id'         => $invoice_id,
            'company_id' => $company_id
        );
        $data = array(
            'deleted'      => 1,
            'updated_by'   => $updated_by,
            'date_updated' => date('Y-m-d H:i:s')
        );
        return parent::update('invoices', $data, 'id = :id and company_id = :company_id', $where);
    }

    /**
     * The finalise mutex. Returns 1 only for the request that moved the invoice out
     * of Draft, so a double-clicked Send makes exactly one set of Stripe calls and
     * the loser is told the invoice is already going out.
     */
    public function claim_finalize($invoice_id, $company_id, $updated_by)
    {
        $where = array(
            'id'         => $invoice_id,
            'company_id' => $company_id
        );
        $data = array(
            'invoice_status' => 'Finalizing',
            'finalize_state' => 'Pending',
            'finalize_error' => '',
            'updated_by'     => $updated_by,
            'date_updated'   => date('Y-m-d H:i:s')
        );
        return parent::update(
            'invoices',
            $data,
            "id = :id and company_id = :company_id and invoice_status = 'Draft' and finalize_state <> 'Pending'",
            $where
        );
    }

    /**
     * Reached by Stripe id alone, with no company_id, because a webhook arrives
     * with no session and the tenant is only known once the row is found. It is
     * safe only because the column is unique and its value comes from a signed
     * Stripe payload rather than from a request parameter - do not copy this
     * shape for anything a caller can name.
     */
    public function by_stripe_invoice_id($stripe_invoice_id)
    {
        $where = array(
            'stripe_invoice_id' => $stripe_invoice_id
        );
        $sql = "SELECT
                    id,
                    company_id,
                    client_id,
                    project_id,
                    subscription_id,
                    invoice_status,
                    payment_state,
                    stripe_account_id,
                    stripe_event_created
                FROM
                    invoices
                WHERE
                    stripe_invoice_id = :stripe_invoice_id
                    and
                    deleted = 0";
        return parent::select($sql, $where);
    }

    /**
     * Claim an event for processing. The unique key on stripe_event_id is the whole
     * deduplication mechanism: insert first and let a duplicate-key failure mean
     * "already seen". A SELECT-then-INSERT would race against Stripe's own retry
     * and process the same event twice.
     *
     * An event whose previous attempt failed is re-claimed rather than refused,
     * or a handler that errored once would lock itself out of every retry.
     */
    public function claim_event($stripe_event_id, $event_type, $event_created, $livemode, $account_id, $object_id): bool
    {
        $now = date('Y-m-d H:i:s');

        try {

            parent::insert('stripe_events', array(
                'stripe_event_id' => $stripe_event_id,
                'event_type'      => $event_type,
                'event_created'   => (int) $event_created,
                'event_status'    => 'Received',
                'livemode'        => $livemode,
                'account_id'      => (string) $account_id,
                'object_id'       => (string) $object_id,
                'attempts'        => 1,
                'date_created'    => $now,
                'date_updated'    => $now
            ));

            return true;

        } catch (\PDOException $e) {

            if ($e->getCode() !== '23000') {
                throw $e;
            }

            $seen = parent::select(
                "SELECT id, event_status, attempts FROM stripe_events WHERE stripe_event_id = :stripe_event_id",
                array('stripe_event_id' => $stripe_event_id)
            );

            if (!isset($seen[0]) || in_array($seen[0]['event_status'], array('Processed', 'Ignored'), true)) {
                return false;
            }

            parent::update(
                'stripe_events',
                array(
                    'event_status' => 'Received',
                    'attempts'     => ((int) $seen[0]['attempts']) + 1,
                    'date_updated' => $now
                ),
                'id = :id',
                array('id' => $seen[0]['id'])
            );

            return true;
        }
    }

    public function mark_event($stripe_event_id, $event_status, $error_message)
    {
        return parent::update(
            'stripe_events',
            array(
                'event_status'  => $event_status,
                'error_message' => substr((string) $error_message, 0, 480),
                'date_updated'  => date('Y-m-d H:i:s')
            ),
            'stripe_event_id = :stripe_event_id',
            array('stripe_event_id' => $stripe_event_id)
        );
    }

    public function event_attempts($stripe_event_id): int
    {
        $rows = parent::select(
            "SELECT attempts FROM stripe_events WHERE stripe_event_id = :stripe_event_id",
            array('stripe_event_id' => $stripe_event_id)
        );

        return isset($rows[0]['attempts']) ? (int) $rows[0]['attempts'] : 0;
    }

    /** As by_stripe_invoice_id, for the payment_intent events that carry no invoice reference. */
    public function by_stripe_payment_intent_id($stripe_payment_intent_id)
    {
        $where = array(
            'stripe_payment_intent_id' => $stripe_payment_intent_id
        );
        $sql = "SELECT
                    id,
                    company_id,
                    client_id,
                    invoice_status,
                    payment_state,
                    stripe_event_created
                FROM
                    invoices
                WHERE
                    stripe_payment_intent_id = :stripe_payment_intent_id
                    and
                    stripe_payment_intent_id <> ''
                    and
                    deleted = 0";
        return parent::select($sql, $where);
    }

}
