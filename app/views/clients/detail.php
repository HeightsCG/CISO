<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@29.5.10/dist/css/intlTelInput.min.css">
<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@29.5.10/dist/js/intlTelInputWithUtils.min.js"></script>
<div class="page">
    <header class="headline">
        <div class="headline__text">
            <h1 class="headline__title"><button type="button" class="headline__edit" id="do_rename_open" aria-label="Edit name"><?php echo htmlspecialchars($this->client['company_name'], ENT_QUOTES, 'UTF-8'); ?></button></h1>
        </div>
        <div class="headline__actions">
            <a class="btn btn--secondary btn--pill" href="/portal/preview/id/<?php echo (int) $this->client['id']; ?>"><i class="fa-regular fa-eye"></i> View Portal</a>
        </div>
    </header>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="panel record__panel">
                <div class="panel__head">
                    <h2 class="panel__title">Assessments</h2>
                    <?php if ($this->room['allowed']) { ?>
                    <button type="button" class="btn btn--icon do_assessment_add" id="do_assessment_add" aria-label="New assessment" title="New assessment"><i class="fa-regular fa-plus"></i></button>
                    <?php } else { ?>
                    <button type="button" class="btn btn--icon" data-bs-toggle="modal" data-bs-target="#upgrade_modal" aria-label="New assessment" title="New assessment"><i class="fa-regular fa-plus"></i></button>
                    <?php } ?>
                </div>
                <div class="panel__body panel__body--flush">
                    <table class="data listpage__table" id="client_assessments_table" hidden>
                        <thead>
                            <tr>
                                <th scope="col">Name</th>
                                <th scope="col">Framework</th>
                                <th scope="col">Status</th>
                                <th scope="col">Progress</th>
                            </tr>
                        </thead>
                        <tbody id="client_assessments_list"></tbody>
                    </table>
                    <p class="tiles__empty" id="client_assessments_empty"><i class="fa-regular fa-spinner-third fa-spin" aria-hidden="true"></i> Loading assessments&hellip;</p>
                </div>
            </div>
            <?php if ((int) Session::get('role_id') === 1) { ?>
            <div class="panel record__panel">
                <div class="panel__head">
                    <h2 class="panel__title">Invoices</h2>
                    <button type="button" class="btn btn--icon" data-bs-toggle="modal" data-bs-target="#invoice_modal" aria-label="New invoice" title="New invoice"><i class="fa-regular fa-plus"></i></button>
                </div>
                <div class="panel__body panel__body--flush">
                    <table class="data listpage__table" id="client_invoices_table" hidden>
                        <thead>
                            <tr>
                                <th scope="col">Invoice</th>
                                <th scope="col">Status</th>
                                <th scope="col">Due</th>
                                <th scope="col" class="num">Total</th>
                            </tr>
                        </thead>
                        <tbody id="client_invoices_list"></tbody>
                    </table>
                    <p class="tiles__empty" id="client_invoices_empty"><i class="fa-regular fa-spinner-third fa-spin" aria-hidden="true"></i> Loading invoices&hellip;</p>
                </div>
            </div>
            <?php } ?>
        </div>
        <div class="col-lg-4">
            <div class="panel record__panel">
                <div class="panel__head">
                    <h2 class="panel__title">Primary Contact</h2>
                    <button type="button" class="btn btn--icon do_contact_edit" data-contact="primary" aria-label="Edit primary contact" title="Edit primary contact"><i class="fa-regular fa-pen"></i></button>
                </div>
                <div class="panel__body">
                    <?php if ($this->client['contact_name'] === '' && $this->client['contact_title'] === '' && $this->client['contact_email'] === '' && $this->client['contact_phone'] === '') { ?>
                    <p class="roster__none">No primary contact yet.</p>
                    <?php } ?>
                    <dl class="datalist datalist--record">
                        <?php if ($this->client['contact_name'] !== '') { ?>
                        <dt>Full Name</dt>
                        <dd><?php echo htmlspecialchars($this->client['contact_name'], ENT_QUOTES, 'UTF-8'); ?></dd>
                        <?php } ?>
                        <?php if ($this->client['contact_title'] !== '') { ?>
                        <dt>Job Title</dt>
                        <dd><?php echo htmlspecialchars($this->client['contact_title'], ENT_QUOTES, 'UTF-8'); ?></dd>
                        <?php } ?>
                        <?php if ($this->client['contact_email'] !== '') { ?>
                        <dt>Email Address</dt>
                        <dd><?php echo '<a href="mailto:'.htmlspecialchars($this->client['contact_email'], ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars($this->client['contact_email'], ENT_QUOTES, 'UTF-8').'</a>'; ?></dd>
                        <?php } ?>
                        <?php if ($this->client['contact_phone'] !== '') { ?>
                        <dt>Phone</dt>
                        <dd><?php echo htmlspecialchars($this->client['contact_phone'], ENT_QUOTES, 'UTF-8'); ?></dd>
                        <?php } ?>
                    </dl>
                </div>
            </div>
            <div class="panel record__panel">
                <div class="panel__head">
                    <h2 class="panel__title">Billing Contact</h2>
                    <button type="button" class="btn btn--icon do_contact_edit" data-contact="billing" aria-label="Edit billing contact" title="Edit billing contact"><i class="fa-regular fa-pen"></i></button>
                </div>
                <div class="panel__body">
                    <?php if ($this->client['billing_name'] === '' && $this->client['billing_title'] === '' && $this->client['billing_email'] === '' && $this->client['billing_phone'] === '') { ?>
                    <p class="roster__none">No billing contact yet.</p>
                    <?php } ?>
                    <dl class="datalist datalist--record">
                        <?php if ($this->client['billing_name'] !== '') { ?>
                        <dt>Full Name</dt>
                        <dd><?php echo htmlspecialchars($this->client['billing_name'], ENT_QUOTES, 'UTF-8'); ?></dd>
                        <?php } ?>
                        <?php if ($this->client['billing_title'] !== '') { ?>
                        <dt>Job Title</dt>
                        <dd><?php echo htmlspecialchars($this->client['billing_title'], ENT_QUOTES, 'UTF-8'); ?></dd>
                        <?php } ?>
                        <?php if ($this->client['billing_email'] !== '') { ?>
                        <dt>Email Address</dt>
                        <dd><?php echo '<a href="mailto:'.htmlspecialchars($this->client['billing_email'], ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars($this->client['billing_email'], ENT_QUOTES, 'UTF-8').'</a>'; ?></dd>
                        <?php } ?>
                        <?php if ($this->client['billing_phone'] !== '') { ?>
                        <dt>Phone</dt>
                        <dd><?php echo htmlspecialchars($this->client['billing_phone'], ENT_QUOTES, 'UTF-8'); ?></dd>
                        <?php } ?>
                    </dl>
                </div>
            </div>
            <div class="panel record__panel">
                <div class="panel__head">
                    <h2 class="panel__title">Address</h2>
                    <button type="button" class="btn btn--icon" id="do_address_edit" aria-label="Edit address" title="Edit address"><i class="fa-regular fa-pen"></i></button>
                </div>
                <div class="panel__body">
                    <?php if ($this->client['address_1'] === '' && $this->client['address_2'] === '' && $this->client['city'] === '' && $this->client['state'] === '' && $this->client['postal_code'] === '' && $this->client['country'] === '') { ?>
                    <p class="roster__none">No address yet.</p>
                    <?php } ?>
                    <dl class="datalist datalist--record">
                        <?php if ($this->client['address_1'] !== '') { ?>
                        <dt>Street</dt>
                        <dd><?php echo htmlspecialchars($this->client['address_1'], ENT_QUOTES, 'UTF-8'); ?></dd>
                        <?php } ?>
                        <?php if ($this->client['address_2'] !== '') { ?>
                        <dt>Suite, Floor</dt>
                        <dd><?php echo htmlspecialchars($this->client['address_2'], ENT_QUOTES, 'UTF-8'); ?></dd>
                        <?php } ?>
                        <?php if ($this->client['city'] !== '') { ?>
                        <dt>City</dt>
                        <dd><?php echo htmlspecialchars($this->client['city'], ENT_QUOTES, 'UTF-8'); ?></dd>
                        <?php } ?>
                        <?php if ($this->client['state'] !== '') { ?>
                        <dt>State</dt>
                        <dd><?php echo htmlspecialchars($this->client['state'], ENT_QUOTES, 'UTF-8'); ?></dd>
                        <?php } ?>
                        <?php if ($this->client['postal_code'] !== '') { ?>
                        <dt>Postal Code</dt>
                        <dd><?php echo htmlspecialchars($this->client['postal_code'], ENT_QUOTES, 'UTF-8'); ?></dd>
                        <?php } ?>
                        <?php if ($this->client['country'] !== '') { ?>
                        <dt>Country</dt>
                        <dd><?php echo htmlspecialchars($this->client['country'], ENT_QUOTES, 'UTF-8'); ?></dd>
                        <?php } ?>
                    </dl>
                </div>
            </div>
            <button type="button" class="btn btn--destructive w-100" id="do_delete_open"><i class="fa-regular fa-trash"></i> Delete Client</button>
        </div>
    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" id="delete_modal" tabindex="-1" aria-labelledby="delete_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="delete_modal_title">Delete Client</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Delete <strong><?php echo htmlspecialchars($this->client['company_name'], ENT_QUOTES, 'UTF-8'); ?></strong>? This cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="do_delete" class="btn btn--destructive">Delete Client</button>
            </div>
        </div>
    </div>
</div>

<?php if ($this->room['allowed']) { ?>
<?php require Main::app_path().'/app/views/partials/assessment_modal.php'; ?>
<?php } else { ?>
<div class="modal fade" id="upgrade_modal" tabindex="-1" aria-labelledby="upgrade_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="upgrade_modal_title">Assessment limit reached</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p><?php echo htmlspecialchars($this->room['plan'], ENT_QUOTES, 'UTF-8'); ?> includes <?php echo (int) $this->room['cap']; ?> <?php echo ((int) $this->room['cap'] === 1 ? 'assessment' : 'assessments'); ?> per client. Upgrade your plan to add more.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary me-auto" data-bs-dismiss="modal">Not Now</button>
                <a class="btn btn--primary" href="/subscription">Upgrade Plan</a>
            </div>
        </div>
    </div>
</div>
<?php } ?>

<div class="modal fade" data-bs-backdrop="static" id="rename_modal" tabindex="-1" aria-labelledby="rename_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="rename_modal_title">Edit Name</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="rename_name">Company Name</label>
                    <input type="text" class="form-control" id="rename_name">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary me-auto" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="do_rename_save" class="btn btn--primary">Save</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" id="address_modal" tabindex="-1" aria-labelledby="address_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="address_modal_title">Edit Address</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-8 form-group">
                        <label for="address_1">Address</label>
                        <input type="text" class="form-control" id="address_1">
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="address_2">Suite, Floor</label>
                        <input type="text" class="form-control" id="address_2">
                    </div>
                    <div class="col-md-6 form-group">
                        <label for="country">Country</label>
                        <select id="country" class="selectpicker" data-style="form-control" data-width="100%" data-live-search="true" data-size="8" data-container="body" title="Choose a country">
                            <?php foreach ($this->countries as $country) { echo '<option value="'.htmlspecialchars($country['name'], ENT_QUOTES, 'UTF-8').'" data-code="'.htmlspecialchars($country['code'], ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars($country['name'], ENT_QUOTES, 'UTF-8').'</option>'; } ?>
                        </select>
                    </div>
                    <div class="col-md-6 form-group" id="state_group">
                        <label for="state">State</label>
                        <select id="state" class="selectpicker" data-style="form-control" data-width="100%" data-live-search="true" data-size="8" data-container="body" title="Choose a state" disabled></select>
                    </div>
                    <div class="col-md-6 form-group">
                        <label for="city">City</label>
                        <input type="text" class="form-control" id="city">
                    </div>
                    <div class="col-md-6 form-group">
                        <label for="postal_code">Postal Code</label>
                        <input type="text" class="form-control" id="postal_code">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary me-auto" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="do_address_save" class="btn btn--primary">Save Address</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" id="contact_modal" tabindex="-1" aria-labelledby="contact_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="contact_modal_title">Edit Contact</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12 form-group">
                        <label for="contact_edit_name">Full Name</label>
                        <input type="text" class="form-control" id="contact_edit_name">
                    </div>
                    <div class="col-12 form-group">
                        <label for="contact_edit_title">Job Title</label>
                        <input type="text" class="form-control" id="contact_edit_title">
                    </div>
                    <div class="col-12 form-group">
                        <label for="contact_edit_email">Email Address</label>
                        <input type="email" class="form-control" id="contact_edit_email" autocapitalize="none" spellcheck="false">
                    </div>
                    <div class="col-12 form-group">
                        <label for="contact_edit_phone">Phone</label>
                        <input type="tel" class="form-control" id="contact_edit_phone">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary me-auto" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="do_contact_save" class="btn btn--primary">Save Contact</button>
            </div>
        </div>
    </div>
</div>

<?php if ((int) Session::get('role_id') === 1) { ?>
<div class="modal fade" data-bs-backdrop="static" id="invoice_modal" tabindex="-1" aria-labelledby="invoice_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="invoice_modal_title">New Invoice</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php require Main::app_path().'/app/views/partials/invoice_editor.php'; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary me-auto" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="do_save" class="btn btn--primary">Save Invoice</button>
            </div>
        </div>
    </div>
</div>

<?php require Main::app_path().'/app/views/partials/invoice_editor_script.php'; ?>
<div class="modal fade" id="invoice_view_modal" tabindex="-1" aria-label="Invoice" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl invoice-view">
        <div class="modal-content">
            <button type="button" class="btn-close invoice-view__close" data-bs-dismiss="modal" aria-label="Close"></button>
            <iframe id="invoice_view_frame" class="invoice-view__frame" title="Invoice" src="about:blank"></iframe>
        </div>
    </div>
</div>
<?php } ?>


<script>
$(document).ready(function () {

    var client_id = <?php echo (int) $this->client['id']; ?>;
    var CONTACTS = <?php echo json_encode($this->contacts, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var FREE_DOMAINS = <?php echo json_encode(EmailRules::FREE_DOMAINS, JSON_HEX_TAG); ?>;
    var EMAIL_FORMAT = /^[^@\s]+@[^@\s.]+(\.[^@\s.]+)+$/;
    var editing = '';
    var company_name = <?php echo json_encode(html_entity_decode((string) $this->client['company_name'], ENT_QUOTES, 'UTF-8'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    $('#do_rename_open').click(function () {
        $('#rename_name').val(company_name).removeClass('is-invalid');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('rename_modal')).show();
    });

    $('#rename_modal').on('shown.bs.modal', function () {
        $('#rename_name').focus().select();
    });

    $('#do_rename_save').click(function () {

        var name = $('#rename_name').val().trim();

        if (name === '') {
            $('#rename_name').addClass('is-invalid').focus();
            toastr.error('Enter the company name.');
            return;
        }

        set_loading('#do_rename_save', true);

        ApiDataSvc.apiCall('post', 'save_client_name', {
            client_id: client_id,
            company_name: name
        }, function (data) {

            var obj = JSON.parse(data);

            if (obj.success) {
                window.location.reload();
                return;
            }

            set_loading('#do_rename_save', false);
            toastr.error(obj.message);
        });
    });
    var ADDRESS = <?php echo json_encode($this->address, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var COUNTRIES = <?php echo json_encode(array_column($this->countries, null, 'name'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>;

    function esc_text(value) {
        return $('<div>').text(value).html();
    }

    function fill_states(country, chosen) {

        var list = (COUNTRIES[country] || { states: [] }).states;
        var html = '';

        for (var i = 0; i < list.length; i++) {
            html += '<option value="' + esc_text(list[i]) + '">' + esc_text(list[i]) + '</option>';
        }

        $('#state').selectpicker('destroy');
        $('#state').html(html).prop('disabled', list.length === 0);
        $('#state').selectpicker();
        $('#state').selectpicker('val', list.indexOf(chosen) === -1 ? '' : chosen);
        $('#state_group').prop('hidden', country !== '' && list.length === 0);
    }

    $('#do_address_edit').click(function () {

        $('#address_1').val(ADDRESS.address_1);
        $('#address_2').val(ADDRESS.address_2);
        $('#city').val(ADDRESS.city);
        $('#postal_code').val(ADDRESS.postal_code);
        $('#country').selectpicker('val', ADDRESS.country);
        fill_states(ADDRESS.country, ADDRESS.state);

        bootstrap.Modal.getOrCreateInstance(document.getElementById('address_modal')).show();
    });

    $('#address_modal').on('shown.bs.modal', function () {
        $('#address_1').focus();
    });

    $('#country').on('changed.bs.select', function (e, index, selected, previous) {
        if (selected) {
            fill_states($(this).val() || '', '');
        }
    });

    AddressAutocomplete.attach({
        input: 'address_1',
        country: 'country',
        targets: {
            city: 'city',
            postal_code: 'postal_code'
        },
        filled: function (place) {

            var match = $('#country option').filter(function () {
                return $(this).attr('data-code') === place.country_code || $(this).val() === place.country;
            }).first();

            if (match.length === 0) {
                return;
            }

            $('#country').selectpicker('val', match.val());
            fill_states(match.val(), place.state);
        }
    });

    $('#do_address_save').click(function () {

        set_loading('#do_address_save', true);

        ApiDataSvc.apiCall('post', 'save_client_address', {
            client_id: client_id,
            address_1: $('#address_1').val().trim(),
            address_2: $('#address_2').val().trim(),
            city: $('#city').val().trim(),
            state: $('#state').val() || '',
            postal_code: $('#postal_code').val().trim(),
            country: $('#country').val() || ''
        }, function (data) {

            var obj = JSON.parse(data);

            if (obj.success) {
                window.location.reload();
                return;
            }

            set_loading('#do_address_save', false);
            toastr.error(obj.message);
        });
    });

    var phone = window.intlTelInput(document.getElementById('contact_edit_phone'), {
        countrySelectorMode: 'DROPDOWN',
        initialCountry: <?php echo json_encode($this->country_code); ?>,
        countrySearch: true,
        separateDialCode: true,
        strictMode: true,
        formatAsYouType: true,
        placeholderNumberPolicy: 'OFF',
        dropdownParent: document.body,
        containerClass: 'iti--field'
    });

    $('.do_contact_edit').click(function () {

        var contact = CONTACTS[$(this).attr('data-contact')];

        editing = $(this).attr('data-contact');

        $('#contact_modal_title').text(editing === 'primary' ? 'Edit Primary Contact' : 'Edit Billing Contact');
        $('#contact_modal .form-control').removeClass('is-invalid');
        $('#contact_edit_name').val(contact.name);
        $('#contact_edit_title').val(contact.title);
        $('#contact_edit_email').val(contact.email);
        phone.setNumber(contact.phone);

        if (contact.phone === '') {
            phone.setSelectedCountry(<?php echo json_encode($this->country_code); ?>);
        }

        bootstrap.Modal.getOrCreateInstance(document.getElementById('contact_modal')).show();
    });

    $('#contact_modal').on('shown.bs.modal', function () {
        $('#contact_edit_name').focus();
    });

    $('#do_contact_save').click(function () {

        var email = $('#contact_edit_email').val().trim();
        var number = $('#contact_edit_phone').val().trim();
        var problem = '';

        $('#contact_modal .form-control').removeClass('is-invalid');

        if (email !== '' && !EMAIL_FORMAT.test(email)) {
            $('#contact_edit_email').addClass('is-invalid');
            problem = 'Enter a valid email address.';
        } else if (email !== '' && FREE_DOMAINS.indexOf(email.split('@').pop().toLowerCase()) !== -1) {
            $('#contact_edit_email').addClass('is-invalid');
            problem = 'Use a business email address, not a free provider.';
        } else if (number !== '' && !phone.isValidNumber()) {
            $('#contact_edit_phone').addClass('is-invalid');
            problem = 'Enter a valid phone number.';
        }

        if (problem !== '') {
            $('#contact_modal .is-invalid').first().focus();
            toastr.error(problem);
            return;
        }

        set_loading('#do_contact_save', true);

        ApiDataSvc.apiCall('post', 'save_client_contact', {
            client_id: client_id,
            contact: editing,
            name: $('#contact_edit_name').val().trim(),
            title: $('#contact_edit_title').val().trim(),
            email: email,
            phone: (number === '' ? '' : phone.getNumber('INTERNATIONAL'))
        }, function (data) {

            var obj = JSON.parse(data);

            if (obj.success) {
                window.location.reload();
                return;
            }

            set_loading('#do_contact_save', false);
            toastr.error(obj.message);
        });
    });

    function size_of(done, total) {
        return total > 0 ? Math.round(done / total * 100) : 0;
    }

    var STATUS = {
        'Planned': {
            label: 'Not Started',
            tone: 'is-none'
        },
        'In Progress': {
            label: 'In Progress',
            tone: 'is-partial'
        },
        'Complete': {
            label: 'Complete',
            tone: 'is-ok'
        }
    };

    function render_assessments(assessments) {

        var html = '';

        for (var i = 0; i < assessments.length; i++) {

            var total = parseInt(assessments[i].item_count, 10);
            var done = parseInt(assessments[i].assessed_count, 10);
            var pct = size_of(done, total);
            var state = STATUS[assessments[i].assessment_status] || STATUS['Planned'];

            html += '<tr class="rowlink">'
                + '<td><a class="rowlink__a listpage__name" href="/assessments/detail/id/' + assessments[i].id + '">' + esc(assessments[i].assessment_name) + '</a></td>'
                + '<td>' + esc(assessments[i].short_code || '') + '</td>'
                + '<td><span class="listpage__status" title="' + state.label + '"><span class="rollup__dot ' + state.tone + '"></span><span class="visually-hidden">' + state.label + '</span></span></td>'
                + '<td><span class="listpage__progress"><span class="progress"><span class="progress__bar" style="width:' + pct + '%"></span></span><span class="listpage__pct">' + pct + '%</span></span></td>'
                + '</tr>';
        }

        $('#client_assessments_list').html(html);
        $('#client_assessments_table').prop('hidden', assessments.length === 0);
        $('#client_assessments_empty').prop('hidden', assessments.length > 0).text('No assessments for this client yet.');
    }

    function load_client_assessments(){
        ApiDataSvc.apiCall('post', 'load_client_assessments', { client_id: client_id }, function (data) {
            render_assessments(JSON.parse(data));
        });
    }

    function invoice_tone(value) {
        if (value === 'Paid') {
            return 'badge--active';
        }
        if (value === 'Overdue' || value === 'Payment Failed') {
            return 'badge--critical';
        }
        if (value === 'Payment Processing' || value === 'Sending' || value === 'Action Needed') {
            return 'badge--admin';
        }
        if (value === 'Awaiting Payment') {
            return 'badge--onboarding';
        }
        if (value === 'Void' || value === 'Written Off') {
            return 'badge--inactive';
        }
        return 'badge--prospect';
    }

    function load_client_invoices(){

        if ($('#client_invoices_list').length === 0) {
            return;
        }

        ApiDataSvc.apiCall('post', 'load_client_invoices', { client_id: client_id }, function (data) {

            var list = JSON.parse(data);
            var html = '';

            for (var i = 0; i < list.length; i++) {

                var due = (list[i].due_date === null
                    ? (list[i].due_days === null ? '' : (parseInt(list[i].due_days, 10) === 0 ? 'Due on receipt' : 'Net ' + parseInt(list[i].due_days, 10)))
                    : 'Due ' + esc(list[i].due_date_display));

                html += '<tr class="rowlink" data-id="' + list[i].id + '" tabindex="0">'
                    + '<td><span class="listpage__name">' + esc(list[i].invoice_number || '') + '</span></td>'
                    + '<td><span class="badge ' + invoice_tone(list[i].invoice_status_display) + '">' + esc(list[i].invoice_status_display) + '</span></td>'
                    + '<td>' + due + '</td>'
                    + '<td class="num">' + esc(list[i].total_display) + '</td>'
                    + '</tr>';
            }

            $('#client_invoices_list').html(html);
            $('#client_invoices_table').prop('hidden', list.length === 0);
            $('#client_invoices_empty').prop('hidden', list.length > 0).text('No invoices for this client yet.');
        });
    }

    $('#client_invoices_list').on('keydown', 'tr', function (event) {
        if (event.key === 'Enter') {
            $(this).trigger('click');
        }
    });

    $('#client_invoices_list').on('click', 'tr', function () {
        $('#invoice_view_frame').attr('src', '/billing/invoice/id/' + $(this).attr('data-id'));
        bootstrap.Modal.getOrCreateInstance(document.getElementById('invoice_view_modal')).show();
    });

    $('#invoice_view_modal').on('hidden.bs.modal', function () {
        $('#invoice_view_frame').attr('src', 'about:blank');
        load_client_invoices();
    });

    load_client_assessments();
    load_client_invoices();

    var client_id = <?php echo (int) $this->client['id']; ?>;

    $('#do_delete_open').click(function () {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('delete_modal')).show();
    });

    $('#do_delete').click(function () {

        set_loading('#do_delete', true);

        ApiDataSvc.apiCall('post', 'delete_client', { client_id: client_id }, function (data) {

            var obj = JSON.parse(data);

            set_loading('#do_delete', false);

            if (obj.success) {

                window.location.href = '/clients';
            } else {
                toastr.error(obj.message);
            }
        });
    });

});
</script>
