<?php
/**
 * Billing schema installer.
 *
 * Creates the invoice, line item, service catalogue, subscription and Stripe
 * event tables, and adds the billing columns to clients. Every statement is
 * guarded, so running it twice changes nothing and it is the same command on
 * development and production.
 *
 * Run from the CLI:
 *     APPLICATION_ENV=development php install_billing.php
 *
 * The schema of this application has until now lived only in the live database.
 * This file is the first written record of any of it, which is the other reason
 * it is checked in rather than applied by hand.
 *
 * Two settings still have to be made in the Stripe dashboard by hand, because
 * neither is reachable from the API:
 *   - the webhook endpoint must be pinned to API version 2026-06-24.dahlia
 *   - invoice reminders and dunning are account settings under Billing
 * Stripe sends every invoice email in this design, so the second one is the
 * feature working rather than a nicety.
 */

if (PHP_SAPI !== 'cli') {
    exit("This installer runs from the command line only.\n");
}

require_once __DIR__.'/libs/Classes/Main.php';

$env    = Main::get_environment();
$config = Main::get_config();

if ($env === '' || empty($config[$env]['db_name'])) {
    exit("Set APPLICATION_ENV to a section of app.ini that has db_ keys.\n");
}

$db = $config[$env];

$pdo = new PDO(
    $db['db_type'].':host='.$db['db_host'].';dbname='.$db['db_name'].';charset=utf8mb4',
    $db['db_user'],
    $db['db_pass'],
    array(
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    )
);

echo "Installing billing schema into '".$db['db_name']."' (".$env.")\n\n";

/* ------------------------------------------------------------------- helpers */

function run(PDO $pdo, string $label, string $sql): void
{
    $pdo->exec($sql);
    echo '  ok    '.$label."\n";
}

/**
 * information_schema rather than a try/catch on a duplicate-column error, so a
 * genuine failure still raises instead of being read as "already applied".
 */
function add_column(PDO $pdo, string $table, string $column, string $definition): void
{
    $check = $pdo->prepare(
        "SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE table_schema = DATABASE()
            and table_name = :t
            and column_name = :c"
    );

    $check->execute(array('t' => $table, 'c' => $column));

    if ((int) $check->fetchColumn() > 0) {
        echo '  skip  '.$table.'.'.$column." already present\n";
        return;
    }

    $pdo->exec('ALTER TABLE `'.$table.'` ADD COLUMN '.$definition);
    echo '  ok    '.$table.'.'.$column."\n";
}

function drop_column(PDO $pdo, string $table, string $column): void
{
    $check = $pdo->prepare(
        "SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE table_schema = DATABASE()
            and table_name = :t
            and column_name = :c"
    );

    $check->execute(array('t' => $table, 'c' => $column));

    if ((int) $check->fetchColumn() === 0) {
        echo '  skip  '.$table.'.'.$column." already absent\n";
        return;
    }

    $pdo->exec('ALTER TABLE `'.$table.'` DROP COLUMN `'.$column.'`');
    echo '  ok    dropped '.$table.'.'.$column."\n";
}

function add_index(PDO $pdo, string $table, string $index, string $definition): void
{
    $check = $pdo->prepare(
        "SELECT COUNT(*)
            FROM information_schema.STATISTICS
            WHERE table_schema = DATABASE()
            and table_name = :t
            and index_name = :i"
    );

    $check->execute(array('t' => $table, 'i' => $index));

    if ((int) $check->fetchColumn() > 0) {
        echo '  skip  '.$table.'.'.$index." already present\n";
        return;
    }

    $pdo->exec('ALTER TABLE `'.$table.'` ADD '.$definition);
    echo '  ok    '.$table.'.'.$index."\n";
}

/* ------------------------------------------------------------------ companies */

/**
 * A company sits on both sides of Stripe and the two must never be conflated:
 * it is a MERCHANT through a connected account of its own, where it invoices its
 * clients and receives their money, and it is a CUSTOMER of CISO.aero in the
 * platform's own account. Hence stripe_connect_account_id (acct_) beside
 * platform_customer_id (cus_) - and note clients.stripe_customer_id is a third
 * thing again, a customer living inside a connected account.
 */

echo "companies\n";

add_column($pdo, 'companies', 'stripe_connect_account_id',
    "`stripe_connect_account_id` varchar(64) DEFAULT NULL AFTER `country`");

add_column($pdo, 'companies', 'stripe_livemode',
    "`stripe_livemode` tinyint(1) NOT NULL DEFAULT '0' AFTER `stripe_connect_account_id`");

add_column($pdo, 'companies', 'stripe_connect_status',
    "`stripe_connect_status` enum('Not Connected','Onboarding','Connected','Restricted','Disconnected') NOT NULL DEFAULT 'Not Connected' AFTER `stripe_livemode`");

