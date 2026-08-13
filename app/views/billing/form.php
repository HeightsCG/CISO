<div class="page page--invoice">

    <a class="page__back" href="/billing"><i class="fa-regular fa-arrow-left"></i> Back to Billing</a>

    <div class="page__head">
        <div>
            <h1 class="page__title"><?php echo ($this->invoice === null ? 'New Invoice' : 'Edit Draft'); ?></h1>
        </div>
        <div class="page__actions">
            <?php if ($this->invoice !== null) { ?>
            <button type="button" class="btn btn--destructive" id="do_delete_open"><i class="fa-regular fa-trash"></i> Delete</button>
            <?php } ?>
        </div>
    </div>

    <div class="invoice-layout">
    <div class="invoice-main">

        <div class="invoice-doc invoice-doc--header">
            <div class="masthead masthead--form">
            <div class="masthead__cell">
                <label for="client_id">Bill to</label>
                <select class="form-control" id="client_id">
                    <option value="0">Select a client</option>
                </select>
            </div>
            <div class="masthead__cell">
                <label for="project_id">Project</label>
                <select class="form-control" id="project_id">
                    <option value="0">No project</option>
                </select>
            </div>
            <div class="masthead__cell">
                <label for="due_terms">Payment terms</label>
                <select class="form-control" id="due_terms">
                    <option value="0">Due on receipt</option>
                    <option value="7">Net 7</option>
                    <option value="14">Net 14</option>
                    <option value="30" selected>Net 30</option>
                    <option value="45">Net 45</option>
                    <option value="60">Net 60</option>
                    <option value="custom">Choose a date</option>
                </select>
            </div>
            <div class="masthead__cell">
                <span class="lbl" id="repeats_label">Repeats</span>
                <div class="segmented masthead__segmented" id="repeats" role="group" aria-labelledby="repeats_label">
                    <button type="button" class="segmented__btn is-on" data-value="0" aria-pressed="true">One-off</button>
                    <button type="button" class="segmented__btn" data-value="1" aria-pressed="false">Recurring</button>
                </div>
                <div class="masthead__sub" id="schedule_summary" hidden></div>
                <button type="button" class="masthead__link" id="edit_schedule" hidden>Change Schedule</button>
            </div>
            <div class="masthead__cell masthead__cell--due">
                <label for="due_date">Due</label>
                <div class="masthead__value" id="due_display"></div>
                <div class="masthead__sub" id="due_sub"></div>
                <input type="date" class="form-control" id="due_date" hidden value="<?php echo ($this->invoice === null || $this->invoice['due_date'] === null ? '' : htmlspecialchars($this->invoice['due_date'], ENT_QUOTES, 'UTF-8')); ?>">
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

    <aside class="summary">
        <h2 class="summary__heading">Summary</h2>
        <dl class="summary__facts">
            <div class="summary__fact">
                <dt>Status</dt>
                <dd><span class="badge badge--prospect">Draft</span></dd>
            </div>
            <div class="summary__fact" id="rail_client_fact" hidden>
                <dt>Bill To</dt>
                <dd id="rail_client"></dd>
            </div>
            <div class="summary__fact" id="rail_project_fact" hidden>
                <dt>Project Name</dt>
                <dd id="rail_project"></dd>
            </div>
            <div class="summary__fact" id="rail_due_fact" hidden>
                <dt>Due Date</dt>
                <dd id="rail_due"></dd>
            </div>
        </dl>

        <div class="summary__money" id="summary_money" hidden>
            <div class="summary__row" id="subtotal_row" hidden>
                <span>SubTotal</span>
                <span id="lines_subtotal">0.00</span>
            </div>
            <div class="summary__row summary__row--credit" id="discount_row" hidden>
                <span>Discount Amount</span>
                <span id="discount_amount">0.00</span>
            </div>
        </div>

        <div class="summary__total">
            <span>Total <?php echo htmlspecialchars(strtoupper($this->invoice === null ? ($this->company['default_currency'] ?? 'usd') : $this->invoice['currency']), ENT_QUOTES, 'UTF-8'); ?></span>
            <span id="lines_total">0.00</span>
        </div>

        <div class="summary__actions">
            <a class="btn btn--secondary" href="/billing">Cancel</a>
            <button type="button" id="do_save" class="btn btn--primary">Save Invoice</button>
        </div>
    </aside>

    </div>
