<div class="page">

    <a class="page__back" href="/billing"><i class="fa-regular fa-arrow-left"></i> Back to Billing</a>

    <div class="page__head">
        <div>
            <h1 class="page__title"><?php echo ($this->invoice === null ? 'New Invoice' : 'Edit Draft'); ?></h1>
            <div class="client__meta">
                <span class="client__segment">Nothing is sent until you choose Send on the next screen</span>
            </div>
        </div>
        <?php if ($this->invoice !== null) { ?>
        <div class="page__actions">
            <button type="button" class="btn btn--destructive" id="do_delete_open"><i class="fa-regular fa-trash"></i> Delete</button>
        </div>
        <?php } ?>
    </div>

    <div class="row g-4 invoice-form">
        <div class="col-lg-9">
            <div class="panel">
                <div class="invoice-form__section">
                    <div class="invoice-form__section-head">
                        <h2 class="invoice-form__heading">Invoice details</h2>
                    </div>
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label for="client_id">Bill to</label>
                            <select class="form-control" id="client_id">
                                <option value="0">Select a client</option>
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label for="project_id">Project</label>
                            <select class="form-control" id="project_id">
                                <option value="0">No project</option>
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label for="due_terms">Payment terms</label>
                            <div class="terms">
                                <select class="form-control" id="due_terms">
                                    <option value="0">Due on receipt</option>
                                    <option value="7">Net 7</option>
                                    <option value="14">Net 14</option>
                                    <option value="30" selected>Net 30</option>
                                    <option value="45">Net 45</option>
                                    <option value="60">Net 60</option>
                                    <option value="custom">Choose a date</option>
                                </select>
                                <input type="date" class="form-control terms__date" id="due_date" hidden value="<?php echo ($this->invoice === null || $this->invoice['due_date'] === null ? '' : htmlspecialchars($this->invoice['due_date'], ENT_QUOTES, 'UTF-8')); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-12 form-group">
                            <label for="invoice_memo">Description</label>
                            <textarea class="form-control" id="invoice_memo" rows="2"><?php echo ($this->invoice === null ? '' : htmlspecialchars($this->invoice['invoice_memo'], ENT_QUOTES, 'UTF-8')); ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="invoice-form__section">
                    <div class="invoice-form__section-head">
                        <h2 class="invoice-form__heading">Line Items</h2>
                        <button type="button" class="btn btn--tertiary btn--sm" id="add_line"><i class="fa-regular fa-plus"></i> Add Line</button>
                    </div>
                    <div class="table-wrap lines__scroll" id="lines_table">
                        <table class="data lines">
                            <thead>
                                <tr>
                                    <th scope="col">Description</th>
                                    <th scope="col" class="num" style="width:110px">Quantity</th>
                                    <th scope="col" class="num" style="width:150px">Unit Amount</th>
                                    <th scope="col" class="num" style="width:150px">Amount</th>
                                    <th scope="col" class="actions"></th>
                                </tr>
                            </thead>
                            <tbody id="line_rows"></tbody>
                            <tfoot id="lines_foot" hidden>
                                <tr>
                                    <td></td>
                                    <td></td>
                                    <th scope="row">Total</th>
                                    <td class="num lines__total"><span id="total_currency"></span> <span id="lines_total">0.00</span></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                        <p class="controls__empty" id="lines_empty">Nothing to see here yet</p>
                    </div>
                </div>

                <div class="invoice-form__section">
                    <div class="row">
                        <div class="col-md-12 form-group">
                            <label for="invoice_footer">Footer Message</label>
                            <textarea class="form-control" id="invoice_footer" rows="2"><?php echo ($this->invoice === null ? '' : htmlspecialchars($this->invoice['invoice_footer'], ENT_QUOTES, 'UTF-8')); ?></textarea>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <div class="col-lg-3">
            <div class="panel">
                <div class="invoice-form__section">
                    <h2 class="invoice-form__heading">Summary</h2>
                    <dl class="rail__facts">
                        <div class="rail__fact">
                            <dt>Status</dt>
                            <dd><span class="badge badge--prospect">Draft</span></dd>
                        </div>
                        <div class="rail__fact" id="rail_client_fact" hidden>
                            <dt>Bill to</dt>
                            <dd id="rail_client"></dd>
                        </div>
                        <div class="rail__fact" id="rail_project_fact" hidden>
                            <dt>Project</dt>
                            <dd id="rail_project"></dd>
                        </div>
                        <div class="rail__fact" id="rail_due_fact" hidden>
                            <dt>Due</dt>
                            <dd id="rail_due"></dd>
                        </div>
                    </dl>
                </div>

                <div class="invoice-form__section">
                    <div class="rail__actions">
                        <a class="btn btn--secondary" href="/billing">Cancel</a>
                        <button type="button" id="do_save" class="btn btn--primary">Save draft</button>
                    </div>
                </div>
            </div>
        </div>
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

