<div class="page">
    <a class="page__back" href="/billing"><i class="fa-regular fa-arrow-left"></i> Back to Billing</a>

    <div class="page__head">
        <div>
            <h1 class="page__title"><?php echo ($this->invoice['invoice_number'] === '' ? 'Draft Invoice' : htmlspecialchars($this->invoice['invoice_number'], ENT_QUOTES, 'UTF-8')); ?></h1>
            <div class="client__meta">
                <span class="badge <?php echo ($this->invoice['invoice_status_display'] === 'Paid' ? 'badge--active' : ($this->invoice['invoice_status_display'] === 'Overdue' || $this->invoice['invoice_status_display'] === 'Payment Failed' ? 'badge--critical' : ($this->invoice['invoice_status_display'] === 'Void' || $this->invoice['invoice_status_display'] === 'Written Off' ? 'badge--inactive' : ($this->invoice['invoice_status_display'] === 'Awaiting Payment' ? 'badge--onboarding' : 'badge--admin')))); ?>"><?php echo htmlspecialchars($this->invoice['invoice_status_display'], ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="client__segment"><?php echo htmlspecialchars($this->invoice['client_name'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>
        <div class="page__actions">
            <?php if ($this->invoice['invoice_status'] === 'Draft') { ?>
            <a class="btn btn--secondary" href="/billing/form/id/<?php echo (int) $this->invoice['id']; ?>"><i class="fa-regular fa-pen"></i> Edit Draft</a>
            <button type="button" class="btn btn--primary" id="do_send_open"><i class="fa-regular fa-paper-plane"></i> Send Invoice</button>
            <?php } ?>
            <?php if ($this->invoice['hosted_invoice_url'] !== '') { ?>
            <a class="btn btn--secondary" href="/billing/pdf/id/<?php echo (int) $this->invoice['id']; ?>" target="_blank" rel="noopener noreferrer"><i class="fa-regular fa-file-pdf"></i> View PDF</a>
            <?php } ?>
            <?php if (in_array($this->invoice['invoice_status'], array('Open', 'Uncollectible'), true)) { ?>
            <button type="button" class="btn btn--destructive" id="do_void_open"><i class="fa-regular fa-ban"></i> Void</button>
            <?php } ?>
        </div>
    </div>

    <?php if ($this->invoice['invoice_status'] === 'Finalizing') { ?>
    <div class="alert alert--critical" role="status">
        <i class="fa-regular fa-triangle-exclamation alert__icon" aria-hidden="true"></i>
        <p class="alert__title">This invoice is still being sent</p>
        <p class="alert__text">Stripe did not confirm the last attempt. It is not retried automatically, because it may already have gone out and a second attempt would bill this client twice. Refresh in a moment to see where it landed.<?php echo ($this->invoice['finalize_error'] === '' ? '' : ' Stripe said: '.htmlspecialchars($this->invoice['finalize_error'], ENT_QUOTES, 'UTF-8')); ?></p>
        <div class="alert__actions">
            <button type="button" class="btn btn--secondary btn--sm" id="do_refresh">Check with Stripe</button>
        </div>
    </div>
    <?php } elseif ($this->invoice['finalize_error'] !== '') { ?>
    <div class="alert alert--warn" role="status">
        <i class="fa-regular fa-triangle-exclamation alert__icon" aria-hidden="true"></i>
        <p class="alert__title">The last attempt to send did not go through</p>
        <p class="alert__text"><?php echo htmlspecialchars($this->invoice['finalize_error'], ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
    <?php } ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="panel record__panel">
                <div class="panel__head">
                    <h2 class="panel__title">Line Items</h2>
                </div>
                <div class="panel__body panel__body--flush">
                    <div class="table-wrap">
                        <table class="data">
                            <thead>
                                <tr>
                                    <th scope="col">Description</th>
                                    <th scope="col" class="num">Qty</th>
                                    <th scope="col" class="num">Unit Price</th>
                                    <th scope="col" class="num">Discount</th>
                                    <th scope="col" class="num">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($this->items as $item) { ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['item_description'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="num"><?php echo htmlspecialchars(Money::format_quantity($item['quantity_milli']), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="num"><?php echo htmlspecialchars(Money::format($item['unit_amount_cents'], $this->invoice['currency']), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="num"><?php echo ((int) $item['discount_cents'] === 0 ? '<span class="roster__none">&mdash;</span>' : htmlspecialchars(($item['discount_type'] === 'Percent' ? Money::format_percent($item['discount_percent_bp']).'% · ' : '').'-'.Money::format($item['discount_cents'], $this->invoice['currency']), ENT_QUOTES, 'UTF-8')); ?></td>
                                    <td class="num"><?php echo htmlspecialchars(Money::format($item['amount_cents'], $this->invoice['currency']), ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (count($this->items) === 0) { ?>
                    <p class="controls__empty">No line items on this invoice yet.</p>
                    <?php } ?>
                </div>
            </div>

            <?php if ($this->invoice['invoice_memo'] !== '') { ?>
            <div class="panel record__panel">
                <div class="panel__head">
                    <h2 class="panel__title">Notes</h2>
                </div>
                <div class="panel__body">
                    <p><?php echo nl2br(htmlspecialchars($this->invoice['invoice_memo'], ENT_QUOTES, 'UTF-8')); ?></p>
                </div>
            </div>
            <?php } ?>
        </div>

        <div class="col-lg-4">
            <div class="panel record__panel">
                <div class="panel__head">
                    <h2 class="panel__title">Summary</h2>
                </div>
                <div class="panel__body">
                    <dl class="datalist datalist--record">
                        <?php if ((int) $this->invoice['discount_cents'] > 0) { ?>
                        <dt>Subtotal</dt>
                        <dd><?php echo htmlspecialchars(Money::format($this->invoice['subtotal_cents'], $this->invoice['currency']), ENT_QUOTES, 'UTF-8'); ?></dd>
                        <dt>Discount</dt>
                        <dd>-<?php echo htmlspecialchars(Money::format($this->invoice['discount_cents'], $this->invoice['currency']), ENT_QUOTES, 'UTF-8'); ?></dd>
                        <?php } ?>
                        <dt>Total</dt>
                        <dd><?php echo htmlspecialchars($this->invoice['total_display'], ENT_QUOTES, 'UTF-8'); ?></dd>
                        <dt>Paid</dt>
                        <dd><?php echo htmlspecialchars($this->invoice['amount_paid_display'], ENT_QUOTES, 'UTF-8'); ?></dd>
                        <dt>Outstanding</dt>
                        <dd><?php echo htmlspecialchars($this->invoice['amount_due_display'], ENT_QUOTES, 'UTF-8'); ?></dd>
                        <dt>Due</dt>
                        <dd><?php echo ($this->invoice['due_date'] === null ? '<span class="roster__none">&mdash;</span>' : htmlspecialchars($this->invoice['due_date_display'], ENT_QUOTES, 'UTF-8')); ?></dd>
                    </dl>
                </div>
            </div>

            <div class="panel record__panel">
                <div class="panel__head">
                    <h2 class="panel__title">Record</h2>
                </div>
                <div class="panel__body">
                    <dl class="datalist datalist--record">
                        <dt>Client</dt>
                        <dd><a href="/clients/detail/id/<?php echo (int) $this->invoice['client_id']; ?>"><?php echo htmlspecialchars($this->invoice['client_name'], ENT_QUOTES, 'UTF-8'); ?></a></dd>
                        <dt>Project</dt>
                        <dd><?php echo ($this->invoice['project_name'] === null ? '<span class="roster__none">&mdash;</span>' : '<a href="/projects/detail/id/'.((int) $this->invoice['project_id']).'">'.htmlspecialchars($this->invoice['project_name'], ENT_QUOTES, 'UTF-8').'</a>'); ?></dd>
                        <dt>Origin</dt>
                        <dd><?php echo ($this->invoice['invoice_origin'] === 'Subscription' ? 'Recurring retainer' : 'One-off invoice'); ?></dd>
                        <dt>Created</dt>
                        <dd><?php echo htmlspecialchars($this->invoice['date_created_display'], ENT_QUOTES, 'UTF-8'); ?></dd>
                        <dt>Issued</dt>
                        <dd><?php echo ($this->invoice['finalized_at'] === null ? '<span class="roster__none">&mdash;</span>' : htmlspecialchars($this->invoice['finalized_display'], ENT_QUOTES, 'UTF-8')); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" id="send_modal" tabindex="-1" aria-labelledby="send_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="send_modal_title">Send Invoice</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Send this invoice to <strong><?php echo htmlspecialchars($this->invoice['client_name'], ENT_QUOTES, 'UTF-8'); ?></strong> for <strong><?php echo htmlspecialchars($this->invoice['total_display'], ENT_QUOTES, 'UTF-8'); ?></strong>?</p>
                <p class="import__hint">Stripe emails the invoice with a payment page and chases it if it goes unpaid. Line items cannot be changed afterwards.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="do_send" class="btn btn--primary">Send Invoice</button>
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
                <p>Void this invoice? The client can no longer pay it and Stripe stops chasing it.</p>
                <p class="import__hint">Voiding is permanent in Stripe. Raise a new invoice if it needs to be reissued.</p>
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

        ApiDataSvc.apiCall('post', 'send_invoice', { invoice_id: invoice_id }, function (data) {

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
