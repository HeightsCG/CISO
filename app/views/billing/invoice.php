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
        <?php if ($this->invoice['invoice_status'] === 'Draft') { ?>
        <div class="page__actions">
            <a class="btn btn--primary" href="/billing/form/id/<?php echo (int) $this->invoice['id']; ?>"><i class="fa-regular fa-pen"></i> Edit Draft</a>
        </div>
        <?php } ?>
    </div>

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
                                    <th scope="col" class="num">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($this->items as $item) { ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['item_description'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="num"><?php echo htmlspecialchars(Money::format_quantity($item['quantity_milli']), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="num"><?php echo htmlspecialchars(Money::format($item['unit_amount_cents'], $this->invoice['currency']), ENT_QUOTES, 'UTF-8'); ?></td>
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
