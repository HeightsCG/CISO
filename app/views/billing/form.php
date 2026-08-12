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
                <label for="repeats">Repeats</label>
                <select class="form-control" id="repeats">
                    <option value="0">One-off Invoice</option>
                    <option value="1">On a Schedule</option>
                </select>
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
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content sked">
            <div class="modal-header">
                <h2 class="modal-title" id="schedule_modal_title">Billing Schedule</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-footer sked__footer">
                <div class="sked__actions">
                    <button type="button" class="btn btn--secondary" data-bs-dismiss="modal" id="cancel_schedule">Cancel</button>
                    <button type="button" id="save_schedule" class="btn btn--primary">Save schedule</button>
                </div>
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

        if (schedule_set) {
            $('#schedule_summary').text(schedule_sentence());
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


    /**
     * Terms and a due date are one fact, so there is one control for it. Net terms
     * state the date underneath; choosing a date replaces the terms rather than
     * sitting beside them, because two inputs for one fact can disagree and the
     * client only ever sees the date.
     */
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


    /**
     * A repeating invoice is not a different record, it is this invoice on a
     * schedule. Everything about that schedule is stated back in a sentence,
     * because "every 3 months, ending after 12 invoices" is a commitment worth
     * reading before it is agreed - and the person setting it up is the only one
     * who can catch it being wrong.
     */
    /**
     * Month arithmetic that does not silently roll over: billing on the 31st in a
     * 30-day month lands on the 30th, not the 1st of the next. Getting this wrong
     * moves a client's invoice into the following period.
     */
    function add_interval(date, unit, count) {

        var next = new Date(date.getTime());

        if (unit === 'Week') {
            next.setDate(next.getDate() + (7 * count));
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

        var count = parseInt($('#interval_count').val(), 10);
        var unit = $('#billing_interval').val();
        var start = $('#start_date').val();
        var mode = $('#ends_mode .segmented__btn.is-on').attr('data-value');

        return {
            valid: !isNaN(count) && count >= 1,
            count: count,
            unit: unit,
            noun: { Week: 'week', Month: 'month', Year: 'year' }[unit] || 'month',
            start: start === '' ? new Date() : new Date(start + 'T00:00:00'),
            explicit_start: start !== '',
            mode: mode,
            after: parseInt($('#ends_after').val(), 10),
            on: $('#ends_on').val()
        };
    }

    function stop(text, modifier) {
        $('#schedule_dates').append('<li class="sked__stop' + (modifier === '' ? '' : ' sked__stop--' + modifier) + '">' + esc(text) + '</li>');
    }

    /**
     * The dates themselves, computed the same way the schedule will run. This is
     * the part worth reading: "every 3 months from the 31st" is easy to agree to
     * and hard to picture, and a wrong interval is obvious the moment the second
     * date is a week away rather than a quarter.
     *
     * The tail is a position on the track like any other, so what comes after the
     * dates shown reads as part of the same sequence.
     */
    function render_dates() {

        var state = schedule_state();

        $('#schedule_dates').empty();

        if (!state.valid) {
            stop('Set how often this bills.', 'note');
            return;
        }

        var limit = 5;
        var ends_on = state.mode === 'On' && state.on !== '' ? new Date(state.on + 'T00:00:00') : null;
        var total = state.mode === 'After' && !isNaN(state.after) && state.after > 0 ? state.after : null;
        var when = state.start;
        var shown = 0;

        while (shown < limit) {

            if (ends_on !== null && when > ends_on) {
                break;
            }

            if (total !== null && shown >= total) {
                break;
            }

            stop(when.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }), shown === 0 ? 'first' : '');

            when = add_interval(when, state.unit, state.count);
            shown++;
        }

        if (shown === 0) {
            stop('That end date is before the first invoice.', 'note');
            return;
        }

        if (total !== null && total > shown) {
            stop('and ' + (total - shown) + ' more', 'tail');
            return;
        }

        if (total === null && ends_on === null) {
            stop('continuing every ' + (state.count === 1 ? state.noun : state.count + ' ' + state.noun + 's'), 'tail');
        }
    }

    function schedule_sentence() {

        var state = schedule_state();

        if (!state.valid) {
            return 'Set how often this bills.';
        }

        var every = state.count === 1 ? ('every ' + state.noun) : ('every ' + state.count + ' ' + state.noun + 's');
        var when = state.explicit_start ? ('from ' + format_date($('#start_date').val())) : 'from today';
        var ends = 'until cancelled';

        if (state.mode === 'After') {
            ends = (isNaN(state.after) || state.after < 1) ? 'until a number of invoices is set' : ('for ' + state.after + ' invoice' + (state.after === 1 ? '' : 's'));
        } else if (state.mode === 'On') {
            ends = state.on === '' ? 'until an end date is set' : ('until ' + format_date(state.on));
        }

        return currency + ' ' + $('#lines_total').text() + ' ' + every + ', ' + when + ', ' + ends + '.';
    }

    function format_date(value) {
        var parts = value.split('-');
        return parts[1] + '/' + parts[2] + '/' + parts[0];
    }

    /* The dates and the sentence describe the same schedule, so they are written
       together and cannot drift apart. */
    function render_sentence() {
        render_dates();
        $('#schedule_sentence').text(schedule_sentence());
    }

    function render_repeats() {

        var repeats = $('#repeats').val() === '1' && schedule_set;

        $('#schedule_summary').prop('hidden', !repeats).text(repeats ? schedule_sentence() : '');
        $('#edit_schedule').prop('hidden', !repeats);
        $('#do_save').text(repeats ? 'Save Recurring Invoice' : 'Save Invoice');
    }

    function open_schedule() {
        render_sentence();
        modal('schedule_modal').show();
    }

    /* Choosing a schedule asks for one immediately; there is nothing to configure
       on a one-off invoice, so the modal never appears for it. */
    $('#repeats').change(function () {

        if ($(this).val() === '1') {
            open_schedule();
            return;
        }

        schedule_set = false;
        render_repeats();
    });

    $('#edit_schedule').click(open_schedule);

    $('#billing_interval').change(render_sentence);
    /* The condition and the field it needs are one choice, so picking a mode
       brings its field with it rather than leaving three fields on screen with
       two of them dead. */
    $('#ends_mode').on('click', '.segmented__btn', function () {

        $('#ends_mode .segmented__btn').removeClass('is-on').attr('aria-pressed', 'false');
        $(this).addClass('is-on').attr('aria-pressed', 'true');

        var mode = $(this).attr('data-value');

        $('#ends_after_field').prop('hidden', mode !== 'After');
        $('#ends_on_field').prop('hidden', mode !== 'On');

        render_sentence();
    });

    $('#start_date, #ends_on').on('change', render_sentence);

    $('#interval_count, #ends_after').on('input', function () {
        numeric_only(this, 0);
        render_sentence();
    });

    $('#save_schedule').click(function () {

        var count = parseInt($('#interval_count').val(), 10);

        if (isNaN(count) || count < 1) {
            $('#interval_count').addClass('is-invalid').focus();
            return;
        }

        var mode = $('#ends_mode .segmented__btn.is-on').attr('data-value');

        if (mode === 'After' && (isNaN(parseInt($('#ends_after').val(), 10)) || parseInt($('#ends_after').val(), 10) < 1)) {
            $('#ends_after').addClass('is-invalid').focus();
            return;
        }

        if (mode === 'On' && $('#ends_on').val() === '') {
            $('#ends_on').addClass('is-invalid').focus();
            return;
        }

        schedule_set = true;
        modal('schedule_modal').hide();
        render_repeats();
    });

    /**
     * Backing out without a schedule leaves the invoice one-off, rather than
     * marked recurring with nothing behind it. Safe to run more than once, so
     * every way out of the modal can call it without coordinating.
     */
    function abandon_schedule() {

        if (!schedule_set) {
            $('#repeats').val('0');
        }

        render_repeats();
    }

    /**
     * Every exit is bound separately, because none of them can be trusted alone:
     * Escape never reaches the Cancel button, Bootstrap 5 fires a native event
     * named literally "hidden.bs.modal" which jQuery reads as a namespace and so
     * never hears, and a keydown only reaches the modal element while focus is
     * still inside it. Missing one leaves the field reading "On a Schedule" with
     * no schedule behind it.
     */
    document.getElementById('schedule_modal').addEventListener('hidden.bs.modal', abandon_schedule);

    $('#cancel_schedule, #schedule_modal .btn-close').click(abandon_schedule);

    $(document).on('keydown', function (event) {

        if (event.key !== 'Escape' || !$('#schedule_modal').hasClass('show')) {
            return;
        }

        modal('schedule_modal').hide();
        abandon_schedule();
    });

    $('#due_terms').change(due_from_terms);
    $('#due_date').on('change', render_masthead);
    $('#project_id').change(render_masthead);

    $('#line_rows').on('change', 'select', function () {
        recalculate();
    });

    /**
     * These three fields feed integer money and quantity arithmetic, so anything
     * that is not a figure is refused at the keystroke rather than caught at save.
     * A letter typed into a rate would otherwise sit there looking valid until the
     * invoice refused to save, with the line total silently reading zero meanwhile.
     *
     * The caret is put back where the typing left it, less whatever was dropped -
     * without that, correcting a character mid-number throws the cursor to the end
     * and the next keystroke lands in the wrong place.
     */
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

    /**
     * Removing a line is confirmed rather than immediate. There is no undo on this
     * form, and the control sits at the end of a row of inputs, so a misplaced
     * click would silently drop work and change the total.
     */
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
            repeats: $('#repeats').val(),
            billing_interval: $('#billing_interval').val(),
            interval_count: $('#interval_count').val().trim(),
            start_date: $('#start_date').val(),
            ends_mode: $('#ends_mode .segmented__btn.is-on').attr('data-value'),
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
