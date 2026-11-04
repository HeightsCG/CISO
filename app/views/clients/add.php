<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@29.5.10/dist/css/intlTelInput.min.css">
<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@29.5.10/dist/js/intlTelInputWithUtils.min.js"></script>
<div class="page">
    <div class="page__head">
        <div>
            <h1 class="page__title">Add Client</h1>
        </div>
    </div>
    <div class="panel wizard">
        <ol class="wizard__steps">
            <li class="wizard__step is-current" data-step="1"><span class="wizard__num">1</span> Company</li>
            <li class="wizard__step" data-step="2"><span class="wizard__num">2</span> Contacts</li>
        </ol>
        <div class="panel__body">
            <section class="wizard__pane" data-step="1">
                <div class="row g-3">
                    <div class="col-md-8 form-group">
                        <label for="company_name">Company Name <abbr title="required">*</abbr></label>
                        <input type="text" class="form-control" id="company_name" placeholder="Helvetic Regional Airways">
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="country">Country</label>
                        <select id="country" class="selectpicker" data-style="form-control" data-width="100%" data-live-search="true" data-size="10" title="Choose a country">
                            <?php foreach ($this->countries as $country) { echo '<option value="'.htmlspecialchars($country['name'], ENT_QUOTES, 'UTF-8').'" data-code="'.htmlspecialchars($country['code'], ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars($country['name'], ENT_QUOTES, 'UTF-8').'</option>'; } ?>
                        </select>
                    </div>
                    <div class="col-md-8 form-group">
                        <label for="address_1">Address</label>
                        <input type="text" class="form-control" id="address_1" placeholder="12 Hangar Way">
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="address_2">Suite, Floor</label>
                        <input type="text" class="form-control" id="address_2" placeholder="Suite 300">
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="city">City</label>
                        <input type="text" class="form-control" id="city" placeholder="Geneva">
                    </div>
                    <div class="col-md-4 form-group" id="state_group">
                        <label for="state">State</label>
                        <select id="state" class="selectpicker" data-style="form-control" data-width="100%" data-live-search="true" data-size="10" title="Choose a state" disabled></select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="postal_code">Postal Code</label>
                        <input type="text" class="form-control" id="postal_code" placeholder="1201">
                    </div>
                </div>
            </section>

            <section class="wizard__pane" data-step="2" hidden>
                <div class="row g-5">
                    <div class="col-md-6">
                        <h2 class="record__group-title">Primary Contact</h2>
                        <div class="row g-3">
                            <div class="col-12 form-group">
                                <label for="contact_name">Full Name</label>
                                <input type="text" class="form-control" id="contact_name" placeholder="John Doe">
                            </div>
                            <div class="col-12 form-group">
                                <label for="contact_title">Job Title</label>
                                <input type="text" class="form-control" id="contact_title" placeholder="Head of IT">
                            </div>
                            <div class="col-12 form-group">
                                <label for="contact_email">Email Address</label>
                                <input type="email" class="form-control" id="contact_email" placeholder="john.doe@example.com" autocapitalize="none" spellcheck="false">
                            </div>
                            <div class="col-12 form-group">
                                <label for="contact_phone">Phone</label>
                                <input type="tel" class="form-control" id="contact_phone">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h2 class="record__group-title">Billing Contact</h2>
                        <div class="row g-3">
                            <div class="col-12 form-group">
                                <label for="billing_name">Full Name</label>
                                <input type="text" class="form-control" id="billing_name" placeholder="Jane Doe">
                            </div>
                            <div class="col-12 form-group">
                                <label for="billing_title">Job Title</label>
                                <input type="text" class="form-control" id="billing_title" placeholder="Accounts Payable">
                            </div>
                            <div class="col-12 form-group">
                                <label for="billing_email">Email Address</label>
                                <input type="email" class="form-control" id="billing_email" placeholder="accounts@example.com" autocapitalize="none" spellcheck="false">
                            </div>
                            <div class="col-12 form-group">
                                <label for="billing_phone">Phone</label>
                                <input type="tel" class="form-control" id="billing_phone">
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <div class="panel__actions">
                <a class="btn btn--secondary me-auto" href="/clients">Cancel</a>
                <button type="button" id="do_back" class="btn btn--secondary" hidden>Back</button>
                <button type="button" id="do_next" class="btn btn--primary">Next</button>
                <button type="button" id="do_save" class="btn btn--primary" hidden>Save Client</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {

    var COUNTRIES = <?php echo json_encode(array_column($this->countries, null, 'name'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>;
    var FREE_DOMAINS = <?php echo json_encode(EmailRules::FREE_DOMAINS, JSON_HEX_TAG); ?>;
    var EMAIL_FORMAT = /^[^@\s]+@[^@\s.]+(\.[^@\s.]+)+$/;
    var step = 1;

    var fields = [
        'company_name', 'address_1', 'address_2', 'city', 'state', 'postal_code', 'country',
        'contact_name', 'contact_title', 'contact_email', 'contact_phone',
        'billing_name', 'billing_title', 'billing_email', 'billing_phone'
    ];

    function fill_states(country, chosen) {

        var list = (COUNTRIES[country] || { states: [] }).states;
        var html = '';

        for (var i = 0; i < list.length; i++) {
            html += '<option value="' + esc(list[i]) + '">' + esc(list[i]) + '</option>';
        }

        $('#state').html(html).prop('disabled', list.length === 0);
        $('#state_group').prop('hidden', country !== '' && list.length === 0);
        $('#state').selectpicker('val', list.indexOf(chosen) === -1 ? '' : chosen);
        $('#state').selectpicker('refresh');
    }

    function esc(value) {
        return $('<div>').text(value).html();
    }

    $('#country').on('changed.bs.select', function () {
        fill_states($(this).val() || '', '');
        match_phone_country();
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
            match_phone_country();
        }
    });

    function is_free(email) {
        return FREE_DOMAINS.indexOf(email.split('@').pop().toLowerCase()) !== -1;
    }

    var phones = {
        contact_phone: phone_field('contact_phone'),
        billing_phone: phone_field('billing_phone')
    };

    function phone_field(id) {
        return window.intlTelInput(document.getElementById(id), {
            countrySelectorMode: 'DROPDOWN',
            initialCountry: 'us',
            countrySearch: true,
            separateDialCode: true,
            strictMode: true,
            formatAsYouType: true,
            containerClass: 'iti--field'
        });
    }

    $('#contact_phone, #billing_phone').on('blur', function () {

        var field = phones[this.id];

        if (value(this.id) !== '' && field.isValidNumber()) {
            field.setNumber(field.getNumber('E164'));
        }
    });

    function company_country_code() {
        return ($('#country option:selected').attr('data-code') || '').toLowerCase();
    }

    function match_phone_country() {

        var code = company_country_code();

        if (code === '') {
            return;
        }

        $.each(phones, function (id, field) {
            if (value(id) === '') {
                field.setSelectedCountry(code);
            }
        });
    }

    function value(id) {
        return ($('#' + id).val() || '').trim();
    }

    function flag(id, message) {
        $('#' + id).addClass('is-invalid');
        return message;
    }

    function check_step(number) {

        var problems = [];

        $('.wizard__pane[data-step="' + number + '"] .form-control').removeClass('is-invalid');

        if (number === 1 && value('company_name') === '') {
            problems.push(flag('company_name', 'Company name is required.'));
        }

        if (number === 2) {

            var emails = ['contact_email', 'billing_email'];

            for (var i = 0; i < emails.length; i++) {

                var email = value(emails[i]);

                if (email !== '' && !EMAIL_FORMAT.test(email)) {
                    problems.push(flag(emails[i], 'Enter a valid email address.'));
                } else if (email !== '' && is_free(email)) {
                    problems.push(flag(emails[i], 'Use a business email address, not a free provider.'));
                }
            }

            $.each(phones, function (id, field) {
                if (value(id) !== '' && !field.isValidNumber()) {
                    problems.push(flag(id, 'Enter a valid phone number.'));
                }
            });
        }

        if (problems.length > 0) {
            $('.wizard__pane[data-step="' + number + '"] .is-invalid').first().focus();
            toastr.error(problems[0]);
            return false;
        }

        return true;
    }

    function show_step(number) {

        step = number;

        $('.wizard__pane').each(function () {
            $(this).prop('hidden', parseInt($(this).attr('data-step'), 10) !== number);
        });

        $('.wizard__step').each(function () {
            var n = parseInt($(this).attr('data-step'), 10);
            $(this).toggleClass('is-current', n === number).toggleClass('is-done', n < number);
        });

        $('#do_back').prop('hidden', number === 1);
        $('#do_next').prop('hidden', number === 2);
        $('#do_save').prop('hidden', number !== 2);

        $('.wizard__pane[data-step="' + number + '"] .form-control').first().focus();
    }

    $('#do_next').click(function () {
        if (check_step(1)) {
            show_step(2);
        }
    });

    $('#do_back').click(function () {
        show_step(1);
    });

    $('#do_save').click(function () {

        if (!check_step(1)) {
            show_step(1);
            return;
        }

        if (!check_step(2)) {
            return;
        }

        var values = { client_id: 0 };

        for (var i = 0; i < fields.length; i++) {
            values[fields[i]] = value(fields[i]);
        }

        $.each(phones, function (id, field) {
            values[id] = (value(id) === '' ? '' : field.getNumber('INTERNATIONAL'));
        });

        set_loading('#do_save', true);

        ApiDataSvc.apiCall('post', 'save_client', values, function (data) {

            var obj = JSON.parse(data);

            set_loading('#do_save', false);

            if (obj.success) {
                window.location.href = '/clients';
            } else {
                toastr.error(obj.message);
            }
        });
    });

    $('#country').selectpicker('val', 'United States');
    fill_states('United States', '');
    $('#company_name').focus();
});
</script>
