<?php
/**
 * The Stripe webhook endpoint, at POST /stripe/webhook.
 *
 * A controller of its own rather than an action on ApiController, because
 * $csrfExempt is a class-level property: on a controller whose entire job is
 * receiving unauthenticated signed input the exemption is the point of the file
 * and cannot be misread, whereas on ApiController it would become a list future
 * actions get added to by accident.
 */
class StripeController extends Controller {

    public $protected = 0;

    /**
     * The first use of this mechanism in the application. A webhook carries no
     * session and no token, so without the exemption every event Stripe sends is
     * rejected as a CSRF failure.
     */
    public $csrfExempt = array('webhookAction');

    public $invoices_model;
    public $companies_model;

    public function __construct(){
        parent::__construct();
        $this->invoices_model = new InvoicesModel();
        $this->companies_model = new CompaniesModel();
    }

    public function webhookAction(){

        /**
         * No session is wanted here, and Bootstrap has already started one for a
         * request that will never use it. Closing it releases the file immediately
         * and makes it structurally impossible for a handler below to read tenant
         * state from a session rather than from the event.
         */
        Session::destroy();

        $secret = StripeService::webhook_secret();

        if ($secret === '') {
            error_log('[stripe] webhook received but stripe_webhook_secret is not configured');
            $this->respond(500, 'webhook secret not configured');
            return;
        }

        $payload   = (string) file_get_contents('php://input');
        $signature = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');

        /**
         * Stripe posts JSON, so PHP never populates $_POST and clean_post_data()
         * neither consumes nor mangles the stream. If that ever stops being true -
         * a proxy rewriting the content type - every signature check would fail
         * with nothing to say why, so the case is named rather than guessed at.
         */
        if ($payload === '') {
            error_log('[stripe] webhook raw body unavailable; content-type may have been rewritten');
            $this->respond(400, 'empty payload');
            return;
        }

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $signature, $secret);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            error_log('[stripe] webhook signature rejected (bytes='.strlen($payload).' header='.($signature === '' ? 'absent' : 'present').' ip='.$this->get_ip_address().')');
            $this->respond(400, 'invalid signature');
            return;
        } catch (\Throwable $e) {
            error_log('[stripe] webhook could not be parsed: '.get_class($e));
            $this->respond(400, 'unparseable payload');
            return;
        }

        $event = $event->toArray();

        /**
         * Two environments sharing one endpoint is a real deployment mistake, and
         * applying a test event to live invoices corrupts money data irreversibly.
         */
        if ((int) (!empty($event['livemode'])) !== StripeService::livemode()) {
            error_log('[stripe] webhook ignored: livemode mismatch for event '.$event['id']);
            $this->respond(200, 'livemode mismatch');
            return;
        }

        $object = $event['data']['object'] ?? array();

        if (!$this->invoices_model->claim_event(
            $event['id'],
            $event['type'],
            $event['created'] ?? 0,
            empty($event['livemode']) ? 0 : 1,
            $event['account'] ?? '',
            $object['id'] ?? ''
        )) {
            $this->respond(200, 'duplicate');
            return;
        }

        try {
            $this->dispatch($event, $object);
        } catch (\Throwable $e) {
            $this->invoices_model->mark_event($event['id'], 'Failed', get_class($e).': '.$e->getMessage());
            error_log('[stripe] webhook handler failed for '.$event['type'].': '.$e->getMessage());
            $this->respond(500, 'handler failed');
        }
    }

    /**
     * Every handler re-reads the object from Stripe rather than trusting the
     * payload. Stripe does not guarantee delivery order, and a live read is current
     * by definition, so ordering stops being something the handlers reason about.
     */
    private function dispatch(array $event, array $object): void
    {
        $account_id = (string) ($event['account'] ?? '');
        $type       = (string) $event['type'];

        if (strpos($type, 'invoice.') === 0) {
            $this->handle_invoice($event, $object, $account_id);
            return;
        }

        if (strpos($type, 'payment_intent.') === 0) {
            $this->handle_payment_intent($event, $object);
            return;
        }

        if ($type === 'account.updated') {
            $this->handle_account($event, $object, $account_id);
            return;
        }

        $this->invoices_model->mark_event($event['id'], 'Ignored', 'no handler for '.$type);
        $this->respond(200, 'ignored');
    }

    private function handle_invoice(array $event, array $object, string $account_id): void
    {
        $stripe_invoice_id = (string) ($object['id'] ?? '');
        $local             = $this->invoices_model->by_stripe_invoice_id($stripe_invoice_id);

        if (!is_array($local) || count($local) !== 1) {

            /**
             * Either our own transaction has not committed yet, in which case a
             * retry resolves it, or the invoice was raised in Stripe by hand and
             * belongs to nothing here. Retrying forever would burn Stripe's retry
             * budget and eventually get the endpoint disabled, so the attempt count
             * decides which it was.
             */
            if ($this->invoices_model->event_attempts($event['id']) < 5) {
                $this->invoices_model->mark_event($event['id'], 'Received', 'no local invoice yet');
                $this->respond(500, 'unmapped, will retry');
                return;
            }

            $this->invoices_model->mark_event($event['id'], 'Ignored', 'no local invoice for '.$stripe_invoice_id);
            $this->respond(200, 'unmapped');
            return;
        }

        $local = $local[0];

        /**
         * Ownership is never taken from the event. Stripe knows nothing of this
         * application's tenancy, so company and client stay as the local row has
         * them; only money and status are allowed to come from Stripe.
         */
        if ($account_id !== '' && $local['stripe_account_id'] !== '' && $account_id !== $local['stripe_account_id']) {
            $this->invoices_model->mark_event($event['id'], 'Ignored', 'account mismatch');
            $this->respond(200, 'account mismatch');
            return;
        }

        /** Stripe does not order events; an older snapshot must not overwrite a newer one. */
        if ((int) ($event['created'] ?? 0) < (int) $local['stripe_event_created']) {
            $this->invoices_model->mark_event($event['id'], 'Ignored', 'older than the last applied event');
            $this->respond(200, 'stale');
            return;
        }

        $current = StripeService::retrieve_invoice($account_id === '' ? $local['stripe_account_id'] : $account_id, $stripe_invoice_id);

        $this->invoices_model->apply_stripe_invoice($local['id'], empty($current) ? $object : $current, $event['created'] ?? 0);

        $this->invoices_model->mark_event($event['id'], 'Processed', '');
        $this->respond(200, 'ok');
    }

    /**
     * A PaymentIntent carries no reference back to its invoice on this API version,
     * so it is matched on the id captured at finalization. This is what makes an
     * ACH debit show as processing for the days it takes to settle, rather than the
     * invoice reading as unpaid and then overdue while the money is in flight.
     */
    private function handle_payment_intent(array $event, array $object): void
    {
        $local = $this->invoices_model->by_stripe_payment_intent_id((string) ($object['id'] ?? ''));

        if (!is_array($local) || count($local) !== 1) {
            $this->invoices_model->mark_event($event['id'], 'Ignored', 'no invoice carries this payment intent');
            $this->respond(200, 'unmapped');
            return;
        }

        $state_map = array(
            'payment_intent.processing'         => 'Processing',
            'payment_intent.succeeded'          => 'Succeeded',
            'payment_intent.payment_failed'     => 'Failed',
            'payment_intent.requires_action'    => 'Requires_Action',
            'payment_intent.amount_capturable_updated' => 'Processing'
        );

        $state = $state_map[$event['type']] ?? '';

        if ($state === '') {
            $this->invoices_model->mark_event($event['id'], 'Ignored', 'no handler for '.$event['type']);
            $this->respond(200, 'ignored');
            return;
        }

        $error = (string) ($object['last_payment_error']['message'] ?? '');

        $this->invoices_model->set_payment_state($local[0]['id'], $state, $error, $event['created'] ?? 0);
        $this->invoices_model->mark_event($event['id'], 'Processed', '');
        $this->respond(200, 'ok');
    }

    /** Capability changes on a connected account, so a restriction stops sending. */
    private function handle_account(array $event, array $object, string $account_id): void
    {
        $company = $this->companies_model->by_connect_account_id($account_id === '' ? ($object['id'] ?? '') : $account_id);

        if (!is_array($company) || count($company) !== 1) {
            $this->invoices_model->mark_event($event['id'], 'Ignored', 'no company for this account');
            $this->respond(200, 'unmapped');
            return;
        }

        $this->companies_model->set_connect_state(
            $company[0]['id'],
            StripeService::connect_status($object),
            empty($object['charges_enabled']) ? 0 : 1,
            empty($object['details_submitted']) ? 0 : 1,
            empty($object['payouts_enabled']) ? 0 : 1,
            StripeService::requirements_summary($object),
            $object['default_currency'] ?? 'usd'
        );

        $this->invoices_model->mark_event($event['id'], 'Processed', '');
        $this->respond(200, 'ok');
    }

    /**
     * 200 means do not send this again, 500 means send it again, 400 means it was
     * never valid. Nothing else in this application sets a status on a JSON path,
     * so every exit states one rather than relying on the default.
     */
    private function respond(int $code, string $message): void
    {
        http_response_code($code);
        echo $message;
    }

}
