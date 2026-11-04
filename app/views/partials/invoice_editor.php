    <div class="invoice-main">

        <div class="invoice-doc invoice-doc--header">
            <div class="masthead masthead--form">
            <div class="masthead__cell">
                <?php if (empty($this->invoice_in_modal)) { ?>
                <label for="client_id">Bill to</label>
                <select class="form-control" id="client_id">
                    <option value="0">Select a client</option>
                </select>
                <?php } else { ?>
                <span class="lbl">Bill to</span>
                <div class="masthead__value masthead__value--field"><?php echo htmlspecialchars(html_entity_decode((string) $this->client['company_name'], ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?></div>
                <select class="form-control" id="client_id" hidden>
                    <option value="0">Select a client</option>
                </select>
                <?php } ?>
            </div>
            <div class="masthead__cell">
                <label for="due_terms">Terms</label>
                <select id="due_terms" class="selectpicker" data-style="form-control" data-width="100%" data-container="body">
                    <option value="0">Due on receipt</option>
                    <option value="7">Net 7</option>
                    <option value="14">Net 14</option>
                    <option value="30" selected>Net 30</option>
                    <option value="45">Net 45</option>
                    <option value="60">Net 60</option>
                </select>
            </div>
            <div class="masthead__cell">
                <label for="repeats">Repeats</label>
                <select id="repeats" class="selectpicker" data-style="form-control" data-width="100%" data-container="body">
                    <option value="0" selected>One-off</option>
                    <option value="1">Recurring</option>
                </select>
                <div class="masthead__sub" id="schedule_summary" hidden></div>
                <button type="button" class="masthead__link" id="edit_schedule" hidden>Change Schedule</button>
            </div>
            <div class="masthead__cell">
                <span class="lbl">Due Date</span>
                <div class="masthead__value masthead__value--field" id="due_date_text"></div>
                <input type="hidden" id="due_date" value="">
                </div>
            </div>
        </div>

        <div class="invoice-doc invoice-doc--lines">

            <div class="lines__head">
                <h2 class="lines__title">Line Items</h2>
                <button type="button" id="add_line"><i class="fa-regular fa-plus"></i> Add Line</button>
            </div>

            <div class="table-wrap lines__scroll">
                <table class="data ledger">
                <thead>
                    <tr>
                        <th scope="col" class="idx"><span class="sr-only">Line</span></th>
                        <th scope="col">Description</th>
                        <th scope="col" class="num quantity">Quantity</th>
                        <th scope="col" class="num unit">Unit Amount</th>
                        <th scope="col" class="num discount">Discount</th>
                        <th scope="col" class="num discount-amount">Discount $</th>
                        <th scope="col" class="num amount">Amount</th>
                        <th scope="col" class="actions"><span class="sr-only">Remove</span></th>
                    </tr>
                </thead>
                <tbody id="line_rows"></tbody>
                <tfoot id="lines_totals" hidden>
                    <tr id="subtotal_row" hidden>
                        <td colspan="6" class="ledger__foot-label">Subtotal</td>
                        <td class="num ledger__amount" id="lines_subtotal">0.00</td>
                        <td></td>
                    </tr>
                    <tr id="discount_row" hidden>
                        <td colspan="6" class="ledger__foot-label">Discount</td>
                        <td class="num ledger__amount" id="discount_amount">0.00</td>
                        <td></td>
                    </tr>
                    <tr class="ledger__foot-total">
                        <td colspan="6" class="ledger__foot-label">Total <?php echo htmlspecialchars(strtoupper($this->invoice === null ? ($this->company['default_currency'] ?? 'usd') : $this->invoice['currency']), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="num ledger__amount" id="lines_total">0.00</td>
                        <td></td>
                    </tr>
                </tfoot>
                <tbody id="lines_empty">
                    <tr>
                        <td colspan="8">
                            <div class="lines__empty">
                                <p class="lines__empty-title">No Line Items</p>
                                <p class="lines__empty-text">Add a line for each service you are billing, then set its quantity and unit amount.</p>
                            </div>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>

        </div>

        <div class="invoice-doc invoice-doc--footer">
            <div class="commit mb-3">
                <div class="form-group commit__field">
                    <label for="invoice_footer">Footer Message <span class="label__note">printed at the foot of the invoice</span></label>
                    <input type="text" class="form-control" id="invoice_footer" placeholder="Thank you. Payment is accepted by card or bank transfer." value="<?php echo ($this->invoice === null ? '' : htmlspecialchars($this->invoice['invoice_footer'], ENT_QUOTES, 'UTF-8')); ?>">
                </div>
            </div>

        </div>

    </div>