add_column($pdo, 'companies', 'stripe_charges_enabled',
    "`stripe_charges_enabled` tinyint(1) NOT NULL DEFAULT '0' AFTER `stripe_connect_status`");

add_column($pdo, 'companies', 'stripe_details_submitted',
    "`stripe_details_submitted` tinyint(1) NOT NULL DEFAULT '0' AFTER `stripe_charges_enabled`");

add_column($pdo, 'companies', 'stripe_payouts_enabled',
    "`stripe_payouts_enabled` tinyint(1) NOT NULL DEFAULT '0' AFTER `stripe_details_submitted`");

add_column($pdo, 'companies', 'stripe_requirements',
    "`stripe_requirements` text NOT NULL AFTER `stripe_payouts_enabled`");

add_column($pdo, 'companies', 'stripe_disabled_reason',
    "`stripe_disabled_reason` varchar(200) NOT NULL DEFAULT '' AFTER `stripe_requirements`");

add_column($pdo, 'companies', 'stripe_connected_at',
    "`stripe_connected_at` datetime DEFAULT NULL AFTER `stripe_requirements`");

add_column($pdo, 'companies', 'stripe_synced_at',
    "`stripe_synced_at` datetime DEFAULT NULL AFTER `stripe_connected_at`");

add_column($pdo, 'companies', 'default_currency',
    "`default_currency` char(3) NOT NULL DEFAULT 'usd' AFTER `stripe_synced_at`");

add_column($pdo, 'companies', 'platform_customer_id',
    "`platform_customer_id` varchar(64) DEFAULT NULL AFTER `default_currency`");

add_column($pdo, 'companies', 'platform_livemode',
    "`platform_livemode` tinyint(1) NOT NULL DEFAULT '0' AFTER `platform_customer_id`");

/**
 * Nullable under a unique key, against the NOT NULL convention everywhere else.
 * MySQL permits any number of NULLs under a unique index but only one empty
 * string, and the index is what stops two administrators onboarding at the same
 * moment from minting two Stripe objects for one company.
 */
add_index($pdo, 'companies', 'uq_companies_connect',
    'UNIQUE KEY `uq_companies_connect` (`stripe_connect_account_id`)');

add_index($pdo, 'companies', 'uq_companies_platform',
    'UNIQUE KEY `uq_companies_platform` (`platform_customer_id`)');

/* -------------------------------------------------------------------- clients */

echo "\nclients\n";

/**
 * Currency belongs to the company, not the client: a connected account settles in
 * its own currency, so a per-client currency could raise an invoice the account
 * cannot take payment in.
 */
drop_column($pdo, 'clients', 'billing_currency');

/**
 * Who the invoice is addressed to, when that is not the compliance contact -
 * an accounts payable desk rather than a person. Both fall back to the primary
 * contact when left empty.
 */
add_column($pdo, 'clients', 'billing_name',
    "`billing_name` varchar(200) NOT NULL DEFAULT '' AFTER `country`");

add_column($pdo, 'clients', 'billing_email',
    "`billing_email` varchar(255) NOT NULL DEFAULT '' AFTER `billing_name`");

/**
 * Nullable under a unique key, against the NOT NULL convention everywhere else
 * in this database. MySQL allows any number of NULLs under a unique index but
 * only one empty string, and the index is what stops two staff finalising at the
 * same moment from minting two Stripe customers for one client.
 */
add_column($pdo, 'clients', 'stripe_customer_id',
    "`stripe_customer_id` varchar(64) DEFAULT NULL AFTER `billing_email`");

add_column($pdo, 'clients', 'stripe_livemode',
    "`stripe_livemode` tinyint(1) NOT NULL DEFAULT '0' AFTER `stripe_customer_id`");

add_index($pdo, 'clients', 'uq_clients_stripe_customer',
    'UNIQUE KEY `uq_clients_stripe_customer` (`stripe_customer_id`)');

/* ------------------------------------------------------------------- invoices */

echo "\ntables\n";

