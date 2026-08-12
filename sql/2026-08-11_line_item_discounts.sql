-- Line item discounts
--
-- A discount is negotiated against the work it applies to, so it is held per line
-- rather than per invoice. Each line carries the kind of discount, the input the
-- staff member typed, and the money that input resolved to; the invoice carries
-- only the sum, so the two can never disagree.
--
-- amount_cents on a line becomes the NET the client pays for it. The gross is
-- quantity_milli x unit_amount_cents and is not stored, because it is exact
-- integer arithmetic and a stored copy could drift from the columns beside it.
--
-- Safe to run more than once: every statement is guarded, so re-running reports
-- the columns as already present and changes nothing.

SET @schema := DATABASE();

-- ---------------------------------------------------------------- invoice_items

SET @sql := (
    SELECT IF(COUNT(*) > 0,
        'SELECT ''skip  invoice_items.discount_type already present''',
        'ALTER TABLE `invoice_items` ADD COLUMN `discount_type` enum(''None'',''Percent'',''Amount'') NOT NULL DEFAULT ''None'' AFTER `unit_amount_cents`')
    FROM information_schema.COLUMNS
    WHERE table_schema = @schema and table_name = 'invoice_items' and column_name = 'discount_type'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Basis points, not a decimal rate: 12.5% has no exact binary form, and the
-- discount it produces has to reconcile with Stripe's own to the cent.
SET @sql := (
    SELECT IF(COUNT(*) > 0,
        'SELECT ''skip  invoice_items.discount_percent_bp already present''',
        'ALTER TABLE `invoice_items` ADD COLUMN `discount_percent_bp` int unsigned NOT NULL DEFAULT ''0'' AFTER `discount_type`')
    FROM information_schema.COLUMNS
    WHERE table_schema = @schema and table_name = 'invoice_items' and column_name = 'discount_percent_bp'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(COUNT(*) > 0,
        'SELECT ''skip  invoice_items.discount_cents already present''',
        'ALTER TABLE `invoice_items` ADD COLUMN `discount_cents` bigint NOT NULL DEFAULT ''0'' AFTER `discount_percent_bp`')
    FROM information_schema.COLUMNS
    WHERE table_schema = @schema and table_name = 'invoice_items' and column_name = 'discount_cents'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- One coupon per discounted line, minted at send. Recorded so a retry cannot mint
-- a second one against an invoice Stripe has already finalized.
SET @sql := (
    SELECT IF(COUNT(*) > 0,
        'SELECT ''skip  invoice_items.stripe_coupon_id already present''',
        'ALTER TABLE `invoice_items` ADD COLUMN `stripe_coupon_id` varchar(64) NOT NULL DEFAULT '''' AFTER `stripe_line_id`')
    FROM information_schema.COLUMNS
    WHERE table_schema = @schema and table_name = 'invoice_items' and column_name = 'stripe_coupon_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- --------------------------------------------------------------------- invoices

-- The roll-up the summary and the client's copy both read. It is the sum of the
-- lines, never a figure entered on its own.
SET @sql := (
    SELECT IF(COUNT(*) > 0,
        'SELECT ''skip  invoices.discount_cents already present''',
        'ALTER TABLE `invoices` ADD COLUMN `discount_cents` bigint NOT NULL DEFAULT ''0'' AFTER `subtotal_cents`')
    FROM information_schema.COLUMNS
    WHERE table_schema = @schema and table_name = 'invoices' and column_name = 'discount_cents'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------------- backfill

-- Existing lines have no discount, so their net already equals their gross and
-- the invoice totals already reconcile. Nothing needs restating; this only marks
-- the discount kind on any row that somehow carries a rate without one.
UPDATE `invoice_items`
    SET `discount_type` = 'Percent'
    WHERE `discount_percent_bp` > 0 and `discount_type` = 'None';
