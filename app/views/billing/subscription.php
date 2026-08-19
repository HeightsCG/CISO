<?php
$is_draft = ($this->subscription['subscription_status'] === 'Draft');
$is_live  = in_array($this->subscription['subscription_status'], array('Active', 'Trialing', 'Past Due', 'Unpaid', 'Paused'), true);
$ending   = ((int) $this->subscription['cancel_at_period_end'] === 1);
?>
<div class="page">
    <a class="page__back" href="/billing"><i class="fa-regular fa-arrow-left"></i> Back to Billing</a>

    <div class="page__head">
        <div>
            <h1 class="page__title"><?php echo htmlspecialchars($this->subscription['subscription_name'], ENT_QUOTES, 'UTF-8'); ?></h1>
            <div class="invoice-ident">
                <span class="invoice-ident__client"><?php echo htmlspecialchars($this->subscription['client_name'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>
        <div class="page__actions">
            <?php if ($is_draft) { ?>
            <a class="btn btn--secondary" href="/billing/subscriptionform/id/<?php echo (int) $this->subscription['id']; ?>"><i class="fa-regular fa-pen"></i> Edit Draft</a>
            <button type="button" class="btn btn--primary" id="do_activate_open"><i class="fa-regular fa-play"></i> Start Subscription</button>
            <?php } ?>
            <?php if ($is_live && !$ending) { ?>
            <button type="button" class="btn btn--destructive" id="do_cancel_open"><i class="fa-regular fa-ban"></i> Cancel Subscription</button>
            <?php } ?>
        </div>
    </div>

    <?php if ($this->subscription['provision_state'] === 'Failed' && $this->subscription['provision_error'] !== '') { ?>
    <div class="alert alert--warn" role="status">
        <i class="fa-regular fa-triangle-exclamation alert__icon" aria-hidden="true"></i>
        <p class="alert__title">The last attempt to start this subscription did not go through</p>
        <p class="alert__text"><?php echo htmlspecialchars($this->subscription['provision_error'], ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
    <?php } elseif ($this->subscription['provision_state'] === 'Pending') { ?>
    <div class="alert alert--critical" role="status">
        <i class="fa-regular fa-triangle-exclamation alert__icon" aria-hidden="true"></i>
        <p class="alert__title">This subscription is still being started</p>
        <p class="alert__text">The last attempt was not confirmed. It is not retried automatically, because it may already have started and a second attempt would bill this client on two schedules.</p>
    </div>
    <?php } ?>

    <?php if ($ending && $this->subscription['current_period_end'] !== null) { ?>
    <div class="alert alert--warn" role="status">
        <i class="fa-regular fa-triangle-exclamation alert__icon" aria-hidden="true"></i>
        <p class="alert__title">This subscription ends on <?php echo htmlspecialchars($this->subscription['renews_display'], ENT_QUOTES, 'UTF-8'); ?></p>
        <p class="alert__text">The subscription is not billed again after that date. Invoices already raised are unaffected.</p>
    </div>
    <?php } ?>

    <div class="invoice-main">

        <div class="invoice-doc invoice-doc--header">
            <div class="masthead masthead--invoice masthead--has-status">
                <div class="masthead__cell">
                    <span class="lbl">Bill to</span>
                    <div class="masthead__value"><a href="/clients/detail/id/<?php echo (int) $this->subscription['client_id']; ?>"><?php echo htmlspecialchars($this->subscription['client_name'], ENT_QUOTES, 'UTF-8'); ?></a></div>
                    <?php $to = ($this->subscription['client_billing_email'] !== '' ? $this->subscription['client_billing_email'] : $this->subscription['client_contact_email']); ?>
                    <?php if (trim($to) !== '') { ?>
                    <div class="masthead__sub"><?php echo htmlspecialchars($to, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php } ?>
                </div>
                <?php if ($this->subscription['project_name'] !== null) { ?>
                <div class="masthead__cell">
                    <span class="lbl">Project</span>
                    <div class="masthead__value"><a href="/projects/detail/id/<?php echo (int) $this->subscription['project_id']; ?>"><?php echo htmlspecialchars($this->subscription['project_name'], ENT_QUOTES, 'UTF-8'); ?></a></div>
                </div>
                <?php } ?>
                <div class="masthead__cell">
                    <span class="lbl">Status</span>
                    <div class="masthead__value"><span class="badge <?php echo ($this->subscription['subscription_status_display'] === 'Active' ? 'badge--active' : ($this->subscription['subscription_status_display'] === 'Past Due' || $this->subscription['subscription_status_display'] === 'Unpaid' ? 'badge--critical' : ($this->subscription['subscription_status_display'] === 'Cancelled' ? 'badge--inactive' : 'badge--prospect'))); ?>"><?php echo htmlspecialchars($this->subscription['subscription_status_display'], ENT_QUOTES, 'UTF-8'); ?></span></div>
                </div>
                <div class="masthead__cell">
                    <span class="lbl">Bills</span>
                    <div class="masthead__value"><?php echo htmlspecialchars(($this->subscription['billing_interval'] === 'Quarter' ? 'Quarterly' : ($this->subscription['billing_interval'] === 'Year' ? 'Yearly' : 'Monthly')), ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="masthead__sub">Net <?php echo (int) $this->subscription['due_days']; ?></div>
                </div>
                <?php if ($this->subscription['current_period_end'] !== null) { ?>
                <div class="masthead__cell masthead__cell--due">
                    <span class="lbl"><?php echo ($ending ? 'Ends' : 'Renews'); ?></span>
                    <div class="masthead__value"><?php echo htmlspecialchars($this->subscription['renews_display'], ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                <?php } elseif ($this->subscription['start_date'] !== null) { ?>
                <div class="masthead__cell masthead__cell--due">
                    <span class="lbl">Starts</span>
                    <div class="masthead__value"><?php echo htmlspecialchars($this->subscription['start_date_display'], ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                <?php } ?>
            </div>
        </div>

        <div class="invoice-doc">
            <div class="lines__head">
                <h2 class="lines__title">What is billed</h2>
            </div>
            <div class="table-wrap lines__scroll">
                <table class="data ledger ledger--static">
                    <thead>
                        <tr>
                            <th scope="col">Description</th>
                            <th scope="col" class="num quantity">Quantity</th>
                            <th scope="col" class="num unit">Unit Amount</th>
                            <th scope="col" class="num amount">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="ledger__desc"><?php echo htmlspecialchars($this->subscription['subscription_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="num"><?php echo (int) $this->subscription['quantity']; ?></td>
                            <td class="num"><?php echo htmlspecialchars(Money::figure($this->subscription['unit_amount_cents']), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="num ledger__amount"><?php echo htmlspecialchars(Money::figure($this->subscription['unit_amount_cents'] * $this->subscription['quantity']), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="ledger__foot-total">
                            <td colspan="3" class="ledger__foot-label">Each period <?php echo htmlspecialchars(strtoupper($this->subscription['currency']), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="num ledger__amount"><?php echo htmlspecialchars(Money::figure($this->subscription['unit_amount_cents'] * $this->subscription['quantity']), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" id="activate_modal" tabindex="-1" aria-labelledby="activate_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="activate_modal_title">Start Subscription</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="send__lede">Bill <strong><?php echo htmlspecialchars($this->subscription['client_name'], ENT_QUOTES, 'UTF-8'); ?></strong> <strong><?php echo htmlspecialchars($this->subscription['amount_display'], ENT_QUOTES, 'UTF-8'); ?></strong> <?php echo htmlspecialchars(($this->subscription['billing_interval'] === 'Quarter' ? 'quarterly' : ($this->subscription['billing_interval'] === 'Year' ? 'yearly' : 'monthly')), ENT_QUOTES, 'UTF-8'); ?>?</p>
                <p class="import__hint">An invoice is raised and emailed to the client every period until the subscription is cancelled. The amount cannot be changed afterwards.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="do_activate" class="btn btn--primary">Start Subscription</button>
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
                <p>Stop billing this client on this subscription?</p>
                <p class="import__hint">It runs to the end of the period already invoiced and is not billed again. Invoices already raised are unaffected.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Keep subscription</button>
                <button type="button" id="do_cancel" class="btn btn--destructive">Cancel Subscription</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {

    var subscription_id = <?php echo (int) $this->subscription['id']; ?>;

    $('#do_activate_open').click(function () {
        modal('activate_modal').show();
    });

    $('#do_cancel_open').click(function () {
        modal('cancel_modal').show();
    });

    $('#do_activate').click(function () {

        set_loading('#do_activate', true);

        ApiDataSvc.apiCall('post', 'activate_subscription', { subscription_id: subscription_id }, function (data) {

            var obj = JSON.parse(data);

            set_loading('#do_activate', false);

            if (obj.success) {
                toastr.success(obj.message);
                window.location.reload();
            } else {
                modal('activate_modal').hide();
                toastr.error(obj.message);
            }
        });
    });

    $('#do_cancel').click(function () {

        set_loading('#do_cancel', true);

        ApiDataSvc.apiCall('post', 'cancel_subscription', { subscription_id: subscription_id }, function (data) {

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

});
</script>
