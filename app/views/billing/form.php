<div class="page">
    <a class="page__back" href="/billing"><i class="fa-regular fa-arrow-left"></i> Back to Billing</a>

    <div class="page__head">
        <div>
            <h1 class="page__title"><?php echo ($this->invoice === null ? 'New Invoice' : 'Edit Draft'); ?></h1>
        </div>
        <?php if ($this->invoice !== null) { ?>
        <div class="page__actions">
            <button type="button" class="btn btn--destructive" id="do_delete_open"><i class="fa-regular fa-trash"></i> Delete</button>
        </div>
        <?php } ?>
    </div>

    <div class="panel">
        <div class="panel__body">

            <div class="row mb-4">
                <div class="col-md-6 form-group">
                    <label for="client_id">Client</label>
                    <select class="form-control" id="client_id">
                        <option value="0">Select a client</option>
                    </select>
                </div>
                <div class="col-md-6 form-group">
                    <label for="project_id">Project</label>
                    <select class="form-control" id="project_id">
                        <option value="0">No project</option>
                    </select>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-3 form-group">
                    <label for="due_days">Payment Terms</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="due_days" inputmode="numeric" value="<?php echo ($this->invoice === null ? '30' : (int) $this->invoice['due_days']); ?>">
                        <span class="input-group-text">days</span>
                    </div>
                </div>
                <div class="col-md-9 form-group">
                    <label for="invoice_memo">Notes</label>
                    <input type="text" class="form-control" id="invoice_memo" placeholder="Shown on the invoice" value="<?php echo ($this->invoice === null ? '' : htmlspecialchars($this->invoice['invoice_memo'], ENT_QUOTES, 'UTF-8')); ?>">
                </div>
            </div>

            <div class="record__group">
                <h2 class="record__group-title">Line Items</h2>
                <div class="table-wrap lines__scroll">
                    <table class="data lines">
                        <thead>
                            <tr>
                                <th scope="col">Description</th>
                                <th scope="col" class="num">Qty</th>
                                <th scope="col" class="num">Unit Price</th>
                                <th scope="col" class="num">Amount</th>
                                <th scope="col" class="actions"></th>
                            </tr>
                        </thead>
                        <tbody id="line_rows"></tbody>
                    </table>
                </div>
                <div class="lines__foot">
                    <button type="button" class="btn btn--tertiary btn--sm" id="add_line"><i class="fa-regular fa-plus"></i> Add line</button>
                    <p class="lines__total">Total <span id="lines_total">0.00</span></p>
                </div>
            </div>

            <div class="panel__actions">
                <a class="btn btn--secondary" href="/billing">Cancel</a>
                <button type="button" id="do_save" class="btn btn--primary">Save Draft</button>
            </div>

        </div>
    </div>
</div>

<?php if ($this->invoice !== null) { ?>
<div class="modal fade" data-bs-backdrop="static" id="delete_modal" tabindex="-1" aria-labelledby="delete_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="delete_modal_title">Delete Draft</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Delete this draft invoice? This cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="do_delete" class="btn btn--destructive">Delete Draft</button>
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
        var total = 0;
        $('#line_rows tr').each(function () {
            var amount = row_amount(this);
            $(this).find('[data-cell=amount]').html(amount === null ? '<span class="roster__none">&mdash;</span>' : esc(money(amount)));
            if (amount !== null) {
                total += amount;
            }
        });
        $('#lines_total').text((currency === '' ? '' : currency + ' ') + money(total));
    }

    function add_line(description, quantity, unit_amount) {
        var idx = next_idx;
        next_idx++;
        var html = '<tr data-idx="' + idx + '">'
            + '<td><input type="text" class="form-control" data-field="description" aria-label="Description" value="' + esc(description) + '"></td>'
            + '<td class="num"><input type="text" class="form-control" data-field="quantity" inputmode="decimal" aria-label="Quantity" value="' + esc(quantity) + '"></td>'
            + '<td class="num"><input type="text" class="form-control" data-field="unit_amount" inputmode="decimal" aria-label="Unit price" value="' + esc(unit_amount) + '"></td>'
            + '<td class="num" data-cell="amount">&mdash;</td>'
            + '<td class="actions"><button type="button" class="btn btn--tertiary btn--sm" data-action="remove_line" aria-label="Remove line"><i class="fa-regular fa-trash"></i></button></td>'
            + '</tr>';
        $('#line_rows').append(html);
        recalculate();
    }

    $('#line_rows').on('input', 'input', function () {
        $(this).removeClass('is-invalid');
        recalculate();
    });

    $('#line_rows').on('click', '[data-action=remove_line]', function () {
        $(this).closest('tr').remove();
        if ($('#line_rows tr').length === 0) {
            add_line('', '1', '');
        }
        recalculate();
    });

    $('#add_line').click(function () {
        add_line('', '1', '');
        $('#line_rows tr').last().find('[data-field=description]').focus();
    });

    $('#client_id').change(function () {
        selected_project = 0;
        load_projects($(this).val());
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
                $('#client_id').append('<option value="' + obj[i].id + '">' + esc(obj[i].company_name) + '</option>');
            }
            if (selected_client > 0) {
                $('#client_id').val(selected_client);
                load_projects(selected_client);
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

        if (/^\d{1,3}$/.test($('#due_days').val().trim()) === false) {
            $('#due_days').addClass('is-invalid');
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
            toastr.error('Add at least one line item.');
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
            due_days: $('#due_days').val().trim(),
            invoice_memo: $('#invoice_memo').val().trim(),
            invoice_footer: '',
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

    if ($('#line_rows tr').length === 0) {
        add_line('', '1', '');
    }

});
</script>
