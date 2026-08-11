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

    /**
     * Request options naming the connected account a call acts on.
     *
     * Deliberately per request. A StripeClient built with stripe_account carries
     * the header on every call and cannot be made to drop it for one - parse()
     * skips a null value and, unlike Stripe-Context, there is no unset branch - so
     * a platform call on such a client silently executes against the connected
     * account instead.
     */
    private static function connected(string $account_id, string $idempotency_key = ''): array
    {
        $opts = array('stripe_account' => $account_id);

        if ($idempotency_key !== '') {
            $opts['idempotency_key'] = $idempotency_key;
        }

        return $opts;
    }

    /**
     * Refuse a connected-scope call that was handed no account. Without this the
     * request would still succeed - against the platform's own account - and the
     * company's invoice would be created in the wrong ledger with nothing to show
     * that it had happened.
     */
    private static function has_account($account_id, string $operation): bool
    {
        if (strpos((string) $account_id, 'acct_') === 0) {
            return true;
        }

        self::$last_error = 'No connected Stripe account for this company';
        error_log('[stripe] '.$operation.' refused: called without a connected account id');

        return false;
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

    /* ------------------------------------------------------- platform scope */

    /**
     * A Standard connected account for a company. Standard means the company owns
     * the account outright - its own dashboard, payouts, disputes and Stripe fees -
     * and this platform never holds its funds.
     *
     * Country is passed only when known: it cannot be changed afterwards and it
     * determines which currencies the account can settle, so Stripe's hosted
     * onboarding is a better place to establish it than a guess made here.
     */
    public static function create_connected_account($company_id, $company_name, $email, $country): array
    {
        if (!self::configured()) {
            return array();
        }

        $params = array(
            'type'     => 'standard',
            'metadata' => array(
                'app'        => 'ciso',
                'company_id' => (string) $company_id
            )
        );

        if (self::text($email) !== '') {
            $params['email'] = self::text($email);
        }

        if (self::text($company_name) !== '') {
            $params['business_profile'] = array('name' => self::text($company_name));
        }

        if (self::text($country) !== '') {
            $params['country'] = strtoupper(self::text($country));
        }

        try {
            $account = self::client()->accounts->create($params, array(
                'idempotency_key' => 'acct-'.self::livemode().'-'.$company_id
            ));
            return $account->toArray();
        } catch (\Throwable $e) {
            self::fail('connected account create', $e);
            return array();
        }
    }

    /**
     * A single-use, short-lived onboarding URL. Both URLs must be absolute, and
     * the return must be a plain top-level GET: the session cookie is SameSite=Lax,
     * so it survives a navigation back from Stripe but would not survive a POST.
     */
    public static function create_account_link($account_id, $refresh_url, $return_url): array
    {
        if (!self::configured() || !self::has_account($account_id, 'account link create')) {
            return array();
        }

        try {
            $link = self::client()->accountLinks->create(array(
                'account'     => $account_id,
                'type'        => 'account_onboarding',
                'refresh_url' => $refresh_url,
                'return_url'  => $return_url
            ));
            return $link->toArray();
        } catch (\Throwable $e) {
            self::fail('account link create', $e);
            return array();
        }
    }

    /** Current state of a connected account - capabilities, requirements, currency. */
    public static function retrieve_account($account_id): array
    {
        if (!self::configured() || !self::has_account($account_id, 'account retrieve')) {
            return array();
        }

        try {
            return self::client()->accounts->retrieve($account_id, array())->toArray();
        } catch (\Throwable $e) {
            self::fail('account retrieve', $e);
            return array();
        }
    }

    /**
     * The company as a customer of this platform, in the platform's own account -
     * a different object entirely from the customers inside its connected account.
     * Nothing is charged; the record exists so that billing companies later needs
     * no backfill.
     */
    public static function create_platform_customer($company_id, $company_name, $email, $address_1, $address_2, $city, $state, $postal_code, $country): array
    {
        if (!self::configured()) {
            return array();
        }

        $params = array(
            'name'     => self::text($company_name),
            'metadata' => array(
                'app'        => 'ciso',
                'company_id' => (string) $company_id
            )
        );

        if (self::text($email) !== '') {
            $params['email'] = self::text($email);
        }

        $address = array(
            'line1'       => self::text($address_1),
            'line2'       => self::text($address_2),
            'city'        => self::text($city),
            'state'       => self::text($state),
            'postal_code' => self::text($postal_code)
        );

        $country_code = self::country_code($country);

        if ($country_code !== '') {
            $address['country'] = $country_code;
        }

        if (self::text($address_1) !== '') {
            $params['address'] = $address;
        }

        try {
            $customer = self::client()->customers->create($params, array(
                'idempotency_key' => 'platformcust-'.self::livemode().'-'.$company_id
            ));
            return $customer->toArray();
        } catch (\Throwable $e) {
            self::fail('platform customer create', $e);
            return array();
        }
    }

    /**
     * Stripe wants a two-letter ISO code; this application stores country as free
     * text, because address lookup fills it. An unrecognised value is dropped
     * rather than guessed - sending a bad code makes the whole call throw, which
     * would turn a cosmetic data problem into a blocked invoice.
     */
    public static function country_code($country): string
    {
        $value = strtolower(self::text($country));

        if ($value === '') {
            return '';
        }

        if (preg_match('/^[a-z]{2}$/', $value)) {
            return strtoupper($value);
        }

        $known = array(
            'united states' => 'US', 'usa' => 'US', 'united states of america' => 'US',
            'switzerland' => 'CH', 'suisse' => 'CH', 'schweiz' => 'CH', 'svizzera' => 'CH',
            'united kingdom' => 'GB', 'great britain' => 'GB', 'england' => 'GB',
            'ireland' => 'IE', 'germany' => 'DE', 'deutschland' => 'DE',
            'france' => 'FR', 'italy' => 'IT', 'italia' => 'IT',
            'spain' => 'ES', 'espana' => 'ES', 'netherlands' => 'NL',
            'belgium' => 'BE', 'austria' => 'AT', 'osterreich' => 'AT',
            'canada' => 'CA', 'australia' => 'AU', 'new zealand' => 'NZ',
            'sweden' => 'SE', 'norway' => 'NO', 'denmark' => 'DK', 'finland' => 'FI',
            'portugal' => 'PT', 'poland' => 'PL', 'luxembourg' => 'LU'
        );

        return $known[$value] ?? '';
    }

    /**
     * Where a connected account stands, as one of the states this application
     * stores.
     *
     * Read from the capability flags rather than from Account.type, which on this
     * API version returns 'none' for an account created with the newer controller
     * parameter - a switch on standard/express/custom silently matches nothing.
     *
     * Restricted is a real state rather than a nicety: a Standard account can
     * finish onboarding and later have charges switched off by Stripe pending
     * documents, and sending must stop with a reason rather than fail at Stripe.
     */
    public static function connect_status(array $account): string
    {
        if (empty($account['id'])) {
            return 'Not Connected';
        }

        if (empty($account['details_submitted'])) {
            return 'Onboarding';
        }

        return empty($account['charges_enabled']) ? 'Restricted' : 'Connected';
    }

    /**
     * What Stripe is still waiting for, as a short line for the settings panel.
     *
     * past_due is merged with currently_due rather than read alone: Stripe moves a
     * requirement between the two as its deadline passes, so reading only one leaves
     * the panel saying nothing is needed while charges are disabled for exactly that
     * reason - which is what an administrator sees as an unexplained "Restricted".
     */
    public static function requirements_summary(array $account): string
    {
        $requirements = $account['requirements'] ?? array();

        $due = array_merge(
            is_array($requirements['past_due'] ?? null) ? $requirements['past_due'] : array(),
            is_array($requirements['currently_due'] ?? null) ? $requirements['currently_due'] : array()
        );

        $tasks = array();

        foreach ($due as $requirement) {

            $task = self::requirement_label((string) $requirement);

            if ($task !== '' && !in_array($task, $tasks, true)) {
                $tasks[] = $task;
            }
        }

        return substr(implode(', ', $tasks), 0, 480);
    }

    /**
     * Turn one of Stripe's requirement keys into the job it actually represents.
     *
     * Stripe names these after its own data model, so left alone the panel tells a
     * compliance director that "tos acceptance ip" is outstanding, which is not a
     * thing anyone can go and do. Several keys are also one job between them -
     * tos_acceptance.date and tos_acceptance.ip are both satisfied by accepting the
     * terms once - so mapping them to the same label and de-duplicating means the
     * count on screen is a count of actions, not of fields.
     *
     * Longest prefix wins, so a family like individual.address.* collapses to one
     * task while individual.id_number keeps its own.
     */
    public static function requirement_label(string $requirement): string
    {
        $map = array(
            'business_profile.product_description' => 'What your business does',
            'business_profile.support_phone'       => 'Support phone number',
            'business_profile.support_email'       => 'Support email address',
            'business_profile.url'                 => 'Business website',
            'business_profile.mcc'                 => 'Business category',
            'business_profile.name'                => 'Business name',
            'external_account'                     => 'Bank account for payouts',
            'tos_acceptance'                       => "Accept Stripe's terms",
            'settings.dashboard.display_name'      => 'Public business name',
            'individual.verification.additional_document' => 'Proof of address for the owner',
            'individual.verification.document'     => 'Photo ID for the owner',
            'individual.id_number'                 => "Owner's tax ID or SSN",
            'individual.ssn_last_4'                => "Last four digits of the owner's SSN",
            'individual.address'                   => "Owner's home address",
            'individual.dob'                       => "Owner's date of birth",
            'individual.phone'                     => "Owner's phone number",
            'individual.email'                     => "Owner's email address",
            'individual.first_name'                => "Owner's name",
            'individual.last_name'                 => "Owner's name",
            'individual'                           => 'Details about the owner',
            'company.verification.document'        => 'Company registration document',
            'company.tax_id'                       => 'Company tax ID',
            'company.address'                      => 'Company address',
            'company.phone'                        => 'Company phone number',
            'company.name'                         => 'Registered company name',
            'company.directors_provided'           => 'Confirm who runs the company',
            'company.executives_provided'          => 'Confirm who runs the company',
            'company.owners_provided'              => 'Confirm who owns the company',
            'relationship.representative'          => 'A representative for the account',
            'representative'                       => 'A representative for the account'
        );

        if (isset($map[$requirement])) {
            return $map[$requirement];
        }

        $longest = '';

        foreach ($map as $prefix => $label) {
            if (strpos($requirement, $prefix.'.') === 0 && strlen($prefix) > strlen($longest)) {
                $longest = $prefix;
            }
        }

        if ($longest !== '') {
            return $map[$longest];
        }

        /** Unknown key: still readable, and it tells us a mapping is missing. */
        return ucfirst(trim(str_replace(array('_', '.'), ' ', $requirement)));
    }

    /**
     * Why Stripe has charges switched off, in its own words, when the requirement
     * list alone would not explain it - an account under review has nothing due.
     */
    public static function disabled_reason(array $account): string
    {
        $reason = (string) ($account['requirements']['disabled_reason'] ?? '');

        if ($reason === '') {
            return '';
        }

        return trim(str_replace(array('_', '.'), array(' ', ' '), $reason));
    }

    /**
     * Which payment methods an invoice may offer, decided by its currency.
     *
     * ACH debit settles only in US dollars: offering it on a CHF or EUR invoice
     * makes Stripe reject the invoice outright at creation, so a company billing
     * in anything but USD could never send at all. Card is always available; the
     * bank options are added only where the currency supports them.
     */
    public static function payment_methods_for($currency): array
    {
        $methods = array('card');

        switch (strtolower((string) $currency)) {

            case 'usd':
                $methods[] = 'us_bank_account';
                break;

            case 'eur':
                $methods[] = 'sepa_debit';
                break;

            case 'gbp':
                $methods[] = 'bacs_debit';
                break;
        }

        return $methods;
    }

    /* ------------------------------------------------------ connected scope */

    /**
     * A customer inside the company's own connected account - the client it bills.
     * Not to be confused with the platform customer above, which is the company
     * itself as a customer of CISO.aero, in a different account entirely.
     */
    public static function create_customer($account_id, $company_id, $client_id, $company_name, $email, $address_1, $address_2, $city, $state, $postal_code, $country): array
    {
        if (!self::configured() || !self::has_account($account_id, 'customer create')) {
            return array();
        }

        $params = array(
            'name'     => self::text($company_name),
            'metadata' => array(
                'app'        => 'ciso',
                'company_id' => (string) $company_id,
                'client_id'  => (string) $client_id
            )
        );

        if (self::text($email) !== '') {
            $params['email'] = self::text($email);
        }

        if (self::text($address_1) !== '') {

            $address = array(
                'line1'       => self::text($address_1),
                'line2'       => self::text($address_2),
                'city'        => self::text($city),
                'state'       => self::text($state),
                'postal_code' => self::text($postal_code)
            );

            $country_code = self::country_code($country);

            if ($country_code !== '') {
                $address['country'] = $country_code;
            }

            $params['address'] = $address;
        }

        try {
            $customer = self::client()->customers->create(
                $params,
                self::connected($account_id, 'cus-'.self::livemode().'-'.$company_id.'-'.$client_id)
            );
            return $customer->toArray();
        } catch (\Throwable $e) {
            self::fail('customer create', $e);
            return array();
        }
    }

    /**
     * A draft invoice on the connected account. collection_method send_invoice is
     * what makes Stripe email the client a hosted payment page rather than trying
     * to charge a stored card, which is the whole shape of this feature.
     */
    public static function create_invoice($account_id, $customer_id, $currency, $memo, $footer, $due_days, $due_date, $company_id, $invoice_id, $client_id, $project_id): array
    {
        if (!self::configured() || !self::has_account($account_id, 'invoice create')) {
            return array();
        }

        $params = array(
            'customer'          => $customer_id,
            'currency'          => strtolower($currency),
            'collection_method' => 'send_invoice',
            'auto_advance'      => true,
            'payment_settings'  => array(
                'payment_method_types' => self::payment_methods_for($currency)
            ),
            'metadata'          => array(
                'app'              => 'ciso',
                'company_id'       => (string) $company_id,
                'client_id'        => (string) $client_id,
                'project_id'       => (string) $project_id,
                'local_invoice_id' => (string) $invoice_id
            )
        );

        /**
         * Stripe takes one or the other, never both. A date the person chose is
         * preferred over a count of days, because the date is what the client reads
         * on the invoice and what they are held to.
         */
        if ((string) $due_date !== '') {
            $params['due_date'] = strtotime($due_date.' 23:59:59');
        } else {
            $params['days_until_due'] = (int) $due_days;
        }

        if (self::text($memo) !== '') {
            $params['description'] = self::text($memo);
        }

        if (self::text($footer) !== '') {
            $params['footer'] = self::text($footer);
        }

        try {
            $invoice = self::client()->invoices->create(
                $params,
                self::connected($account_id, 'inv-'.self::livemode().'-'.$company_id.'-'.$invoice_id)
            );
            return $invoice->toArray();
        } catch (\Throwable $e) {
            self::fail('invoice create', $e);
            return array();
        }
    }

    /**
     * One line on the invoice the client receives.
     *
     * quantity_decimal and unit_amount_decimal are used rather than the integer
     * quantity, because a fractional quantity cannot be expressed as an integer and
     * folding it into a single amount would print "Qty 1" on the client's invoice
     * beside a unit price that is really seven and a half units of something else.
     * The decimal fields let the Qty and Unit price columns state what was actually
     * agreed.
     *
     * unit_amount_decimal is denominated in the currency's minor unit, so a price of
     * 450.00 is sent as 45000 - the integer this application already stores, with no
     * conversion. Stripe multiplies out the line total, and apply_stripe_invoice
     * then takes the totals back from Stripe, so its arithmetic is the one of record
     * and the mirror cannot drift from the document the client holds.
     */
    public static function add_invoice_line($account_id, $stripe_invoice_id, $customer_id, $currency, $description, $quantity_milli, $unit_amount_cents, $amount_cents, $company_id, $invoice_id, $item_id): array
    {
        if (!self::configured() || !self::has_account($account_id, 'invoice line create')) {
            return array();
        }

        $quantity = rtrim(rtrim(number_format($quantity_milli / 1000, 3, '.', ''), '0'), '.');

        $params = array(
            'customer'            => $customer_id,
            'invoice'             => $stripe_invoice_id,
            'currency'            => strtolower($currency),
            'description'         => self::text($description),
            'quantity_decimal'    => ($quantity === '' ? '1' : $quantity),
            'unit_amount_decimal' => (string) ((int) $unit_amount_cents)
        );

        try {
            $item = self::client()->invoiceItems->create(
                $params,
                self::connected($account_id, 'ii-'.self::livemode().'-'.$company_id.'-'.$invoice_id.'-'.$item_id)
            );
            return $item->toArray();
        } catch (\Throwable $e) {
            self::fail('invoice line create', $e);
            return array();
        }
    }

    /** Finalization is the point at which Stripe takes ownership of the invoice. */
    public static function finalize_invoice($account_id, $stripe_invoice_id, $company_id, $invoice_id): array
    {
        if (!self::configured() || !self::has_account($account_id, 'invoice finalize')) {
            return array();
        }

        try {
            $invoice = self::client()->invoices->finalizeInvoice(
                $stripe_invoice_id,
                array('expand' => array('payments')),
                self::connected($account_id, 'fin-'.self::livemode().'-'.$company_id.'-'.$invoice_id)
            );
            return $invoice->toArray();
        } catch (\Throwable $e) {
            self::fail('invoice finalize', $e);
            return array();
        }
    }

    /**
     * Sends Stripe's own email. No idempotency key: a staff resend is an
     * intentional repeat of an identical request, and a fixed key would swallow it.
     */
    public static function send_invoice($account_id, $stripe_invoice_id): array
    {
        if (!self::configured() || !self::has_account($account_id, 'invoice send')) {
            return array();
        }

        try {
            $invoice = self::client()->invoices->sendInvoice(
                $stripe_invoice_id,
                array(),
                self::connected($account_id)
            );
            return $invoice->toArray();
        } catch (\Throwable $e) {
            self::fail('invoice send', $e);
            return array();
        }
    }

    public static function void_invoice($account_id, $stripe_invoice_id, $company_id, $invoice_id): array
    {
        if (!self::configured() || !self::has_account($account_id, 'invoice void')) {
            return array();
        }

        try {
            $invoice = self::client()->invoices->voidInvoice(
                $stripe_invoice_id,
                array(),
                self::connected($account_id, 'void-'.self::livemode().'-'.$company_id.'-'.$invoice_id)
            );
            return $invoice->toArray();
        } catch (\Throwable $e) {
            self::fail('invoice void', $e);
            return array();
        }
    }

    /**
     * Look for an invoice Stripe created when the local write that would have
     * recorded its id then failed. Matched on the metadata every created object
     * carries, which is the only thread back to the local record once the id is
     * lost - without it such an invoice is unrecoverable except by hand, and Stripe
     * may already have emailed it to the client.
     *
     * Listed rather than searched: Stripe's search index is eventually consistent
     * by about a minute, which is precisely the window this runs in.
     */
    public static function find_orphan_invoice($account_id, $customer_id, $company_id, $invoice_id, $created_after): array
    {
        if (!self::configured() || !self::has_account($account_id, 'orphan invoice lookup')) {
            return array();
        }

        try {

            $params = array(
                'limit'   => 100,
                'created' => array('gte' => (int) $created_after)
            );

            if ((string) $customer_id !== '') {
                $params['customer'] = $customer_id;
            }

            $invoices = self::client()->invoices->all($params, self::connected($account_id));

            foreach ($invoices->data as $invoice) {

                $metadata = $invoice->metadata;

                if ((string) ($metadata['local_invoice_id'] ?? '') === (string) $invoice_id
                    && (string) ($metadata['company_id'] ?? '') === (string) $company_id) {
                    return $invoice->toArray();
                }
            }

        } catch (\Throwable $e) {
            self::fail('orphan invoice lookup', $e);
        }

        return array();
    }

    /** A draft that was pushed but never finalized can still be removed outright. */
    public static function delete_draft_invoice($account_id, $stripe_invoice_id): bool
    {
        if (!self::configured() || !self::has_account($account_id, 'invoice delete')) {
            return false;
        }

        try {
            self::client()->invoices->delete($stripe_invoice_id, array(), self::connected($account_id));
            return true;
        } catch (\Throwable $e) {
            self::fail('invoice delete', $e);
            return false;
        }
    }

    /**
     * Webhook handlers re-read the object rather than trusting the event payload.
     * Stripe does not guarantee delivery order, and a live read is current by
     * definition, so ordering stops being something the handlers have to reason
     * about at all.
     */
    public static function retrieve_invoice($account_id, $stripe_invoice_id): array
    {
        if (!self::configured() || (string) $stripe_invoice_id === '') {
            return array();
        }

        if (!self::has_account($account_id, 'invoice retrieve')) {
            return array();
        }

        try {
            $invoice = self::client()->invoices->retrieve(
                (string) $stripe_invoice_id,
                array('expand' => array('payments')),
                self::connected($account_id)
            );
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
