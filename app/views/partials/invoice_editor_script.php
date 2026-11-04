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
    var invoice_in_modal = <?php echo (empty($this->invoice_in_modal) ? 'false' : 'true'); ?>;
    var selected_client = <?php echo (int) $this->client_id; ?>;
    var currency = "<?php echo htmlspecialchars(strtoupper($this->invoice === null ? ($this->company['default_currency'] ?? 'usd') : $this->invoice['currency']), ENT_QUOTES, 'UTF-8'); ?>";
    var invoice_total = 0;
    var saved = { count: '1', unit: 'Month', start: "<?php echo date('Y-m-d'); ?>", mode: 'Never', after: '12', on: '' };
    var schedule_set = false;
    var next_idx = 0;
    var line_to_remove = null;

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
        $('#lines_totals').prop('hidden', rows === 0);

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

        $('#subtotal_row').prop('hidden', discount === 0);
        $('#discount_row').prop('hidden', discount === 0);
        $('#lines_subtotal').text(money(subtotal));
        $('#discount_amount').text('-' + money(discount));
        $('#lines_total').text(money(subtotal - discount));

        invoice_total = subtotal - discount;

        if (schedule_set) {
            $('#schedule_summary').text(schedule_short());
        }
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

    function iso_date(date) {
        return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
    }

    function due_from_terms() {

        var due = new Date();

        due.setDate(due.getDate() + parseInt($('#due_terms').val() || '30', 10));
        $('#due_date').val(iso_date(due));
        $('#due_date_text').text(due.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }));
    }

    function terms_from_due() {

        var due = $('#due_date').val();
        var matched = '30';

        $('#due_terms option').each(function () {
            var date = new Date();
            date.setDate(date.getDate() + parseInt(this.value, 10));

            if (iso_date(date) === due) {
                matched = this.value;
            }
        });

        $('#due_terms').selectpicker('val', matched);
        $('#due_date').removeClass('is-invalid');
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

        var repeats = $('#repeats').val() === '1' && schedule_set;

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

    $('#repeats').change(function () {

        if ($(this).val() === '1') {
            open_schedule();
            return;
        }

        schedule_set = false;
        render_repeats();
    });

    $('#schedule_modal').on('hidden.bs.modal', function () {
        if (!schedule_set) {
            set_repeats('0');
            render_repeats();
        }
    });

    function set_repeats(value) {
        $('#repeats').val(value).selectpicker('render');
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

    function load_clients() {
        ApiDataSvc.apiCall('post', 'load_clients', {}, function (data) {
            var obj = JSON.parse(data);
            for (var i = 0; i < obj.length; i++) {
                $('#client_id').append('<option value="' + obj[i].id + '">' + esc(obj[i].company_name) + '</option>');
            }
            if (selected_client > 0) {
                $('#client_id').val(selected_client);
            }
        });
    }

    $('#do_save').click(function () {

        $('.form-control').removeClass('is-invalid');

        var errors = 0;

        if (parseInt($('#client_id').val(), 10) === 0) {
            $('#client_id').addClass('is-invalid');
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
            due_days: ($('#due_terms').val() || '30'),
            due_date: '',
            invoice_footer: $('#invoice_footer').val().trim(),
            repeats: $('#repeats').val(),
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

            if (obj.success && invoice_in_modal) {
                window.location.reload();
                return;
            }

            if (obj.success) {
                window.location.href = '/billing/invoice/id/' + obj.invoice_id;
            } else {
                toastr.error(obj.message);
            }
        });
    });

    $('#do_delete_open').click(function () {
        if (invoice_id > 0) {
            modal('delete_modal').show();
        }
    });

    $('#do_delete').click(function () {

        if (invoice_id === 0) {
            return;
        }

        set_loading('#do_delete', true);

        ApiDataSvc.apiCall('post', 'delete_invoice', { invoice_id: invoice_id }, function (data) {

            var obj = JSON.parse(data);

            set_loading('#do_delete', false);

            if (obj.success) {
                window.location.href = '/clients/detail/id/' + <?php echo (int) $this->client_id; ?>;
            } else {
                modal('delete_modal').hide();
                toastr.error(obj.message);
            }
        });
    });

    load_clients();

    <?php foreach ($this->items as $item) { ?>
    add_line(<?php echo json_encode(html_entity_decode((string) $item['item_description'], ENT_QUOTES, 'UTF-8'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, "<?php echo Money::format_quantity($item['quantity_milli']); ?>", "<?php echo number_format($item['unit_amount_cents'] / 100, 2, '.', ''); ?>", "<?php echo ($item['discount_type'] === 'Percent' ? Money::format_percent($item['discount_percent_bp']) : ($item['discount_type'] === 'Amount' ? number_format($item['discount_cents'] / 100, 2, '.', '') : '0')); ?>", "<?php echo htmlspecialchars($item['discount_type'], ENT_QUOTES, 'UTF-8'); ?>");
    <?php } ?>

    <?php if ($this->invoice !== null && $this->invoice['due_date'] !== null) { ?>
    $('#due_date').val(<?php echo json_encode((string) $this->invoice['due_date']); ?>);
    terms_from_due();
    <?php } elseif ($this->invoice !== null) { ?>
    $('#due_terms').val($('#due_terms option[value="<?php echo (int) $this->invoice['due_days']; ?>"]').length ? '<?php echo (int) $this->invoice['due_days']; ?>' : '30').selectpicker('render');
    <?php } ?>
    due_from_terms();

    render_repeats();
    recalculate();

});
</script>
