<div class="page">
        <div class="page__head">
            <div>
                <h1 class="page__title"><?php echo ($this->client === null ? 'Add Client' : 'Edit Client'); ?></h1>
            </div>
        </div>
        <div class="panel">
            <div class="panel__body">
                <div class="record__group">
                    <h2 class="record__group-title">Company</h2>
                    <div class="form-row">
                        <div class="field">
                            <label for="company_name">Company Name <abbr title="required">*</abbr></label>
                            <input type="text" id="company_name" placeholder="Helvetic Regional Airways" value="<?php echo ($this->client['company_name'] === null ? '' : htmlspecialchars($this->client['company_name'], ENT_QUOTES, 'UTF-8')); ?>">
                        </div>
                        <div class="field">
                            <label for="website">Website</label>
                            <input type="text" id="website" placeholder="https://example.com" autocapitalize="none" spellcheck="false" value="<?php echo ($this->client['website'] === null ? '' : htmlspecialchars($this->client['website'], ENT_QUOTES, 'UTF-8')); ?>">
                        </div>
                    </div>
                </div>
                <div class="record__group">
                    <h2 class="record__group-title">Address</h2>
                    <div class="form-row">
                        <div class="field">
                            <label for="address_1">Address</label>
                            <input type="text" id="address_1" placeholder="12 Hangar Way" value="<?php echo ($this->client['address_1'] === null ? '' : htmlspecialchars($this->client['address_1'], ENT_QUOTES, 'UTF-8')); ?>">
                        </div>
                        <div class="field">
                            <label for="address_2">Suite, Floor</label>
                            <input type="text" id="address_2" placeholder="Suite 300" value="<?php echo ($this->client['address_2'] === null ? '' : htmlspecialchars($this->client['address_2'], ENT_QUOTES, 'UTF-8')); ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="field">
                            <label for="city">City</label>
                            <input type="text" id="city" placeholder="Geneva" value="<?php echo ($this->client['city'] === null ? '' : htmlspecialchars($this->client['city'], ENT_QUOTES, 'UTF-8')); ?>">
                        </div>
                        <div class="field">
                            <label for="state">State</label>
                            <input type="text" id="state" placeholder="GE" value="<?php echo ($this->client['state'] === null ? '' : htmlspecialchars($this->client['state'], ENT_QUOTES, 'UTF-8')); ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="field">
                            <label for="postal_code">Postal Code</label>
                            <input type="text" id="postal_code" placeholder="1201" value="<?php echo ($this->client['postal_code'] === null ? '' : htmlspecialchars($this->client['postal_code'], ENT_QUOTES, 'UTF-8')); ?>">
                        </div>
                        <div class="field">
                            <label for="country">Country</label>
                            <input type="text" id="country" placeholder="Switzerland" value="<?php echo ($this->client['country'] === null ? '' : htmlspecialchars($this->client['country'], ENT_QUOTES, 'UTF-8')); ?>">
                        </div>
                    </div>
                </div>
                <div class="record__group">
                    <h2 class="record__group-title">Primary Contact</h2>
                    <div class="form-row">
                        <div class="field">
                            <label for="contact_name">Full Name</label>
                            <input type="text" id="contact_name" placeholder="John Doe" value="<?php echo ($this->client === null ? '' : htmlspecialchars($this->client['contact_name'], ENT_QUOTES, 'UTF-8')); ?>">
                        </div>
                        <div class="field">
                            <label for="contact_title">Job Title</label>
                            <input type="text" id="contact_title" placeholder="Head of IT" value="<?php echo ($this->client === null ? '' : htmlspecialchars($this->client['contact_title'], ENT_QUOTES, 'UTF-8')); ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="field">
                            <label for="contact_email">Email Address</label>
                            <input type="email" id="contact_email" placeholder="john.doe@example.com" autocapitalize="none" spellcheck="false" value="<?php echo ($this->client === null ? '' : htmlspecialchars($this->client['contact_email'], ENT_QUOTES, 'UTF-8')); ?>">
                        </div>
                        <div class="field">
                            <label for="contact_phone">Phone</label>
                            <input type="text" id="contact_phone" placeholder="+41 22 555 0110" value="<?php echo ($this->client === null ? '' : htmlspecialchars($this->client['contact_phone'], ENT_QUOTES, 'UTF-8')); ?>">
                        </div>
                    </div>
                </div>
                <div class="record__group">
                    <h2 class="record__group-title">Billing</h2>
                    <div class="form-row">
                        <div class="field">
                            <label for="billing_currency">Currency</label>
                            <select id="billing_currency">
                                <option value="usd"<?php echo ($this->client !== null && $this->client['billing_currency'] === 'usd' ? ' selected' : ''); ?>>USD &mdash; US Dollar</option>
                                <option value="eur"<?php echo ($this->client !== null && $this->client['billing_currency'] === 'eur' ? ' selected' : ''); ?>>EUR &mdash; Euro</option>
                                <option value="chf"<?php echo ($this->client !== null && $this->client['billing_currency'] === 'chf' ? ' selected' : ''); ?>>CHF &mdash; Swiss Franc</option>
                                <option value="gbp"<?php echo ($this->client !== null && $this->client['billing_currency'] === 'gbp' ? ' selected' : ''); ?>>GBP &mdash; Pound Sterling</option>
                            </select>
                            <p class="field__help">Every invoice for this client is raised in this currency.</p>
                        </div>
                        <div class="field">
                            <label for="billing_email">Billing Email</label>
                            <input type="email" id="billing_email" placeholder="accounts@example.com" autocapitalize="none" spellcheck="false" value="<?php echo ($this->client === null ? '' : htmlspecialchars($this->client['billing_email'], ENT_QUOTES, 'UTF-8')); ?>">
                            <p class="field__help">Where invoices are sent. Falls back to the contact email.</p>
                        </div>
                    </div>
                </div>
                <div class="panel__actions">
                    <a class="btn btn--secondary" href="<?php echo (Main::get_param('from') === 'detail' ? '/clients/detail/id/'.((int) Main::get_param('id')) : '/clients'); ?>">Cancel</a>
                    <button type="button" id="do_save" class="btn btn--primary">Save Client</button>
                </div>
            </div>
        </div>
