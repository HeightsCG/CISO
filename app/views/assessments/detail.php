<div class="page page--assessment">

    <header class="headline">
        <div class="headline__text">
            <h1 class="headline__title"><?php echo htmlspecialchars($this->assessment['assessment_name'], ENT_QUOTES, 'UTF-8'); ?></h1>
            <p class="headline__sub">
                <span class="headline__status <?php echo ($this->assessment['assessment_status'] === 'Complete' ? 'is-ok' : ($this->assessment['assessment_status'] === 'In Progress' ? 'is-progress' : 'is-muted')); ?>" id="status_badge"><?php echo htmlspecialchars($this->assessment['assessment_status'], ENT_QUOTES, 'UTF-8'); ?></span>
            </p>
        </div>
        <div class="headline__actions">
            <button type="button" class="btn btn--primary btn--pill" id="do_complete" disabled><i class="fa-regular fa-circle-check"></i> Mark Complete</button>
        </div>
    </header>

    <nav class="subtabs subtabs--page" role="tablist" aria-label="Assessment">
        <button class="subtabs__link active" id="tab_controls_btn" data-bs-toggle="pill" data-bs-target="#tab_controls" type="button" role="tab" aria-controls="tab_controls" aria-selected="true">Controls</button>
        <button class="subtabs__link" id="tab_vault_btn" data-bs-toggle="pill" data-bs-target="#tab_vault" type="button" role="tab" aria-controls="tab_vault" aria-selected="false">Evidence Vault</button>
        <button class="subtabs__link" id="tab_reports_btn" data-bs-toggle="pill" data-bs-target="#tab_reports" type="button" role="tab" aria-controls="tab_reports" aria-selected="false">Reports</button>
        <button class="subtabs__link" id="tab_settings_btn" data-bs-toggle="pill" data-bs-target="#tab_settings" type="button" role="tab" aria-controls="tab_settings" aria-selected="false">Settings</button>
    </nav>

    <div class="tab-content assess__panes">
        <div class="tab-pane show active" id="tab_controls" role="tabpanel" aria-labelledby="tab_controls_btn">
            <div class="assess__progress assess__progress--wide" id="score_row" hidden>
                <span class="assess__progress-count" id="rollup_label"></span>
                <div class="assess__bar" id="rollup_bar"></div>
                <span class="compare__summary" id="compare_summary" hidden></span>
                <span class="assess__progress-outcome" id="score_outcome"></span>
            </div>
            <div class="panel record__panel workbench">
                <div class="panel__head roster__toolbar">
                    <input type="search" id="item_search" class="input roster__search" placeholder="Search by identifier or title..." autocomplete="off" spellcheck="false">
                    <div class="roster__filters">
                        <div class="roster__filter">
                            <select id="family_filter" class="form-select">
                                <option value="">All families</option>
                            </select>
                        </div>
                        <div class="roster__filter">
                            <select id="result_filter" class="form-select">
                                <option value="">All results</option>
                                <option value="Not Assessed">Not Assessed</option>
                                <option value="Implemented">Implemented</option>
                                <option value="Partially Implemented">Partially</option>
                                <option value="Not Implemented">Not Implemented</option>
                                <option value="Not Applicable">Not Applicable</option>
                            </select>
                        </div>
                        <div class="roster__filter">
                            <select id="evidence_filter" class="form-select">
                                <option value="">All evidence</option>
                                <option value="yes">Has evidence</option>
                                <option value="no">No evidence</option>
                            </select>
                        </div>
                    </div>
                    <button type="button" class="btn btn--icon workbench__expand" id="do_fullscreen" aria-label="Full screen" title="Full screen" aria-pressed="false"><i class="fa-regular fa-expand"></i></button>
                </div>
                <div class="workbench__body">
                    <div class="workbench__list">
                        <table class="data controls__table" id="items_table">
                            <thead>
                                <tr>
                                    <th scope="col">Identifier</th>
                                    <th scope="col">Control</th>
                                    <th scope="col">Result</th>
                                    <th scope="col" title="Evidence" aria-label="Evidence"><i class="fa-regular fa-paperclip" aria-hidden="true"></i></th>
                                </tr>
                            </thead>
                            <tbody id="items_body"></tbody>
                        </table>
                        <p class="controls__empty" id="items_empty"><i class="fa-regular fa-spinner-third fa-spin" aria-hidden="true"></i> Loading items&hellip;</p>
                    </div>

                    <section class="workbench__detail" id="detail" aria-label="Control detail">
                        <p class="workbench__empty" id="detail_empty">Select a control to assess it.</p>

                        <div class="workbench__form" id="detail_form" hidden>
                            <div class="workbench__scroll">
                                <div class="assess__control-head workbench__head">
                                    <div class="objectives__head workbench__eyebrow">
                                        <p class="assess__eyebrow"><span class="assess__id" id="item_identifier"></span> <span class="assess__sep" aria-hidden="true">&middot;</span> <span class="assess__family" id="item_family"></span></p>
                                        <button type="button" class="objectives__evidence" id="do_evidence_open" aria-label="Evidence for this control" title="Evidence for this control"><i class="fa-regular fa-paperclip" aria-hidden="true"></i><span class="objectives__evidence-count" id="evidence_count"></span></button>
                                    </div>
                                    <h3 class="assess__title" id="item_title"></h3>
                                    <div class="workbench__links">
                                        <div class="dropdown workbench__guidance" id="item_guidance_wrap">
                                            <button type="button" class="workbench__guidance-toggle" id="item_guidance_toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false"><i class="fa-regular fa-circle-info"></i> Assessment guidance</button>
                                            <div class="dropdown-menu workbench__guidance-menu">
                                                <p class="assess__text workbench__requirement" id="item_description"></p>
                                                <ul class="workbench__guidance-list" id="item_guidance"></ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="workbench__section">
                                    <div class="row mb-4" id="applicability_row">
                                        <div class="col-md-4 form-group">
                                            <label for="item_applicability">Applicability</label>
                                            <select class="form-control" id="item_applicability">
                                                <option value="Applicable">Applicable</option>
                                                <option value="Excluded">Excluded</option>
                                            </select>
                                        </div>
                                        <div class="col-md-8 form-group" id="justification_group">
                                            <label for="item_justification">Justification for exclusion <abbr title="required">*</abbr></label>
                                            <textarea class="form-control" id="item_justification" rows="2"></textarea>
                                        </div>
                                    </div>

                                    <div class="form-group mb-4" id="objectives_group">
                                        <ul class="objectives" id="item_objectives"></ul>
                                    </div>

                                    <div class="form-group mb-4" id="result_group">
                                        <label id="item_result_label">Result</label>
                                        <div class="segmented segmented--wrap" id="item_result" role="group" aria-labelledby="item_result_label">
                                            <button type="button" class="segmented__btn" data-value="Not Assessed" aria-pressed="false">Not Assessed</button>
                                            <button type="button" class="segmented__btn" data-value="Implemented" aria-pressed="false">Implemented</button>
                                            <button type="button" class="segmented__btn" data-value="Partially Implemented" aria-pressed="false">Partially</button>
                                            <button type="button" class="segmented__btn" data-value="Not Implemented" aria-pressed="false">Not Implemented</button>
                                            <button type="button" class="segmented__btn" data-value="Not Applicable" aria-pressed="false">Not Applicable</button>
                                        </div>
                                    </div>

                                    <div class="form-check mb-4" id="partial_row">
                                        <input class="form-check-input" type="checkbox" id="item_partial_credit">
                                        <label class="form-check-label" for="item_partial_credit" id="partial_label">Partially effective</label>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>

        <div class="tab-pane assess__vault" id="tab_vault" role="tabpanel" aria-labelledby="tab_vault_btn">
            <?php require Main::app_path().'/app/views/partials/vault.php'; ?>
        </div>

        <div class="tab-pane" id="tab_reports" role="tabpanel" aria-labelledby="tab_reports_btn">
            <div class="reports">
                <section class="panel reports__card reports__bar">
                    <div class="reports__line">
                        <div class="reports__field">
                            <span class="reports__label" id="report_detail_label">Detail</span>
                            <div class="segmented segmented--switch reports__detail" id="report_detail" role="group" aria-labelledby="report_detail_label">
                                <button type="button" class="segmented__btn" data-value="executive">Executive</button>
                                <button type="button" class="segmented__btn is-on" data-value="standard">Standard</button>
                                <button type="button" class="segmented__btn" data-value="comprehensive">Comprehensive</button>
                            </div>
                        </div>
                        <div class="reports__field">
                            <span class="reports__label" id="report_formats_label">Formats</span>
                            <div class="segmented segmented--switch reports__formats-pick" id="report_formats" role="group" aria-labelledby="report_formats_label">
                                <button type="button" class="segmented__btn is-on" data-value="pdf" aria-pressed="true">PDF</button>
                                <button type="button" class="segmented__btn is-on" data-value="docx" aria-pressed="true">Word</button>
                                <button type="button" class="segmented__btn is-on" data-value="xlsx" aria-pressed="true">Excel</button>
                            </div>
                        </div>
                        <div class="reports__field">
                            <label class="reports__label" for="report_appendices">Appendices</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="report_appendices" checked>
                            </div>
                        </div>
                    </div>
                    <div class="reports__line">
                        <div class="reports__field">
                            <label class="reports__label" for="report_status">Status</label>
                            <div class="reports__status">
                                <select class="form-control" id="report_status" data-style="form-control" data-container="body" disabled>
                                    <option value="Draft">Draft</option>
                                    <option value="In Review">In Review</option>
                                    <option value="Final">Final</option>
                                </select>
                            </div>
                            <span class="reports__version" id="report_version"></span>
                            <span class="reports__warn" id="report_changed" hidden><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> Changed since final</span>
                        </div>
                        <button type="button" class="btn btn--primary" id="do_package"><i class="fa-regular fa-file-zipper"></i> Download Package</button>
                    </div>
                    <div class="reports__job" id="package_job" hidden>
                        <div class="progress"><span class="progress__bar" id="package_bar"></span></div>
                        <span class="reports__job-label" id="package_label"></span>
                    </div>
                </section>

                <section class="panel reports__card reports__readiness" id="report_readiness">
                    <button type="button" class="reports__readiness-head collapsed" id="report_readiness_toggle" data-bs-toggle="collapse" data-bs-target="#report_readiness_body" aria-expanded="false" aria-controls="report_readiness_body">
                        <span class="reports__readiness-icon" id="report_readiness_ok" hidden><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span>
                        <span class="reports__readiness-icon" id="report_readiness_warn"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></span>
                        <span class="reports__readiness-title" id="report_readiness_title">Report readiness</span>
                        <span class="reports__chevron" id="report_readiness_chevron"><i class="fa-regular fa-chevron-down" aria-hidden="true"></i></span>
                    </button>
                    <div class="collapse" id="report_readiness_body">
                        <ul class="reports__issues" id="report_readiness_list"></ul>
                    </div>
                </section>

                <section class="panel reports__card reports__ai" id="report_ai">
                    <div class="reports__line">
                        <div class="reports__field reports__field--grow">
                            <span class="reports__label">AI narratives</span>
                            <span class="reports__counts" id="narrative_counts"><i class="fa-regular fa-spinner-third fa-spin" aria-hidden="true"></i></span>
                        </div>
                        <div class="reports__actions">
                            <button type="button" class="btn btn--secondary" id="do_narratives_generate" hidden><i class="fa-regular fa-wand-magic-sparkles"></i> Generate</button>
                            <button type="button" class="btn btn--secondary" id="do_narratives_review"><i class="fa-regular fa-list-check"></i> Review</button>
                        </div>
                    </div>
                    <div class="reports__job" id="narratives_job" hidden>
                        <div class="progress"><span class="progress__bar" id="narratives_bar"></span></div>
                        <span class="reports__job-label" id="narratives_label"></span>
                        <button type="button" class="btn btn--icon" id="do_narratives_cancel" aria-label="Stop generating" title="Stop generating"><i class="fa-regular fa-stop"></i></button>
                    </div>
                    <ul class="reports__issues" id="narratives_stale_list" hidden></ul>
                    <div class="reports__failed" id="narratives_failed" hidden>
                        <ul class="reports__issues" id="narratives_failed_list"></ul>
                        <button type="button" class="btn btn--secondary btn--sm" id="do_narratives_retry"><i class="fa-regular fa-rotate-right"></i> Retry</button>
                    </div>
                </section>

                <div class="panel record__panel assess__reports">
                    <table class="data data--light reports__table">
                        <thead>
                            <tr>
                                <th scope="col">Report</th>
                                <th scope="col" class="reports__col-download"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (ReadyReports::catalog_for('assessment') as $report) { ?>
                            <tr>
                                <td>
                                    <span class="roster__name"><?php echo htmlspecialchars($report['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </td>
                                <td class="reports__col-download">
                                    <span class="reports__formats">
                                        <?php if (in_array('pdf', $report['formats'], true)) { ?>
                                        <button type="button" class="btn btn--tertiary btn--sm" data-action="preview_report" data-report="<?php echo htmlspecialchars($report['key'], ENT_QUOTES, 'UTF-8'); ?>" data-name="<?php echo htmlspecialchars($report['name'], ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-regular fa-eye"></i> Preview</button>
                                        <?php } ?>
                                        <?php foreach ($report['formats'] as $format) { ?>
                                        <button type="button" class="btn btn--secondary btn--sm" data-action="download_report" data-report="<?php echo htmlspecialchars($report['key'], ENT_QUOTES, 'UTF-8'); ?>" data-format="<?php echo htmlspecialchars($format, ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-regular <?php echo $format === 'pdf' ? 'fa-file-pdf' : ($format === 'docx' ? 'fa-file-word' : 'fa-file-excel'); ?>"></i> <?php echo $format === 'pdf' ? 'PDF' : ($format === 'docx' ? 'Word' : 'Excel'); ?></button>
                                        <?php } ?>
                                    </span>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="tab-pane" id="tab_settings" role="tabpanel" aria-labelledby="tab_settings_btn">
            <div class="assess__form">
                <div class="settings settings--single prefs" id="details_form">
                    <section class="card prefs__group">
                        <header class="card__head">
                            <h2 class="card__title">Assessment Details</h2>
                        </header>
                        <div class="row prefs__row edit-field">
                            <label class="col-md-4 prefs__label" for="edit_name">Name</label>
                            <div class="col-md-8 prefs__control">
                                <input type="text" class="form-control prefs__input" id="edit_name" value="<?php echo htmlspecialchars($this->assessment['assessment_name'], ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                        </div>
                        <div class="row prefs__row edit-field">
                            <span class="col-md-4 prefs__label">Schedule</span>
                            <div class="col-md-8 prefs__control">
                                <div class="segmented segmented--switch prefs__input" id="edit_schedule" role="group" aria-label="Schedule">
                                    <button type="button" class="segmented__btn<?php echo ((string) ($this->assessment['end_date'] ?? '') === '' ? '' : ' is-on'); ?>" data-value="Fixed dates">Fixed dates</button>
                                    <button type="button" class="segmented__btn<?php echo ((string) ($this->assessment['end_date'] ?? '') === '' ? ' is-on' : ''); ?>" data-value="Ongoing">Ongoing</button>
                                </div>
                            </div>
                        </div>
                        <div class="row prefs__row edit-field edit-dates">
                            <label class="col-md-4 prefs__label" for="edit_start">Dates</label>
                            <div class="col-md-8 prefs__control">
                                <div class="date-range prefs__input">
                                    <input type="date" class="form-control" id="edit_start" placeholder="Start date" aria-label="Start date" value="<?php echo htmlspecialchars((string) ($this->assessment['start_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                    <i class="fa-regular fa-arrow-right date-range__sep" aria-hidden="true"></i>
                                    <input type="date" class="form-control" id="edit_end" placeholder="End date" aria-label="End date" value="<?php echo htmlspecialchars((string) ($this->assessment['end_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                            </div>
                        </div>
                        <div class="row prefs__row">
                            <label class="col-md-4 prefs__label" for="details_compare">Compare Against</label>
                            <div class="col-md-8 prefs__control">
                                <div class="prefs__input">
                                    <select class="form-control" id="details_compare"></select>
                                </div>
                            </div>
                        </div>
                        <?php if ($this->features['ssi_field'] !== null) { ?>
                        <div class="row prefs__row">
                            <label class="col-md-4 prefs__label" for="details_ssi"><?php echo htmlspecialchars($this->features['ssi_field']['label'], ENT_QUOTES, 'UTF-8'); ?></label>
                            <div class="col-md-8 prefs__control">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="details_ssi"<?php echo (int) $this->assessment['ssi_reviewed'] === 1 ? ' checked' : ''; ?>>
                                </div>
                            </div>
                        </div>
                        <div class="row prefs__row" id="ssi_note_row">
                            <label class="col-md-4 prefs__label" for="details_ssi_note">SSI Review Note</label>
                            <div class="col-md-8 prefs__control">
                                <textarea class="form-control form-control--notes prefs__input" id="details_ssi_note" placeholder="Who reviewed the SSI requirements, when, and how"><?php echo htmlspecialchars((string) $this->assessment['ssi_note'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </div>
                        </div>
                        <?php } ?>
                    </section>
                    <div class="prefs__actions">
                        <button type="button" class="btn btn--destructive" id="do_assessment_delete_open"><i class="fa-regular fa-trash"></i> Delete Assessment</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="item_evidence_modal" tabindex="-1" aria-labelledby="item_evidence_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="item_evidence_modal_title">Evidence</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <ul class="evidence__list" id="item_evidence"></ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" id="do_attach_open"><i class="fa-regular fa-link"></i> Attach from Vault</button>
                <button type="button" class="btn btn--primary" id="do_item_upload_open"><i class="fa-regular fa-arrow-up-from-bracket"></i> Upload New</button>
                <input type="file" id="item_upload_file" multiple hidden>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" id="checks_modal" tabindex="-1" aria-labelledby="checks_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="checks_modal_title">Checks</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h3 class="record__group-title" id="checks_block_title">Fix before completing</h3>
                <ul class="checks" id="checks_block"></ul>
                <h3 class="record__group-title" id="checks_warn_title">Worth a look</h3>
                <ul class="checks" id="checks_warn"></ul>
                <p class="checks__clear" id="checks_clear">Everything checks out.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn--primary" id="do_checks_complete">Mark Complete</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" id="attach_modal" tabindex="-1" aria-labelledby="attach_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="attach_modal_title">Attach Evidence</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="pick__label">Files in <span id="pick_client"></span>&rsquo;s evidence vault <span class="pick__count" id="pick_count"></span></p>
                <div class="vault vault--pick">
                    <aside class="vault__tree">
                        <div class="vault__tree-head">
                            <span class="vault__tree-title">Folders</span>
                        </div>
                        <ul class="vault__list" id="pick_tree"></ul>
                    </aside>

                    <section class="vault__files">
                        <div class="vault__toolbar">
                            <input type="search" id="vault_search" class="input vault__search" placeholder="Search title or file name..." autocomplete="off" spellcheck="false">
                        </div>
                        <div class="pick__scroll">
                            <table class="data data--light" id="vault_table">
                                <thead>
                                    <tr>
                                        <th scope="col" class="pick__sort is-sortable" data-sort="evidence_title">Name</th>
                                        <th scope="col" class="vault__col-size pick__sort is-sortable" data-sort="file_size">Size</th>
                                        <th scope="col" class="vault__col-date pick__sort is-sortable" data-sort="date_created">Uploaded</th>
                                        <th scope="col" class="vault__col-links pick__sort is-sortable" data-sort="link_count">Controls</th>
                                        <th scope="col" class="pick__col-attach"><span class="visually-hidden">Attach</span></th>
                                    </tr>
                                </thead>
                                <tbody id="vault_list"></tbody>
                            </table>
                            <p class="vault__empty" id="vault_empty" hidden></p>
                        </div>
                    </section>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Done</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" id="assessment_delete_modal" tabindex="-1" aria-labelledby="assessment_delete_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="assessment_delete_modal_title">Delete Assessment</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Delete <strong><?php echo htmlspecialchars($this->assessment['assessment_name'], ENT_QUOTES, 'UTF-8'); ?></strong> and all <?php echo (int) $this->assessment['item_count']; ?> of its items, including results and notes? Evidence itself stays in the client&rsquo;s vault. This cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="do_assessment_delete" class="btn btn--destructive">Delete</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" id="narrative_modal" tabindex="-1" aria-labelledby="narrative_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="narrative_modal_title">AI Narratives</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="review">
                    <ul class="review__list" id="narrative_list"></ul>
                    <section class="review__editor" id="narrative_editor" aria-live="polite">
                        <p class="review__empty"><i class="fa-regular fa-spinner-third fa-spin" aria-hidden="true"></i> Loading narratives&hellip;</p>
                    </section>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary me-auto" id="do_narratives_approve_all_open"><i class="fa-regular fa-check-double"></i> Approve All Current Drafts</button>
                <button type="button" class="btn btn--secondary" id="do_narrative_reject"><i class="fa-regular fa-xmark"></i> Reject</button>
                <button type="button" class="btn btn--secondary" id="do_narrative_save"><i class="fa-regular fa-floppy-disk"></i> Save Edits</button>
                <button type="button" class="btn btn--primary" id="do_narrative_approve"><i class="fa-regular fa-check"></i> Approve</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" id="narratives_approve_all_modal" tabindex="-1" aria-labelledby="narratives_approve_all_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="narratives_approve_all_modal_title">Approve All Drafts</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="narratives_approve_all_text"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn--primary" id="do_narratives_approve_all">Approve All</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="report_preview_modal" tabindex="-1" aria-labelledby="report_preview_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="report_preview_modal_title">Preview</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="preview">
                    <p class="preview__loading" id="report_preview_loading"><i class="fa-regular fa-spinner-third fa-spin" aria-hidden="true"></i> Preparing preview&hellip;</p>
                    <iframe class="preview__frame" id="report_preview_frame" title="Report preview" hidden></iframe>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="report_blockers_modal" tabindex="-1" aria-labelledby="report_blockers_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="report_blockers_modal_title">Not Ready for Final</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <ul class="checks" id="report_blockers"></ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn--primary" id="do_blockers_review">Review Narratives</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {

    var assessment_id = <?php echo (int) $this->assessment['id']; ?>;
    var assessment_name = <?php echo json_encode(html_entity_decode((string) $this->assessment['assessment_name'], ENT_QUOTES, 'UTF-8'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    var schedule = <?php echo json_encode(array(
        'start' => (string) ($this->assessment['start_date'] ?? ''),
        'end' => (string) ($this->assessment['end_date'] ?? '')
    ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    function schedule_value() {
        return $('#edit_schedule .segmented__btn.is-on').attr('data-value') || 'Fixed dates';
    }

    function edit_dates() {
        $('.edit-dates').prop('hidden', schedule_value() === 'Ongoing');
    }

    edit_dates();

    function save_assessment() {

        var ongoing = (schedule_value() === 'Ongoing');
        var values = {
            assessment_id: assessment_id,
            assessment_name: $('#edit_name').val().trim(),
            schedule: schedule_value(),
            start_date: (ongoing ? '' : $('#edit_start').val()),
            end_date: (ongoing ? '' : $('#edit_end').val())
        };

        $('.edit-field .form-control').removeClass('is-invalid');

        if (values.assessment_name === '') {
            $('#edit_name').addClass('is-invalid');
            toastr.error('Enter an assessment name.');
            return;
        }

        if (!ongoing && (values.start_date === '' || values.end_date === '')) {
            return;
        }

        if (!ongoing && values.end_date < values.start_date) {
            $('#edit_end').addClass('is-invalid');
            toastr.error('The end date cannot fall before the start date.');
            return;
        }

        ApiDataSvc.apiCall('post', 'save_assessment', values, function (data) {

            var obj = JSON.parse(data);

            if (!obj.success) {
                toastr.error(obj.message);
                return;
            }

            assessment_name = values.assessment_name;
            schedule.start = values.start_date;
            schedule.end = values.end_date;
            $('.headline__title').text(values.assessment_name);
            $('.crumbs__item:last-child span').text(values.assessment_name);
        });
    }

    $('#edit_schedule').on('click', '.segmented__btn', function () {

        if ($(this).hasClass('is-on')) {
            return;
        }

        $('#edit_schedule .segmented__btn').removeClass('is-on');
        $(this).addClass('is-on');
        edit_dates();
        save_assessment();
    });

    $('#edit_name, #edit_start, #edit_end').on('change', save_assessment);

    var client_id = <?php echo (int) $this->assessment['client_id']; ?>;
    var standard_name = <?php echo json_encode(html_entity_decode((string) $this->assessment['standard_name'], ENT_QUOTES, 'UTF-8'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var client_name = <?php echo json_encode(html_entity_decode((string) $this->assessment['client_name'], ENT_QUOTES, 'UTF-8'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var terms = <?php echo json_encode($this->terminology, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var review = null;
    var partial_labels = <?php echo json_encode((object) $this->features['partial_labels'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var details = <?php echo json_encode(array(
        'compare_assessment_id' => (int) $this->assessment['compare_assessment_id']
    ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var items = [];
    var controls = [];
    var objectives_of = {};
    var current_item = null;
    var evidence_target = null;

    var RESULTS = ['Not Assessed', 'Implemented', 'Partially Implemented', 'Not Implemented', 'Not Applicable'];
    var OBJECTIVE_CHOICES = [
        {
            value: 'Implemented',
            label: 'Met',
            icon: 'fa-check'
        },
        {
            value: 'Partially Implemented',
            label: 'Partially Met',
            icon: 'fa-circle-half-stroke'
        },
        {
            value: 'Not Implemented',
            label: 'Not Met',
            icon: 'fa-xmark'
        },
        {
            value: 'Not Applicable',
            label: 'Not Applicable',
            icon: 'fa-ban'
        }
    ];
    var RESULT_CLASS = {
        'Not Assessed': 'badge--inactive',
        'Implemented': 'badge--active',
        'Partially Implemented': 'badge--onboarding',
        'Not Implemented': 'badge--critical',
        'Not Applicable': 'badge--prospect'
    };

    function term(key, fallback) {
        return terms && terms[key] ? terms[key] : fallback;
    }

    function flags_of(item) {
        if (!item.control_flags) {
            return {};
        }

        try {
            return JSON.parse(item.control_flags);
        } catch (e) {
            return {};
        }
    }

    function set_result(item_result) {
        $('#item_result .segmented__btn').removeClass('is-on').attr('aria-pressed', 'false');
        $('#item_result .segmented__btn[data-value="' + item_result + '"]').addClass('is-on').attr('aria-pressed', 'true');
    }

    function current_result() {
        return $('#item_result .segmented__btn.is-on').attr('data-value') || 'Not Assessed';
    }

    $('#item_result').on('click', '.segmented__btn', function () {
        set_result($(this).attr('data-value'));
    });

    function result_from_objectives(results) {

        var unanswered = results.filter(function (result) {
            return result === 'Not Assessed';
        }).length;

        if (results.length === 0 || unanswered === results.length) {
            return 'Not Assessed';
        }

        if (unanswered > 0) {
            return 'Partially Implemented';
        }

        var applicable = results.filter(function (result) {
            return result !== 'Not Applicable';
        });

        if (applicable.length === 0) {
            return 'Not Applicable';
        }

        var met = applicable.filter(function (result) {
            return result === 'Implemented';
        }).length;
        var not_met = applicable.filter(function (result) {
            return result === 'Not Implemented';
        }).length;

        if (met === applicable.length) {
            return 'Implemented';
        }

        return not_met === applicable.length ? 'Not Implemented' : 'Partially Implemented';
    }

    function result_label(value) {
        return value === 'Partially Implemented' ? 'Partially' : value;
    }

    var compare = {
        assessment_name: '',
        results: {}
    };
    var RANK = {
        'Not Implemented': 0,
        'Partially Implemented': 1,
        'Implemented': 2
    };

    function change_of(item) {

        var before = compare.results[item.control_identifier];

        if (compare.assessment_name === '' || before === undefined || before === item.item_result) {
            return 'same';
        }

        if (RANK[before] !== undefined && RANK[item.item_result] !== undefined) {
            return RANK[item.item_result] > RANK[before] ? 'improved' : 'worse';
        }

        return 'changed';
    }

    function compare_note(item, change) {

        if (change === 'same') {
            return '';
        }

        var icon = change === 'improved' ? 'fa-arrow-up' : (change === 'worse' ? 'fa-arrow-down' : 'fa-arrows-left-right');

        return '<span class="compare__was is-' + change + '" title="' + esc(compare.assessment_name) + '"><i class="fa-solid ' + icon + '" aria-hidden="true"></i> was ' + esc(result_label(compare.results[item.control_identifier])) + '</span>';
    }

    function evidence_total(control) {
        return (objectives_of[control.control_identifier] || []).reduce(function (sum, objective) {
            return sum + parseInt(objective.evidence_count, 10);
        }, parseInt(control.evidence_count, 10));
    }

    function result_badge(value) {
        return '<span class="badge ' + (RESULT_CLASS[value] || 'badge--inactive') + '">' + esc(result_label(value)) + '</span>';
    }

    function size_label(bytes) {
        var n = parseInt(bytes, 10) || 0;
        if (n < 1024) { return n + ' B'; }
        if (n < 1048576) { return Math.round(n / 1024) + ' KB'; }
        return (n / 1048576).toFixed(1) + ' MB';
    }

    function counts_toward_scope(control) {
        return control.applicability !== 'Excluded';
    }

    function rollup() {

        var counts = {};
        var evidenced = 0;
        var total = 0;

        for (var i = 0; i < RESULTS.length; i++) {
            counts[RESULTS[i]] = 0;
        }

        for (var j = 0; j < controls.length; j++) {
            if (!counts_toward_scope(controls[j])) {
                continue;
            }

            total++;
            counts[controls[j].item_result] = (counts[controls[j].item_result] || 0) + 1;

            if (evidence_total(controls[j]) > 0) {
                evidenced++;
            }
        }

        var assessed = total - counts['Not Assessed'];

        for (var k = 0; k < RESULTS.length; k++) {
            $('#result_filter option[value="' + RESULTS[k] + '"]').text(result_label(RESULTS[k]) + ' (' + counts[RESULTS[k]] + ')');
        }

        $('#evidence_filter option[value="yes"]').text('Has evidence (' + evidenced + ')');
        $('#evidence_filter option[value="no"]').text('No evidence (' + (total - evidenced) + ')');
        var segments = '';
        var BAR = [
            ['Implemented', 'is-ok'],
            ['Partially Implemented', 'is-partial'],
            ['Not Implemented', 'is-gap'],
            ['Not Applicable', 'is-na']
        ];

        for (var b = 0; b < BAR.length; b++) {
            if (counts[BAR[b][0]] > 0) {
                segments += '<span class="assess__bar-part ' + BAR[b][1] + '" style="width:' + (counts[BAR[b][0]] / total * 100) + '%" title="' + counts[BAR[b][0]] + ' ' + result_label(BAR[b][0]) + '"></span>';
            }
        }

        $('#rollup_bar').html(segments);
        compare_rollup();
        $('#rollup_label').text((total > 0 ? Math.round(assessed / total * 100) : 0) + '%');

        sync_complete(incomplete_controls());
    }

    function compare_rollup() {

        var changes = {
            improved: 0,
            worse: 0,
            changed: 0
        };

        for (var i = 0; i < controls.length; i++) {
            var change = change_of(controls[i]);

            if (change !== 'same') {
                changes.changed++;
            }

            if (change === 'improved' || change === 'worse') {
                changes[change]++;
            }
        }

        $('#result_filter optgroup').remove();

        if (compare.assessment_name === '') {
            $('#compare_summary').attr('hidden', true);

            if ($('#result_filter').val() === null) {
                $('#result_filter').val('');
            }

            return;
        }

        $('#result_filter').append('<optgroup label="Since ' + esc(compare.assessment_name) + '">'
            + '<option value="change:improved">Improved (' + changes.improved + ')</option>'
            + '<option value="change:worse">Got worse (' + changes.worse + ')</option>'
            + '<option value="change:changed">Any change (' + changes.changed + ')</option>'
            + '</optgroup>');

        $('#compare_summary').html('<span class="is-improved">' + changes.improved + ' improved</span>, <span class="is-worse">' + changes.worse + ' worse</span> since ' + esc(compare.assessment_name)).removeAttr('hidden');
    }

    function incomplete_controls() {

        var remaining = 0;

        for (var i = 0; i < controls.length; i++) {
            if (!counts_toward_scope(controls[i])) {
                continue;
            }

            var open = (objectives_of[controls[i].control_identifier] || []).filter(function (objective) {
                return objective.item_result === 'Not Assessed';
            }).length;

            if (controls[i].item_result === 'Not Assessed' || open > 0) {
                remaining++;
            }
        }

        return remaining;
    }

    function sync_complete(remaining) {

        if ($('#status_badge').text().trim() === 'Complete') {
            $('#do_complete').prop('disabled', true).attr('title', '').html('<i class="fa-regular fa-circle-check"></i> Completed');
            return;
        }

        $('#do_complete').prop('disabled', remaining > 0).attr('title', remaining > 0
            ? remaining + ' ' + (remaining === 1 ? term('control', 'control') : term('controls', 'controls')) + ' still unanswered'
            : '');
    }

    function set_status(assessment_status) {
        $('#status_badge').text(assessment_status)
            .toggleClass('is-ok', assessment_status === 'Complete')
            .toggleClass('is-progress', assessment_status === 'In Progress')
            .toggleClass('is-muted', assessment_status !== 'Complete' && assessment_status !== 'In Progress');
    }

    function outcome_text(outcome) {

        if (!outcome) {
            return '';
        }

        if (outcome.status === 'pass' || outcome.status === 'conditional') {
            return 'Supports ' + outcome.label;
        }

        if (outcome.status === 'provisional_pass' || outcome.status === 'provisional_conditional') {
            return 'On track for ' + outcome.label;
        }

        return outcome.label;
    }

    function render_review() {

        if (!review) {
            return;
        }

        var score = review.score;

        if (score) {
            $('#score_outcome').text(outcome_text(score.outcome))
                .attr('title', score.outcome && score.outcome.reasons.length ? score.outcome.reasons.join('\n') : '')
                .toggleClass('is-ok', !!score.outcome && score.outcome.status !== 'not_eligible');
        }

        $('#score_row').prop('hidden', false);
        rollup();
    }

    function refresh_checks(done) {
        ApiDataSvc.apiCall('post', 'assessment_checks', { assessment_id: assessment_id }, function (data) {

            var obj = JSON.parse(data);

            if (obj.success) {
                review = obj;
                render_review();
            }

            if (typeof done === 'function') {
                done();
            }
        });
    }

    function issue_item(issue) {

        var links = '';

        for (var i = 0; i < issue.controls.length && i < 12; i++) {
            links += '<button type="button" class="btn btn--tertiary btn--sm checks__control" data-identifier="' + esc(issue.controls[i]) + '">' + esc(issue.controls[i]) + '</button>';
        }

        if (issue.controls.length > 12) {
            links += '<span class="checks__more">and ' + (issue.controls.length - 12) + ' more</span>';
        }

        return '<li class="checks__item checks__item--' + issue.severity + '"><p class="checks__message">' + esc(issue.message) + '</p>'
            + (links === '' ? '' : '<div class="checks__controls">' + links + '</div>') + '</li>';
    }

    function show_checks(issues) {

        var block = '';
        var warn = '';

        for (var i = 0; i < issues.length; i++) {
            if (issues[i].severity === 'block') {
                block += issue_item(issues[i]);
            } else {
                warn += issue_item(issues[i]);
            }
        }

        $('#checks_block').html(block);
        $('#checks_warn').html(warn);
        $('#checks_block, #checks_block_title').toggle(block !== '');
        $('#checks_warn, #checks_warn_title').toggle(warn !== '');
        $('#checks_clear').toggle(block === '' && warn === '');
        $('#do_checks_complete').prop('disabled', block !== '' || $('#status_badge').text().trim() === 'Complete');
        modal('checks_modal').show();
    }

    $('#checks_modal').on('click', '.checks__control', function () {

        var identifier = $(this).attr('data-identifier');

        for (var i = 0; i < controls.length; i++) {
            if (controls[i].control_identifier === identifier) {
                modal('checks_modal').hide();
                $('#item_search').val('');
                $('#family_filter, #result_filter, #evidence_filter').val('');
                filter();
                open_item(controls[i].id);

                var row = $('#items_body .item__row[data-id="' + controls[i].id + '"]')[0];

                if (row) {
                    row.scrollIntoView({ block: 'center' });
                }

                return;
            }
        }
    });

    function complete(button) {

        set_loading(button, true);

        ApiDataSvc.apiCall('post', 'complete_assessment', { assessment_id: assessment_id }, function (data) {

            var obj = JSON.parse(data);

            set_loading(button, false);

            if (obj.success) {
                modal('checks_modal').hide();
                toastr.success(obj.message);
                set_status(obj.assessment_status);
                load(true);
            } else if (obj.issues) {
                show_checks(obj.issues);
            } else {
                toastr.error(obj.message);
            }
        });
    }

    $('#do_complete').click(function () {
        complete('#do_complete');
    });

    $('#do_checks_complete').click(function () {
        complete('#do_checks_complete');
    });

    function objective_summary(control) {

        var list = objectives_of[control.control_identifier] || [];
        var answered = 0;

        for (var i = 0; i < list.length; i++) {
            if (list[i].item_result !== 'Not Assessed') {
                answered++;
            }
        }

        if (list.length === 0) {
            return '';
        }

        return '<span class="controls__sub">' + answered + ' of ' + list.length + ' ' + esc(term('objectives', 'objectives')) + ' answered</span>';
    }

    function render() {

        var html = '';
        var current = null;
        var families = [];

        for (var i = 0; i < controls.length; i++) {

            var it = controls[i];
            var family = it.family === '' ? 'Ungrouped' : it.family;

            if (family !== current) {
                current = family;
                families.push(family);
                html += '<tr class="controls__family" data-family="' + esc(family) + '"><td colspan="4">' + esc(family) + '</td></tr>';
            }

            var change = change_of(it);

            html += '<tr class="item__row' + (current_item && parseInt(current_item.id, 10) === parseInt(it.id, 10) ? ' is-selected' : '') + '" data-id="' + it.id + '" data-family="' + esc(family) + '"'
                + ' data-result="' + esc(it.item_result) + '"'
                + ' data-change="' + change + '"'
                + ' data-evidence="' + (evidence_total(it) > 0 ? 'yes' : 'no') + '" tabindex="0">'
                + '<td class="controls__id">' + esc(it.control_identifier) + '</td>'
                + '<td><span class="controls__title">' + esc(it.control_title) + '</span>' + objective_summary(it) + '</td>'
                + '<td>' + result_badge(it.item_result) + compare_note(it, change) + '</td>'
                + '<td class="item__evidence">' + (evidence_total(it) > 0
                    ? '<span class="badge badge--active">' + evidence_total(it) + '</span>'
                    : '') + '</td>'
                + '</tr>';
        }

        $('#items_body').html(html);

        var chosen = $('#family_filter').val();
        var options = '<option value="">All ' + esc(term('families', 'families')) + '</option>';

        for (var f = 0; f < families.length; f++) {
            options += '<option value="' + esc(families[f]) + '">' + esc(families[f]) + '</option>';
        }

        $('#family_filter').html(options).val(chosen);

        rollup();
    }

    function filter() {

        var text_term = $('#item_search').val().trim().toLowerCase();
        var family = $('#family_filter').val();
        var result = $('#result_filter').val();
        var evidence = $('#evidence_filter').val();
        var visible = 0;

        $('#items_body .controls__family').each(function () {

            var matched = 0;

            $(this).nextUntil('.controls__family', '.item__row').each(function () {

                var row = $(this);
                var text = row.find('.controls__id').text().toLowerCase()
                    + ' ' + row.find('.controls__title').text().toLowerCase();

                var hit = (text_term === '' || text.indexOf(text_term) !== -1)
                    && (family === '' || row.attr('data-family') === family)
                    && (result === '' || (result.indexOf('change:') === 0
                        ? (result === 'change:changed' ? row.attr('data-change') !== 'same' : row.attr('data-change') === result.slice(7))
                        : row.attr('data-result') === result))
                    && (evidence === '' || row.attr('data-evidence') === evidence);

                row.toggle(hit);

                if (hit) {
                    matched++;
                }
            });

            $(this).toggle(matched > 0);
            visible += matched;
        });

        $('#items_table').toggle(visible > 0);
        $('#items_empty').toggle(visible === 0);

        if (visible === 0) {
            $('#items_empty').text(controls.length === 0
                ? 'This assessment has no items.'
                : 'No items match the current filters');
        }
    }

    function split(list) {

        controls = [];
        objectives_of = {};

        for (var i = 0; i < list.length; i++) {
            if (list[i].control_kind === 'objective') {
                if (!objectives_of[list[i].parent_identifier]) {
                    objectives_of[list[i].parent_identifier] = [];
                }

                objectives_of[list[i].parent_identifier].push(list[i]);
            } else {
                controls.push(list[i]);
            }
        }
    }

    function load(keep_open) {

        ApiDataSvc.apiCall('post', 'load_compare_results', { assessment_id: assessment_id }, function (compared) {

            compare = JSON.parse(compared);

            ApiDataSvc.apiCall('post', 'load_items', { assessment_id: assessment_id }, function (data) {

                split(JSON.parse(data));

                if (keep_open && current_item) {
                    for (var j = 0; j < controls.length; j++) {
                        if (parseInt(controls[j].id, 10) === parseInt(current_item.id, 10)) {
                            current_item = controls[j];
                            break;
                        }
                    }
                }

                render();
                filter();
                refresh_checks();

                if (!current_item) {
                    var first = $('#items_body .item__row:visible').first();

                    if (first.length > 0) {
                        open_item(first.attr('data-id'));
                    }
                }
            });
        });
    }

    function set_fullscreen(on) {
        $('.workbench').toggleClass('is-fullscreen', on);
        $('body').toggleClass('has-fullscreen', on);
        $('#do_fullscreen').attr({
            'aria-pressed': on ? 'true' : 'false',
            'aria-label': on ? 'Exit full screen' : 'Full screen',
            'title': on ? 'Exit full screen' : 'Full screen'
        }).html('<i class="fa-regular ' + (on ? 'fa-compress' : 'fa-expand') + '"></i>');
    }

    $('#do_fullscreen').click(function () {
        set_fullscreen(!$('.workbench').hasClass('is-fullscreen'));
    });

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && $('.workbench').hasClass('is-fullscreen') && $('.modal.show').length === 0) {
            set_fullscreen(false);
        }
    });

    $('#item_search').on('input', filter);
    $('#family_filter, #result_filter, #evidence_filter').on('change', filter);

    function sentence(text) {

        var trimmed = String(text || '').trim();

        return trimmed.charAt(0).toUpperCase() + trimmed.slice(1);
    }

    function render_objectives(list) {

        var html = '';

        for (var i = 0; i < list.length; i++) {

            var buttons = '';

            for (var c = 0; c < OBJECTIVE_CHOICES.length; c++) {
                var on = list[i].item_result === OBJECTIVE_CHOICES[c].value;

                buttons += '<button type="button" class="segmented__btn' + (on ? ' is-on' : '') + '" data-value="' + OBJECTIVE_CHOICES[c].value + '" aria-pressed="' + (on ? 'true' : 'false') + '">' + (OBJECTIVE_CHOICES[c].icon === '' ? '' : '<i class="fa-regular ' + OBJECTIVE_CHOICES[c].icon + '"></i> ') + OBJECTIVE_CHOICES[c].label + '</button>';
            }

            var short_id = list[i].control_identifier.indexOf(list[i].parent_identifier) === 0
                ? list[i].control_identifier.slice(list[i].parent_identifier.length).replace(/^[-.]/, '')
                : list[i].control_identifier;

            html += '<li class="objectives__item" data-id="' + list[i].id + '" data-result="' + esc(list[i].item_result) + '">'
                + '<div class="objectives__text"><div class="objectives__head"><span class="objectives__id">' + esc(short_id) + '</span>'
                + '<button type="button" class="objectives__evidence" data-id="' + list[i].id + '" aria-label="Evidence for ' + esc(list[i].control_identifier) + '" title="Evidence for ' + esc(list[i].control_identifier) + '"><i class="fa-regular fa-paperclip" aria-hidden="true"></i><span class="objectives__evidence-count">' + (parseInt(list[i].evidence_count, 10) || 0) + '</span></button></div>'
                + (list[i].description && list[i].description !== list[i].control_title ? '<span class="objectives__title">' + esc(list[i].control_title) + '</span>' : '')
                + '<span class="objectives__statement">' + esc(sentence(list[i].description || list[i].control_title)) + '</span></div>'
                + '<div class="segmented segmented--sm objectives__choice" role="group" aria-label="Result for ' + esc(list[i].control_identifier) + '">' + buttons + '</div>'
                + '</li>';
        }

        $('#item_objectives').html(html);
    }

    function objective_values() {
        return $('#item_objectives .objectives__item').map(function () {
            return {
                id: $(this).attr('data-id'),
                result: $(this).find('.segmented__btn.is-on').attr('data-value') || 'Not Assessed'
            };
        }).get();
    }

    function sync_locked_result() {

        var list = objective_values();
        var excluded = $('#applicability_row').css('display') !== 'none' && $('#item_applicability').val() === 'Excluded';

        if (excluded) {
            set_result('Not Applicable');
        } else if (list.length > 0) {
            set_result(result_from_objectives(list.map(function (o) {
                return o.result;
            })));
        }

        $('#result_group').toggle(!excluded && list.length === 0);
    }

    $('#item_objectives').on('click', '.segmented__btn', function () {

        var group = $(this).closest('.segmented');
        var was_on = $(this).hasClass('is-on');

        group.find('.segmented__btn').removeClass('is-on').attr('aria-pressed', 'false');

        if (!was_on) {
            $(this).addClass('is-on').attr('aria-pressed', 'true');
        }

        $(this).closest('.objectives__item').attr('data-result', was_on ? 'Not Assessed' : $(this).attr('data-value'));

        sync_locked_result();

        if (!was_on) {
            scroll_to_next_objective($(this).closest('.objectives__item'));
        }
    });

    function scroll_to_next_objective(item) {

        var next = item.nextAll('.objectives__item').first();
        var pane = $('#detail .workbench__scroll');

        if (next.length === 0 || pane.length === 0) {
            return;
        }

        pane.stop(true).animate({
            scrollTop: pane.scrollTop() + next.offset().top - pane.offset().top - 16
        }, 250);
    }

    $('#item_applicability').on('change', function () {
        $('#justification_group').toggle($(this).val() === 'Excluded');
        sync_locked_result();
    });

    function open_item(id) {

        current_item = null;

        for (var i = 0; i < controls.length; i++) {
            if (parseInt(controls[i].id, 10) === parseInt(id, 10)) {
                current_item = controls[i];
                break;
            }
        }

        if (!current_item) {
            return;
        }

        var flags = flags_of(current_item);
        var list = objectives_of[current_item.control_identifier] || [];
        var guidance = (current_item.guidance || '').trim();
        var weight = current_item.control_weight === null ? null : parseInt(current_item.control_weight, 10);
        var partial = current_item.partial_weight === null ? null : parseInt(current_item.partial_weight, 10);

        $('#items_body .item__row').removeClass('is-selected');
        $('#items_body .item__row[data-id="' + current_item.id + '"]').addClass('is-selected');

        $('#item_identifier').text(current_item.control_identifier);
        $('#item_title').text(current_item.control_title);
        $('#item_family').text(current_item.family === '' ? 'Ungrouped' : current_item.family);
        $('#item_description').text(current_item.description === current_item.control_title ? '' : current_item.description).toggle(current_item.description !== '' && current_item.description !== current_item.control_title);
        $('#item_guidance').html(guidance === '' ? '' : guidance.split('\n').filter(function (line) {
            return line.trim() !== '';
        }).map(function (line) {
            var match = line.match(/^([^:]{2,60}):\s*(.+)$/);

            return match
                ? '<li><strong>' + esc(match[1]) + '</strong> ' + esc(match[2]) + '</li>'
                : '<li>' + esc(line) + '</li>';
        }).join(''));
        bootstrap.Dropdown.getOrCreateInstance(document.getElementById('item_guidance_toggle')).hide();
        $('#item_guidance_wrap').toggle(guidance !== '' || (current_item.description !== '' && current_item.description !== current_item.control_title));

        $('#applicability_row').toggle(flags.applicability === true);
        $('#item_applicability').val(current_item.applicability || 'Applicable');
        $('#item_justification').val(current_item.applicability_justification || '');
        $('#justification_group').toggle(current_item.applicability === 'Excluded');

        $('#objectives_group').toggle(list.length > 0);
        render_objectives(list);

        $('#partial_row').toggle(partial !== null);
        $('#partial_label').text(partial === null ? '' : (partial_labels[current_item.control_identifier] || 'Partially effective') + ': subtract ' + partial + ' points instead of ' + weight);
        $('#item_partial_credit').prop('checked', parseInt(current_item.partial_credit, 10) === 1);

        set_result(current_item.item_result);
        sync_locked_result();


        $('#detail_empty').prop('hidden', true);
        $('#detail_form').prop('hidden', false);
        $('#detail .workbench__scroll').scrollTop(0);

        evidence_target = current_item;
        $('#evidence_count').text(parseInt(current_item.evidence_count, 10) || 0);
        load_item_evidence();
    }

    $('#items_body').on('click', '.item__row', function () {
        open_item($(this).attr('data-id'));
    });

    $('#items_body').on('keydown', '.item__row', function (e) {
        if (e.key === 'Enter') {
            open_item($(this).attr('data-id'));
        }
    });

    var saving = false;
    var save_again = false;

    function save_item() {

        if (!current_item) {
            return;
        }

        if ($('#applicability_row').css('display') !== 'none' && $('#item_applicability').val() === 'Excluded' && $('#item_justification').val().trim() === '') {
            return;
        }

        if (saving) {
            save_again = true;
            return;
        }

        saving = true;

        var values = {
            item_id: current_item.id,
            item_result: current_result(),
            partial_credit: $('#item_partial_credit').is(':checked') ? '1' : '0',
            applicability: $('#item_applicability').val(),
            applicability_justification: $('#item_justification').val().trim(),
            objectives: objective_values()
        };

        ApiDataSvc.apiCall('post', 'save_item', values, function (data) {

            var obj = JSON.parse(data);

            saving = false;

            if (obj.success) {
                set_status(obj.assessment_status);
                load(true);
            } else {
                toastr.error(obj.message);
            }

            if (save_again) {
                save_again = false;
                save_item();
            }
        }, function () {
            saving = false;
            toastr.error('That change could not be saved. Try again.');
        });
    }

    $('#item_objectives, #item_result').on('click', '.segmented__btn', save_item);
    $('#item_applicability, #item_justification, #item_partial_credit').on('change', save_item);

    function open_evidence(target) {
        evidence_target = target;
        $('#item_evidence_modal_title').text('Evidence for ' + target.control_identifier);
        $('#item_evidence').html('<li class="evidence__none"><i class="fa-regular fa-spinner-third fa-spin" aria-hidden="true"></i> Loading evidence&hellip;</li>');
        load_item_evidence();
        modal('item_evidence_modal').show();
    }

    $('#do_evidence_open').click(function () {
        open_evidence(current_item);
    });

    $('#item_objectives').on('click', '.objectives__evidence', function () {

        var id = parseInt($(this).attr('data-id'), 10);
        var target = (objectives_of[current_item.control_identifier] || []).filter(function (objective) {
            return parseInt(objective.id, 10) === id;
        })[0];

        if (target) {
            open_evidence(target);
        }
    });

    function load_item_evidence() {

        if (!evidence_target) {
            return;
        }

        var target = evidence_target;
        var is_objective = target.control_kind === 'objective';

        ApiDataSvc.apiCall('post', 'load_item_evidence', { item_id: target.id }, function (data) {

            var list = JSON.parse(data);
            var html = '';

            for (var i = 0; i < list.length; i++) {
                html += '<li class="evidence__item" data-id="' + list[i].id + '">'
                    + '<div><a href="#" class="evidence__open" data-id="' + list[i].id + '">' + esc(list[i].evidence_title) + '</a>'
                    + '<span class="evidence__meta">' + esc(list[i].file_name) + ' · ' + size_label(list[i].file_size)
                    + '</span></div>'
                    + '<button type="button" class="btn btn--tertiary btn--sm" data-action="detach" data-id="' + list[i].id + '" aria-label="Detach ' + esc(list[i].evidence_title) + '"><i class="fa-regular fa-link-slash"></i></button>'
                    + '</li>';
            }

            if (evidence_target !== target) {
                return;
            }

            $('#item_evidence').html(html === '' ? '<li class="evidence__none">No evidence attached to this ' + (is_objective ? 'objective' : 'control') + ' yet.</li>' : html);

            if (is_objective) {
                target.evidence_count = list.length;
                $('#item_objectives .objectives__evidence[data-id="' + target.id + '"] .objectives__evidence-count').text(list.length);
            } else {
                $('#evidence_count').text(list.length);
            }
        });
    }

    $('#item_evidence').on('click', '[data-action="detach"]', function () {

        var evidence_id = $(this).attr('data-id');

        ApiDataSvc.apiCall('post', 'unlink_evidence', { item_id: evidence_target.id, evidence_id: evidence_id }, function (data) {

            var obj = JSON.parse(data);

            if (obj.success) {

                toastr.success(obj.message);
                load_item_evidence();
                load(true);
            } else {
                toastr.error(obj.message);
            }
        });
    });

    $('#item_evidence, #vault_list').on('click', '.evidence__open', function (e) {
        ApiDataSvc.apiCall('post', 'evidence_url', { evidence_id: $(this).attr('data-id') }, function (data) {

            var obj = JSON.parse(data);

            if (obj.success) {

                window.open(obj.url, '_blank', 'noopener');
            } else {
                toastr.error(obj.message);
            }
        });
    });

    $('#do_attach_open').click(function () {

        $('#vault_search').val('');
        $('#pick_client').text(client_name);

        pick.folders = [];
        pick.evidence = [];
        pick.folder = 0;
        pick.sort = 'date_created';
        pick.dir = 'desc';

        $('#vault_list').empty();
        pick_render_tree();
        pick_render();

        ApiDataSvc.apiCall('post', 'load_folders', { client_id: client_id }, function (data) {
            pick.folders = JSON.parse(data);
            pick_render_tree();
        });

        ApiDataSvc.apiCall('post', 'load_evidence', { client_id: client_id }, function (data) {
            pick.evidence = JSON.parse(data);
            pick_render_tree();
            pick_render();
        });

        modal('attach_modal').show();
    });

    function extension_of(name) {
        var at = String(name || '').lastIndexOf('.');
        return at === -1 ? 'FILE' : String(name).slice(at + 1).toUpperCase().slice(0, 4);
    }

    var pick = { folders: [], evidence: [], folder: 0, sort: 'date_created', dir: 'desc' };

    function attached_ids() {
        return $('#item_evidence .evidence__item').map(function () {
            return String($(this).attr('data-id'));
        }).get();
    }

    function pick_children(parent_id) {

        var out = [];

        for (var i = 0; i < pick.folders.length; i++) {
            if (parseInt(pick.folders[i].parent_id, 10) === parseInt(parent_id, 10)) {
                out.push(pick.folders[i]);
            }
        }

        return out;
    }

    function pick_count_of(id) {

        var total = 0;

        for (var i = 0; i < pick.evidence.length; i++) {
            if (parseInt(id, 10) === 0 || parseInt(pick.evidence[i].folder_id, 10) === parseInt(id, 10)) {
                total++;
            }
        }

        return '<span class="vault__count' + (total === 0 ? ' vault__count--empty' : '') + '">' + total + '</span>';
    }

    function pick_branch(parent_id, depth) {

        var html = '';
        var kids = pick_children(parent_id);

        for (var i = 0; i < kids.length; i++) {
            html += '<li class="vault__item' + (pick.folder === parseInt(kids[i].id, 10) ? ' is-on' : '') + '" style="--tree-depth:' + depth + '">'
                + '<button type="button" class="vault__link" data-id="' + kids[i].id + '">'
                + '<i class="fa-regular fa-folder"></i><span class="vault__name">' + esc(kids[i].folder_name) + '</span>'
                + pick_count_of(kids[i].id) + '</button></li>'
                + pick_branch(kids[i].id, depth + 1);
        }

        return html;
    }

    function pick_render_tree() {
        $('#pick_tree').html('<li class="vault__item' + (pick.folder === 0 ? ' is-on' : '') + '" style="--tree-depth:0">'
            + '<button type="button" class="vault__link" data-id="0">'
            + '<i class="fa-regular fa-inbox"></i><span class="vault__name">All Evidence</span>'
            + pick_count_of(0) + '</button></li>'
            + pick_branch(0, 0));
    }

    function pick_key(row) {

        if (pick.sort === 'file_size' || pick.sort === 'link_count') {
            return parseInt(row[pick.sort], 10) || 0;
        }

        if (pick.sort === 'date_created') {
            return String(row.date_created);
        }

        return String(row.evidence_title || row.file_name).toLowerCase();
    }

    function pick_visible() {

        var term = $('#vault_search').val().trim().toLowerCase();
        var out = [];

        for (var i = 0; i < pick.evidence.length; i++) {

            var e = pick.evidence[i];

            if (pick.folder !== 0 && parseInt(e.folder_id, 10) !== pick.folder) {
                continue;
            }

            if (term !== '' && (e.evidence_title + ' ' + e.file_name).toLowerCase().indexOf(term) === -1) {
                continue;
            }

            out.push(e);
        }

        out.sort(function (a, b) {

            var x = pick_key(a);
            var y = pick_key(b);

            return (x < y ? -1 : (x > y ? 1 : 0)) * (pick.dir === 'asc' ? 1 : -1);
        });

        return out;
    }

    function pick_empty_text() {

        var term = $('#vault_search').val().trim();

        if (pick.evidence.length === 0) {
            return client_name + ' has no evidence yet. Close this and choose Upload New \u2014 the file is saved to the client\u2019s vault and attached here in one step.';
        }

        if (term !== '') {
            return 'No files match \u201c' + term + '\u201d.';
        }

        return 'This folder is empty.';
    }

    function pick_row(row, attached) {

        var already = attached.indexOf(String(row.id)) !== -1;
        var used = parseInt(row.link_count, 10);

        return '<tr class="vault__row">'
            + '<td class="vault__cell-name"><span class="vault__line">'
            + '<a href="#" class="vault__title evidence__open" data-id="' + row.id + '" title="' + esc(row.evidence_title) + '">' + esc(row.evidence_title) + '</a>'
            + '<span class="vault__file" title="' + esc(row.file_name) + '">' + esc(row.file_name) + ' &middot; ' + esc(extension_of(row.file_name)) + '</span>'
            + '</span></td>'
            + '<td class="vault__col-size">' + size_label(row.file_size) + '</td>'
            + '<td class="vault__col-date">' + esc(row.date_created_display) + '</td>'
            + '<td class="vault__col-links"><span class="chip' + (used > 0 ? '' : ' chip--empty') + '">' + used + '</span></td>'
            + '<td class="pick__col-attach">' + (already
                ? '<span class="badge badge--active">Attached</span>'
                : '<button type="button" class="btn btn--secondary btn--sm" data-action="attach" data-id="' + row.id + '">Attach</button>')
            + '</td></tr>';
    }

    function mark_sort() {
        $('.pick__sort').removeClass('is-asc is-desc');
        $('.pick__sort[data-sort="' + pick.sort + '"]').addClass(pick.dir === 'asc' ? 'is-asc' : 'is-desc');
    }

    function pick_render() {

        var rows = pick_visible();
        var attached = attached_ids();
        var html = '';

        for (var i = 0; i < rows.length; i++) {
            html += pick_row(rows[i], attached);
        }

        $('#vault_list').html(html);
        $('#vault_table').toggle(rows.length > 0);
        $('#vault_empty').prop('hidden', rows.length > 0).text(pick_empty_text());
        $('#pick_count').text(pick.evidence.length + (pick.evidence.length === 1 ? ' file' : ' files'));
        mark_sort();
    }

    $('#pick_tree').on('click', '.vault__link', function () {
        pick.folder = parseInt($(this).attr('data-id'), 10);
        pick_render_tree();
        pick_render();
    });

    $('#vault_search').on('input', pick_render);

    $(document).on('click', '#attach_modal .is-sortable', function () {

        var column = $(this).attr('data-sort');

        if (pick.sort === column) {
            pick.dir = (pick.dir === 'asc') ? 'desc' : 'asc';
        } else {
            pick.sort = column;
            pick.dir = (column === 'evidence_title') ? 'asc' : 'desc';
        }

        pick_render();
    });

    $('#vault_list').on('click', '[data-action="attach"]', function () {

        var button = this;

        set_loading(button, true);

        ApiDataSvc.apiCall('post', 'link_evidence', { item_id: evidence_target.id, evidence_id: $(this).attr('data-id') }, function (data) {

            var obj = JSON.parse(data);

            set_loading(button, false);

            if (obj.success) {

                modal('attach_modal').hide();
                toastr.success(obj.message);
                load_item_evidence();
                load(true);
            } else {
                toastr.error(obj.message);
            }
        });
    });

    $('#do_item_upload_open').click(function () {
        $('#item_upload_file').val('').trigger('click');
    });

    function file_title(name) {

        var at = String(name).lastIndexOf('.');

        return (at > 0 ? String(name).slice(0, at) : String(name)).replace(/[_-]+/g, ' ').trim();
    }

    var uploaded = 0;

    function upload_next(files, index, done) {

        if (index >= files.length) {
            done();
            return;
        }

        var form_data = new FormData();

        form_data.append('evidence_file', files[index]);
        form_data.append('client_id', client_id);
        form_data.append('evidence_title', file_title(files[index].name));
        form_data.append('description', '');
        form_data.append('csrf_token', $('meta[name="csrf-token"]').attr('content'));

        $.ajax({
            url: '/api/upload_evidence',
            type: 'POST',
            data: form_data,
            processData: false,
            contentType: false,
            success: function (data) {

                var obj = JSON.parse(data);

                if (!obj.success) {
                    toastr.error(files[index].name + ': ' + obj.message);
                    upload_next(files, index + 1, done);
                    return;
                }

                ApiDataSvc.apiCall('post', 'link_evidence', { item_id: evidence_target.id, evidence_id: obj.evidence_id }, function (linked) {

                    var res = JSON.parse(linked);

                    if (res.success) {
                        uploaded++;
                    } else {
                        toastr.error(files[index].name + ': ' + res.message);
                    }

                    upload_next(files, index + 1, done);
                });
            },
            error: function () {
                toastr.error(files[index].name + ' could not be uploaded.');
                upload_next(files, index + 1, done);
            }
        });
    }

    $('#item_upload_file').on('change', function () {

        var files = Array.prototype.slice.call(this.files || []);

        if (files.length === 0) {
            return;
        }

        uploaded = 0;
        set_loading('#do_item_upload_open', true);

        upload_next(files, 0, function () {
            set_loading('#do_item_upload_open', false);

            if (uploaded > 0) {
                toastr.success(uploaded === 1 ? 'Evidence uploaded' : uploaded + ' files uploaded');
            }

            load_item_evidence();
            load(true);
        });
    });

    function sync_details_form() {
        $('#ssi_note_row').toggle($('#details_ssi').is(':checked'));
    }

    $('#details_ssi').on('change', sync_details_form);

    function load_compare_options() {

        ApiDataSvc.apiCall('post', 'load_compare_options', { assessment_id: assessment_id }, function (data) {

            var list = JSON.parse(data);
            var options = '<option value="0">' + (list.length === 0 ? 'No other ' + esc(standard_name) + ' assessments for this client' : 'Don&rsquo;t compare') + '</option>';

            for (var i = 0; i < list.length; i++) {
                options += '<option value="' + list[i].id + '">' + esc(list[i].assessment_name) + ' (' + esc(list[i].created_on) + ')' + (i === 0 ? ', Baseline' : '') + '</option>';
            }

            $('#details_compare').html(options).val(String(details.compare_assessment_id));

            if ($('#details_compare').val() === null) {
                $('#details_compare').val('0');
            }
        });
    }

    sync_details_form();
    load_compare_options();

    function save_details() {

        var values = {
            assessment_id: assessment_id,
            compare_assessment_id: $('#details_compare').val() || '0',
            ssi_reviewed: $('#details_ssi').is(':checked') ? '1' : '0',
            ssi_note: $('#details_ssi_note').length ? $('#details_ssi_note').val().trim() : ''
        };

        ApiDataSvc.apiCall('post', 'save_assessment_details', values, function (data) {

            var obj = JSON.parse(data);

            if (obj.success) {
                details.compare_assessment_id = parseInt(values.compare_assessment_id, 10);
                load(true);
            } else {
                toastr.error(obj.message);
            }
        });
    }

    $('#details_form').on('change', 'input, select, textarea', function () {

        if ($(this).closest('.edit-field').length > 0) {
            return;
        }

        save_details();
    });

    $('#do_assessment_delete_open').click(function () {
        modal('assessment_delete_modal').show();
    });

    $('#do_assessment_delete').click(function () {

        set_loading('#do_assessment_delete', true);

        ApiDataSvc.apiCall('post', 'delete_assessment', { assessment_id: assessment_id }, function (data) {

            var obj = JSON.parse(data);

            set_loading('#do_assessment_delete', false);

            if (obj.success) {
                window.location.href = '/assessments';
            } else {
                toastr.error(obj.message);
            }
        });
    });

    load();

});
</script>

<script>
$(document).ready(function () {

    var assessment_id = <?php echo (int) $this->assessment['id']; ?>;
    var center = null;
    var center_loaded = false;
    var sections = [];
    var selected_key = null;
    var dirty = false;
    var field_seq = 0;
    var package_requested = false;
    var failed_keys = [];
    var jobs = {
        narratives: null,
        package: null
    };
    var timers = {
        narratives: null,
        package: null
    };

    var ACTIVE = ['Queued', 'Running'];
    var LOCKED = ['ref', 'phase', 'kind', 'severity', 'section'];
    var PILL_CLASS = {
        'Approved': 'badge--active',
        'Draft': 'badge--inactive',
        'Rejected': 'badge--critical',
        'Out of date': 'badge--onboarding',
        'Missing': 'badge--prospect'
    };
    var SEVERITY_CLASS = {
        'High': 'badge--critical',
        'Medium': 'badge--onboarding',
        'Low': 'badge--prospect'
    };
    var SEVERITY_RANK = {
        'High': 3,
        'Medium': 2,
        'Low': 1
    };

    function report_options() {
        return {
            detail: $('#report_detail .segmented__btn.is-on').attr('data-value') || 'standard',
            appendices: $('#report_appendices').is(':checked')
        };
    }

    $('#report_detail').on('click', '.segmented__btn', function () {
        $('#report_detail .segmented__btn').removeClass('is-on');
        $(this).addClass('is-on');
    });

    function report_formats() {
        return $('#report_formats .segmented__btn.is-on').map(function () {
            return $(this).attr('data-value');
        }).get();
    }

    $('#report_formats').on('click', '.segmented__btn', function () {

        if ($(this).hasClass('is-on') && report_formats().length === 1) {
            toastr.error('Choose at least one format.');
            return;
        }

        $(this).toggleClass('is-on');
        $(this).attr('aria-pressed', $(this).hasClass('is-on') ? 'true' : 'false');
    });

    function load_center(done) {

        ApiDataSvc.apiCall('post', 'report_center', { assessment_id: assessment_id }, function (data) {

            var obj = JSON.parse(data);

            if (!obj.success) {
                toastr.error(obj.message);
                return;
            }

            center = obj;
            render_state(obj.state);
            render_readiness(obj.readiness || []);
            render_narratives(obj.narratives, obj.ai_configured);
            resume_jobs(obj.jobs || {});

            if (typeof done === 'function') {
                done();
            }
        });
    }

    $('#tab_reports_btn').on('shown.bs.tab', function () {

        if (center_loaded) {
            return;
        }

        center_loaded = true;
        load_center();
    });

    function render_state(state) {

        var version = state.report_status + ' v' + state.report_version;

        if (state.report_status === 'Final' && state.finalized_by_name) {
            version += ' · ' + state.finalized_by_name + (state.finalized_date ? ', ' + state.finalized_date : '');
        }

        $('#report_status').prop('disabled', false).val(state.report_status);
        $('#report_version').text(version);
        $('#report_changed').prop('hidden', !state.changed_since_final);
    }

    function blocker_text(blocker) {
        return typeof blocker === 'string' ? blocker : (blocker.message || blocker.label || '');
    }

    function show_blockers(blockers) {

        var html = '';

        for (var i = 0; i < blockers.length; i++) {
            html += '<li class="checks__item checks__item--block"><p class="checks__message">' + esc(blocker_text(blockers[i])) + '</p></li>';
        }

        $('#report_blockers').html(html);
        modal('report_blockers_modal').show();
    }

    $('#report_status').on('change', function () {

        var report_status = $(this).val();

        if (!center || report_status === center.state.report_status) {
            return;
        }

        $('#report_status').prop('disabled', true);
        $('#report_version').html('<i class="fa-regular fa-spinner-third fa-spin" aria-hidden="true"></i>');

        ApiDataSvc.apiCall('post', 'report_status_set', { assessment_id: assessment_id, report_status: report_status }, function (data) {

            var obj = JSON.parse(data);

            if (obj.success) {
                toastr.success(obj.message && obj.message !== 'ok' ? obj.message : 'Report status set to ' + report_status);
                center.state = obj.state || center.state;
                render_state(center.state);
                load_center();
                return;
            }

            render_state(center.state);

            var blockers = obj.finalize_blockers || obj.blockers || (obj.state && obj.state.finalize_blockers) || [];

            if (report_status === 'Final' && blockers.length === 0) {
                blockers = center.state.finalize_blockers || [];
            }

            if (blockers.length > 0) {
                show_blockers(blockers);
            } else {
                toastr.error(obj.message);
            }
        }, function () {
            render_state(center.state);
            toastr.error('The report status could not be changed. Try again.');
        });
    });

    $('#do_blockers_review').click(function () {
        modal('report_blockers_modal').hide();
        open_review();
    });

    function render_readiness(list) {

        var html = '';
        var top = '';

        for (var i = 0; i < list.length; i++) {
            html += '<li class="reports__issue"><span class="badge ' + (SEVERITY_CLASS[list[i].severity] || 'badge--inactive') + '">' + esc(list[i].severity) + '</span>'
                + '<span class="reports__issue-text">' + esc(list[i].message) + '</span></li>';

            if ((SEVERITY_RANK[list[i].severity] || 0) > (SEVERITY_RANK[top] || 0)) {
                top = list[i].severity;
            }
        }

        $('#report_readiness_list').html(html);
        $('#report_readiness').attr('data-severity', list.length === 0 ? 'ok' : top.toLowerCase());
        $('#report_readiness_title').text(list.length === 0 ? 'Ready: no issues found' : 'Report readiness (' + list.length + ')');
        $('#report_readiness_ok').prop('hidden', list.length > 0);
        $('#report_readiness_warn').prop('hidden', list.length === 0);
        $('#report_readiness_chevron').prop('hidden', list.length === 0);
        $('#report_readiness_toggle').prop('disabled', list.length === 0);

        if (list.length === 0) {
            bootstrap.Collapse.getOrCreateInstance(document.getElementById('report_readiness_body'), { toggle: false }).hide();
        }
    }

    function render_narratives(narratives, ai_configured) {

        var parts = [narratives.approved + ' of ' + narratives.total + ' approved'];
        var extra = [
            ['draft', 'draft'],
            ['stale', 'out of date'],
            ['missing', 'missing'],
            ['rejected', 'rejected']
        ];

        for (var i = 0; i < extra.length; i++) {
            if (narratives[extra[i][0]] > 0) {
                parts.push(narratives[extra[i][0]] + ' ' + extra[i][1]);
            }
        }

        $('#narrative_counts').text(parts.join(' · '));

        var stale = (narratives.sections || []).filter(function (section) {
            return section.state === 'stale';
        });
        var html = '';

        for (var s = 0; s < stale.length; s++) {
            html += '<li class="reports__issue"><span class="badge badge--onboarding">Out of date</span><span><strong>' + esc(stale[s].label) + '.</strong> ' + esc(stale[s].stale_reason || '') + '</span></li>';
        }

        $('#narratives_stale_list').html(html).prop('hidden', stale.length === 0);
        $('#do_narratives_generate').prop('hidden', !ai_configured);
        $('#do_narratives_review').prop('disabled', narratives.total === 0);
    }

    function is_active(job) {
        return !!job && ACTIVE.indexOf(job.job_status) !== -1;
    }

    function resume_jobs(latest) {

        var types = ['narratives', 'package'];

        for (var i = 0; i < types.length; i++) {

            var job = latest[types[i]] || null;

            if (timers[types[i]] !== null) {
                continue;
            }

            render_job(types[i], job);

            if (is_active(job)) {
                poll(types[i], job.id);
            }
        }
    }

    function job_label(job) {

        if (job.job_status === 'Queued' && parseInt(job.done_steps, 10) === 0) {
            return 'Queued';
        }

        var label = (parseInt(job.done_steps, 10) || 0) + ' of ' + (parseInt(job.total_steps, 10) || 0);

        return job.current_step ? label + ' · ' + job.current_step : label;
    }

    function render_job(type, job) {

        var running = is_active(job);
        var total = job ? parseInt(job.total_steps, 10) || 0 : 0;
        var done = job ? parseInt(job.done_steps, 10) || 0 : 0;

        jobs[type] = job;

        $('#' + type + '_job').prop('hidden', !running);

        if (running) {
            $('#' + type + '_bar').css('width', (total > 0 ? Math.round(done / total * 100) : 0) + '%');
            $('#' + type + '_label').text(job_label(job));
        }

        set_loading(type === 'package' ? '#do_package' : '#do_narratives_generate', running);

        if (type === 'narratives') {
            render_failed(running ? null : job);
            set_loading('#do_narratives_cancel', false);
        }
    }

    function render_failed(job) {

        var html = '';
        var steps = job && job.steps ? job.steps : [];

        failed_keys = [];

        if (job && job.job_status === 'Failed') {
            for (var i = 0; i < steps.length; i++) {
                if (steps[i].state === 'Failed') {
                    failed_keys.push(steps[i].key);
                    html += '<li class="reports__issue"><span class="badge badge--critical">Failed</span>'
                        + '<span class="reports__issue-text"><strong>' + esc(steps[i].label) + '</strong>' + (steps[i].error ? ' ' + esc(steps[i].error) : '') + '</span></li>';
                }
            }

            if (html === '' && job.last_error) {
                html = '<li class="reports__issue"><span class="badge badge--critical">Failed</span><span class="reports__issue-text">' + esc(job.last_error) + '</span></li>';
            }
        }

        $('#narratives_failed_list').html(html);
        $('#narratives_failed').prop('hidden', html === '');
        $('#do_narratives_retry').prop('hidden', failed_keys.length === 0);
        set_loading('#do_narratives_retry', false);
    }

    function poll(type, job_id) {

        clearTimeout(timers[type]);

        timers[type] = setTimeout(function () {
            ApiDataSvc.apiCall('post', 'report_job_status', { job_id: job_id }, function (data) {

                var obj = JSON.parse(data);

                if (!obj.success) {
                    timers[type] = null;
                    render_job(type, null);
                    toastr.error(obj.message);
                    return;
                }

                render_job(type, obj.job);

                if (is_active(obj.job)) {
                    poll(type, job_id);
                    return;
                }

                timers[type] = null;
                job_finished(type, obj);
            }, function () {
                poll(type, job_id);
            });
        }, 3000);
    }

    function job_finished(type, obj) {

        if (type === 'package') {
            if (obj.job.job_status === 'Completed' && obj.download_url && package_requested) {
                window.location.href = obj.download_url;
            } else if (obj.job.job_status === 'Failed' && package_requested) {
                toastr.error(obj.job.last_error || 'The report package could not be built.');
            }

            package_requested = false;
            return;
        }

        if (obj.job.job_status === 'Completed') {
            toastr.success('AI narratives are up to date');
        } else if (obj.job.job_status === 'Failed') {
            toastr.error('Some narrative sections could not be written');
        }

        load_center();

        if ($('#narrative_modal').hasClass('show')) {
            load_sections();
        }
    }

    function start_job(type, job_type, options, button) {

        set_loading(button, true);

        var values = {
            assessment_id: assessment_id,
            job_type: job_type,
            options: JSON.stringify(options)
        };

        ApiDataSvc.apiCall('post', 'report_job_start', values, function (data) {

            var obj = JSON.parse(data);

            if (!obj.success) {
                set_loading(button, false);

                if (type === 'package') {
                    package_requested = false;
                }

                toastr.error(obj.message);
                return;
            }

            set_loading(button, false);
            render_job(type, {
                id: obj.job_id,
                job_type: job_type,
                job_status: 'Queued',
                done_steps: 0,
                total_steps: 0,
                current_step: '',
                steps: []
            });
            poll(type, obj.job_id);
        }, function () {
            set_loading(button, false);
            load_center();
        });
    }

    $('#do_narratives_generate').click(function () {
        start_job('narratives', 'Narratives', {
            mode: 'missing_or_stale'
        }, '#do_narratives_generate');
    });

    $('#do_narratives_retry').click(function () {
        start_job('narratives', 'Narratives', {
            mode: 'sections',
            sections: failed_keys
        }, '#do_narratives_retry');
    });

    $('#do_package').click(function () {

        var options = report_options();
        var formats = report_formats();

        if (formats.length === 0) {
            toastr.error('Choose at least one format.');
            return;
        }

        package_requested = true;
        start_job('package', 'Package', {
            detail: options.detail,
            appendices: options.appendices,
            formats: formats
        }, '#do_package');
    });

    $('#do_narratives_cancel').click(function () {

        set_loading('#do_narratives_cancel', true);
        $('#narratives_label').text('Stopping');

        ApiDataSvc.apiCall('post', 'report_job_cancel', {
            assessment_id: assessment_id,
            job_type: 'Narratives'
        }, function (data) {

            var obj = JSON.parse(data);

            set_loading('#do_narratives_cancel', false);

            if (obj.success) {
                toastr.success('Generation stopped');
            } else {
                toastr.error(obj.message);
            }

            load_center();
        });
    });

    function pill_status(section) {

        if (section.state === 'missing') {
            return 'Missing';
        }

        if (section.state === 'stale') {
            return 'Out of date';
        }

        return section.narrative_status || 'Draft';
    }

    function pill(section) {

        var label = pill_status(section);

        return '<span class="badge ' + (PILL_CLASS[label] || 'badge--inactive') + '">' + esc(label) + '</span>';
    }

    function humanize(key) {

        var text = String(key).replace(/_/g, ' ');

        return text.charAt(0).toUpperCase() + text.slice(1);
    }

    function infer_fields(value, skip_locked) {

        var fields = [];

        if (!value || typeof value !== 'object' || Array.isArray(value)) {
            return fields;
        }

        Object.keys(value).forEach(function (key) {

            var item = value[key];

            if (skip_locked && LOCKED.indexOf(key) !== -1) {
                return;
            }

            fields.push({
                path: key,
                label: humanize(key),
                type: Array.isArray(item)
                    ? (item.length > 0 && item[0] !== null && typeof item[0] === 'object' ? 'objects' : 'list')
                    : 'text'
            });
        });

        return fields;
    }

    function value_at(content, path) {

        var parts = String(path).split('.');
        var value = content;

        for (var i = 0; i < parts.length; i++) {
            if (value === null || value === undefined) {
                return undefined;
            }

            value = value[parts[i]];
        }

        return value;
    }

    function set_at(content, path, value) {

        var parts = String(path).split('.');
        var target = content;

        for (var i = 0; i < parts.length - 1; i++) {
            target = target[parts[i]];
        }

        target[parts[parts.length - 1]] = value;
    }

    function textarea(path, type, label, value) {

        field_seq++;

        var id = 'narrative_field_' + field_seq;
        var text = type === 'list' ? (Array.isArray(value) ? value.join('\n\n') : '') : (value === null || value === undefined ? '' : String(value));

        return '<div class="form-group review__field">'
            + '<label for="' + id + '">' + esc(label) + '</label>'
            + '<textarea class="form-control review__text" id="' + id + '" rows="1" data-path="' + esc(path) + '" data-type="' + type + '">' + esc(text) + '</textarea>'
            + '</div>';
    }

    function locked_chips(item) {

        var html = '';

        for (var i = 0; i < LOCKED.length; i++) {
            if (typeof item[LOCKED[i]] === 'string' && item[LOCKED[i]] !== '') {
                html += '<span class="badge badge--inactive">' + esc(item[LOCKED[i]]) + '</span>';
            }
        }

        return html;
    }

    function render_field(field, content, base) {

        var path = base === '' ? field.path : base + '.' + field.path;
        var value = value_at(content, path);

        if (field.type !== 'objects') {
            return textarea(path, field.type, field.label, value);
        }

        var list = Array.isArray(value) ? value : [];
        var html = '';

        for (var i = 0; i < list.length; i++) {

            var item_path = path + '.' + i;
            var subs = field.fields && field.fields.length ? field.fields : infer_fields(list[i], true);
            var chips = locked_chips(list[i]);
            var inner = '';

            for (var s = 0; s < subs.length; s++) {
                inner += render_field(subs[s], content, item_path);
            }

            html += '<div class="review__object">'
                + '<div class="review__object-head"><span class="review__object-num">' + (i + 1) + '</span>' + chips + '</div>'
                + inner + '</div>';
        }

        if (html === '') {
            return '';
        }

        return '<div class="review__group"><span class="review__group-title">' + esc(field.label) + '</span>' + html + '</div>';
    }

    function section_of(key) {

        for (var i = 0; i < sections.length; i++) {
            if (sections[i].section_key === key) {
                return sections[i];
            }
        }

        return null;
    }

    function autosize(element) {

        if (element.offsetParent === null) {
            return;
        }

        element.style.height = 'auto';
        element.style.height = (element.scrollHeight + 2) + 'px';
    }

    $('#narrative_modal').on('shown.bs.modal', function () {
        $('#narrative_editor .review__text').each(function () {
            autosize(this);
        });
    });

    function render_list() {

        var html = '';

        for (var i = 0; i < sections.length; i++) {
            html += '<li><button type="button" class="review__item' + (sections[i].section_key === selected_key ? ' is-on' : '') + '" data-key="' + esc(sections[i].section_key) + '">'
                + '<span class="review__name">' + esc(sections[i].label) + '</span>' + pill(sections[i]) + '</button></li>';
        }

        $('#narrative_list').html(html);

        var drafts = current_drafts().length;

        $('#do_narratives_approve_all_open').prop('disabled', drafts === 0);
    }

    function current_drafts() {
        return sections.filter(function (section) {
            return section.state === 'current' && section.narrative_status === 'Draft';
        });
    }

    function sync_buttons() {

        var section = section_of(selected_key);
        var status = section ? pill_status(section) : 'Missing';

        $('#do_narrative_save').prop('disabled', status === 'Missing' || !dirty);
        $('#do_narrative_approve').prop('disabled', status === 'Missing' || status === 'Stale' || (status === 'Approved' && !dirty));
        $('#do_narrative_reject').prop('disabled', status === 'Missing' || status === 'Rejected');
    }

    function render_editor() {

        var section = section_of(selected_key);
        var html = '';

        dirty = false;

        if (!section) {
            $('#narrative_editor').html('<p class="review__empty">No narrative sections for this assessment.</p>');
            sync_buttons();
            return;
        }

        html += '<div class="review__head"><h3 class="review__title">' + esc(section.label) + '</h3>' + pill(section) + '</div>';

        if (section.state === 'stale' && section.stale_reason) {
            html += '<p class="review__stale">' + esc(section.stale_reason) + '</p>';
        }

        if (section.state === 'missing' || !section.content) {
            html += '<p class="review__empty">This section has not been written yet.</p>';
        } else {
            var fields = section.fields && section.fields.length ? section.fields : infer_fields(section.content, false);

            for (var i = 0; i < fields.length; i++) {
                html += render_field(fields[i], section.content, '');
            }
        }

        $('#narrative_editor').html(html).scrollTop(0);
        $('#narrative_editor .review__text').each(function () {
            autosize(this);
        });
        sync_buttons();
    }

    $('#narrative_editor').on('input', '.review__text', function () {
        autosize(this);
        dirty = true;
        sync_buttons();
    });

    function edited_content() {

        var section = section_of(selected_key);
        var content = JSON.parse(JSON.stringify(section.content));

        $('#narrative_editor .review__text').each(function () {

            var value = $(this).val();

            if ($(this).attr('data-type') === 'list') {
                value = value.split(/\n+/).map(function (line) {
                    return line.trim();
                }).filter(function (line) {
                    return line !== '';
                });
            } else {
                value = value.trim();
            }

            set_at(content, $(this).attr('data-path'), value);
        });

        return content;
    }

    function replace_section(updated) {

        for (var i = 0; i < sections.length; i++) {
            if (sections[i].section_key === updated.section_key) {
                updated.fields = updated.fields || sections[i].fields;
                sections[i] = updated;
            }
        }
    }

    function load_sections() {

        ApiDataSvc.apiCall('post', 'report_narrative_sections', { assessment_id: assessment_id }, function (data) {

            var obj = JSON.parse(data);

            if (!obj.success) {
                toastr.error(obj.message);
                return;
            }

            sections = obj.sections || [];

            if (!section_of(selected_key)) {
                selected_key = sections.length > 0 ? sections[0].section_key : null;
            }

            render_list();
            render_editor();
        });
    }

    function open_review() {

        sections = [];
        $('#narrative_list').empty();
        $('#narrative_editor').html('<p class="review__empty"><i class="fa-regular fa-spinner-third fa-spin" aria-hidden="true"></i> Loading narratives&hellip;</p>');
        $('#do_narrative_save, #do_narrative_approve, #do_narrative_reject, #do_narratives_approve_all_open').prop('disabled', true);
        modal('narrative_modal').show();
        load_sections();
    }

    $('#do_narratives_review').click(open_review);

    function save_section(button, done) {

        set_loading(button, true);

        var values = {
            assessment_id: assessment_id,
            section_key: selected_key,
            content: JSON.stringify(edited_content())
        };

        ApiDataSvc.apiCall('post', 'report_narrative_save', values, function (data) {

            var obj = JSON.parse(data);

            set_loading(button, false);

            if (!obj.success) {
                toastr.error(obj.message);
                sync_buttons();
                return;
            }

            if (obj.section) {
                replace_section(obj.section);
            }

            dirty = false;
            done();
        }, function () {
            set_loading(button, false);
            sync_buttons();
            toastr.error('The edits could not be saved. Try again.');
        });
    }

    function set_status(button, narrative_status) {

        set_loading(button, true);

        var values = {
            assessment_id: assessment_id,
            section_key: selected_key,
            narrative_status: narrative_status
        };

        ApiDataSvc.apiCall('post', 'report_narrative_status', values, function (data) {

            var obj = JSON.parse(data);

            set_loading(button, false);

            if (!obj.success) {
                toastr.error(obj.message);
                sync_buttons();
                return;
            }

            if (obj.section) {
                replace_section(obj.section);
            }

            render_list();
            render_editor();
            load_center();
        }, function () {
            set_loading(button, false);
            sync_buttons();
            toastr.error('That could not be saved. Try again.');
        });
    }

    $('#narrative_list').on('click', '.review__item', function () {

        var key = $(this).attr('data-key');

        if (key === selected_key) {
            return;
        }

        if (!dirty) {
            selected_key = key;
            render_list();
            render_editor();
            return;
        }

        save_section(this, function () {
            toastr.success('Edits saved');
            selected_key = key;
            render_list();
            render_editor();
            load_center();
        });
    });

    $('#do_narrative_save').click(function () {
        save_section('#do_narrative_save', function () {
            toastr.success('Edits saved');
            render_list();
            render_editor();
            load_center();
        });
    });

    $('#do_narrative_approve').click(function () {

        if (!dirty) {
            set_status('#do_narrative_approve', 'Approved');
            return;
        }

        save_section('#do_narrative_approve', function () {
            set_status('#do_narrative_approve', 'Approved');
        });
    });

    $('#do_narrative_reject').click(function () {
        set_status('#do_narrative_reject', 'Rejected');
    });

    $('#do_narratives_approve_all_open').click(function () {

        var count = current_drafts().length;

        $('#narratives_approve_all_text').text('Approve ' + count + ' current draft ' + (count === 1 ? 'section' : 'sections') + ' as written? Stale, missing and rejected sections are left as they are.');
        modal('narratives_approve_all_modal').show();
    });

    function approve_next(list, index, done) {

        if (index >= list.length) {
            done();
            return;
        }

        var values = {
            assessment_id: assessment_id,
            section_key: list[index].section_key,
            narrative_status: 'Approved'
        };

        ApiDataSvc.apiCall('post', 'report_narrative_status', values, function (data) {

            var obj = JSON.parse(data);

            if (!obj.success) {
                toastr.error(list[index].label + ': ' + obj.message);
            }

            approve_next(list, index + 1, done);
        }, function () {
            toastr.error(list[index].label + ' could not be approved.');
            approve_next(list, index + 1, done);
        });
    }

    $('#do_narratives_approve_all').click(function () {

        var list = current_drafts();

        set_loading('#do_narratives_approve_all', true);

        approve_next(list, 0, function () {
            set_loading('#do_narratives_approve_all', false);
            modal('narratives_approve_all_modal').hide();
            toastr.success(list.length === 1 ? 'Section approved' : list.length + ' sections approved');
            load_sections();
            load_center();
        });
    });

    function report_values(button, format) {

        var options = report_options();

        return {
            report_type: $(button).attr('data-report'),
            scope: 'assessment',
            id: assessment_id,
            format: format,
            detail: options.detail,
            appendices: options.appendices ? '1' : '0'
        };
    }

    $('#tab_reports').on('click', "[data-action='preview_report']", function () {

        var button = this;
        var values = report_values(button, 'pdf');

        values.preview = '1';

        $('#report_preview_modal_title').text($(button).attr('data-name'));
        $('#report_preview_frame').attr('src', 'about:blank').prop('hidden', true);
        $('#report_preview_loading').prop('hidden', false);
        set_loading(button, true);
        modal('report_preview_modal').show();

        ApiDataSvc.apiCall('post', 'ready_report_download', values, function (data) {

            var obj = JSON.parse(data);

            set_loading(button, false);

            if (!obj.success) {
                modal('report_preview_modal').hide();
                toastr.error(obj.message);
                return;
            }

            $('#report_preview_frame').prop('hidden', false).one('load', function () {
                $('#report_preview_loading').prop('hidden', true);
            }).attr('src', obj.url);
        }, function () {
            set_loading(button, false);
            modal('report_preview_modal').hide();
            toastr.error('The preview could not be prepared. Try again.');
        });
    });

    $('#report_preview_modal').on('hidden.bs.modal', function () {
        $('#report_preview_frame').off('load').attr('src', 'about:blank').prop('hidden', true);
    });

    $('#tab_reports').on('click', "[data-action='download_report']", function () {

        var button = this;
        var format = $(button).attr('data-format');
        var values = report_values(button, format);

        set_loading(button, true);

        var tab = report_tab(format);

        ApiDataSvc.apiCall('post', 'ready_report_download', values, function (data) {

            var obj = JSON.parse(data);

            set_loading(button, false);

            if (obj.success) {
                show_report_tab(tab, obj.url);
            } else {
                close_report_tab(tab);
                toastr.error(obj.message);
            }
        }, function () {
            set_loading(button, false);
            close_report_tab(tab);
            toastr.error('The report could not be prepared. Try again.');
        });
    });

});
</script>
