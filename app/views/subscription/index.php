<?php
$states = array(
    'active'             => 'Active',
    'trialing'           => 'Trial',
    'past_due'           => 'Past Due',
    'unpaid'             => 'Unpaid',
    'paused'             => 'Paused',
    'canceled'           => 'Cancelled',
    'incomplete'         => 'Incomplete',
    'incomplete_expired' => 'Expired'
);
$item    = $this->subscription['items']['data'][0] ?? array();
$price   = $item['price'] ?? array();
$has_sub = count($this->subscription) > 0 && in_array(($this->subscription['status'] ?? ''), array('active', 'trialing', 'past_due', 'unpaid', 'paused'), true);
$ending  = !empty($this->subscription['cancel_at_period_end']);
?>
<div class="page">
    <div class="page__head">
        <div>
            <h1 class="page__title">CISO.aero Subscription</h1>
        </div>
    </div>

    <?php if (!$this->configured) { ?>
    <div class="alert alert--warn" role="status">
        <i class="fa-regular fa-triangle-exclamation alert__icon" aria-hidden="true"></i>
        <p class="alert__title">Payments are not configured on this installation</p>
        <p class="alert__text">Subscription details cannot be read until payments are configured.</p>
    </div>
    <?php } ?>

    <?php if ($has_sub && !$ending && !empty($item['current_period_end'])) { ?>
    <div class="alert alert--info" role="status">
        <i class="fa-regular fa-circle-info alert__icon" aria-hidden="true"></i>
        <p class="alert__title">This subscription renews on <?php echo htmlspecialchars(date('m/d/Y', (int) $item['current_period_end']), ENT_QUOTES, 'UTF-8'); ?></p>
        <p class="alert__text">It bills automatically to the card on file until it is cancelled.</p>
    </div>
    <?php } ?>

    <?php if ($ending) { ?>
    <div class="alert alert--warn" role="status">
        <i class="fa-regular fa-triangle-exclamation alert__icon" aria-hidden="true"></i>
        <p class="alert__title">This subscription ends on <?php echo htmlspecialchars(date('m/d/Y', (int) ($item['current_period_end'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></p>
        <p class="alert__text">Access continues until then. Resume any time before that date to keep it running.</p>
    </div>
    <?php } ?>

    <div class="invoice-main">
        <section class="plans">
            <div class="plans__head">
                <h2 class="plans__title"><?php echo ($has_sub ? 'Change Plan' : 'Choose a Plan'); ?></h2>
            </div>

            <?php if (count($this->plans) === 0) { ?>
            <div class="invoice-doc">
                <div class="lines__empty">
                    <p class="lines__empty-title">No Plans Available</p>
                    <p class="lines__empty-text">There are no plans to subscribe to yet.</p>
                </div>
            </div>
            <?php } else { ?>
            <div class="plans__grid">
                <?php foreach ($this->plans as $plan) { ?>
                <?php $current = ($plan['price_id'] === ($price['id'] ?? '')); ?>
                <article class="plan<?php echo ($current ? ' plan--current' : ''); ?>">
                    <?php if ($current) { ?>
                    <span class="plan__flag">Current Plan</span>
                    <?php } ?>
                    <h3 class="plan__name"><?php echo htmlspecialchars($plan['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                    <p class="plan__price">
                        <span class="plan__figure"><?php echo htmlspecialchars(Money::symbol($plan['currency']).Money::figure($plan['unit_amount']), ENT_QUOTES, 'UTF-8'); ?></span>
                    </p>
                    <p class="plan__cycle"><?php echo htmlspecialchars(($plan['interval_count'] > 1 ? 'every '.$plan['interval_count'].' '.$plan['interval'].'s' : 'per '.$plan['interval']), ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php if ($plan['description'] !== '') { ?>
                    <p class="plan__desc"><?php echo htmlspecialchars($plan['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php } ?>
                    <?php if (count($plan['features']) > 0) { ?>
                    <ul class="plan__features">
                        <?php foreach ($plan['features'] as $feature) { ?>
                        <li><i class="fa-regular fa-check" aria-hidden="true"></i><?php echo htmlspecialchars($feature, ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php } ?>
                    </ul>
                    <?php } ?>
                    <div class="plan__act">
                        <?php if ($current && !$ending) { ?>
                        <button type="button" class="btn btn--secondary btn--block btn--destructive" id="do_cancel_open">Cancel Subscription</button>
                        <?php } elseif ($current) { ?>
                        <button type="button" class="btn btn--secondary btn--block" id="do_resume_card">Resume Subscription</button>
                        <?php } else { ?>
                        <button type="button" class="btn btn--primary btn--block" data-action="choose_plan" data-price="<?php echo htmlspecialchars($plan['price_id'], ENT_QUOTES, 'UTF-8'); ?>" data-name="<?php echo htmlspecialchars($plan['name'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo ($has_sub ? 'Switch to this Plan' : 'Subscribe'); ?></button>
                        <?php } ?>
                    </div>
                </article>
                <?php } ?>
            </div>
            <?php } ?>
        </section>

        <div class="invoice-doc">
            <div class="lines__head lines__head--tabs">
                <nav class="subtabs" role="tablist" aria-label="Billing records">
                    <button class="subtabs__link active" id="tab_cards_btn" data-bs-toggle="pill" data-bs-target="#tab_cards" type="button" role="tab" aria-controls="tab_cards" aria-selected="true">Payment Methods</button>
                    <button class="subtabs__link" id="tab_txn_btn" data-bs-toggle="pill" data-bs-target="#tab_txn" type="button" role="tab" aria-controls="tab_txn" aria-selected="false">Transaction History</button>
                    <button class="subtabs__link" id="tab_inv_btn" data-bs-toggle="pill" data-bs-target="#tab_inv" type="button" role="tab" aria-controls="tab_inv" aria-selected="false">Invoices</button>
                </nav>
                <button type="button" class="btn btn--secondary btn--sm" id="do_add_card"><i class="fa-regular fa-plus"></i> Add Card</button>
            </div>
            <div class="tab-content">
                <div class="tab-pane fade show active" id="tab_cards" role="tabpanel" aria-labelledby="tab_cards_btn">

            <?php if (count($this->cards) === 0) { ?>
            <div class="lines__empty">
                <p class="lines__empty-title">No Cards on File</p>
                <p class="lines__empty-text">Add a card to pay for a subscription.</p>
            </div>
            <?php } else { ?>
            <ul class="cards">
                <?php foreach ($this->cards as $card) { ?>
                <li class="card-row">
                    <div class="card-row__id">
                        <span class="card-row__brand"><?php echo htmlspecialchars(ucfirst($card['brand']), ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="card-row__digits">ending <?php echo htmlspecialchars($card['last4'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <span class="card-row__exp">Expires <?php echo htmlspecialchars(str_pad((string) $card['exp_month'], 2, '0', STR_PAD_LEFT).'/'.$card['exp_year'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <div class="card-row__act">
                        <?php if ($card['default']) { ?>
                        <span class="card-row__flag">Default</span>
                        <?php } else { ?>
                        <button type="button" class="btn btn--tertiary btn--sm" data-action="default_card" data-card="<?php echo htmlspecialchars($card['id'], ENT_QUOTES, 'UTF-8'); ?>">Make Default</button>
                        <button type="button" class="btn btn--tertiary btn--sm" data-action="remove_card" data-card="<?php echo htmlspecialchars($card['id'], ENT_QUOTES, 'UTF-8'); ?>" aria-label="Remove card"><i class="fa-regular fa-trash"></i></button>
                        <?php } ?>
                    </div>
                </li>
                <?php } ?>
            </ul>
            <?php } ?>
                </div>
                <div class="tab-pane fade" id="tab_txn" role="tabpanel" aria-labelledby="tab_txn_btn">

            <?php if (count($this->transactions) === 0) { ?>
            <div class="lines__empty">
                <p class="lines__empty-title">No Transactions Yet</p>
                <p class="lines__empty-text">Payments taken against this account appear here.</p>
            </div>
            <?php } else { ?>
            <div class="table-wrap lines__scroll">
                <table class="data ledger ledger--static">
                    <thead>
                        <tr>
                            <th scope="col">Paid with</th>
                            <th scope="col" class="num quantity">Date</th>
                            <th scope="col" class="num discount-amount">Status</th>
                            <th scope="col" class="num amount">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($this->transactions as $transaction) { ?>
                        <tr>
                            <td class="ledger__desc"><?php echo ($transaction['last4'] === '' ? 'Card' : htmlspecialchars(ucfirst($transaction['brand']).' ending '.$transaction['last4'], ENT_QUOTES, 'UTF-8')); ?></td>
                            <td class="num"><?php echo htmlspecialchars(date('m/d/Y', $transaction['created']), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="num"><?php echo htmlspecialchars(($transaction['refunded'] > 0 ? 'Refunded' : ucfirst($transaction['status'])), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="num ledger__amount"><?php echo htmlspecialchars(Money::symbol($transaction['currency']).Money::figure($transaction['amount']), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <?php } ?>
                </div>
                <div class="tab-pane fade" id="tab_inv" role="tabpanel" aria-labelledby="tab_inv_btn">

            <?php if (count($this->invoices) === 0) { ?>
            <div class="lines__empty">
                <p class="lines__empty-title">No Invoices Yet</p>
                <p class="lines__empty-text">Invoices appear here once the first one is issued.</p>
            </div>
            <?php } else { ?>
            <div class="table-wrap lines__scroll">
                <table class="data ledger ledger--static">
                    <thead>
                        <tr>
                            <th scope="col">Invoice</th>
                            <th scope="col" class="num quantity">Date</th>
                            <th scope="col" class="num discount-amount">Status</th>
                            <th scope="col" class="num amount">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($this->invoices as $invoice) { ?>
                        <tr>
                            <td class="ledger__desc"><?php echo htmlspecialchars($invoice['number'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="num"><?php echo htmlspecialchars(date('m/d/Y', $invoice['created']), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="num"><?php echo htmlspecialchars(ucfirst($invoice['status']), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="num ledger__amount"><?php echo htmlspecialchars(strtoupper($invoice['currency']).' '.Money::figure($invoice['total']), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <?php } ?>
                </div>
            </div>
        </div>

    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" id="pay_modal" tabindex="-1" aria-labelledby="pay_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="pay_modal_title">Confirm Subscription</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="pay_summary"></p>
                <div id="pay_element" class="pay__element"></div>
                <p class="import__hint">Card details go straight to our payment provider and are never sent to this application.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="do_pay" class="btn btn--primary">Confirm and Pay</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" id="card_modal" tabindex="-1" aria-labelledby="card_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="card_modal_title">Add a Card</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="card_element" class="pay__element"></div>
                <p class="import__hint">Card details go straight to our payment provider and are never sent to this application.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="do_save_card" class="btn btn--primary">Save Card</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" id="cancel_modal" tabindex="-1" aria-labelledby="cancel_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="cancel_modal_title">Cancel Subscription</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Stop this subscription from renewing?</p>
                <p class="import__hint">Access continues to the end of the period already paid for. It can be resumed at any point before then.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Keep Subscription</button>
                <button type="button" id="do_cancel" class="btn btn--destructive">Cancel Subscription</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" id="proration_modal" tabindex="-1" aria-labelledby="proration_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="proration_modal_title">Change Plan</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="proration_summary"></p>
                <p class="import__hint" id="proration_hint"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="do_change_plan" class="btn btn--primary">Confirm Change</button>
            </div>
        </div>
    </div>
</div>

<script src="https://js.stripe.com/v3/"></script>
<script>
$(document).ready(function () {

    var stripe = Stripe("<?php echo htmlspecialchars($this->publish_key, ENT_QUOTES, 'UTF-8'); ?>");
    var has_subscription = <?php echo ($has_sub ? 'true' : 'false'); ?>;
    var elements = null;

    function set_loading(target, loading) {
        if (loading) {
            $(target).addClass("is-loading").prop("disabled", true);
        } else {
            $(target).removeClass("is-loading").prop("disabled", false);
        }
    }

    function modal(id) {
        return bootstrap.Modal.getOrCreateInstance(document.getElementById(id));
    }

    /* Every plan button is held, not just the one pressed: disabling only the
       pressed button let a second click on another plan raise a second
       subscription two seconds behind the first. */
    function hold_plans(holding) {
        $('[data-action=choose_plan]').prop('disabled', holding);
    }

    function open_payment(client_secret, plan_name) {

        $('#pay_element').empty();

        elements = stripe.elements({
            clientSecret: client_secret,
            appearance: { theme: 'stripe' }
        });

        elements.create('payment').mount('#pay_element');

        $('#pay_summary').text('Subscribing to ' + plan_name + '.');

        modal('pay_modal').show();
    }

    var pending_change = null;

    /**
     * Starting a subscription goes straight through. Changing one is priced
     * first and agreed to, because a change part-way through a period is
     * prorated and the person should see that figure before it is charged.
     */
    $('[data-action=choose_plan]').click(function () {

        var button = $(this);

        set_loading(button, true);
        hold_plans(true);

        if (!has_subscription) {

            ApiDataSvc.apiCall('post', 'subscription_start', { price_id: button.data('price') }, function (data) {

                var obj = JSON.parse(data);

                set_loading(button, false);
                hold_plans(false);

                if (!obj.success) {
                    toastr.error(obj.message);
                    return;
                }

                if (!obj.client_secret) {
                    toastr.success(obj.message);
                    window.location.reload();
                    return;
                }

                open_payment(obj.client_secret, button.data('name'));
            });

            return;
        }

        ApiDataSvc.apiCall('post', 'subscription_preview', { price_id: button.data('price') }, function (data) {

            var obj = JSON.parse(data);

            set_loading(button, false);
            hold_plans(false);

            if (!obj.success) {
                toastr.error(obj.message);
                return;
            }

            pending_change = { price_id: button.data('price'), name: button.data('name'), proration_date: obj.proration_date };

            if (obj.is_downgrade) {
                $('#proration_summary').text(button.data('name') + ' starts on ' + obj.starts_on + '.');
                $('#proration_hint').text('The current plan continues until then, because this period is already paid for. It is not refunded or credited.');
                $('#do_change_plan').text('Schedule Change');
            } else if (obj.amount_due > 0) {
                $('#proration_summary').text('Switching to ' + button.data('name') + ' charges ' + obj.due_display + ' today.');
                $('#proration_hint').text('This covers the rest of the current period at the new rate. The card on file is charged straight away.');
                $('#do_change_plan').text('Confirm Change');
            } else {
                $('#proration_summary').text('Switch to ' + button.data('name') + '?');
                $('#proration_hint').text('Nothing is charged today. The new rate applies from the next invoice.');
                $('#do_change_plan').text('Confirm Change');
            }

            modal('proration_modal').show();
        });
    });

    $('#do_change_plan').click(function () {

        if (pending_change === null) {
            return;
        }

        set_loading('#do_change_plan', true);

        ApiDataSvc.apiCall('post', 'subscription_change', { price_id: pending_change.price_id, proration_date: pending_change.proration_date }, function (data) {

            var obj = JSON.parse(data);

            set_loading('#do_change_plan', false);

            if (!obj.success) {
                modal('proration_modal').hide();
                toastr.error(obj.message);
                return;
            }

            modal('proration_modal').hide();

            if (!obj.client_secret) {
                toastr.success(obj.message);
                window.location.reload();
                return;
            }

            open_payment(obj.client_secret, pending_change.name);
        });
    });

    $('#do_pay').click(function () {

        set_loading('#do_pay', true);

        stripe.confirmPayment({
            elements: elements,
            redirect: 'if_required'
        }).then(function (result) {

            set_loading('#do_pay', false);

            /* An intent that has already succeeded is a payment that went
               through, not one that failed - Stripe refuses the second confirm
               and its message reads as a decline. */
            if (result.error && result.error.code !== 'payment_intent_unexpected_state') {
                toastr.error(result.error.message);
                return;
            }

            modal('pay_modal').hide();
            toastr.success('Subscription Active');
            window.location.reload();
        });
    });

    var card_elements = null;

    /* Add Card sits in the shared tab bar, so it is shown only while the tab it
       acts on is the one open. */
    $('.subtabs__link').on('shown.bs.tab', function () {
        $('#do_add_card').toggle($(this).attr('id') === 'tab_cards_btn');
    });

    $('#do_add_card').click(function () {

        set_loading('#do_add_card', true);

        ApiDataSvc.apiCall('post', 'payment_method_add', {}, function (data) {

            var obj = JSON.parse(data);

            set_loading('#do_add_card', false);

            if (!obj.success) {
                toastr.error(obj.message);
                return;
            }

            $('#card_element').empty();

            card_elements = stripe.elements({
                clientSecret: obj.client_secret,
                appearance: { theme: 'stripe' }
            });

            card_elements.create('payment').mount('#card_element');

            modal('card_modal').show();
        });
    });

    $('#do_save_card').click(function () {

        set_loading('#do_save_card', true);

        stripe.confirmSetup({
            elements: card_elements,
            redirect: 'if_required'
        }).then(function (result) {

            set_loading('#do_save_card', false);

            if (result.error) {
                toastr.error(result.error.message);
                return;
            }

            modal('card_modal').hide();
            toastr.success('Card Saved');
            window.location.reload();
        });
    });

    $('[data-action=default_card]').click(function () {

        var button = $(this);

        set_loading(button, true);

        ApiDataSvc.apiCall('post', 'payment_method_default', { payment_method_id: button.data('card') }, function (data) {

            var obj = JSON.parse(data);

            set_loading(button, false);

            if (obj.success) {
                toastr.success(obj.message);
                window.location.reload();
            } else {
                toastr.error(obj.message);
            }
        });
    });

    $('[data-action=remove_card]').click(function () {

        var button = $(this);

        set_loading(button, true);

        ApiDataSvc.apiCall('post', 'payment_method_remove', { payment_method_id: button.data('card') }, function (data) {

            var obj = JSON.parse(data);

            set_loading(button, false);

            if (obj.success) {
                toastr.success(obj.message);
                window.location.reload();
            } else {
                toastr.error(obj.message);
            }
        });
    });

    $('#do_cancel_open').click(function () {
        modal('cancel_modal').show();
    });

    $('#do_cancel').click(function () {

        set_loading('#do_cancel', true);

        ApiDataSvc.apiCall('post', 'subscription_cancel', {}, function (data) {

            var obj = JSON.parse(data);

            set_loading('#do_cancel', false);

            if (obj.success) {
                toastr.success(obj.message);
                window.location.reload();
            } else {
                modal('cancel_modal').hide();
                toastr.error(obj.message);
            }
        });
    });

    $('#do_resume, #do_resume_card').click(function () {

        var resume_button = '#' + $(this).attr('id');

        set_loading(resume_button, true);

        ApiDataSvc.apiCall('post', 'subscription_resume', {}, function (data) {

            var obj = JSON.parse(data);

            set_loading(resume_button, false);

            if (obj.success) {
                toastr.success(obj.message);
                window.location.reload();
            } else {
                toastr.error(obj.message);
            }
        });
    });

});
</script>
