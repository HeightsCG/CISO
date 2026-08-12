<div class="page">
    <a class="page__back" href="/billing"><i class="fa-regular fa-arrow-left"></i> Back to Billing</a>

    <div class="page__head">
        <div>
            <h1 class="page__title"><?php echo ($this->invoice['invoice_number'] === '' ? 'Draft Invoice' : htmlspecialchars($this->invoice['invoice_number'], ENT_QUOTES, 'UTF-8')); ?></h1>
            <div class="invoice-ident">
                <span class="invoice-ident__client"><?php echo htmlspecialchars($this->invoice['client_name'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>
        <div class="page__actions">
            <?php if ($this->invoice['hosted_invoice_url'] !== '') { ?>
            <a class="btn btn--secondary" href="/billing/pdf/id/<?php echo (int) $this->invoice['id']; ?>" target="_blank" rel="noopener noreferrer"><i class="fa-regular fa-file-pdf"></i> View PDF</a>
            <?php } ?>
            <?php if (in_array($this->invoice['invoice_status'], array('Open', 'Uncollectible'), true)) { ?>
            <button type="button" class="btn btn--destructive" id="do_void_open"><i class="fa-regular fa-ban"></i> Void</button>
            <button type="button" class="btn btn--primary" id="do_send_open"><i class="fa-regular fa-paper-plane"></i> Resend Invoice</button>
            <?php } ?>
            <?php if ($this->invoice['invoice_status'] === 'Draft') { ?>
            <a class="btn btn--secondary" href="/billing/form/id/<?php echo (int) $this->invoice['id']; ?>"><i class="fa-regular fa-pen"></i> Edit Draft</a>
            <button type="button" class="btn btn--primary" id="do_send_open"><i class="fa-regular fa-paper-plane"></i> Send Invoice</button>
            <?php } ?>
        </div>
    </div>

    <?php if ($this->invoice['invoice_status'] === 'Finalizing') { ?>
    <div class="alert alert--critical" role="status">
        <i class="fa-regular fa-triangle-exclamation alert__icon" aria-hidden="true"></i>
        <p class="alert__title">This invoice is still being sent</p>
        <p class="alert__text">The last attempt was not confirmed. It is not retried automatically, because it may already have gone out and a second attempt would bill this client twice. Refresh in a moment to see where it landed.<?php echo ($this->invoice['finalize_error'] === '' ? '' : ' The payment provider said: '.htmlspecialchars($this->invoice['finalize_error'], ENT_QUOTES, 'UTF-8')); ?></p>
        <div class="alert__actions">
            <button type="button" class="btn btn--secondary btn--sm" id="do_refresh">Check for updates</button>
        </div>
    </div>
    <?php } elseif ($this->invoice['finalize_error'] !== '') { ?>
    <div class="alert alert--warn" role="status">
        <i class="fa-regular fa-triangle-exclamation alert__icon" aria-hidden="true"></i>
        <p class="alert__title">The last attempt to send did not go through</p>
        <p class="alert__text"><?php echo htmlspecialchars($this->invoice['finalize_error'], ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
    <?php } ?>

    <div class="invoice-main">

        <div class="invoice-doc invoice-doc--header">
            <div class="masthead masthead--invoice<?php echo ($this->invoice['invoice_status'] === 'Draft' ? '' : ' masthead--has-status'); ?>">
                <div class="masthead__cell">
                    <span class="lbl">Bill to</span>
                    <div class="masthead__value"><a href="/clients/detail/id/<?php echo (int) $this->invoice['client_id']; ?>"><?php echo htmlspecialchars($this->invoice['client_name'], ENT_QUOTES, 'UTF-8'); ?></a></div>
                    <div class="masthead__sub">
                        <?php echo ($this->invoice['client_address_1'] === '' ? '' : htmlspecialchars($this->invoice['client_address_1'], ENT_QUOTES, 'UTF-8').'<br>'); ?>
                        <?php echo ($this->invoice['client_address_2'] === '' ? '' : htmlspecialchars($this->invoice['client_address_2'], ENT_QUOTES, 'UTF-8').'<br>'); ?>
                        <?php echo htmlspecialchars(trim(implode(', ', array_filter(array($this->invoice['client_city'], trim($this->invoice['client_state'].' '.$this->invoice['client_postal_code'])))))  , ENT_QUOTES, 'UTF-8'); ?>
                        <?php echo ($this->invoice['client_country'] === '' ? '' : '<br>'.htmlspecialchars($this->invoice['client_country'], ENT_QUOTES, 'UTF-8')); ?>
                    </div>
                </div>
                <div class="masthead__cell">
                    <span class="lbl">Finance contact</span>
                    <?php if (trim($this->invoice['client_billing_name']) !== '' || trim($this->invoice['client_contact_name']) !== '') { ?>
                    <div class="masthead__value"><?php echo (trim($this->invoice['client_billing_name']) !== '' ? htmlspecialchars($this->invoice['client_billing_name'], ENT_QUOTES, 'UTF-8') : htmlspecialchars($this->invoice['client_contact_name'], ENT_QUOTES, 'UTF-8')); ?></div>
                    <?php } ?>
                    <div class="masthead__sub">
                        <?php echo (trim($this->invoice['client_billing_email']) !== '' ? htmlspecialchars($this->invoice['client_billing_email'], ENT_QUOTES, 'UTF-8') : (trim($this->invoice['client_contact_email']) !== '' ? '<a href="mailto:'.htmlspecialchars($this->invoice['client_contact_email'], ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars($this->invoice['client_contact_email'], ENT_QUOTES, 'UTF-8').'</a>' : '<span class="billto__warn">No email on file</span>')); ?>
                        <?php echo (trim($this->invoice['client_contact_phone']) === '' ? '' : '<br>'.htmlspecialchars($this->invoice['client_contact_phone'], ENT_QUOTES, 'UTF-8')); ?>
                    </div>
                </div>
                <?php if ($this->invoice['project_name'] !== null) { ?>
                <div class="masthead__cell">
                    <span class="lbl">Project</span>
                    <div class="masthead__value"><a href="/projects/detail/id/<?php echo (int) $this->invoice['project_id']; ?>"><?php echo htmlspecialchars($this->invoice['project_name'], ENT_QUOTES, 'UTF-8'); ?></a></div>
                    <?php if ($this->invoice['invoice_origin'] === 'Subscription') { ?>
                    <div class="masthead__sub">Recurring retainer</div>
                    <?php } ?>
                </div>
                <?php } ?>
                <?php if ($this->invoice['invoice_status'] !== 'Draft') { ?>
                <div class="masthead__cell">
                    <span class="lbl">Status</span>
                    <div class="masthead__value"><span class="badge <?php echo ($this->invoice['invoice_status_display'] === 'Paid' ? 'badge--active' : ($this->invoice['invoice_status_display'] === 'Overdue' || $this->invoice['invoice_status_display'] === 'Payment Failed' ? 'badge--critical' : ($this->invoice['invoice_status_display'] === 'Void' || $this->invoice['invoice_status_display'] === 'Written Off' ? 'badge--inactive' : ($this->invoice['invoice_status_display'] === 'Awaiting Payment' ? 'badge--onboarding' : 'badge--admin')))); ?>"><?php echo htmlspecialchars($this->invoice['invoice_status_display'], ENT_QUOTES, 'UTF-8'); ?></span></div>
                </div>
                <?php } ?>
                <?php if ($this->invoice['finalized_at'] === null) { ?>
                <div class="masthead__cell">
                    <span class="lbl">Created</span>
                    <div class="masthead__value"><?php echo htmlspecialchars($this->invoice['date_created_display'], ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                <?php } else { ?>
                <div class="masthead__cell">
                    <span class="lbl">Issued</span>
                    <div class="masthead__value"><?php echo htmlspecialchars($this->invoice['finalized_display'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="masthead__sub">Created <?php echo htmlspecialchars($this->invoice['date_created_display'], ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                <?php } ?>
                <?php if ($this->invoice['due_date'] !== null) { ?>
                <div class="masthead__cell masthead__cell--due">
                    <span class="lbl">Due</span>
                    <div class="masthead__value"><?php echo htmlspecialchars($this->invoice['due_date_display'], ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                <?php } ?>
            </div>
        </div>

        <div class="invoice-doc">
            <div class="lines__head">
                <h2 class="lines__title">Line Items</h2>
            </div>
            <div class="table-wrap lines__scroll">
                <table class="data ledger ledger--static">
                    <thead>
                        <tr>
                            <th scope="col" class="idx"><span class="sr-only">Line</span></th>
                            <th scope="col">Description</th>
                            <th scope="col" class="num quantity">Quantity</th>
                            <th scope="col" class="num unit">Unit Amount</th>
                            <th scope="col" class="num discount-amount">Discount</th>
                            <th scope="col" class="num subtotal">Discount Amount</th>
                            <th scope="col" class="num amount">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $line = 0; foreach ($this->items as $item) { $line++; ?>
                        <tr>
                            <td class="idx"><?php echo $line; ?></td>
                            <td class="ledger__desc"><?php echo htmlspecialchars($item['item_description'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="num"><?php echo htmlspecialchars(Money::format_quantity($item['quantity_milli']), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="num"><?php echo htmlspecialchars(Money::figure($item['unit_amount_cents']), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="num"><?php echo ($item['discount_type'] === 'Percent' ? htmlspecialchars(Money::format_percent($item['discount_percent_bp']).'%', ENT_QUOTES, 'UTF-8') : ($item['discount_type'] === 'Amount' ? htmlspecialchars(strtoupper($this->invoice['currency']).' '.Money::figure($item['discount_cents']), ENT_QUOTES, 'UTF-8') : '')); ?></td>
                            <td class="num"><?php echo ((int) $item['discount_cents'] === 0 ? '' : htmlspecialchars('-'.Money::figure($item['discount_cents']), ENT_QUOTES, 'UTF-8')); ?></td>
                            <td class="num ledger__amount"><?php echo htmlspecialchars(Money::figure($item['amount_cents']), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                        <?php } ?>
                        <?php if (count($this->items) === 0) { ?>
                        <tr>
                            <td colspan="7">
                                <div class="lines__empty">
                                    <p class="lines__empty-title">No Line Items</p>
                                    <p class="lines__empty-text">This invoice has no lines on it.</p>
                                </div>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                    <tfoot>
                        <?php if ((int) $this->invoice['discount_cents'] > 0) { ?>
                        <tr>
                            <td colspan="6" class="ledger__foot-label">Subtotal</td>
                            <td class="num ledger__amount"><?php echo htmlspecialchars(Money::figure($this->invoice['subtotal_cents']), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                        <tr>
                            <td colspan="6" class="ledger__foot-label">Discount</td>
                            <td class="num ledger__amount">-<?php echo htmlspecialchars(Money::figure($this->invoice['discount_cents']), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                        <?php } ?>
                        <tr>
                            <td colspan="6" class="ledger__foot-label">Total</td>
                            <td class="num ledger__amount"><?php echo htmlspecialchars(Money::figure($this->invoice['total_cents']), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                        <tr>
                            <td colspan="6" class="ledger__foot-label">Paid</td>
                            <td class="num ledger__amount"><?php echo htmlspecialchars(Money::figure($this->invoice['amount_paid_cents']), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                        <tr class="ledger__foot-total">
                            <td colspan="6" class="ledger__foot-label">Outstanding <?php echo htmlspecialchars(strtoupper($this->invoice['currency']), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="num ledger__amount"><?php echo htmlspecialchars(Money::figure($this->invoice['amount_due_cents']), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <?php if ($this->invoice['invoice_memo'] !== '') { ?>
        <div class="invoice-doc invoice-doc--footer">
            <div class="commit">
                <div class="form-group commit__field">
                    <span class="lbl">Description <span class="label__note">printed above the line items</span></span>
                    <p class="commit__text"><?php echo nl2br(htmlspecialchars($this->invoice['invoice_memo'], ENT_QUOTES, 'UTF-8')); ?></p>
                </div>
            </div>
        </div>
        <?php } ?>

        <?php if ($this->invoice['invoice_footer'] !== '') { ?>
        <div class="invoice-doc invoice-doc--footer">
            <div class="commit">
                <div class="form-group commit__field">
                    <span class="lbl">Footer Message <span class="label__note">printed at the foot of the invoice</span></span>
                    <p class="commit__text"><?php echo htmlspecialchars($this->invoice['invoice_footer'], ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </div>
        </div>
        <?php } ?>

    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" id="send_modal" tabindex="-1" aria-labelledby="send_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="send_modal_title"><?php echo ($this->invoice['invoice_status'] === 'Draft' ? 'Send Invoice' : 'Resend Invoice'); ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="send__lede">Send this invoice<?php echo ($this->invoice['invoice_status'] === 'Draft' ? '' : ' again'); ?> for <strong><?php echo htmlspecialchars($this->invoice['total_display'], ENT_QUOTES, 'UTF-8'); ?></strong>?</p>
                <div class="form-group mb-3">
                    <label for="recipient_name">Send to</label>
                    <input type="text" class="form-control" id="recipient_name" autocomplete="off" value="<?php echo htmlspecialchars(($this->invoice['client_billing_name'] !== '' ? $this->invoice['client_billing_name'] : $this->invoice['client_name']), ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="form-group mb-3">
                    <label for="recipient_email">Email</label>
                    <input type="email" class="form-control" id="recipient_email" autocomplete="off" value="<?php echo htmlspecialchars(($this->invoice['client_billing_email'] !== '' ? $this->invoice['client_billing_email'] : $this->invoice['client_contact_email']), ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <?php if ($this->invoice['invoice_status'] === 'Draft') { ?>
                <p class="import__hint">The invoice is emailed with a payment page and chased if it goes unpaid. Changing either also sets how this client's future invoices are addressed. Line items cannot be changed afterwards.</p>
                <?php } else { ?>
                <p class="import__hint">The same invoice is emailed again with its payment page. Send it as often as you need. Changing either field also sets how this client's future invoices are addressed.</p>
                <?php } ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="do_send" class="btn btn--primary"><?php echo ($this->invoice['invoice_status'] === 'Draft' ? 'Send Invoice' : 'Resend Invoice'); ?></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" id="void_modal" tabindex="-1" aria-labelledby="void_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="void_modal_title">Void Invoice</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Void this invoice? The client can no longer pay it and it stops being chased.</p>
                <p class="import__hint">Voiding is permanent. Raise a new invoice if it needs to be reissued.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="do_void" class="btn btn--destructive">Void Invoice</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {

    var invoice_id = <?php echo (int) $this->invoice['id']; ?>;
    var is_draft = <?php echo ($this->invoice['invoice_status'] === 'Draft' ? 'true' : 'false'); ?>;

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

    $('#do_refresh').click(function () {

        set_loading('#do_refresh', true);

        ApiDataSvc.apiCall('post', 'sync_invoice', { invoice_id: invoice_id }, function (data) {

            var obj = JSON.parse(data);

            set_loading('#do_refresh', false);

            if (obj.success) {
                toastr.success(obj.message);
                window.location.reload();
            } else {
                toastr.error(obj.message);
            }
        });
    });

    $('#do_send_open').click(function () {
        modal('send_modal').show();
    });

    $('#do_void_open').click(function () {
        modal('void_modal').show();
    });

    $('#do_send').click(function () {

        set_loading('#do_send', true);

        ApiDataSvc.apiCall('post', (is_draft ? 'send_invoice' : 'resend_invoice'), { invoice_id: invoice_id, recipient_name: $('#recipient_name').val().trim(), recipient_email: $('#recipient_email').val().trim() }, function (data) {

            var obj = JSON.parse(data);

            set_loading('#do_send', false);

            if (obj.success) {
                toastr.success(obj.message);
                window.location.reload();
            } else {
                modal('send_modal').hide();
                toastr.error(obj.message);
            }
        });
    });

    $('#do_void').click(function () {

        set_loading('#do_void', true);

        ApiDataSvc.apiCall('post', 'void_invoice', { invoice_id: invoice_id }, function (data) {

            var obj = JSON.parse(data);

            set_loading('#do_void', false);

            if (obj.success) {
                toastr.success(obj.message);
                window.location.reload();
            } else {
                modal('void_modal').hide();
                toastr.error(obj.message);
            }
        });
    });

});
</script>
