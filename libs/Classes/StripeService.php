<?php
/**
 * Thin wrapper around Stripe, in the shape of S3Service: static, guarded by
 * configured(), and returning empty values rather than throwing. Nothing above
 * this class ever sees a \Stripe\ object - every method hands back a plain array,
 * so callers read a Stripe response exactly as they read a PDO row and the whole
 * integration stays behind one file.
 *
 * The API version is pinned to the one the vendored SDK was built against. It is
 * not optional: on this version an invoice no longer carries subscription or
 * payment_intent at the top level, and a subscription no longer carries
 * current_period_end. Left unpinned, the account's own default governs the
 * response shape and those reads return null with no error raised anywhere.
 */
class StripeService {

    private static $last_error = '';

    /**
     * Environment section first, [global] second. The keys belong in the
     * environment section - a test key read by production writes invoices into
     * the wrong ledger and emails nobody - but the fallback means a deployment
     * that has not been migrated yet still works.
     */
    private static function cfg($key): string
    {
        $config = Main::get_config();
        $env    = Main::get_environment();

        if (!empty($config[$env][$key])) {
            return (string) $config[$env][$key];
        }

        return (string) ($config['global'][$key] ?? '');
    }

    public static function api_key(): string
    {
        return self::cfg('stripe_api_key');
    }

    public static function webhook_secret(): string
    {
        return self::cfg('stripe_webhook_secret');
    }

    public static function configured(): bool
    {
        return strpos(self::api_key(), 'sk_') === 0;
    }

    /**
     * Every mirrored row records the mode it was created under, because an id
     * minted with a test key is a 404 under a live key. Without the flag the
     * first action after a key swap fails with "no such customer" and nothing
     * points at the cause.
     */
    public static function livemode(): int
    {
        return strpos(self::api_key(), 'sk_live_') === 0 ? 1 : 0;
    }

    public static function last_error(): string
    {
        return self::$last_error;
    }

    /**
     * Values reaching Stripe are printed on the PDF and in the email Stripe
     * sends, so they are decoded here at the boundary. clean_post_data() runs
     * htmlentities over every scalar on the way in, so a client called
     * O'Brien Aviation is held as O&#039;Brien Aviation and would be billed
     * under that name.
     */
    public static function text($value): string
    {
        return trim(html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8'));
    }

    private static function client(): \Stripe\StripeClient
    {
        return new \Stripe\StripeClient(array(
            'api_key'             => self::api_key(),
            'stripe_version'      => \Stripe\Util\ApiVersion::CURRENT,
            'max_network_retries' => 2,
        ));
    }

    /**
     * A secret can appear in an exception message, and casting the exception to
     * string would add a stack trace whose frames carry the key as a constructor
     * argument. Only the class and the redacted message are ever recorded.
     */
    private static function fail($operation, \Throwable $e): void
    {
        $message = preg_replace('/\b(sk|rk|pk|whsec)_[A-Za-z0-9_]+/', '$1_[redacted]', $e->getMessage());

        self::$last_error = substr((string) $message, 0, 200);

        error_log('[stripe] '.$operation.' failed: '.get_class($e).': '.self::$last_error);
    }

    /** The account this key belongs to, or an empty array. Used to prove configuration. */
    public static function account(): array
    {
        if (!self::configured()) {
            error_log('[stripe] account requested but Stripe is not configured');
            return array();
        }

        try {
            return self::client()->accounts->retrieve()->toArray();
        } catch (\Throwable $e) {
            self::fail('account retrieve', $e);
            return array();
        }
    }

    /**
     * Webhook handlers re-read the object rather than trusting the event payload.
     * Stripe does not guarantee delivery order, and a live read is current by
     * definition, so ordering stops being something the handlers have to reason
     * about at all.
     */
    public static function retrieve_invoice($stripe_invoice_id): array
    {
        if (!self::configured() || (string) $stripe_invoice_id === '') {
            return array();
        }

        try {
            $invoice = self::client()->invoices->retrieve((string) $stripe_invoice_id, array(
                'expand' => array('payments')
            ));
            return $invoice->toArray();
        } catch (\Throwable $e) {
            self::fail('invoice retrieve', $e);
            return array();
        }
    }

    /**
     * The subscription an invoice was generated by. Reads through parent rather
     * than the invoice root, where it used to live - the root property does not
     * exist on the pinned API version and would silently read as null.
     */
    public static function invoice_subscription_id(array $invoice): string
    {
        $subscription = $invoice['parent']['subscription_details']['subscription'] ?? '';

        if (is_array($subscription)) {
            $subscription = $subscription['id'] ?? '';
        }

        return (string) $subscription;
    }

    /**
     * The payment intent behind an invoice, taken from the payments collection.
     * A PaymentIntent carries no reference back to its invoice on this API
     * version, so this id has to be stored locally at finalization or a later
     * payment_intent event cannot be matched to anything.
     */
    public static function invoice_payment_intent_id(array $invoice): string
    {
        $payments = $invoice['payments']['data'] ?? array();

        if (!is_array($payments)) {
            return '';
        }

        foreach ($payments as $payment) {
            $intent = $payment['payment']['payment_intent'] ?? '';

            if (is_array($intent)) {
                $intent = $intent['id'] ?? '';
            }

            if ((string) $intent !== '') {
                return (string) $intent;
            }
        }

        return '';
    }

}