<script>
$(document).ready(function () {

    var invoice_id = <?php echo ($this->invoice === null ? 0 : (int) $this->invoice['id']); ?>;
    var selected_client = <?php echo ($this->invoice === null ? 0 : (int) $this->invoice['client_id']); ?>;
    var selected_project = <?php echo ($this->invoice === null ? 0 : (int) $this->invoice['project_id']); ?>;
    var currency = "<?php echo htmlspecialchars(strtoupper($this->invoice === null ? ($this->company['default_currency'] ?? 'usd') : $this->invoice['currency']), ENT_QUOTES, 'UTF-8'); ?>";
    var clients = {};
    var next_idx = 0;

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

    function row_amount(row) {
        var quantity = to_quantity($(row).find('[data-field=quantity]').val());
        var unit = to_cents($(row).find('[data-field=unit_amount]').val());
        if (quantity === null || unit === null) {
            return null;
        }
        return Math.round(quantity * unit / 1000);
    }

    function recalculate() {

        var rows = $('#line_rows tr').length;

        $('#lines_empty').prop('hidden', rows > 0);
        $('#lines_foot').prop('hidden', rows === 0);

        var total = 0;
        var priced = 0;
        $('#line_rows tr').each(function () {
            var amount = row_amount(this);
            $(this).find('[data-cell=amount]').text(money(amount === null ? 0 : amount));
            if (amount !== null) {
                total += amount;
                priced++;
            }
        });
        $('#total_currency').text(currency);
        $('#lines_total').text(money(total));

        render_rail();
    }

    function add_line(description, quantity, unit_amount) {
        var idx = next_idx;
        next_idx++;
        var html = '<tr data-idx="' + idx + '">'
            + '<td><input type="text" class="form-control" data-field="description" aria-label="Description" value="' + esc(description) + '"></td>'
            + '<td class="num"><input type="text" class="form-control" data-field="quantity" inputmode="decimal" aria-label="Quantity" value="' + esc(quantity) + '"></td>'
            + '<td class="num"><input type="text" class="form-control" data-field="unit_amount" inputmode="decimal" aria-label="Unit price" value="' + esc(unit_amount) + '"></td>'
            + '<td class="num lines__row-amount" data-cell="amount">0.00</td>'
            + '<td class="actions"><button type="button" class="btn btn--tertiary btn--sm" data-action="remove_line" aria-label="Remove line"><i class="fa-regular fa-trash"></i></button></td>'
            + '</tr>';
        $('#line_rows').append(html);
        recalculate();
    }

    /**
     * The rail reads the invoice back as it is being written, so the person can see
     * what the client will get without leaving the form.
     */
    function render_rail() {

        var client = clients[$('#client_id').val()];
        var project = $('#project_id option:selected').text();
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
            $('#rail_project').text(project);
        }

        $('#rail_due_fact').prop('hidden', due === '');

        if (due !== '') {
            var parts = due.split('-');
            var terms = $('#due_terms').val();
            var label = esc(parts[1] + '/' + parts[2] + '/' + parts[0]);

            if (terms !== 'custom' && parseInt(terms, 10) > 0) {
                label += '<br><span class="rail__quiet">' + esc($('#due_terms option:selected').text()) + '</span>';
            }

            $('#rail_due').html(label);
        }
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
            if ($('#due_date').val() === '') {
                var fallback = new Date();
                fallback.setDate(fallback.getDate() + 30);
                $('#due_date').val(fallback.toISOString().slice(0, 10));
            }
            render_rail();
            return;
        }

        $('#due_date').prop('hidden', true);

        var due = new Date();
        due.setDate(due.getDate() + parseInt(terms, 10));
        $('#due_date').val(due.toISOString().slice(0, 10));
        render_rail();
    }


    $('#due_terms').change(due_from_terms);
    $('#due_date').on('change', render_rail);
    $('#project_id').change(render_rail);

    $('#line_rows').on('input', 'input', function () {
        $(this).removeClass('is-invalid');
        recalculate();
    });

    $('#line_rows').on('click', '[data-action=remove_line]', function () {
        $(this).closest('tr').remove();
        recalculate();
    });

    $('#add_line').click(function () {
        add_line('', '1', '');
        $('#line_rows tr').last().find('[data-field=description]').focus();
    });

    $('#client_id').change(function () {
        selected_project = 0;
        load_projects($(this).val());
        render_rail();
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
            render_rail();
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

            lines.push({
                item_description: description.val().trim(),
                quantity: quantity.val().trim(),
                unit_amount: unit.val().trim()
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
            invoice_memo: $('#invoice_memo').val().trim(),
            invoice_footer: $('#invoice_footer').val().trim(),
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
    add_line("<?php echo htmlspecialchars(addslashes($item['item_description']), ENT_QUOTES, 'UTF-8'); ?>", "<?php echo Money::format_quantity($item['quantity_milli']); ?>", "<?php echo number_format($item['unit_amount_cents'] / 100, 2, '.', ''); ?>");
    <?php } ?>

    <?php if ($this->invoice !== null && $this->invoice['due_date'] !== null) { ?>
    $('#due_terms').val('custom');
    <?php } elseif ($this->invoice !== null) { ?>
    $('#due_terms').val($('#due_terms option[value="<?php echo (int) $this->invoice['due_days']; ?>"]').length ? '<?php echo (int) $this->invoice['due_days']; ?>' : 'custom');
    <?php } ?>
    due_from_terms();

    recalculate();

});
</script>