run($pdo, 'invoices', "
CREATE TABLE IF NOT EXISTS `invoices` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `company_id` bigint unsigned NOT NULL,
    `client_id` bigint unsigned NOT NULL,
    `project_id` bigint unsigned NOT NULL DEFAULT '0',
    `subscription_id` bigint unsigned NOT NULL DEFAULT '0',
    `invoice_origin` enum('Manual','Subscription') NOT NULL DEFAULT 'Manual',
    `invoice_status` enum('Draft','Finalizing','Open','Paid','Void','Uncollectible') NOT NULL DEFAULT 'Draft',
    `payment_state` enum('None','Requires_Action','Processing','Succeeded','Failed') NOT NULL DEFAULT 'None',
    `payment_method_kind` enum('','Card','Bank','Other') NOT NULL DEFAULT '',
    `finalize_state` enum('Idle','Pending','Failed') NOT NULL DEFAULT 'Idle',
    `finalize_error` varchar(500) NOT NULL DEFAULT '',
    `payment_error` varchar(255) NOT NULL DEFAULT '',
    `invoice_number` varchar(64) NOT NULL DEFAULT '',
    `invoice_memo` text NOT NULL,
    `invoice_footer` varchar(500) NOT NULL DEFAULT '',
    `currency` char(3) NOT NULL DEFAULT 'usd',
    `subtotal_cents` bigint NOT NULL DEFAULT '0',
    `total_cents` bigint NOT NULL DEFAULT '0',
    `amount_paid_cents` bigint NOT NULL DEFAULT '0',
    `amount_due_cents` bigint NOT NULL DEFAULT '0',
    `due_days` smallint unsigned NOT NULL DEFAULT '30',
    `due_date` date DEFAULT NULL,
    `stripe_invoice_id` varchar(64) DEFAULT NULL,
    `stripe_customer_id` varchar(64) NOT NULL DEFAULT '',
    `stripe_payment_intent_id` varchar(64) NOT NULL DEFAULT '',
    `stripe_livemode` tinyint(1) NOT NULL DEFAULT '0',
    `stripe_event_created` bigint unsigned NOT NULL DEFAULT '0',
    `stripe_synced_at` datetime DEFAULT NULL,
    `hosted_invoice_url` text NOT NULL,
    `invoice_pdf_url` text NOT NULL,
    `finalized_at` datetime DEFAULT NULL,
    `paid_at` datetime DEFAULT NULL,
    `voided_at` datetime DEFAULT NULL,
    `created_by` bigint unsigned NOT NULL,
    `updated_by` bigint unsigned NOT NULL,
    `date_created` datetime NOT NULL,
    `date_updated` datetime NOT NULL,
    `deleted` tinyint(1) NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_invoices_stripe` (`stripe_invoice_id`),
    KEY `idx_invoices_company` (`company_id`,`deleted`),
    KEY `idx_invoices_client` (`client_id`,`company_id`,`deleted`),
    KEY `idx_invoices_project` (`project_id`,`deleted`),
    KEY `idx_invoices_status` (`company_id`,`invoice_status`,`deleted`),
    KEY `idx_invoices_subscription` (`subscription_id`,`deleted`),
    KEY `idx_invoices_payment_intent` (`stripe_payment_intent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

run($pdo, 'invoice_items', "
CREATE TABLE IF NOT EXISTS `invoice_items` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `invoice_id` bigint unsigned NOT NULL,
    `service_id` bigint unsigned NOT NULL DEFAULT '0',
    `line_source` enum('Local','Stripe') NOT NULL DEFAULT 'Local',
    `item_description` varchar(500) NOT NULL,
    `quantity_milli` bigint unsigned NOT NULL DEFAULT '1000',
    `unit_amount_cents` bigint NOT NULL DEFAULT '0',
    `amount_cents` bigint NOT NULL DEFAULT '0',
    `sort_order` smallint NOT NULL DEFAULT '0',
    `stripe_line_id` varchar(64) NOT NULL DEFAULT '',
    `updated_by` bigint unsigned NOT NULL,
    `date_created` datetime NOT NULL,
    `date_updated` datetime NOT NULL,
    `deleted` tinyint(1) NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`),
    KEY `idx_invoice_items_invoice` (`invoice_id`,`deleted`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

run($pdo, 'billing_services', "
CREATE TABLE IF NOT EXISTS `billing_services` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `company_id` bigint unsigned NOT NULL,
    `service_name` varchar(200) NOT NULL,
    `service_description` text NOT NULL,
    `default_unit_amount_cents` bigint NOT NULL DEFAULT '0',
    `currency` char(3) NOT NULL DEFAULT 'usd',
    `billing_interval` enum('One Time','Month','Quarter','Year') NOT NULL DEFAULT 'One Time',
    `service_status` enum('Active','Archived') NOT NULL DEFAULT 'Active',
    `stripe_product_id` varchar(64) NOT NULL DEFAULT '',
    `stripe_price_id` varchar(64) NOT NULL DEFAULT '',
    `created_by` bigint unsigned NOT NULL,
    `updated_by` bigint unsigned NOT NULL,
    `date_created` datetime NOT NULL,
    `date_updated` datetime NOT NULL,
    `deleted` tinyint(1) NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`),
    KEY `idx_billing_services_company` (`company_id`,`service_status`,`deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

run($pdo, 'subscriptions', "
CREATE TABLE IF NOT EXISTS `subscriptions` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `company_id` bigint unsigned NOT NULL,
    `client_id` bigint unsigned NOT NULL,
    `project_id` bigint unsigned NOT NULL DEFAULT '0',
    `service_id` bigint unsigned NOT NULL DEFAULT '0',
    `subscription_name` varchar(200) NOT NULL,
    `subscription_status` enum('Draft','Incomplete','Incomplete Expired','Trialing','Active','Past Due','Unpaid','Paused','Canceled') NOT NULL DEFAULT 'Draft',
    `provision_state` enum('Idle','Pending','Failed') NOT NULL DEFAULT 'Idle',
    `provision_error` varchar(500) NOT NULL DEFAULT '',
    `currency` char(3) NOT NULL DEFAULT 'usd',
    `unit_amount_cents` bigint NOT NULL DEFAULT '0',
    `quantity` int unsigned NOT NULL DEFAULT '1',
    `billing_interval` enum('Month','Quarter','Year') NOT NULL DEFAULT 'Month',
    `due_days` smallint unsigned NOT NULL DEFAULT '30',
    `start_date` date DEFAULT NULL,
    `current_period_end` datetime DEFAULT NULL,
    `cancel_at_period_end` tinyint(1) NOT NULL DEFAULT '0',
    `canceled_at` datetime DEFAULT NULL,
    `stripe_subscription_id` varchar(64) DEFAULT NULL,
    `stripe_subscription_item_id` varchar(64) NOT NULL DEFAULT '',
    `stripe_customer_id` varchar(64) NOT NULL DEFAULT '',
    `stripe_product_id` varchar(64) NOT NULL DEFAULT '',
    `stripe_price_id` varchar(64) NOT NULL DEFAULT '',
    `stripe_livemode` tinyint(1) NOT NULL DEFAULT '0',
    `stripe_event_created` bigint unsigned NOT NULL DEFAULT '0',
    `stripe_synced_at` datetime DEFAULT NULL,
    `created_by` bigint unsigned NOT NULL,
    `updated_by` bigint unsigned NOT NULL,
    `date_created` datetime NOT NULL,
    `date_updated` datetime NOT NULL,
    `deleted` tinyint(1) NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_subscriptions_stripe` (`stripe_subscription_id`),
    KEY `idx_subscriptions_company` (`company_id`,`deleted`),
    KEY `idx_subscriptions_client` (`client_id`,`company_id`,`deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

/**
 * No company_id, no deleted, no created_by: a webhook arrives with no session and
 * no tenant, and the tenant is only known once the event resolves to a local row.
 * It is an append-only machine log, the shape evidence_links already uses. The
 * raw payload is deliberately not stored - it holds client names and addresses,
 * it is retrievable from Stripe, and it would become the largest store of
 * personal data in the application.
 */
run($pdo, 'stripe_events', "
CREATE TABLE IF NOT EXISTS `stripe_events` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `stripe_event_id` varchar(64) NOT NULL,
    `event_type` varchar(120) NOT NULL,
    `event_created` bigint unsigned NOT NULL DEFAULT '0',
    `event_status` enum('Received','Processed','Ignored','Deferred','Failed') NOT NULL DEFAULT 'Received',
    `livemode` tinyint(1) NOT NULL DEFAULT '0',
    `object_id` varchar(64) NOT NULL DEFAULT '',
    `attempts` int unsigned NOT NULL DEFAULT '0',
    `error_message` varchar(500) NOT NULL DEFAULT '',
    `date_created` datetime NOT NULL,
    `date_updated` datetime NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_stripe_event` (`stripe_event_id`),
    KEY `idx_stripe_events_state` (`event_status`,`attempts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

/* --------------------------------------------------------- connected-account stamps */

/**
 * Stamped on the row rather than derived from the company at read time. A company
 * that disconnects and connects a DIFFERENT Stripe account would otherwise have
 * its historical invoices silently reinterpreted against the new one, so every
 * Stripe call about an invoice uses the account id carried by the invoice.
 */

echo "\nconnected-account stamps\n";

add_column($pdo, 'invoices', 'stripe_account_id',
    "`stripe_account_id` varchar(64) NOT NULL DEFAULT '' AFTER `stripe_customer_id`");

add_column($pdo, 'subscriptions', 'stripe_account_id',
    "`stripe_account_id` varchar(64) NOT NULL DEFAULT '' AFTER `stripe_customer_id`");

/** event.account - the connected account a Connect webhook originated from. */
add_column($pdo, 'stripe_events', 'account_id',
    "`account_id` varchar(64) NOT NULL DEFAULT '' AFTER `livemode`");

echo "\nDone.\n";
