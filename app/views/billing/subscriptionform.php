<div class="page">

    <a class="page__back" href="/billing"><i class="fa-regular fa-arrow-left"></i> Back to Billing</a>

    <div class="page__head">
        <div>
            <h1 class="page__title"><?php echo ($this->subscription === null ? 'New Subscription' : 'Edit Draft'); ?></h1>
        </div>
        <div class="page__actions">
            <span class="badge badge--prospect">Draft</span>
            <?php if ($this->subscription !== null) { ?>
            <button type="button" class="btn btn--destructive" id="do_delete_open"><i class="fa-regular fa-trash"></i> Delete</button>
            <?php } ?>
        </div>
    </div>

    <div class="invoice-main">

        <div class="invoice-doc invoice-doc--header">
            <div class="masthead masthead--invoice">
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
                    <label for="billing_interval">Bills</label>
                    <select class="form-control" id="billing_interval">
                        <option value="Month">Monthly</option>
                        <option value="Quarter">Quarterly</option>
                        <option value="Year">Yearly</option>
                    </select>
                </div>
                <div class="masthead__cell">
                    <label for="due_days">Payment terms</label>
                    <select class="form-control" id="due_days">
                        <option value="0">Due on receipt</option>
                        <option value="7">Net 7</option>
                        <option value="14">Net 14</option>
                        <option value="30" selected>Net 30</option>
                        <option value="45">Net 45</option>
                        <option value="60">Net 60</option>
                    </select>
                </div>
                <div class="masthead__cell masthead__cell--due">
                    <label for="start_date">Starts</label>
                    <input type="date" class="form-control" id="start_date" value="<?php echo ($this->subscription === null || $this->subscription['start_date'] === null ? '' : htmlspecialchars($this->subscription['start_date'], ENT_QUOTES, 'UTF-8')); ?>">
                </div>
            </div>
        </div>

        <div class="invoice-doc">
            <div class="lines__head">
                <h2 class="lines__title">What is billed</h2>
            </div>
            <div class="table-wrap lines__scroll">
                <table class="data ledger">
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
                            <td><input type="text" class="form-control form-control-lg" id="subscription_name" aria-label="Description" placeholder="Managed security subscription" value="<?php echo ($this->subscription === null ? '' : htmlspecialchars($this->subscription['subscription_name'], ENT_QUOTES, 'UTF-8')); ?>"></td>
                            <td class="num"><input type="text" class="form-control form-control-lg" id="quantity" inputmode="numeric" aria-label="Quantity" value="<?php echo ($this->subscription === null ? '1' : (int) $this->subscription['quantity']); ?>"></td>
                            <td class="num"><input type="text" class="form-control form-control-lg" id="unit_amount" inputmode="decimal" aria-label="Unit amount" value="<?php echo ($this->subscription === null ? '0' : number_format($this->subscription['unit_amount_cents'] / 100, 2, '.', '')); ?>"></td>
                            <td class="num lines__row-amount" id="line_amount">0.00</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="invoice-doc invoice-doc--footer">
            <div class="commit commit--split">
                <div class="form-group commit__field">
                    <span class="lbl">Each period</span>
                    <p class="commit__text" id="cadence_text">This bills the client automatically until it is cancelled.</p>
                </div>
                <div class="commit__actions">
                    <a class="btn btn--secondary" href="/billing">Cancel</a>
                    <button type="button" id="do_save" class="btn btn--primary">Save</button>
                </div>
            </div>
        </div>

    </div>
</div>

<?php if ($this->subscription !== null) { ?>
<div class="modal fade" data-bs-backdrop="static" id="delete_modal" tabindex="-1" aria-labelledby="delete_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="delete_modal_title">Delete draft</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Delete this draft subscription? This cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="do_delete" class="btn btn--destructive">Delete</button>
            </div>
        </div>
    </div>
</div>
<?php } ?>

<script>
$(document).ready(function () {

    var subscription_id = <?php echo ($this->subscription === null ? 0 : (int) $this->subscription['id']); ?>;
    var selected_client = <?php echo ($this->subscription === null ? 0 : (int) $this->subscription['client_id']); ?>;
    var selected_project = <?php echo ($this->subscription === null ? 0 : (int) $this->subscription['project_id']); ?>;
    var currency = "<?php echo htmlspecialchars(strtoupper($this->subscription === null ? ($this->company['default_currency'] ?? 'usd') : $this->subscription['currency']), ENT_QUOTES, 'UTF-8'); ?>";

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
        var parts = /^(\d{1,9})(?:\.(\d{1,2}))?$/.exec(value);
        if (parts === null) {
            return null;
        }
        var fraction = parts[2] === undefined ? '00' : (parts[2] + '0').slice(0, 2);
        return parseInt(parts[1], 10) * 100 + parseInt(fraction, 10);
    }

    function money(cents) {
        var whole = Math.floor(Math.abs(cents) / 100);
        var rest = String(Math.abs(cents) % 100);
        while (rest.length < 2) {
            rest = '0' + rest;
        }
        return whole.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',') + '.' + rest;
    }

    /** Only figures reach the money and quantity fields, as on the invoice editor. */
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

    function recalculate() {

        var unit = to_cents($('#unit_amount').val());
        var quantity = parseInt($('#quantity').val(), 10);

        if (unit === null || isNaN(quantity) || quantity < 1) {
            $('#line_amount').text(money(0));
            return;
        }

        var total = unit * quantity;

        $('#line_amount').text(money(total));

        var cadence = $('#billing_interval option:selected').text().toLowerCase();

        $('#cadence_text').text('The client is invoiced ' + currency + ' ' + money(total) + ' ' + cadence + ', automatically, until this subscription is cancelled.');
    }

    $('#unit_amount').on('input', function () {
        numeric_only(this, 2);
        $(this).removeClass('is-invalid');
        recalculate();
    });

    $('#quantity').on('input', function () {
        numeric_only(this, 0);
        $(this).removeClass('is-invalid');
        recalculate();
    });

    $('#billing_interval').change(recalculate);

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

        if ($('#subscription_name').val().trim() === '') {
            $('#subscription_name').addClass('is-invalid');
            errors++;
        }

        var unit = to_cents($('#unit_amount').val());

        if (unit === null || unit <= 0) {
            $('#unit_amount').addClass('is-invalid');
            errors++;
        }

        if (errors > 0) {
            $('.form-control.is-invalid').first().focus();
            toastr.error('Please fix the errors before continuing.');
            return;
        }

        var values = {
            subscription_id: subscription_id,
            client_id: $('#client_id').val(),
            project_id: $('#project_id').val(),
            subscription_name: $('#subscription_name').val().trim(),
            unit_amount: $('#unit_amount').val().trim(),
            quantity: $('#quantity').val().trim(),
            billing_interval: $('#billing_interval').val(),
            due_days: $('#due_days').val(),
            start_date: $('#start_date').val()
        };

        set_loading('#do_save', true);

        ApiDataSvc.apiCall('post', 'save_subscription', values, function (data) {

            var obj = JSON.parse(data);

            set_loading('#do_save', false);

            if (obj.success) {
                window.location.href = '/billing/subscription/id/' + obj.subscription_id;
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

        ApiDataSvc.apiCall('post', 'delete_subscription', { subscription_id: subscription_id }, function (data) {

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

    <?php if ($this->subscription !== null) { ?>
    $('#billing_interval').val("<?php echo htmlspecialchars($this->subscription['billing_interval'], ENT_QUOTES, 'UTF-8'); ?>");
    $('#due_days').val("<?php echo (int) $this->subscription['due_days']; ?>");
    <?php } ?>

    recalculate();

});
</script>
