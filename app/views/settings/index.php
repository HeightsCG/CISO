<div class="page">
    <div class="page__head">
        <div>
            <h1 class="page__title">Settings</h1>
        </div>
    </div>
    <div class="settings">

        <nav class="nav nav-pills flex-column submenu" role="tablist" aria-label="Settings">
            <button class="nav-link submenu__link active" id="tab_company_btn" data-bs-toggle="pill" data-bs-target="#tab_company" type="button" role="tab" aria-controls="tab_company" aria-selected="true">Company Details</button>
            <button class="nav-link submenu__link" id="tab_regional_btn" data-bs-toggle="pill" data-bs-target="#tab_regional" type="button" role="tab" aria-controls="tab_regional" aria-selected="false">Regional Defaults</button>
            <button class="nav-link submenu__link" id="tab_branding_btn" data-bs-toggle="pill" data-bs-target="#tab_branding" type="button" role="tab" aria-controls="tab_branding" aria-selected="false">Branding</button>
            <button class="nav-link submenu__link" id="tab_security_btn" data-bs-toggle="pill" data-bs-target="#tab_security" type="button" role="tab" aria-controls="tab_security" aria-selected="false">Security</button>
        </nav>

        <div class="tab-content settings-panes">
            <div class="tab-pane fade show active" id="tab_company" role="tabpanel" aria-labelledby="tab_company_btn">
                <section class="card">
                    <header class="card__head">
                        <h2 class="card__title">Company Details</h2>
                    </header>
                    <div class="card__body">
                        <div class="grid grid--2">
                            <div class="field">
                                <div class="form-floating">
                                    <input type="text" id="company_name" class="form-control" placeholder="Company name" required>
                                    <label for="company_name">Company Name <abbr title="required">*</abbr></label>
                                </div>
                            </div>
                            <div class="field">
                                <div class="form-floating">
                                    <input type="text" id="trading_name" class="form-control" placeholder="Trading name">
                                    <label for="trading_name">Trading Name</label>
                                </div>
                            </div>
                        </div>
                        <div class="grid grid--2">
                            <div class="field">
                                <div class="form-floating">
                                    <input type="text" id="email_domain" class="form-control" placeholder="Email domain" autocapitalize="none" spellcheck="false" aria-describedby="email_domain_help">
                                    <label for="email_domain">Email Domain</label>
                                </div>
                                <p class="field__help" id="email_domain_help">Domain only, without a protocol. For example: example.com</p>
                            </div>
                            <div class="field">
                                <div class="form-floating">
                                    <input type="text" id="website" class="form-control" placeholder="Website" autocapitalize="none" spellcheck="false" aria-describedby="website_help">
                                    <label for="website">Website</label>
                                </div>
                                <p class="field__help" id="website_help">Include the protocol. For example: https://example.com</p>
                            </div>
                        </div>
                    </div>
                </section>
                <section class="card">
                    <header class="card__head">
                        <h2 class="card__title">Business Address</h2>
                    </header>
                    <div class="card__body">
                        <div class="grid grid--2">
                            <div class="field">
                                <div class="form-floating">
                                    <input type="text" id="address_1" class="form-control" placeholder="Street">
                                    <label for="address_1">Street</label>
                                </div>
                            </div>
                            <div class="field">
                                <div class="form-floating">
                                    <input type="text" id="address_2" class="form-control" placeholder="Suite, floor">
                                    <label for="address_2">Suite, floor</label>
                                </div>
                            </div>
                        </div>
                        <div class="grid grid--2">
                            <div class="field">
                                <div class="form-floating">
                                    <input type="text" id="city" class="form-control" placeholder="City">
                                    <label for="city">City</label>
                                </div>
                            </div>
                            <div class="field">
                                <div class="form-floating">
                                    <input type="text" id="state" class="form-control" placeholder="State">
                                    <label for="state">State</label>
                                </div>
                            </div>
                        </div>
                        <div class="grid grid--2">
                            <div class="field">
                                <div class="form-floating">
                                    <input type="text" id="postal_code" class="form-control" placeholder="Postal code">
                                    <label for="postal_code">Postal code</label>
                                </div>
                            </div>
                            <div class="field">
                                <div class="form-floating">
                                    <input type="text" id="country" class="form-control" placeholder="Country">
                                    <label for="country">Country</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
            <div class="tab-pane fade" id="tab_regional" role="tabpanel" aria-labelledby="tab_regional_btn">
                <section class="card">
                    <header class="card__head">
                        <h2 class="card__title">Regional Defaults</h2>
                    </header>
                    <div class="card__body">
                        <div class="field">
                            <div class="form-floating form-floating--select">
                                <select id="timezone" class="selectpicker" data-live-search="true" data-size="10" required></select>
                                <label for="timezone">Timezone <abbr title="required">*</abbr></label>
                            </div>
                        </div>
                        <div class="grid grid--2">
                            <div class="field">
                                <div class="form-floating">
                                    <select id="date_format_id" class="form-select"></select>
                                    <label for="date_format_id">Date Format</label>
                                </div>
                            </div>
                            <div class="field">
                                <div class="form-floating">
                                    <select id="time_format" class="form-select">
                                        <option value="H:i">14:30 &mdash; 24 hour</option>
                                        <option value="H:i:s">14:30:00 &mdash; 24 hour with seconds</option>
                                        <option value="g:i A">2:30 PM &mdash; 12 hour</option>
                                        <option value="g:i a">2:30 pm &mdash; 12 hour lowercase</option>
                                        <option value="g:i:s A">2:30:00 PM &mdash; 12 hour with seconds</option>
                                    </select>
                                    <label for="time_format">Time Format</label>
                                </div>
                            </div>
                        </div>
                        <p class="field__help" id="format_example" aria-live="polite"></p>
                    </div>
                </section>
            </div>
            <div class="tab-pane fade" id="tab_branding" role="tabpanel" aria-labelledby="tab_branding_btn">
                <section class="card">
                    <header class="card__head">
                        <h2 class="card__title">Logo</h2>
                    </header>
                    <div class="card__body">
                        <div class="logo" id="logo_empty" hidden>
                            <div class="dropzone" id="logo_dropzone" tabindex="0" role="button" aria-label="Upload report logo">
                                <span class="dropzone__badge">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"></path></svg>
                                </span>
                                <p class="dropzone__lead">Drop a logo here, or <span class="dropzone__link">browse</span></p>
                                <p class="dropzone__meta">No logo is set</p>
                            </div>
                            <p class="field__help">PNG or JPG, at least 120 &times; 40 px, up to 2 MB &middot; rendered 40 px tall on reports.</p>
                        </div>
                        <div class="logo" id="logo_set" hidden>
                            <div class="logo__card" id="logo_replace" tabindex="0" role="button" aria-label="Replace report logo">
                                <div class="logo__thumb">
                                    <img id="logo_image" alt="Report logo">
                                </div>
                                <div class="logo__meta">
                                    <p class="logo__name" id="logo_name"></p>
                                    <p class="logo__size" id="logo_size"></p>
                                </div>
                                <button type="button" class="logo__remove" id="do_remove_logo" aria-label="Remove logo" title="Remove logo">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"></path></svg>
                                </button>
                                <div class="logo__overlay">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"></path></svg>
                                    <span>Drop to replace</span>
                                </div>
                            </div>
                            <p class="field__help">Click or drop a file to replace &middot; rendered 40 px tall on reports</p>
                        </div>
                        <input type="file" id="logo_file" accept="image/png,image/jpeg" hidden>
                    </div>
                </section>
                <section class="card">
                    <header class="card__head">
                        <h2 class="card__title">Brand Colors</h2>
                    </header>
                    <div class="card__body">
                        <div class="grid grid--3">
                            <div class="field">
                                <div class="colorpick">
                                    <input type="color" id="brand_swatch" class="colorpick__swatch" aria-label="Pick primary color">
                                    <div class="form-floating colorpick__field">
                                        <input type="text" id="brand_color" class="form-control colorpick__hex" placeholder="#075985" maxlength="7" spellcheck="false" autocapitalize="none" aria-describedby="brand_color_help" required>
                                        <label for="brand_color">Primary <abbr title="required">*</abbr></label>
                                    </div>
                                </div>
                                <p class="field__help" id="brand_color_help">Headings and accents on reports.</p>
                            </div>
                            <div class="field">
                                <div class="colorpick">
                                    <input type="color" id="brand_swatch_secondary" class="colorpick__swatch" aria-label="Pick secondary color">
                                    <div class="form-floating colorpick__field">
                                        <input type="text" id="brand_color_secondary" class="form-control colorpick__hex" placeholder="#334155" maxlength="7" spellcheck="false" autocapitalize="none" aria-describedby="brand_color_secondary_help">
                                        <label for="brand_color_secondary">Secondary</label>
                                    </div>
                                </div>
                                <p class="field__help" id="brand_color_secondary_help">Subheadings and table headers.</p>
                            </div>
                            <div class="field">
                                <div class="colorpick">
                                    <input type="color" id="brand_swatch_accent" class="colorpick__swatch" aria-label="Pick accent color">
                                    <div class="form-floating colorpick__field">
                                        <input type="text" id="brand_color_accent" class="form-control colorpick__hex" placeholder="#0EA5E9" maxlength="7" spellcheck="false" autocapitalize="none" aria-describedby="brand_color_accent_help">
                                        <label for="brand_color_accent">Accent</label>
                                    </div>
                                </div>
                                <p class="field__help" id="brand_color_accent_help">Charts, callouts, and highlights.</p>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
            <div class="tab-pane fade" id="tab_security" role="tabpanel" aria-labelledby="tab_security_btn">
                <section class="card">
                    <header class="card__head">
                        <h2 class="card__title">Security Policies</h2>
                    </header>
                    <div class="policy" id="policy_session">
                        <span class="policy__tile">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path></svg>
                        </span>
                        <div class="policy__main">
                            <h3 class="policy__name" id="policy_session_name">Session Timeout</h3>
                            <p class="policy__desc">Signs a user out after a period of inactivity.</p>
                            <div class="policy__fields">
                                <div class="numfield">
                                    <label class="numfield__label" for="session_timeout_minutes">Sign out after</label>
                                    <span class="numfield__control">
                                        <input type="text" id="session_timeout_minutes" class="numinput" inputmode="numeric" autocomplete="off">
                                        <span class="numfield__unit">min</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="segmented" id="seg_session" data-enabled="1" role="group" aria-labelledby="policy_session_name">
                            <button type="button" class="segmented__btn" data-value="1" aria-pressed="false">On</button>
                            <button type="button" class="segmented__btn" data-value="0" aria-pressed="false">Off</button>
                        </div>
                    </div>
                    <div class="policy" id="policy_expiry">
                        <span class="policy__tile">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-3-6.7"></path><path d="M21 4v5h-5"></path></svg>
                        </span>
                        <div class="policy__main">
                            <h3 class="policy__name" id="policy_expiry_name">Password Expiry</h3>
                            <p class="policy__desc">Requires a new password on a schedule.</p>
                            <div class="policy__fields">
                                <div class="numfield">
                                    <label class="numfield__label" for="password_expiry_days">Change every</label>
                                    <span class="numfield__control">
                                        <input type="text" id="password_expiry_days" class="numinput" inputmode="numeric" autocomplete="off">
                                        <span class="numfield__unit">days</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="segmented" id="seg_expiry" data-enabled="1" role="group" aria-labelledby="policy_expiry_name">
                            <button type="button" class="segmented__btn" data-value="1" aria-pressed="false">On</button>
                            <button type="button" class="segmented__btn" data-value="0" aria-pressed="false">Off</button>
                        </div>
                    </div>
                    <div class="policy" id="policy_lockout">
                        <span class="policy__tile">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="1"></rect><path d="M8 11V7a4 4 0 0 1 8 0v4"></path></svg>
                        </span>
                        <div class="policy__main">
                            <h3 class="policy__name" id="policy_lockout_name">Account Lockout</h3>
                            <p class="policy__desc">Blocks sign-in after repeated failed attempts.</p>
                            <div class="policy__fields">
                                <div class="numfield">
                                    <label class="numfield__label" for="lockout_attempts">Failed attempts</label>
                                    <span class="numfield__control">
                                        <input type="text" id="lockout_attempts" class="numinput" inputmode="numeric" autocomplete="off">
                                    </span>
                                </div>
                                <div class="numfield">
                                    <label class="numfield__label" for="lockout_minutes">Lock account for</label>
                                    <span class="numfield__control">
                                        <input type="text" id="lockout_minutes" class="numinput" inputmode="numeric" autocomplete="off">
                                        <span class="numfield__unit">min</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="segmented" id="seg_lockout" data-enabled="1" role="group" aria-labelledby="policy_lockout_name">
                            <button type="button" class="segmented__btn" data-value="1" aria-pressed="false">On</button>
                            <button type="button" class="segmented__btn" data-value="0" aria-pressed="false">Off</button>
                        </div>
                    </div>
                    <div class="policy" id="policy_mfa">
                        <span class="policy__tile">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l7 3v6c0 4.4-3 7.7-7 9-4-1.3-7-4.6-7-9V6z"></path><path d="M9 12l2 2 4-4"></path></svg>
                        </span>
                        <div class="policy__main">
                            <h3 class="policy__name" id="policy_mfa_name">Multi-factor Authentication</h3>
                            <p class="policy__desc">Requires a second factor at sign-in.</p>
                            <div class="policy__fields policy__fields--stack">
                                <span class="numfield__label" id="mfa_methods_label">Methods users may enroll</span>
                                <div class="methods" role="group" aria-labelledby="mfa_methods_label">
                                    <label class="method">
                                        <input type="checkbox" id="mfa_authenticator">
                                        <span>Authenticator app</span>
                                    </label>
                                    <label class="method">
                                        <input type="checkbox" id="mfa_email">
                                        <span>Email code</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="segmented" id="seg_mfa" data-enabled="1" role="group" aria-labelledby="policy_mfa_name">
                            <button type="button" class="segmented__btn" data-value="1" aria-pressed="false">On</button>
                            <button type="button" class="segmented__btn" data-value="0" aria-pressed="false">Off</button>
                        </div>
                    </div>
                </section>
                <div class="savebar" id="security_savebar">
                    <span class="savebar__status" id="security_status" aria-live="polite">Loading settings&hellip;</span>
                    <span class="savebar__actions">
                        <button type="button" id="do_security_save" class="btn btn--primary" disabled>Save changes</button>
                    </span>
                </div>
            </div>
            <div class="savebar" id="savebar">
                <span class="savebar__status" id="savebar_text" aria-live="polite">Loading settings&hellip;</span>
                <span class="savebar__actions">
                    <button type="button" id="do_save" class="btn btn--primary" disabled>Save changes</button>
                </span>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {

    var snapshot = null;

    function set_loading(target, loading) {
        if (loading) {
            $(target).addClass("is-loading").prop("disabled", true);
        } else {
            $(target).removeClass("is-loading").prop("disabled", false);
        }
    }

    $(document).ajaxComplete(function () {
        if (snapshot !== null) {
            set_loading("#do_save", false);
            set_loading("#do_security_save", false);
        }
    });

    function human_size(bytes) {

        var value = parseInt(bytes, 10);

        if (!value) {
            return "";
        }

        if (value < 1024) {
            return value + " B";
        }

        if (value < 1048576) {
            return (value / 1024).toFixed(1).replace(/\.0$/, "") + " KB";
        }

        return (value / 1048576).toFixed(1).replace(/\.0$/, "") + " MB";
    }

    function set_logo(logo_path, logo_filename, logo_size) {
        if (logo_path === "") {
            $("#logo_empty").prop("hidden", false);
            $("#logo_set").prop("hidden", true);
            $("#logo_image").attr("src", "");
            return;
        }
        $("#logo_empty").prop("hidden", true);
        $("#logo_set").prop("hidden", false);
        $("#logo_image").attr("src", logo_path);
        $("#logo_name").text(logo_filename || "logo");
        $("#logo_size").text(human_size(logo_size));
    }

    function update_format_example() {
        var d = $("#date_format_id option:selected").text();
        var t = $("#time_format option:selected").text().split(" — ")[0];
        $("#format_example").text("Dates and times will read: " + d + " at " + t);
    }

    var colors = [
        { hex: "brand_color",           swatch: "brand_swatch",           required: true },
        { hex: "brand_color_secondary", swatch: "brand_swatch_secondary", required: false },
        { hex: "brand_color_accent",    swatch: "brand_swatch_accent",    required: false }
    ];

    function set_color(hex_id, swatch_id, value, fallback) {

        var color = (value || "").trim().toUpperCase();

        if (!/^#[0-9A-Fa-f]{6}$/.test(color)) {
            color = fallback;
        }

        $("#" + hex_id).val(color);
        $("#" + swatch_id).val(color);
    }

    var policies = [
        { seg: "seg_session", row: "policy_session", key: "session_timeout_enabled" },
        { seg: "seg_expiry",  row: "policy_expiry",  key: "password_expiry_enabled" },
        { seg: "seg_lockout", row: "policy_lockout", key: "account_lockout_enabled" },
        { seg: "seg_mfa",     row: "policy_mfa",     key: "mfa_enabled" }
    ];

    function set_policy(seg_id, enabled) {

        var seg = $("#" + seg_id);
        var row = seg.closest(".policy");

        seg.attr("data-enabled", enabled ? "1" : "0");
        seg.find(".segmented__btn").removeClass("is-on").attr("aria-pressed", "false");
        seg.find('.segmented__btn[data-value="' + (enabled ? "1" : "0") + '"]').addClass("is-on").attr("aria-pressed", "true");

        row.toggleClass("is-off", !enabled);
        row.find(".policy__fields").find("input").prop("disabled", !enabled);
    }

    function policy_on(seg_id) {
        return $("#" + seg_id).attr("data-enabled") === "1";
    }

    function collect_security() {

        var methods = [];

        if ($("#mfa_authenticator").is(":checked")) {
            methods.push("authenticator");
        }

        if ($("#mfa_email").is(":checked")) {
            methods.push("email");
        }

        return {
            session_timeout_enabled: (policy_on("seg_session") ? 1 : 0),
            session_timeout_minutes: $("#session_timeout_minutes").val().trim(),
            password_expiry_enabled: (policy_on("seg_expiry") ? 1 : 0),
            password_expiry_days: $("#password_expiry_days").val().trim(),
            account_lockout_enabled: (policy_on("seg_lockout") ? 1 : 0),
            lockout_attempts: $("#lockout_attempts").val().trim(),
            lockout_minutes: $("#lockout_minutes").val().trim(),
            mfa_enabled: (policy_on("seg_mfa") ? 1 : 0),
            mfa_methods: methods.join(",")
        };
    }

    function collect_company() {
        return {
            company_name: $("#company_name").val().trim(),
            trading_name: $("#trading_name").val().trim(),
            email_domain: $("#email_domain").val().trim().toLowerCase(),
            website: $("#website").val().trim(),
            address_1: $("#address_1").val().trim(),
            address_2: $("#address_2").val().trim(),
            city: $("#city").val().trim(),
            state: $("#state").val().trim(),
            postal_code: $("#postal_code").val().trim(),
            country: $("#country").val().trim(),
            timezone: $("#timezone").val(),
            date_format_id: $("#date_format_id").val(),
            time_format: $("#time_format").val(),
            brand_color: $("#brand_color").val().trim().toUpperCase(),
            brand_color_secondary: $("#brand_color_secondary").val().trim().toUpperCase(),
            brand_color_accent: $("#brand_color_accent").val().trim().toUpperCase()
        };
    }

    function take_snapshot() {
        snapshot = {
            company: JSON.stringify(collect_company()),
            security: JSON.stringify(collect_security())
        };
    }

    function company_dirty() {
        return snapshot !== null && JSON.stringify(collect_company()) !== snapshot.company;
    }

    function security_dirty() {
        return snapshot !== null && JSON.stringify(collect_security()) !== snapshot.security;
    }

    function refresh_savebar() {

        if (snapshot === null) {
            return;
        }

        var dirty_company = company_dirty();
        var dirty_security = security_dirty();

        $("#security_savebar").toggleClass("is-dirty", dirty_security);
        $("#security_status").text(dirty_security ? "Unsaved changes" : "All changes saved");

        if (dirty_security) {
            $("#security_savebar").removeClass("is-saved");
        }

        $("#savebar").toggleClass("is-dirty", dirty_company || dirty_security);
        $("#savebar_text").text((dirty_company || dirty_security) ? "Unsaved changes" : "All changes saved");

        if (dirty_company || dirty_security) {
            $("#savebar").removeClass("is-saved");
        }
    }

    function confirm_saved(bar_id) {
        $("#" + bar_id).removeClass("is-dirty").addClass("is-saved");
    }

    function upload_logo(file) {

        if (file.type !== "image/png" && file.type !== "image/jpeg") {
            toastr.error("The logo must be a PNG or JPG image.");
            return;
        }

        if (file.size > 2097152) {
            toastr.error("The logo must be 2MB or smaller.");
            return;
        }

        var form_data = new FormData();

        form_data.append("logo_file", file);
        form_data.append("csrf_token", $('meta[name="csrf-token"]').attr("content"));

        $("#logo_dropzone, #logo_replace").addClass("is-uploading");

        $.ajax({
            url: "/api/save_logo",
            type: "POST",
            data: form_data,
            processData: false,
            contentType: false,
            success: function (data) {

                $("#logo_dropzone, #logo_replace").removeClass("is-uploading");

                var obj = JSON.parse(data);

                if (obj.success) {
                    set_logo(obj.logo_path, obj.logo_filename, obj.logo_size);
                    toastr.success(obj.message);
                } else {
                    toastr.error(obj.message);
                }
            },
            error: function () {
                $("#logo_dropzone, #logo_replace").removeClass("is-uploading");
                toastr.error("The logo could not be uploaded.");
            }
        });
    }

    function load_company() {

        ApiDataSvc.apiCall('post', 'get_company', {}, function (data) {

            var obj = JSON.parse(data);
            var company = obj.data;
            var groups = {};
            var order = [];

            $.each(obj.timezones, function (index, zone) {

                if (!groups[zone.group]) {
                    groups[zone.group] = [];
                    order.push(zone.group);
                }

                groups[zone.group].push(
                    $("<option>")
                        .attr("value", zone.id)
                        .attr("data-tokens", zone.tokens)
                        .text(zone.label)
                );
            });

            $("#timezone").empty();

            $.each(order, function (index, name) {
                $("#timezone").append($("<optgroup>").attr("label", name).append(groups[name]));
            });

            $("#date_format_id").empty();

            $.each(obj.date_formats, function (index, format) {
                $("#date_format_id").append(
                    $("<option>").attr("value", format.id).text(format.label)
                );
            });

            var selected = company.timezone;

            if (!selected) {

                var detected = "";

                try {
                    detected = Intl.DateTimeFormat().resolvedOptions().timeZone;
                } catch (e) {
                    detected = "";
                }

                if (detected && $("#timezone option[value='" + detected + "']").length > 0) {
                    selected = detected;
                }
            }

            $("#timezone").val(selected);
            $("#timezone").selectpicker("refresh");

            $("#company_name").val(company.company_name);
            $("#trading_name").val(company.trading_name);
            $("#email_domain").val(company.email_domain);
            $("#website").val(company.website);
            $("#address_1").val(company.address_1);
            $("#address_2").val(company.address_2);
            $("#city").val(company.city);
            $("#state").val(company.state);
            $("#postal_code").val(company.postal_code);
            $("#country").val(company.country);
            $("#date_format_id").val(company.date_format_id);
            $("#time_format").val(company.time_format);
            set_color("brand_color", "brand_swatch", company.brand_color, "#075985");
            set_color("brand_color_secondary", "brand_swatch_secondary", company.brand_color_secondary, "#334155");
            set_color("brand_color_accent", "brand_swatch_accent", company.brand_color_accent, "#0EA5E9");

            $("#session_timeout_minutes").val(company.session_timeout_minutes);
            $("#password_expiry_days").val(company.password_expiry_days);
            $("#lockout_attempts").val(company.lockout_attempts);
            $("#lockout_minutes").val(company.lockout_minutes);

            var stored_methods = (company.mfa_methods || "").split(",");

            $("#mfa_authenticator").prop("checked", $.inArray("authenticator", stored_methods) !== -1);
            $("#mfa_email").prop("checked", $.inArray("email", stored_methods) !== -1);

            $.each(policies, function (index, policy) {
                set_policy(policy.seg, parseInt(company[policy.key], 10) === 1);
            });
            set_logo(company.logo_path, company.logo_filename, company.logo_size);
            update_format_example();
            take_snapshot();
            $("#do_save, #do_security_save").prop("disabled", false);
            refresh_savebar();
        });
    }

    $(".settings").on("input change", "input, select", function () {
        refresh_savebar();
    });

    $("#timezone").on("changed.bs.select", function () {
        refresh_savebar();
    });

    function sync_savebar_visibility() {
        $("#savebar").prop("hidden", $(".submenu__link.active").attr("id") === "tab_security_btn");
    }

    $('.submenu__link').on("shown.bs.tab", function () {
        sync_savebar_visibility();
    });

    $(".segmented__btn").click(function () {

        var seg = $(this).closest(".segmented");

        set_policy(seg.attr("id"), $(this).data("value") === 1);
        refresh_savebar();
    });

    $("#date_format_id, #time_format").change(function () {
        update_format_example();
    });

    $.each(colors, function (index, color) {

        $("#" + color.swatch).on("input", function () {
            $("#" + color.hex).val($(this).val().toUpperCase()).removeClass("is-invalid");
            refresh_savebar();
        });

        $("#" + color.hex).on("input", function () {
            var value = $(this).val().trim();
            if (/^#[0-9A-Fa-f]{6}$/.test(value)) {
                $("#" + color.swatch).val(value);
            }
        });
    });

    $("#logo_dropzone").click(function () {
        $("#logo_file").val("").click();
    });

    $("#logo_dropzone").keydown(function (event) {
        if (event.key === "Enter" || event.key === " ") {
            event.preventDefault();
            $("#logo_file").val("").click();
        }
    });

    $("#logo_replace").click(function () {
        $("#logo_file").val("").click();
    });

    $("#logo_replace").keydown(function (event) {
        if (event.key === "Enter" || event.key === " ") {
            event.preventDefault();
            $("#logo_file").val("").click();
        }
    });

    $("#logo_replace").on("dragover dragenter", function (event) {
        $(this).addClass("is-dragging");
    });

    $("#logo_replace").on("dragleave dragend drop", function (event) {
        $(this).removeClass("is-dragging");
    });

    $("#logo_replace").on("drop", function (event) {
        var files = event.originalEvent.dataTransfer.files;
        if (files.length > 0) {
            upload_logo(files[0]);
        }
    });

    $("#logo_dropzone").on("dragover dragenter", function (event) {
        $(this).addClass("is-dragging");
    });

    $("#logo_dropzone").on("dragleave dragend drop", function (event) {
        $(this).removeClass("is-dragging");
    });

    $("#logo_dropzone").on("drop", function (event) {

        var files = event.originalEvent.dataTransfer.files;

        if (files.length > 0) {
            upload_logo(files[0]);
        }
    });

    $("#logo_file").change(function () {

        if (this.files[0]) {
            upload_logo(this.files[0]);
        }
    });

    $("#do_remove_logo").click(function (event) {

        event.stopPropagation();

        set_loading("#do_remove_logo", true);

        ApiDataSvc.apiCall('post', 'remove_logo', {}, function (data) {

            var obj = JSON.parse(data);

            set_loading("#do_remove_logo", false);

            if (obj.success) {
                set_logo("", "", 0);
                toastr.success(obj.message);
            } else {
                toastr.error(obj.message);
            }
        });
    });

    $("#do_security_save").click(function () {

        $(".numinput").removeClass("is-invalid");

        var errors = 0;
        var checks = [
            { seg: "seg_session", field: "session_timeout_minutes" },
            { seg: "seg_expiry",  field: "password_expiry_days" },
            { seg: "seg_lockout", field: "lockout_attempts" },
            { seg: "seg_lockout", field: "lockout_minutes" }
        ];

        $.each(checks, function (index, check) {

            if (!policy_on(check.seg)) {
                return;
            }

            if (!/^[1-9][0-9]*$/.test($("#" + check.field).val().trim())) {
                $("#" + check.field).addClass("is-invalid");
                errors++;
            }
        });

        if (policy_on("seg_mfa") && !$("#mfa_authenticator").is(":checked") && !$("#mfa_email").is(":checked")) {
            toastr.error("Choose at least one multi-factor method.");
            return;
        }

        if (errors > 0) {
            $(".numinput.is-invalid").first().focus();
            toastr.error("Every value must be a whole number greater than zero.");
            return;
        }

        set_loading("#do_security_save", true);

        ApiDataSvc.apiCall('post', 'save_security', collect_security(), function (data) {

            var obj = JSON.parse(data);

            set_loading("#do_security_save", false);

            if (obj.success) {
                take_snapshot();
                refresh_savebar();
                confirm_saved("security_savebar");
                toastr.success(obj.message);
            } else {
                toastr.error(obj.message);
            }
        });

    });

    $("#do_save").click(function () {

        $(".form-control").removeClass("is-invalid");

        var errors = 0;

        if ($("#company_name").val().trim() === "") {
            $("#company_name").addClass("is-invalid");
            errors++;
        }

        if ($("#website").val().trim() !== "" && !/^https?:\/\/.+/i.test($("#website").val().trim())) {
            $("#website").addClass("is-invalid");
            errors++;
        }

        if ($("#email_domain").val().trim() !== "" && !/^[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)+$/.test($("#email_domain").val().trim())) {
            $("#email_domain").addClass("is-invalid");
            errors++;
        }

        $.each(colors, function (index, color) {

            var value = $("#" + color.hex).val().trim();

            if (value === "" && !color.required) {
                return;
            }

            if (!/^#[0-9A-Fa-f]{6}$/.test(value)) {
                $("#" + color.hex).addClass("is-invalid");
                errors++;
            }
        });

        if (errors > 0) {

            var first = $(".settings .form-control.is-invalid").first();
            var pane = first.closest(".tab-pane").attr("id");
            var trigger = document.querySelector('.submenu__link[data-bs-target="#' + pane + '"]');

            if (trigger && !$(trigger).hasClass("active")) {
                bootstrap.Tab.getOrCreateInstance(trigger).show();
            }

            first.focus();
            toastr.error("Please fix the errors before continuing.");
            return;
        }

        set_loading("#do_save", true);

        ApiDataSvc.apiCall('post', 'save_company', collect_company(), function (data) {

            var obj = JSON.parse(data);

            set_loading("#do_save", false);

            if (obj.success) {
                take_snapshot();
                refresh_savebar();
                confirm_saved("savebar");
                toastr.success(obj.message);
            } else {
                toastr.error(obj.message);
            }
        });

    });

    sync_savebar_visibility();
    load_company();

});
</script>