</div>

<script>
$(document).ready(function () {

    var client_id = <?php echo ($this->client === null ? 0 : (int) $this->client['id']); ?>;
    var return_url = "<?php echo (Main::get_param('from') === 'detail' ? '/clients/detail/id/'.((int) Main::get_param('id')) : '/clients'); ?>";

    var fields = [
        'company_name', 'website', 'address_1', 'address_2', 'city', 'state',
        'postal_code', 'country', 'contact_name', 'contact_title', 'contact_email', 'contact_phone',
        'billing_currency', 'billing_email'
    ];

    AddressAutocomplete.attach({
        input: 'address_1',
        country: 'country',
        targets: {
            city: 'city',
            state: 'state',
            postal_code: 'postal_code',
            country: 'country'
        }
    });

    function set_loading(target, loading) {
        if (loading) {
            $(target).addClass("is-loading").prop("disabled", true);
        } else {
            $(target).removeClass("is-loading").prop("disabled", false);
        }
    }

    function collect() {

        var values = { client_id: client_id };

        for (var i = 0; i < fields.length; i++) {
            values[fields[i]] = $('#' + fields[i]).val().trim();
        }

        return values;
    }

    $('#do_save').click(function () {

        $('.field input').removeClass('is-invalid');

        var values = collect();
        var errors = 0;

        if (values.company_name === '') {
            $('#company_name').addClass('is-invalid');
            errors++;
        }

        if (values.contact_email !== '' && !/^[^@\s]+@[^@\s.]+(\.[^@\s.]+)+$/.test(values.contact_email)) {
            $('#contact_email').addClass('is-invalid');
            errors++;
        }

        if (values.billing_email !== '' && !/^[^@\s]+@[^@\s.]+(\.[^@\s.]+)+$/.test(values.billing_email)) {
            $('#billing_email').addClass('is-invalid');
            errors++;
        }

        if (values.website !== '' && !/^https?:\/\/.+/i.test(values.website)) {
            $('#website').addClass('is-invalid');
            errors++;
        }

        if (errors > 0) {
            $('.field input.is-invalid').first().focus();
            toastr.error('Please fix the errors before continuing.');
            return;
        }

        set_loading('#do_save', true);

        ApiDataSvc.apiCall('post', 'save_client', values, function (data) {

            var obj = JSON.parse(data);

            set_loading('#do_save', false);

            if (obj.success) {

                window.location.href = return_url;
            } else {
                toastr.error(obj.message);
            }
        });
    });

});
</script>