</div>

<?php if ($this->invoice !== null) { ?>
<div class="modal fade" data-bs-backdrop="static" id="delete_modal" tabindex="-1" aria-labelledby="delete_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="delete_modal_title">Delete draft</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Delete this draft invoice? This cannot be undone.</p>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="do_delete" class="btn btn--destructive">Delete draft</button>
            </div>
        </div>
    </div>
</div>
<?php } ?>

<div class="modal fade" data-bs-backdrop="static" id="delete_line_modal" tabindex="-1" aria-labelledby="delete_line_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="delete_line_modal_title">Remove line</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="delete_line_text">Remove this line from the invoice?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="do_delete_line" class="btn btn--destructive">Remove line</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" id="schedule_modal" tabindex="-1" aria-labelledby="schedule_modal_title" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title" id="schedule_modal_title">Billing Schedule</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-12">
                        <div class="form-floating">
                            <select class="form-select" id="repeat_pattern">
                                <option value="1|Month" selected>Monthly</option>
                                <option value="3|Month">Quarterly</option>
                                <option value="1|Year">Annually</option>
                                <option value="custom">Custom</option>
                            </select>
                            <label class="form-label" for="repeat_pattern">Repeats</label>
                        </div>
                    </div>
                </div>
                <div class="row mb-3" id="custom_interval" hidden>
                    <div class="col-md-12">
                        <label class="form-label">Every</label>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="interval_count" inputmode="numeric" value="6">
                            <label class="form-label" for="interval_count">Interval</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating">
                            <select class="form-select" id="billing_interval" aria-label="Interval unit">
                                <option value="Day">Days</option>
                                <option value="Week">Weeks</option>
                                <option value="Month" selected>Months</option>
                                <option value="Year">Years</option>
                            </select>
                            <label class="form-label" for="billing_interval">Interval Unit</label>
                        </div>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-12">
                        <div class="form-floating">
                            <input type="date" class="form-control" id="start_date" value="<?php echo date('Y-m-d'); ?>">
                            <label class="form-label" for="start_date">Starts On</label>
                        </div>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-12">
                        <div class="form-floating">
                            <select class="form-select" id="ends_mode">
                                <option value="Never" selected>Never</option>
                                <option value="After">After</option>
                                <option value="On">On</option>
                            </select>
                            <label class="form-label" for="ends_mode">Ends On</label>
                        </div>
                    </div>
                </div>
                <div class="row mb-3" id="ends_after_row" hidden>
                    <div class="col-md-12">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="ends_after" inputmode="numeric" value="12" aria-label="Number of invoices">
                            <label class="form-label" for="ends_after">Number of Invoices</label>
                        </div>
                    </div>
                </div>
                <div class="row mb-3" id="ends_on_row" hidden>
                    <div class="col-md-12">
                        <div class="form-floating">
                            <input type="date" class="form-control" id="ends_on" aria-label="End date">
                            <label class="form-label" for="ends_on">End Date</label>
                        </div>
                    </div>
                </div>
                <p id="schedule_summary_text" aria-live="polite"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal" id="cancel_schedule">Cancel</button>
                <button type="button" id="save_schedule" class="btn btn--primary">Save</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {

    var invoice_id = <?php echo ($this->invoice === null ? 0 : (int) $this->invoice['id']); ?>;
    var selected_client = <?php echo ($this->invoice === null ? 0 : (int) $this->invoice['client_id']); ?>;
    var selected_project = <?php echo ($this->invoice === null ? 0 : (int) $this->invoice['project_id']); ?>;
    var currency = "<?php echo htmlspecialchars(strtoupper($this->invoice === null ? ($this->company['default_currency'] ?? 'usd') : $this->invoice['currency']), ENT_QUOTES, 'UTF-8'); ?>";
    var invoice_total = 0;
    var saved = { count: '1', unit: 'Month', start: "<?php echo date('Y-m-d'); ?>", mode: 'Never', after: '12', on: '' };
    var schedule_set = false;
    var clients = {};
    var next_idx = 0;
    var line_to_remove = null;

    function esc(value) {
        return $('<div>').text(value === null ? '' : value).html();
    }

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

    function to_cents(raw) {
        var value = String(raw === undefined || raw === null ? '' : raw).replace(/[,\s$]/g, '');
        var parts = /^(-?)(\d{1,9})(?:\.(\d{1,2}))?$/.exec(value);
        if (parts === null) {
            return null;
        }
        var fraction = parts[3] === undefined ? '00' : (parts[3] + '0').slice(0, 2);
        var cents = parseInt(parts[2], 10) * 100 + parseInt(fraction, 10);
        return parts[1] === '-' ? -cents : cents;
    }

    function to_quantity(raw) {
        var value = String(raw === undefined || raw === null ? '' : raw).replace(/[,\s]/g, '');
        var parts = /^(\d{1,5})(?:\.(\d{1,3}))?$/.exec(value);
        if (parts === null) {
            return null;
        }
        var fraction = parts[2] === undefined ? '000' : (parts[2] + '00').slice(0, 3);
        var milli = parseInt(parts[1], 10) * 1000 + parseInt(fraction, 10);
        return milli > 0 ? milli : null;
    }

    function money(cents) {
        var sign = cents < 0 ? '-' : '';
        var whole = Math.floor(Math.abs(cents) / 100);
        var rest = String(Math.abs(cents) % 100);
        while (rest.length < 2) {
            rest = '0' + rest;
        }
        return sign + whole.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',') + '.' + rest;
    }

    /** A percentage as typed into basis points, or null when it is not one. */
    function to_percent_bp(raw) {
        var value = String(raw === undefined || raw === null ? '' : raw).replace(/[,\s%]/g, '');
        if (value === '') {
            return 0;
        }
        var parts = /^(\d{1,3})(?:\.(\d{1,2}))?$/.exec(value);
        if (parts === null) {
            return null;
        }
        var fraction = parts[2] === undefined ? '00' : (parts[2] + '0').slice(0, 2);
        var bp = parseInt(parts[1], 10) * 100 + parseInt(fraction, 10);
        return bp > 10000 ? null : bp;
    }

    /**
     * The gross line, its discount, and what is left. The discount is rounded once
     * here and the net is gross minus that rounded figure, so a line's amount is
     * always exactly the two numbers above it and cannot drift by a cent. It is
     * capped at the line: a discount larger than the work is not a credit note.
     */
    function row_amount(row) {

        var quantity = to_quantity($(row).find('[data-field=quantity]').val());
        var unit = to_cents($(row).find('[data-field=unit_amount]').val());

        if (quantity === null || unit === null) {
            return null;
        }

        var gross = Math.round(quantity * unit / 1000);
        var chosen = $(row).find('[data-field=discount_unit]').val();
        var raw = $(row).find('[data-field=discount_value]').val().trim();
        var is_percent = chosen === 'Percent';
        var discount = 0;

        if (chosen !== 'None' && raw !== '') {

            if (is_percent) {
                var bp = to_percent_bp(raw);
                if (bp === null) {
                    return null;
                }
                discount = bp === 0 ? 0 : Math.floor((gross * bp + 5000) / 10000);
            } else {
                var amount = to_cents(raw);
                if (amount === null || amount < 0) {
                    return null;
                }
                discount = amount;
            }
        }

        if (discount > gross) {
            discount = gross;
        }

        return { gross: gross, discount: discount, net: gross - discount };
    }

    function recalculate() {

        var rows = $('#line_rows tr').length;

        $('#lines_empty').prop('hidden', rows > 0);

        var subtotal = 0;
        var discount = 0;

        $('#line_rows tr').each(function (position) {

            var line = row_amount(this);

            $(this).find('[data-cell=idx]').text(position + 1);
            $(this).find('[data-cell=amount]').text(money(line === null ? 0 : line.net));

            /* The rate says what was agreed, this column says what it costs. It is
               always written, including at zero, so the column reconciles down its
               whole length rather than showing gaps. */
            var chosen_unit = $(this).find('[data-field=discount_unit]').val();
            var value_input = $(this).find('[data-field=discount_value]');

            value_input.prop('disabled', chosen_unit === 'None');

            if (chosen_unit === 'None') {
                value_input.val('0').removeClass('is-invalid');
            }

            var line_discount = line === null ? 0 : line.discount;

            $(this).find('[data-cell=discount_amount]').text(line_discount === 0 ? money(0) : '-' + money(line_discount));

            if (line !== null) {
                subtotal += line.gross;
                discount += line.discount;
            }
        });

        $('#summary_money').prop('hidden', discount === 0);
        $('#subtotal_row').prop('hidden', discount === 0);
        $('#discount_row').prop('hidden', discount === 0);
        $('#lines_subtotal').text(money(subtotal));
        $('#discount_amount').text('-' + money(discount));
        $('#lines_total').text(money(subtotal - discount));

        invoice_total = subtotal - discount;

        if (schedule_set) {
            $('#schedule_summary').text(schedule_short());
        }

        render_masthead();
    }

    function add_line(description, quantity, unit_amount, discount_value, discount_type) {
        var idx = next_idx;
        next_idx++;
        var html = '<tr data-idx="' + idx + '">'
            + '<td class="idx" data-cell="idx"></td>'
            + '<td><input type="text" class="form-control form-control-lg" data-field="description" aria-label="Description" value="' + esc(description) + '"></td>'
            + '<td class="num"><input type="text" class="form-control form-control-lg" data-field="quantity" inputmode="decimal" aria-label="Quantity" value="' + esc(quantity) + '"></td>'
            + '<td class="num"><input type="text" class="form-control form-control-lg" data-field="unit_amount" inputmode="decimal" aria-label="Unit price" value="' + esc(unit_amount) + '"></td>'
            + '<td class="num">'
            + '<div class="input-group input-group-lg">'
            + '<input type="text" class="form-control form-control-lg" data-field="discount_value" inputmode="decimal" aria-label="Discount" value="' + esc(discount_value) + '">'
            + '<select class="form-select form-select-lg" data-field="discount_unit" aria-label="Discount unit">'
            + '<option value="None"' + (discount_type === 'Percent' || discount_type === 'Amount' ? '' : ' selected') + '>None</option>'
            + '<option value="Percent"' + (discount_type === 'Percent' ? ' selected' : '') + '>%</option>'
            + '<option value="Amount"' + (discount_type === 'Amount' ? ' selected' : '') + '>' + esc(currency) + '</option>'
            + '</select>'
            + '</div>'
            + '</td>'
            + '<td class="num lines__row-amount" data-cell="discount_amount">0.00</td>'
            + '<td class="num lines__row-amount" data-cell="amount">0.00</td>'
            + '<td class="actions text-center"><button type="button" class="btn btn--tertiary btn--sm" data-action="remove_line" aria-label="Remove line"><i class="fa-regular fa-trash"></i></button></td>'
            + '</tr>';
        $('#line_rows').append(html);
        recalculate();
    }

    /**
     * The summary reads the invoice back as it is being written, so the person can
     * see what the client will get without leaving the form. The due cell in the
     * masthead is written here too, because it is derived from the terms rather
     * than typed.
     */
    function render_masthead() {

        var client = clients[$('#client_id').val()];
        var due = $('#due_date').val();

        $('#rail_client_fact').prop('hidden', client === undefined);

        if (client !== undefined) {
            var name = client.billing_name !== '' ? client.billing_name : client.company_name;
            var email = client.billing_email !== '' ? client.billing_email : client.contact_email;
            var html = '<span class="rail__strong">' + esc(name) + '</span>';
            var city = [client.city, client.state].filter(function (p) { return p !== ''; }).join(', ');
            var region = [city, client.postal_code].filter(function (p) { return p !== ''; }).join(' ');
            [client.address_1, region, client.country].forEach(function (line) {
                if (line !== '' && line !== undefined) {
                    html += '<br>' + esc(line);
                }
            });
            if (email !== '') {
                html += '<br>' + esc(email);
            } else {
                html += '<br><span class="billto__warn">No email on file</span>';
            }
            $('#rail_client').html(html);
        }

        var has_project = parseInt($('#project_id').val(), 10) !== 0;

        $('#rail_project_fact').prop('hidden', !has_project);

        if (has_project) {
            $('#rail_project').text($('#project_id option:selected').text());
        }

        $('#rail_due_fact').prop('hidden', due === '');
        $('#due_display').text('');
        $('#due_sub').text('');

        if (due === '') {
            return;
        }

        var parts = due.split('-');
        var terms = $('#due_terms').val();
        var label = parts[1] + '/' + parts[2] + '/' + parts[0];
        var term_name = terms === 'custom' ? '' : $('#due_terms option:selected').text();

        $('#due_display').text(label);
        $('#due_sub').text(term_name);

        $('#rail_due').html(esc(label) + (term_name === '' ? '' : '<br><span class="rail__quiet">' + esc(term_name) + '</span>'));
    }

    function due_from_terms() {
        var terms = $('#due_terms').val();
        if (terms === 'custom') {
            $('#due_date').prop('hidden', false);
            $('#due_display').prop('hidden', true);
            if ($('#due_date').val() === '') {
                var fallback = new Date();
                fallback.setDate(fallback.getDate() + 30);
                $('#due_date').val(fallback.toISOString().slice(0, 10));
            }
            render_masthead();
            return;
        }
        $('#due_date').prop('hidden', true);
        $('#due_display').prop('hidden', false);
        var due = new Date();
        due.setDate(due.getDate() + parseInt(terms, 10));
        $('#due_date').val(due.toISOString().slice(0, 10));
        render_masthead();
    }

    function add_interval(date, unit, count) {
        var next = new Date(date.getTime());
        if (unit === 'Day' || unit === 'Week') {
            next.setDate(next.getDate() + ((unit === 'Week' ? 7 : 1) * count));
            return next;
        }
        var day = next.getDate();
        var months = unit === 'Year' ? (12 * count) : count;
        var target = new Date(next.getFullYear(), next.getMonth() + months, 1);
        var last = new Date(target.getFullYear(), target.getMonth() + 1, 0).getDate();
        target.setDate(Math.min(day, last));
        return target;
    }

    function schedule_state() {

        var pattern = $('#repeat_pattern').val();
        var custom = pattern === 'custom';
        var preset = custom ? [] : pattern.split('|');
        var count = custom ? parseInt($('#interval_count').val(), 10) : parseInt(preset[0], 10);
        var unit = custom ? $('#billing_interval').val() : preset[1];
        var start = $('#start_date').val();
        var mode = $('#ends_mode').val();

        return {
            valid: !isNaN(count) && count >= 1 && start !== '',
            custom: custom,
            count: count,
            unit: unit,
            noun: { Day: 'day', Week: 'week', Month: 'month', Year: 'year' }[unit] || 'month',
            start: start === '' ? new Date() : new Date(start + 'T00:00:00'),
            mode: mode,
            after: parseInt($('#ends_after').val(), 10),
            on: $('#ends_on').val()
        };
    }

    function schedule_short() {

        var state = schedule_state();

        if (!state.valid) {
            return '';
        }

        var name = state.custom
            ? ('Every ' + state.count + ' ' + state.noun + (state.count === 1 ? '' : 's'))
            : $('#repeat_pattern option:selected').text();

        if (state.mode === 'After' && !isNaN(state.after) && state.after > 0) {
            return name + ', ' + state.after + ' invoice' + (state.after === 1 ? '' : 's');
        }

        if (state.mode === 'On' && state.on !== '') {
            return name + ', until ' + format_date(state.on);
        }

        return name + ', until ended';
    }

    function format_date(value) {
        var parts = value.split('-');
        return parts[1] + '/' + parts[2] + '/' + parts[0];
    }

    function render_schedule() {
        render_summary();
        render_valid();
    }

    function long_date(value) {
        return new Date(value + 'T00:00:00').toLocaleDateString(undefined, { month: 'long', day: 'numeric', year: 'numeric' });
    }

    function render_summary() {

        var state = schedule_state();

        if ($('#start_date').val() === '') {
            $('#schedule_summary_text').text('Choose the date of the first invoice.');
            return;
        }

        if (isNaN(state.count) || state.count < 1) {
            $('#schedule_summary_text').text('Set how often this repeats.');
            return;
        }

        if (state.mode === 'After' && (isNaN(state.after) || state.after < 1)) {
            $('#schedule_summary_text').text('Set how many invoices to send.');
            return;
        }

        if (state.mode === 'On' && state.on === '') {
            $('#schedule_summary_text').text('Choose the date billing ends.');
            return;
        }

        var every = state.count === 1 ? ('every ' + state.noun) : ('every ' + state.count + ' ' + state.noun + 's');
        var ending = 'until ended';

        if (state.mode === 'After') {
            ending = 'for ' + state.after + ' invoice' + (state.after === 1 ? '' : 's');
        } else if (state.mode === 'On') {
            ending = 'until ' + long_date(state.on);
        }

        $('#schedule_summary_text').text('Invoices begin ' + long_date($('#start_date').val()) + ' and repeat ' + every + ' ' + ending + '.');
    }

    function schedule_valid() {

        var state = schedule_state();

        if ($('#start_date').val() === '') {
            return false;
        }

        if (isNaN(state.count) || state.count < 1) {
            return false;
        }

        if (state.mode === 'After' && (isNaN(state.after) || state.after < 1)) {
            return false;
        }

        return !(state.mode === 'On' && state.on === '');
    }

    function render_valid() {
        $('#save_schedule').prop('disabled', !schedule_valid());
    }

    function render_repeats() {

        var repeats = $('#repeats .segmented__btn.is-on').attr('data-value') === '1' && schedule_set;

        $('#schedule_summary').prop('hidden', !repeats).text(repeats ? schedule_short() : '');
        $('#edit_schedule').prop('hidden', !repeats);
        $('#do_save').text(repeats ? 'Save Recurring Invoice' : 'Save Invoice');
    }

    function open_schedule() {

        var pattern = saved.count + '|' + saved.unit;

        if ($('#repeat_pattern option[value="' + pattern + '"]').length === 0) {
            pattern = 'custom';
        }

        $('#repeat_pattern').val(pattern);
        $('#interval_count').val(saved.count === '' ? '6' : saved.count);
        $('#billing_interval').val(saved.unit === '' ? 'Month' : saved.unit);
        $('#start_date').val(saved.start);
        $('#ends_mode').val(saved.mode);
        $('#ends_after').val(saved.after);
        $('#ends_on').val(saved.on);

        render_custom();
        render_ends();
        render_schedule();

        modal('schedule_modal').show();
    }

    function render_custom() {
        $('#custom_interval').prop('hidden', $('#repeat_pattern').val() !== 'custom');
    }

    function render_ends() {

        var mode = $('#ends_mode').val();

        $('#ends_after_row').prop('hidden', mode !== 'After');
        $('#ends_on_row').prop('hidden', mode !== 'On');
    }

    $('#repeats').on('click', '.segmented__btn', function () {

        if ($(this).attr('data-value') === '1') {
            open_schedule();
            return;
        }

        schedule_set = false;
        set_repeats('0');
        render_repeats();
    });

    function set_repeats(value) {
        $('#repeats .segmented__btn').removeClass('is-on').attr('aria-pressed', 'false');
        $('#repeats .segmented__btn[data-value="' + value + '"]').addClass('is-on').attr('aria-pressed', 'true');
    }

    $('#edit_schedule').click(open_schedule);

    $('#repeat_pattern').change(function () {

        var current = $('#interval_count').val() + '|' + $('#billing_interval').val();

        if ($(this).val() === 'custom' && $('#repeat_pattern option[value="' + current + '"]').length > 0) {
            $('#interval_count').val('6');
            $('#billing_interval').val('Month');
        }

        render_custom();
        render_schedule();
    });

    $('#ends_mode').change(function () {
        render_ends();
        render_schedule();
    });

    $('#billing_interval').change(render_schedule);
    $('#start_date, #ends_on').on('change', function () {
        $(this).removeClass('is-invalid');
        render_schedule();
    });

    $('#interval_count, #ends_after').on('input', function () {
        numeric_only(this, 0);
        $(this).removeClass('is-invalid');
        render_schedule();
    });

    $('#schedule_modal').on('keydown', function (event) {

        if (event.key !== 'Enter' || $(event.target).is('textarea')) {
            return;
        }

        event.preventDefault();

        if (schedule_valid()) {
            $('#save_schedule').click();
        }
    });

    $('#save_schedule').click(function () {

        if (!schedule_valid()) {
            return;
        }

        var state = schedule_state();

        saved = {
            count: String(state.count),
            unit: state.unit,
            start: $('#start_date').val(),
            mode: state.mode,
            after: $('#ends_after').val(),
            on: $('#ends_on').val()
        };

        schedule_set = true;
        set_repeats('1');

        modal('schedule_modal').hide();
        render_repeats();
    });

    $('#due_terms').change(due_from_terms);
    $('#due_date').on('change', render_masthead);
    $('#project_id').change(render_masthead);

    $('#line_rows').on('change', 'select', function () {
        recalculate();
    });

    function numeric_only(field, decimals) {

        var value = field.value;
        var cleaned = value.replace(/[^0-9.]/g, '');
        var parts = cleaned.split('.');

        if (parts.length > 2) {
            cleaned = parts.shift() + '.' + parts.join('');
        }

        var dot = cleaned.indexOf('.');

        if (dot !== -1 && cleaned.length - dot - 1 > decimals) {
            cleaned = cleaned.slice(0, dot + decimals + 1);
        }

        if (cleaned === value) {
            return;
        }

        var caret = field.selectionStart - (value.length - cleaned.length);

        field.value = cleaned;
        field.setSelectionRange(caret < 0 ? 0 : caret, caret < 0 ? 0 : caret);
    }

    $('#line_rows').on('input', 'input', function () {

        var field = $(this).attr('data-field');

        if (field === 'quantity') {
            numeric_only(this, 3);
        } else if (field === 'unit_amount' || field === 'discount_value') {
            numeric_only(this, 2);
        }

        $(this).removeClass('is-invalid');
        recalculate();
    });

    $('#line_rows').on('click', '[data-action=remove_line]', function () {

        line_to_remove = $(this).closest('tr');

        var description = line_to_remove.find('[data-field=description]').val().trim();

        $('#delete_line_text').text(description === '' ? 'Remove this line from the invoice?' : 'Remove "' + description + '" from the invoice?');

        modal('delete_line_modal').show();
    });

    $('#do_delete_line').click(function () {

        if (line_to_remove !== null) {
            line_to_remove.remove();
            line_to_remove = null;
            recalculate();
        }

        modal('delete_line_modal').hide();
    });

    $('#add_line').click(function () {
        add_line('', '1', '0', '0', 'None');
        $('#line_rows tr').last().find('[data-field=description]').focus();
    });

    $('#client_id').change(function () {
        selected_project = 0;
        load_projects($(this).val());
        render_masthead();
    });

    function load_projects(client_id) {
        $('#project_id').html('<option value="0">No project</option>');
        if (parseInt(client_id, 10) === 0) {
            return;
        }
        ApiDataSvc.apiCall('post', 'load_client_projects', { client_id: client_id }, function (data) {
            var obj = JSON.parse(data);
            for (var i = 0; i < obj.length; i++) {
                $('#project_id').append('<option value="' + obj[i].id + '">' + esc(obj[i].project_name) + '</option>');
            }
            if (selected_project > 0) {
                $('#project_id').val(selected_project);
            }
        });
    }

    function load_clients() {
        ApiDataSvc.apiCall('post', 'load_clients', {}, function (data) {
            var obj = JSON.parse(data);
            for (var i = 0; i < obj.length; i++) {
                clients[obj[i].id] = obj[i];
                $('#client_id').append('<option value="' + obj[i].id + '">' + esc(obj[i].company_name) + '</option>');
            }
            if (selected_client > 0) {
                $('#client_id').val(selected_client);
                load_projects(selected_client);
            }
            render_masthead();
        });
    }

    $('#do_save').click(function () {

        $('.form-control').removeClass('is-invalid');

        var errors = 0;

        if (parseInt($('#client_id').val(), 10) === 0) {
            $('#client_id').addClass('is-invalid');
            errors++;
        }

        if ($('#due_terms').val() === 'custom' && $('#due_date').val() === '') {
            $('#due_date').addClass('is-invalid');
            errors++;
        }

        var lines = [];

        $('#line_rows tr').each(function () {

            var description = $(this).find('[data-field=description]');
            var quantity = $(this).find('[data-field=quantity]');
            var unit = $(this).find('[data-field=unit_amount]');

            if (description.val().trim() === '') {
                description.addClass('is-invalid');
                errors++;
            }

            if (to_quantity(quantity.val()) === null) {
                quantity.addClass('is-invalid');
                errors++;
            }

            if (to_cents(unit.val()) === null) {
                unit.addClass('is-invalid');
                errors++;
            }

            var discount = $(this).find('[data-field=discount_value]');
            var discount_unit = $(this).find('[data-field=discount_unit]').val();
            var discount_raw = discount.val().trim();

            if (discount_unit !== 'None' && discount_raw !== '') {
                var parsed = discount_unit === 'Amount' ? to_cents(discount_raw) : to_percent_bp(discount_raw);
                if (parsed === null || parsed < 0) {
                    discount.addClass('is-invalid');
                    errors++;
                }
            }

            lines.push({
                item_description: description.val().trim(),
                quantity: quantity.val().trim(),
                unit_amount: unit.val().trim(),
                discount_type: (discount_unit === 'None' || discount_raw === '' || parseFloat(discount_raw.replace(/[,\s%$]/g, '')) === 0 ? 'None' : discount_unit),
                discount_value: discount_raw
            });
        });

        if (lines.length === 0) {
            toastr.error('Add at least one line before saving.');
            return;
        }

        if (errors > 0) {
            $('.form-control.is-invalid').first().focus();
            toastr.error('Please fix the errors before continuing.');
            return;
        }

        var values = {
            invoice_id: invoice_id,
            client_id: $('#client_id').val(),
            project_id: $('#project_id').val(),
            due_days: ($('#due_terms').val() === 'custom' ? '' : $('#due_terms').val()),
            due_date: ($('#due_terms').val() === 'custom' ? $('#due_date').val() : ''),
            invoice_footer: $('#invoice_footer').val().trim(),
            repeats: $('#repeats .segmented__btn.is-on').attr('data-value'),
            billing_interval: $('#billing_interval').val(),
            interval_count: $('#interval_count').val().trim(),
            start_date: $('#start_date').val(),
            ends_mode: $('#ends_mode').val(),
            ends_after: $('#ends_after').val().trim(),
            ends_on: $('#ends_on').val(),
            lines_json: JSON.stringify(lines)
        };

        set_loading('#do_save', true);

        ApiDataSvc.apiCall('post', 'save_invoice', values, function (data) {

            var obj = JSON.parse(data);

            set_loading('#do_save', false);

            if (obj.success) {
                window.location.href = '/billing/invoice/id/' + obj.invoice_id;
            } else {
                toastr.error(obj.message);
            }
        });
    });

    $('#do_delete_open').click(function () {
        modal('delete_modal').show();
    });

    $('#do_delete').click(function () {

        set_loading('#do_delete', true);

        ApiDataSvc.apiCall('post', 'delete_invoice', { invoice_id: invoice_id }, function (data) {

            var obj = JSON.parse(data);

            set_loading('#do_delete', false);

            if (obj.success) {
                window.location.href = '/billing';
            } else {
                modal('delete_modal').hide();
                toastr.error(obj.message);
            }
        });
    });

    load_clients();

    <?php foreach ($this->items as $item) { ?>
    add_line("<?php echo htmlspecialchars(addslashes($item['item_description']), ENT_QUOTES, 'UTF-8'); ?>", "<?php echo Money::format_quantity($item['quantity_milli']); ?>", "<?php echo number_format($item['unit_amount_cents'] / 100, 2, '.', ''); ?>", "<?php echo ($item['discount_type'] === 'Percent' ? Money::format_percent($item['discount_percent_bp']) : ($item['discount_type'] === 'Amount' ? number_format($item['discount_cents'] / 100, 2, '.', '') : '0')); ?>", "<?php echo htmlspecialchars($item['discount_type'], ENT_QUOTES, 'UTF-8'); ?>");
    <?php } ?>

    <?php if ($this->invoice !== null && $this->invoice['due_date'] !== null) { ?>
    $('#due_terms').val('custom');
    <?php } elseif ($this->invoice !== null) { ?>
    $('#due_terms').val($('#due_terms option[value="<?php echo (int) $this->invoice['due_days']; ?>"]').length ? '<?php echo (int) $this->invoice['due_days']; ?>' : 'custom');
    <?php } ?>
    due_from_terms();

    render_repeats();
    recalculate();

});
</script>
