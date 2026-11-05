<div class="page page--assessment">

    <div class="assess__tabsbar">
        <nav class="subtabs" role="tablist" aria-label="Assessment">
            <button class="subtabs__link active" id="tab_dashboard_btn" data-bs-toggle="pill" data-bs-target="#tab_dashboard" type="button" role="tab" aria-controls="tab_dashboard" aria-selected="true">Dashboard</button>
            <button class="subtabs__link" id="tab_controls_btn" data-bs-toggle="pill" data-bs-target="#tab_controls" type="button" role="tab" aria-controls="tab_controls" aria-selected="false">Controls</button>
            <button class="subtabs__link" id="tab_vault_btn" data-bs-toggle="pill" data-bs-target="#tab_vault" type="button" role="tab" aria-controls="tab_vault" aria-selected="false">Evidence Vault</button>
            <button class="subtabs__link" id="tab_reports_btn" data-bs-toggle="pill" data-bs-target="#tab_reports" type="button" role="tab" aria-controls="tab_reports" aria-selected="false">Reports</button>
            <button class="subtabs__link" id="tab_settings_btn" data-bs-toggle="pill" data-bs-target="#tab_settings" type="button" role="tab" aria-controls="tab_settings" aria-selected="false">Settings</button>
        </nav>
    </div>

    <div class="tab-content assess__panes">
        <div class="tab-pane show active" id="tab_dashboard" role="tabpanel" aria-labelledby="tab_dashboard_btn">
            <div class="panel record__panel">
                <dl class="kpi adash__kpi" id="adash_kpis"></dl>
            </div>
            <div class="row gx-4 adash__grid">
                <div class="col-lg-7 adash__col">
                    <div class="panel record__panel adash__panel">
                        <div class="panel__head">
                            <h2 class="panel__title">Results by Category</h2>
                        </div>
                        <div class="panel__body adash__scroll">
                            <ul class="adash__families" id="adash_families"></ul>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5 adash__col adash__side">
                    <div class="panel record__panel adash__panel">
                        <div class="panel__head">
                            <h2 class="panel__title">Weakest Categories</h2>
                        </div>
                        <div class="panel__body adash__scroll">
                            <ul class="adash__rows" id="adash_weakest"></ul>
                            <h3 class="adash__section">POA&amp;M by Remediation Priority</h3>
                            <ul class="adash__rows" id="adash_poam"></ul>
                            <h3 class="adash__section">Report Readiness</h3>
                            <ul class="adash__rows" id="adash_report"></ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="tab-pane" id="tab_controls" role="tabpanel" aria-labelledby="tab_controls_btn">
            <div class="assess__progress assess__progress--wide" id="score_row" hidden>
                <span class="assess__progress-count" id="rollup_label"></span>
                <div class="assess__bar" id="rollup_bar"></div>
                <span class="compare__summary" id="compare_summary" hidden></span>
                <span class="assess__progress-outcome" id="score_outcome"></span>
                <div class="assess__tabsbar-actions">
                    <span class="headline__status <?php echo ($this->assessment['assessment_status'] === 'Complete' ? 'is-ok' : ($this->assessment['assessment_status'] === 'In Progress' ? 'is-progress' : 'is-muted')); ?>" id="status_badge" hidden><?php echo htmlspecialchars($this->assessment['assessment_status'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <button type="button" class="btn btn--primary btn--pill" id="do_complete" disabled><i class="fa-regular fa-circle-check"></i> Mark Complete</button>
                </div>
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
                                        <div class="segmented segmented--wrap" id="item_result" role="group" aria-label="Result">
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
            <div class="reports reports--split">
                <section class="panel reports__panel" id="report_ai">
                    <div class="panel__head">
                        <div class="reports__field">
                            <h2 class="panel__title">Reports</h2>
                        </div>
                        <div class="reports__actions">
                            <button type="button" class="btn btn--secondary" id="do_narratives_next"><i class="fa-regular fa-list-check"></i> Review Drafts</button>
                            <button type="button" class="btn btn--primary" id="do_package_open"><i class="fa-regular fa-file-zipper"></i> Download Package</button>
                        </div>
                    </div>
                    <div class="reports__job" id="narratives_job" hidden>
                        <div class="progress" id="narratives_overall"><span class="progress__bar" id="narratives_bar"></span></div>
                        <span class="reports__job-label" id="narratives_label"></span>
                        <ul class="reports__steps" id="narratives_steps" hidden></ul>
                        <button type="button" class="btn btn--secondary btn--sm" id="do_narratives_cancel"><i class="fa-regular fa-stop"></i> Stop</button>
                    </div>
                    <div class="reports__failed" id="narratives_failed" hidden>
                        <ul class="reports__issues" id="narratives_failed_list"></ul>
                        <button type="button" class="btn btn--secondary btn--sm" id="do_narratives_retry"><i class="fa-regular fa-rotate-right"></i> Retry</button>
                    </div>
                    <ul class="reports__list">
                        <?php foreach (ReadyReports::catalog_for('assessment') as $report) { ?>
                        <li class="reports__row">
                            <span class="reports__name"><?php echo htmlspecialchars($report['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="reports__row-actions">
                                <?php if ($report['key'] === 'poam') { ?>
                                <button type="button" class="btn btn--secondary btn--sm" id="do_poam_open">Manage</button>
                                <?php } ?>
                                <?php if (in_array('pdf', $report['formats'], true)) { ?>
                                <button type="button" class="btn btn--secondary btn--sm" data-action="preview_report" data-report="<?php echo htmlspecialchars($report['key'], ENT_QUOTES, 'UTF-8'); ?>" data-name="<?php echo htmlspecialchars($report['name'], ENT_QUOTES, 'UTF-8'); ?>">Preview</button>
                                <?php } ?>
                                <span class="dropdown">
                                    <button type="button" class="btn btn--secondary btn--sm" data-bs-toggle="dropdown" aria-expanded="false">Download <i class="fa-regular fa-chevron-down"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <?php foreach ($report['formats'] as $format) { ?>
                                        <li><button type="button" class="dropdown-item" data-action="download_report" data-report="<?php echo htmlspecialchars($report['key'], ENT_QUOTES, 'UTF-8'); ?>" data-format="<?php echo htmlspecialchars($format, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $format === 'pdf' ? 'PDF' : ($format === 'docx' ? 'Word' : 'Excel'); ?></button></li>
                                        <?php } ?>
                                    </ul>
                                </span>
                            </span>
                        </li>
                        <?php } ?>
                    </ul>
                </section>

                <section class="panel reports__panel" id="report_readiness" hidden>
                    <div class="panel__head">
                        <h2 class="panel__title">Report Readiness</h2>
                    </div>
                    <ul class="reports__issues" id="report_readiness_list"></ul>
                </section>
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
                    <section class="card prefs__group">
                        <header class="card__head">
                            <h2 class="card__title">Report Settings</h2>
                        </header>
                        <button type="button" class="prefs__item" id="do_detail_open">
                            <span class="prefs__item-label">Level of Detail</span>
                            <span class="prefs__item-value" id="report_detail_value">Standard</span>
                            <i class="fa-regular fa-chevron-right prefs__item-go" aria-hidden="true"></i>
                        </button>
                        <button type="button" class="prefs__item" id="do_appendices_open">
                            <span class="prefs__item-label">Appendices</span>
                            <span class="prefs__item-value" id="report_appendices_value">Yes</span>
                            <i class="fa-regular fa-chevron-right prefs__item-go" aria-hidden="true"></i>
                        </button>
                        <button type="button" class="prefs__item" id="do_status_open" disabled>
                            <span class="prefs__item-label">Report Status</span>
                            <span class="prefs__item-value"><span id="report_status_value"></span><span class="reports__warn" id="report_changed" hidden><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> Changed since final</span></span>
                            <i class="fa-regular fa-chevron-right prefs__item-go" aria-hidden="true"></i>
                        </button>
                        <?php if (Poam::profile((string) $this->assessment['framework_key']) === 'conditional') { ?>
                        <button type="button" class="prefs__item" id="do_poam_clock_open">
                            <span class="prefs__item-label">POA&amp;M Clock Date</span>
                            <span class="prefs__item-value" id="poam_clock_value"><?php echo htmlspecialchars(Poam::date_label($this->assessment['poam_clock_date'] ?? null), ENT_QUOTES, 'UTF-8'); ?></span>
                            <i class="fa-regular fa-chevron-right prefs__item-go" aria-hidden="true"></i>
                        </button>
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
                        <div class="panel__head">
                            <h2 class="panel__title">Folders</h2>
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
                    <div class="review__pane">
                        <div class="review__bar">
                            <div class="review__bar-title">
                                <h3 class="review__title" id="narrative_section_title"></h3>
                                <span id="narrative_section_pill"></span>
                            </div>
                            <div class="review__actions" role="group" aria-label="Section actions">
                                <button type="button" class="review__action review__action--approve" id="do_narrative_approve"><i class="fa-regular fa-check"></i> Approve Section</button>
                                <button type="button" class="review__action" id="do_narrative_save"><i class="fa-regular fa-floppy-disk"></i> Save Edits</button>
                                <button type="button" class="review__action" id="do_narrative_rewrite" hidden><i class="fa-regular fa-rotate-right"></i> Rewrite Section</button>
                                <button type="button" class="review__action review__action--reject" id="do_narrative_reject"><i class="fa-regular fa-xmark"></i> Reject Section</button>
                            </div>
                        </div>
                        <section class="review__editor" id="narrative_editor" aria-live="polite">
                            <p class="review__empty"><i class="fa-regular fa-spinner-third fa-spin" aria-hidden="true"></i> Loading narratives&hellip;</p>
                        </section>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" id="do_narratives_approve_all_open"><i class="fa-regular fa-check-double"></i> Approve All Current Drafts</button>
                <button type="button" class="btn btn--secondary me-auto" id="do_rewrite_open" hidden><i class="fa-regular fa-wand-magic-sparkles"></i> Rewrite All</button>
                <button type="button" class="btn btn--primary" data-bs-dismiss="modal">Close</button>
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

<div class="modal fade" id="report_detail_modal" tabindex="-1" aria-labelledby="report_detail_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="report_detail_modal_title">Level of Detail</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="segmented segmented--switch prefs__choice" id="report_detail" role="group" aria-labelledby="report_detail_modal_title">
                    <button type="button" class="segmented__btn" data-value="executive">Executive</button>
                    <button type="button" class="segmented__btn is-on" data-value="standard">Standard</button>
                    <button type="button" class="segmented__btn" data-value="comprehensive">Comprehensive</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="report_appendices_modal" tabindex="-1" aria-labelledby="report_appendices_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="report_appendices_modal_title">Appendices</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="segmented segmented--switch prefs__choice" id="report_appendices" role="group" aria-labelledby="report_appendices_modal_title">
                    <button type="button" class="segmented__btn is-on" data-value="1">Yes</button>
                    <button type="button" class="segmented__btn" data-value="0">No</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="report_status_modal" tabindex="-1" aria-labelledby="report_status_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="report_status_modal_title">Report Status</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="segmented segmented--switch prefs__choice" id="report_status_pick" role="group" aria-labelledby="report_status_modal_title">
                    <button type="button" class="segmented__btn" data-value="Draft">Draft</button>
                    <button type="button" class="segmented__btn" data-value="In Review">In Review</button>
                    <button type="button" class="segmented__btn" data-value="Final">Final</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="report_rewrite_modal" tabindex="-1" aria-labelledby="report_rewrite_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="report_rewrite_modal_title">Rewrite All Narratives</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Every AI narrative section is written again from the current answers and goes back to Draft.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn--primary" id="do_narratives_rewrite_all"><i class="fa-regular fa-wand-magic-sparkles"></i> Rewrite All</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="report_package_modal" tabindex="-1" aria-labelledby="report_package_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="report_package_modal_title">Download Package</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body prefs">
                <div class="row prefs__row">
                    <span class="col-md-4 prefs__label" id="report_formats_label">Formats</span>
                    <div class="col-md-8 prefs__control">
                        <div class="segmented segmented--switch prefs__input reports__formats-pick" id="report_formats" role="group" aria-labelledby="report_formats_label">
                            <button type="button" class="segmented__btn is-on" data-value="pdf" aria-pressed="true">PDF</button>
                            <button type="button" class="segmented__btn is-on" data-value="docx" aria-pressed="true">Word</button>
                            <button type="button" class="segmented__btn" data-value="xlsx" aria-pressed="false">Excel</button>
                        </div>
                    </div>
                </div>
                <div class="reports__job" id="package_job" hidden>
                    <div class="progress"><span class="progress__bar" id="package_bar"></span></div>
                    <span class="reports__job-label" id="package_label"></span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn--primary" id="do_package"><i class="fa-regular fa-arrow-down-to-line"></i> Download</button>
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

<div class="modal fade" id="poam_modal" tabindex="-1" aria-labelledby="poam_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="poam_modal_title">POA&amp;M Register</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="notice notice--warn poam__banner" id="poam_banner" hidden></p>
                <div class="poam__toolbar">
                    <input type="search" id="poam_search" class="input poam__search" placeholder="Search by ID, control, weakness or owner..." autocomplete="off" spellcheck="false">
                    <div class="poam__filter">
                        <select id="poam_status_filter" class="form-select" data-style="form-control" aria-label="Status">
                            <option value="">All statuses</option>
                            <option value="Open">Open</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Ready for Validation">Ready for Validation</option>
                            <option value="Closed">Closed</option>
                        </select>
                    </div>
                    <span class="poam__counts" id="poam_counts"></span>
                </div>
                <div class="poam__scroll">
                    <table class="data poam__table" id="poam_table" hidden>
                        <thead>
                            <tr>
                                <th scope="col" class="poam__col-id">ID</th>
                                <th scope="col" class="poam__col-control">Control</th>
                                <th scope="col">Weakness</th>
                                <th scope="col" class="poam__col-owner">Responsible Owner</th>
                                <th scope="col" class="poam__col-date">Scheduled</th>
                                <th scope="col" class="poam__col-status">Status</th>
                                <th scope="col" class="poam__col-priority">Remediation Priority</th>
                            </tr>
                        </thead>
                        <tbody id="poam_list"></tbody>
                    </table>
                    <p class="poam__empty" id="poam_empty"><i class="fa-regular fa-spinner-third fa-spin" aria-hidden="true"></i> Loading items&hellip;</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" id="poam_item_modal" tabindex="-1" aria-labelledby="poam_item_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="poam_item_modal_title">Edit Item</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="poam__head">
                    <p class="assess__eyebrow"><span class="assess__id" id="poam_item_control"></span></p>
                    <h3 class="assess__title" id="poam_item_title"></h3>
                    <p class="poam__meta">
                        <span id="poam_item_status"></span>
                        <span class="poam__meta-item" id="poam_item_priority"></span>
                        <span class="poam__meta-item" id="poam_item_eligibility"></span>
                    </p>
                </div>

                <div class="poam__section" id="poam_item_fields">
                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label for="poam_weakness">Weakness</label>
                            <textarea class="form-control poam__text" id="poam_weakness" rows="4"></textarea>
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label for="poam_action">Corrective Action</label>
                            <textarea class="form-control poam__text" id="poam_action" rows="4"></textarea>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label for="poam_owner">Responsible Owner</label>
                            <input type="text" class="form-control" id="poam_owner" maxlength="200">
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label for="poam_status">Status</label>
                            <select class="form-control" id="poam_status" data-style="form-control">
                                <option value="Open">Open</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Ready for Validation">Ready for Validation</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label for="poam_detection">Detection Date</label>
                            <input type="date" class="form-control" id="poam_detection">
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label for="poam_scheduled">Scheduled Completion Date</label>
                            <input type="date" class="form-control" id="poam_scheduled">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 form-group mb-3">
                            <label for="poam_resources">Resources Required</label>
                            <textarea class="form-control poam__text" id="poam_resources" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="poam__fedramp" id="poam_fedramp" hidden>
                        <div class="row">
                            <div class="col-md-6 form-group mb-3">
                                <label for="poam_risk">Risk Rating</label>
                                <select class="form-control" id="poam_risk" data-style="form-control">
                                    <option value="">Not set</option>
                                    <option value="Low">Low</option>
                                    <option value="Moderate">Moderate</option>
                                    <option value="High">High</option>
                                    <option value="Critical">Critical</option>
                                </select>
                            </div>
                            <div class="col-md-6 form-group mb-3">
                                <label for="poam_deviation_type">Deviation Type</label>
                                <select class="form-control" id="poam_deviation_type" data-style="form-control">
                                    <option value="">None</option>
                                    <option value="Risk Adjustment">Risk Adjustment</option>
                                    <option value="False Positive">False Positive</option>
                                    <option value="Operational Requirement">Operational Requirement</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-group mb-3">
                                <label for="poam_vendor_dependency">Vendor Dependency</label>
                                <select class="form-control" id="poam_vendor_dependency" data-style="form-control">
                                    <option value="0">No</option>
                                    <option value="1">Yes</option>
                                </select>
                            </div>
                            <div class="col-md-6 form-group mb-3">
                                <label for="poam_vendor_checkin">Last Vendor Check-in Date</label>
                                <input type="date" class="form-control" id="poam_vendor_checkin">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12 form-group mb-3">
                                <label for="poam_vendor_product">Vendor Product</label>
                                <input type="text" class="form-control" id="poam_vendor_product" maxlength="200">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12 form-group mb-3">
                                <label for="poam_deviation_rationale">Deviation Rationale</label>
                                <textarea class="form-control poam__text" id="poam_deviation_rationale" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 form-group">
                            <label for="poam_comments">Comments</label>
                            <textarea class="form-control poam__text" id="poam_comments" rows="3"></textarea>
                        </div>
                    </div>
                </div>

                <div class="poam__section">
                    <div class="poam__section-head">
                        <h3 class="record__group-title">Milestones</h3>
                        <button type="button" class="btn btn--secondary btn--sm" id="do_poam_milestone_add"><i class="fa-regular fa-plus"></i> Add Milestone</button>
                    </div>
                    <table class="data data--light poam__milestones" id="poam_milestones_table" hidden>
                        <thead>
                            <tr>
                                <th scope="col">Milestone</th>
                                <th scope="col" class="poam__ms-date">Due</th>
                                <th scope="col" class="poam__ms-date">Completed</th>
                                <th scope="col" class="poam__ms-status">Status</th>
                                <th scope="col" class="poam__ms-actions"><span class="visually-hidden">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody id="poam_milestones"></tbody>
                    </table>
                    <p class="poam__none" id="poam_milestones_empty">No milestones.</p>
                </div>

                <div class="poam__section">
                    <div class="poam__section-head">
                        <h3 class="record__group-title">Evidence</h3>
                        <button type="button" class="btn btn--secondary btn--sm" id="do_poam_evidence_open"><i class="fa-regular fa-link"></i> Link Evidence</button>
                    </div>
                    <ul class="evidence__list poam__evidence" id="poam_evidence" hidden></ul>
                    <p class="poam__none" id="poam_evidence_empty">No evidence linked.</p>
                </div>

                <div class="poam__section">
                    <button type="button" class="poam__toggle collapsed" id="poam_history_toggle" data-bs-toggle="collapse" data-bs-target="#poam_history_wrap" aria-expanded="false" aria-controls="poam_history_wrap">
                        <span class="record__group-title">History</span>
                        <span class="poam__count" id="poam_history_count"></span>
                        <i class="fa-regular fa-chevron-down poam__toggle-go" aria-hidden="true"></i>
                    </button>
                    <div class="collapse" id="poam_history_wrap">
                        <ul class="poam__history" id="poam_history"></ul>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <span class="me-auto modal-footer__aux">
                    <button type="button" class="btn btn--secondary" id="do_poam_close_open" hidden>Close Item&hellip;</button>
                    <button type="button" class="btn btn--secondary" id="do_poam_reopen_open" hidden>Reopen&hellip;</button>
                </span>
                <button type="button" class="btn btn--secondary" id="poam_item_dismiss" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn--primary" id="do_poam_save">Save</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" id="poam_evidence_modal" tabindex="-1" aria-labelledby="poam_evidence_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="poam_evidence_modal_title">Link Evidence</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="poam__toolbar">
                    <input type="search" id="poam_evidence_search" class="input poam__search" placeholder="Search title or file name..." autocomplete="off" spellcheck="false">
                    <div class="poam__filter">
                        <select id="poam_evidence_role" class="form-select" data-style="form-control" aria-label="Role">
                            <option value="Progress">Progress</option>
                            <option value="Closure">Closure</option>
                        </select>
                    </div>
                </div>
                <div class="poam__pick">
                    <table class="data data--light" id="poam_pick_table" hidden>
                        <thead>
                            <tr>
                                <th scope="col">Name</th>
                                <th scope="col" class="vault__col-date">Uploaded</th>
                                <th scope="col" class="pick__col-attach"><span class="visually-hidden">Link</span></th>
                            </tr>
                        </thead>
                        <tbody id="poam_pick_list"></tbody>
                    </table>
                    <p class="poam__empty" id="poam_pick_empty"><i class="fa-regular fa-spinner-third fa-spin" aria-hidden="true"></i> Loading evidence&hellip;</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Done</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" id="poam_close_modal" tabindex="-1" aria-labelledby="poam_close_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="poam_close_modal_title">Confirm Closure</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="form-group mb-3">
                    <label for="poam_closure_note">Closure Note <abbr title="required">*</abbr></label>
                    <textarea class="form-control poam__text" id="poam_closure_note" rows="3"></textarea>
                </div>
                <div class="form-group mb-3" id="poam_closure_reason_group">
                    <label for="poam_closure_reason">Closure Reason</label>
                    <textarea class="form-control poam__text" id="poam_closure_reason" rows="2"></textarea>
                </div>
                <div class="form-check" id="poam_override_group">
                    <input class="form-check-input" type="checkbox" id="poam_override_ack">
                    <label class="form-check-label" for="poam_override_ack" id="poam_override_label"></label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn--primary" id="do_poam_close">Confirm Closure</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" id="poam_reopen_modal" tabindex="-1" aria-labelledby="poam_reopen_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="poam_reopen_modal_title">Reopen Item</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="poam_reopen_note">Reopen Note <abbr title="required">*</abbr></label>
                    <textarea class="form-control poam__text" id="poam_reopen_note" rows="3" maxlength="400"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn--primary" id="do_poam_reopen">Reopen</button>
            </div>
        </div>
    </div>
</div>

<?php if (Poam::profile((string) $this->assessment['framework_key']) === 'conditional') { ?>
<div class="modal fade" id="poam_clock_modal" tabindex="-1" aria-labelledby="poam_clock_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="poam_clock_modal_title">POA&amp;M Clock Date</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="poam_clock_date">Conditional Status Date</label>
                    <input type="date" class="form-control" id="poam_clock_date" max="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars((string) ($this->assessment['poam_clock_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn--primary" id="do_poam_clock_save">Save</button>
            </div>
        </div>
    </div>
</div>
<?php } ?>

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
            label: 'Partial',
            icon: 'fa-circle-half-stroke'
        },
        {
            value: 'Not Implemented',
            label: 'Not Met',
            icon: 'fa-xmark'
        },
        {
            value: 'Not Applicable',
            label: 'N/A',
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
        render_dashboard();
    }

    var dash_poam = {
        open: 0,
        overdue: 0
    };

    var DASH_PARTS = [
        ['Implemented', 'is-ok'],
        ['Partially Implemented', 'is-partial'],
        ['Not Implemented', 'is-gap'],
        ['Not Applicable', 'is-na'],
        ['Not Assessed', 'is-none']
    ];

    function dash_kpi(label, value, sub, tone) {
        return '<div class="kpi__cell">'
            + '<dt class="kpi__label">' + label + '</dt>'
            + '<dd class="kpi__value' + (tone === '' ? '' : ' ' + tone) + '">' + value + '</dd>'
            + '<dd class="kpi__sub">' + sub + '</dd>'
            + '</div>';
    }

    function dash_row(label, value, sub) {
        return '<li class="adash__row">'
            + '<span class="adash__row-label">' + label + (sub ? '<span class="adash__row-sub">' + sub + '</span>' : '') + '</span>'
            + '<span class="adash__row-value">' + value + '</span>'
            + '</li>';
    }

    function render_dashboard() {

        if (!review || !review.score) {
            return;
        }

        var score = review.score;
        var counts = score.counts;
        var in_scope = parseInt(score.in_scope, 10) || 0;
        var assessed = in_scope - (counts['Not Assessed'] || 0);
        var gaps = (counts['Not Implemented'] || 0) + (counts['Partially Implemented'] || 0);
        var scoped = 0;
        var evidenced = 0;

        for (var i = 0; i < controls.length; i++) {
            if (!counts_toward_scope(controls[i])) {
                continue;
            }
            scoped++;
            if (evidence_total(controls[i]) > 0) {
                evidenced++;
            }
        }

        var score_value = score.percentage === null ? 'Not yet scored' : Math.round(score.percentage) + '%';
        var score_lines = [];

        if (score.percentage !== null && !score.complete) {
            score_lines.push('Provisional | ' + assessed + ' of ' + in_scope + ' assessed');
        }

        var formal = [];

        if (score.points) {
            formal.push('Points ' + score.points.score + ' of ' + score.points.maximum);
        }

        if (outcome_text(score.outcome) !== '') {
            formal.push(esc(outcome_text(score.outcome)));
        }

        if (formal.length > 0) {
            score_lines.push(formal.join(' · '));
        }

        $('#adash_kpis').html(
            dash_kpi(
                'Progress',
                (in_scope > 0 ? Math.round(assessed / in_scope * 100) : 0) + '%',
                assessed + ' of ' + in_scope + ' assessed',
                ''
            )
            + dash_kpi(
                'Score',
                score_value,
                score_lines.join('<br>'),
                ''
            )
            + dash_kpi(
                'Gaps',
                gaps,
                (counts['Not Implemented'] || 0) + ' not implemented, ' + (counts['Partially Implemented'] || 0) + ' partial',
                gaps > 0 ? 'is-gap' : ''
            )
            + dash_kpi(
                'Evidence',
                (scoped > 0 ? Math.round(evidenced / scoped * 100) : 0) + '%',
                evidenced + ' of ' + scoped + ' with evidence',
                ''
            )
            + dash_kpi(
                'Open POA&amp;M',
                dash_poam.open,
                dash_poam.overdue > 0 ? '<span class="kpi__alert"><i class="fa-regular fa-clock"></i> ' + dash_poam.overdue + ' overdue</span>' : 'none overdue',
                ''
            )
        );

        var families = score.families.slice();
        var html = '';

        for (var f = 0; f < families.length; f++) {

            var fam = families[f];
            var total = 0;
            var bar = '';

            for (var k = 0; k < DASH_PARTS.length; k++) {
                total += fam.counts[DASH_PARTS[k][0]] || 0;
            }

            for (var b = 0; b < DASH_PARTS.length; b++) {
                var n = fam.counts[DASH_PARTS[b][0]] || 0;
                if (n > 0) {
                    bar += '<span class="dash__bar-part ' + DASH_PARTS[b][1] + '" style="width:' + (n / total * 100) + '%" title="' + n + ' ' + result_label(DASH_PARTS[b][0]) + '"></span>';
                }
            }

            html += '<li class="adash__family">'
                + '<span class="adash__family-name" title="' + esc(fam.name) + '">' + esc(fam.name) + '</span>'
                + '<span class="dash__bar adash__family-bar">' + bar + '</span>'
                + '<span class="adash__family-pct">' + (fam.percentage === null ? 'Not yet scored' : Math.round(fam.percentage) + '%') + '</span>'
                + '</li>';
        }

        $('#adash_families').html(html);

        var weakest = families.filter(function (fam) {
            return (fam.counts['Not Implemented'] || 0) + (fam.counts['Partially Implemented'] || 0) > 0;
        }).sort(function (a, b) {
            var gaps_a = (a.counts['Not Implemented'] || 0) + (a.counts['Partially Implemented'] || 0);
            var gaps_b = (b.counts['Not Implemented'] || 0) + (b.counts['Partially Implemented'] || 0);

            return (a.percentage || 0) - (b.percentage || 0) || gaps_b - gaps_a;
        }).slice(0, 5);

        html = '';

        for (var w = 0; w < weakest.length; w++) {
            var open = (weakest[w].counts['Not Implemented'] || 0) + (weakest[w].counts['Partially Implemented'] || 0);
            html += dash_row(esc(weakest[w].name), Math.round(weakest[w].percentage) + '%', open + (open === 1 ? ' gap' : ' gaps'));
        }

        $('#adash_weakest').html(html === '' ? '<li class="adash__empty">No gaps recorded.</li>' : html);
    }

    function load_dashboard_extras() {

        ApiDataSvc.apiCall('post', 'poam_summary', { assessment_id: assessment_id }, function (data) {

            var obj = JSON.parse(data);

            if (!obj.success) {
                return;
            }

            var by = {};
            var open = 0;
            var overdue = 0;
            var html = '';

            for (var i = 0; i < obj.priorities.length; i++) {
                by[obj.priorities[i].priority] = obj.priorities[i];
                open += parseInt(obj.priorities[i].open_count, 10) || 0;
                overdue += parseInt(obj.priorities[i].overdue_count, 10) || 0;
            }

            ['High', 'Medium', 'Low'].forEach(function (level) {
                var row = by[level];
                if (!row) {
                    return;
                }
                var o = parseInt(row.open_count, 10) || 0;
                var c = parseInt(row.closed_count, 10) || 0;
                html += dash_row(level, o + ' open', c + ' closed');
            });

            $('#adash_poam').html(html === '' ? '<li class="adash__empty">No POA&amp;M items recorded yet.</li>' : html);
            dash_poam.open = open;
            dash_poam.overdue = overdue;
            render_dashboard();
        });

        ApiDataSvc.apiCall('post', 'report_center', { assessment_id: assessment_id }, function (data) {

            var obj = JSON.parse(data);
            var n = obj.narratives;

            if (!n || !n.total) {
                $('#adash_report').html('<li class="adash__empty">No narrative sections.</li>');
                return;
            }

            $('#adash_report').html(
                dash_row('Approved', n.approved + ' of ' + n.total, '')
                + dash_row('Drafts to review', n.draft + n.rejected, '')
                + dash_row('Out of date', n.stale, '')
                + dash_row('Not written', n.missing, '')
            );
        });
    }

    $('#tab_dashboard_btn').on('shown.bs.tab', function () {
        render_dashboard();
        load_dashboard_extras();
    });

    load_dashboard_extras();

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

        var titles = {};

        for (var i = 0; i < list.length; i++) {
            if (list[i].control_kind === 'objective') {
                if (!objectives_of[list[i].parent_identifier]) {
                    objectives_of[list[i].parent_identifier] = [];
                }

                objectives_of[list[i].parent_identifier].push(list[i]);
            } else {
                controls.push(list[i]);
            }

            titles[list[i].control_identifier] = list[i].control_title;
        }

        $('#tab_controls').data('titles', titles);
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
                    var first = $('#items_body .item__row').filter(function () {
                        return this.style.display !== 'none';
                    }).first();

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
    var tickers = {
        narratives: null,
        package: null
    };
    var clock_offset = 0;
    var MAX_STEP_ROWS = 6;

    var ACTIVE = ['Queued', 'Running'];
    var LOCKED = ['ref', 'phase', 'kind', 'severity', 'section'];
    var rewriting = {};
    var last_narratives = null;
    var last_ai_configured = false;
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
            appendices: $('#report_appendices .segmented__btn.is-on').attr('data-value') !== '0'
        };
    }

    $('#report_detail').on('click', '.segmented__btn', function () {
        $('#report_detail .segmented__btn').removeClass('is-on');
        $(this).addClass('is-on');
        $('#report_detail_value').text($(this).text());
        modal('report_detail_modal').hide();
    });

    $('#do_detail_open').click(function () {
        modal('report_detail_modal').show();
    });

    $('#do_appendices_open').click(function () {
        modal('report_appendices_modal').show();
    });

    $('#report_appendices').on('click', '.segmented__btn', function () {
        $('#report_appendices .segmented__btn').removeClass('is-on');
        $(this).addClass('is-on');
        $('#report_appendices_value').text($(this).text());
        modal('report_appendices_modal').hide();
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

    $('#tab_reports_btn, #tab_settings_btn').on('shown.bs.tab', function () {

        if (center_loaded) {
            return;
        }

        center_loaded = true;
        load_center();
    });

    function render_state(state) {

        var version = state.report_version;

        if (state.report_status === 'Final' && state.finalized_by_name) {
            version += ' · ' + state.finalized_by_name + (state.finalized_date ? ', ' + state.finalized_date : '');
        }

        $('#do_status_open').prop('disabled', false);
        $('#report_status_value').text(state.report_status);
        $('#report_status_pick .segmented__btn').each(function () {
            $(this).toggleClass('is-on', $(this).attr('data-value') === state.report_status);
        });
        $('#report_version').text('v' + version);
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

    $('#do_status_open').click(function () {
        modal('report_status_modal').show();
    });

    $('#report_status_pick').on('click', '.segmented__btn', function () {

        var report_status = $(this).attr('data-value');

        if (!center || report_status === center.state.report_status) {
            modal('report_status_modal').hide();
            return;
        }

        modal('report_status_modal').hide();
        $('#do_status_open').prop('disabled', true);
        $('#report_status_value').html('<i class="fa-regular fa-spinner-third fa-spin" aria-hidden="true"></i>');

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

        list = list.slice().sort(function (a, b) {
            return (SEVERITY_RANK[b.severity] || 0) - (SEVERITY_RANK[a.severity] || 0);
        });

        for (var i = 0; i < list.length; i++) {
            html += '<li class="reports__issue"><span class="badge ' + (SEVERITY_CLASS[list[i].severity] || 'badge--inactive') + '">' + esc(list[i].severity) + '</span>'
                + '<span class="reports__issue-text">' + esc(list[i].message) + '</span></li>';
        }

        $('#report_readiness_list').html(html);
        $('#report_readiness').prop('hidden', list.length === 0);
    }

    function render_narratives(narratives, ai_configured) {

        last_narratives = narratives;
        last_ai_configured = ai_configured;
        $('#do_rewrite_open').prop('hidden', !ai_configured);
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

    function server_now() {
        return Math.floor(Date.now() / 1000) + clock_offset;
    }

    function job_progress(job) {

        var steps = job.steps || [];
        var total = steps.length || parseInt(job.total_steps, 10) || 0;
        var done = 0;
        var part = 0;
        var step = null;

        for (var i = 0; i < steps.length; i++) {
            if (['Done', 'Skipped', 'Failed'].indexOf(steps[i].state) !== -1) {
                done++;
            } else if (steps[i].state === 'Running' && steps[i].started) {
                step = steps[i];
            }
        }

        if (steps.length === 0) {
            done = parseInt(job.done_steps, 10) || 0;
        }

        var elapsed = step ? Math.max(0, server_now() - step.started) : 0;

        if (step && step.expected) {
            part = Math.min(elapsed / step.expected, 0.95);
        }

        return {
            percent: total > 0 ? (done + part) / total * 100 : 0,
            done: done,
            total: total,
            step: step
        };
    }

    function job_label(job, progress) {

        if (job.job_status === 'Queued' && progress.done === 0) {
            return 'Queued';
        }

        var label = progress.done + ' of ' + progress.total;

        return job.current_step ? label + ' · ' + job.current_step : label;
    }

    function step_status(step) {

        if (step.state === 'Failed') {
            return {
                percent: 100,
                text: 'Failed'
            };
        }

        if (step.state !== 'Running' || !step.started) {
            return {
                percent: 0,
                text: 'Queued'
            };
        }

        var elapsed = Math.max(0, server_now() - step.started);

        return {
            percent: step.expected ? Math.min(elapsed / step.expected, 0.95) * 100 : 0,
            text: 'Writing'
        };
    }

    function draw_steps(type, steps) {

        var html = '';

        for (var i = 0; i < steps.length; i++) {
            if (steps[i].state === 'Done' || steps[i].state === 'Skipped') {
                continue;
            }

            var status = step_status(steps[i]);

            html += '<li class="reports__step' + (steps[i].state === 'Failed' ? ' is-failed' : '') + '">'
                + '<span class="reports__step-name">' + esc(steps[i].label) + '</span>'
                + '<div class="progress"><span class="progress__bar" style="width: ' + Math.round(status.percent) + '%"></span></div>'
                + '<span class="reports__step-status">' + esc(status.text) + '</span>'
                + '</li>';
        }

        $('#' + type + '_steps').html(html);
    }

    function draw_job(type) {

        var job = jobs[type];

        if (!is_active(job)) {
            clearInterval(tickers[type]);
            tickers[type] = null;
            return;
        }

        var steps = job.steps || [];
        var each = type === 'narratives' && steps.length > 0 && steps.length <= MAX_STEP_ROWS;

        $('#' + type + '_overall, #' + type + '_label').prop('hidden', each);
        $('#' + type + '_steps').prop('hidden', !each);

        if (each) {
            draw_steps(type, steps);
            return;
        }

        var progress = job_progress(job);

        $('#' + type + '_bar').css('width', Math.round(progress.percent) + '%');
        $('#' + type + '_label').text(job_label(job, progress));
    }

    function render_job(type, job) {

        var running = is_active(job);

        jobs[type] = job;

        $('#' + type + '_job').prop('hidden', !running);

        if (job && job.now) {
            clock_offset = job.now - Math.floor(Date.now() / 1000);
        }

        draw_job(type);

        if (running && tickers[type] === null) {
            tickers[type] = setInterval(function () {
                draw_job(type);
            }, 1000);
        }

        if (type === 'package') {
            set_loading('#do_package', running);
        }

        if (type === 'narratives') {
            var steps = running && job.steps ? job.steps : [];

            for (var k = 0; k < steps.length; k++) {
                if (steps[k].state === 'Pending' || steps[k].state === 'Running') {
                    rewriting[steps[k].key] = true;
                }
            }

            if ($('#narrative_modal').hasClass('show')) {
                sync_buttons();
            }

            if (last_narratives) {
                render_narratives(last_narratives, last_ai_configured);
            }
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
                modal('report_package_modal').hide();
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

        rewriting = {};
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
                rewriting = {};

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

    function regenerate_section(key, button) {
        start_job('narratives', 'Narratives', {
            mode: 'sections',
            sections: [key],
            revise: 'none'
        }, button);
        rewriting[key] = true;
        load_center();

        if ($('#narrative_modal').hasClass('show')) {
            render_editor();
        }
    }

    $(document).on('click', '#narrative_editor [data-action="regenerate"]', function () {
        regenerate_section($(this).attr('data-key'), this);
    });

    $('#do_narratives_next').click(function () {
        open_review();
    });

    $('#do_rewrite_open').click(function () {
        modal('narrative_modal').hide();
        modal('report_rewrite_modal').show();
    });

    $('#do_narratives_rewrite_all').click(function () {
        modal('report_rewrite_modal').hide();
        start_job('narratives', 'Narratives', {
            mode: 'all'
        }, '#do_narratives_next');
    });

    $('#do_package_open').click(function () {
        modal('report_package_modal').show();
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

        var text = String(key).replace(/_/g, ' ').replace(/\brefs\b/g, 'references').replace(/\bref\b/g, 'reference');

        return text.replace(/\b[a-z]/g, function (letter) {
            return letter.toUpperCase();
        });
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

    function control_title(identifier) {

        var titles = $('#tab_controls').data('titles') || {};

        return titles[identifier] || '';
    }

    function ref_tags(label, value) {

        var list = Array.isArray(value) ? value : [];
        var html = '';

        for (var i = 0; i < list.length; i++) {
            html += '<span class="review__ref" title="' + esc(control_title(list[i])) + '">' + esc(list[i]) + '</span>';
        }

        if (html === '') {
            return '';
        }

        return '<div class="form-group review__field"><label>' + esc(label) + '</label><div class="review__refs">' + html + '</div></div>';
    }

    function object_head(item) {

        var html = '';

        if (typeof item.ref === 'string' && item.ref !== '') {
            html += '<span class="review__object-ref">' + esc(item.ref) + '</span>'
                + '<span class="review__object-title">' + esc(control_title(item.ref)) + '</span>';
        }

        return html + locked_chips(item);
    }

    function locked_chips(item) {

        var html = '';

        for (var i = 0; i < LOCKED.length; i++) {
            if (LOCKED[i] === 'ref') {
                continue;
            }

            if (typeof item[LOCKED[i]] === 'string' && item[LOCKED[i]] !== '') {
                html += '<span class="badge badge--inactive">' + esc(item[LOCKED[i]]) + '</span>';
            }
        }

        return html;
    }

    function render_field(field, content, base) {

        var path = base === '' ? field.path : base + '.' + field.path;
        var value = value_at(content, path);

        if (field.type === 'list' && /(^|_)refs$/.test(field.path)) {
            return ref_tags(field.label, value);
        }

        if (field.type !== 'objects') {
            return textarea(path, field.type, field.label, value);
        }

        var list = Array.isArray(value) ? value : [];
        var html = '';

        for (var i = 0; i < list.length; i++) {

            var item_path = path + '.' + i;
            var subs = field.fields && field.fields.length ? field.fields : infer_fields(list[i], true);
            var chips = object_head(list[i]);
            var inner = '';

            for (var s = 0; s < subs.length; s++) {
                if (LOCKED.indexOf(subs[s].path) !== -1) {
                    continue;
                }

                inner += render_field(subs[s], content, item_path);
            }

            html += '<div class="review__object">'
                + (chips === '' ? '' : '<div class="review__object-head">' + chips + '</div>')
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
        $('#do_narrative_approve').prop('disabled', status === 'Missing' || status === 'Out of date' || (status === 'Approved' && !dirty));
        $('#do_narrative_reject').prop('disabled', status === 'Missing' || status === 'Rejected');
        $('#do_narrative_rewrite')
            .prop('hidden', !center || !center.ai_configured)
            .prop('disabled', !section || !!rewriting[selected_key])
            .html(rewriting[selected_key]
                ? '<i class="fa-regular fa-spinner-third fa-spin"></i> Rewriting Section'
                : '<i class="fa-regular fa-rotate-right"></i> Rewrite Section');
    }

    $('#do_narrative_rewrite').click(function () {

        if (!selected_key) {
            return;
        }

        regenerate_section(selected_key, this);
    });

    function render_editor() {

        var section = section_of(selected_key);
        var html = '';

        dirty = false;

        if (!section) {
            $('#narrative_section_title').text('');
            $('#narrative_section_pill').html('');
            $('#narrative_editor').html('<p class="review__empty">No narrative sections for this assessment.</p>');
            sync_buttons();
            return;
        }

        $('#narrative_section_title').text(section.label);
        $('#narrative_section_pill').html(pill(section));

        if (section.state === 'stale') {
            html += '<div class="review__stale"><p><strong>Out of date.</strong> ' + esc(section.stale_reason || '') + '</p>'
                + (rewriting[section.section_key]
                    ? '<p class="review__stale-busy"><i class="fa-regular fa-spinner-third fa-spin" aria-hidden="true"></i> Rewriting this section now. It takes a few minutes; you can close this window and keep working.</p>'
                    : '<button type="button" class="btn btn--secondary btn--sm" data-action="regenerate" data-key="' + esc(section.section_key) + '"><i class="fa-regular fa-rotate-right"></i> Regenerate This Section</button>')
                + '</div>';
        }

        if (section.state !== 'stale' && rewriting[section.section_key]) {
            html += '<div class="review__stale"><p class="review__stale-busy"><i class="fa-regular fa-spinner-third fa-spin" aria-hidden="true"></i> Rewriting this section now. It takes a few minutes; you can close this window and keep working.</p></div>';
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

    function approve_section(button) {

        if (!dirty) {
            set_status(button, 'Approved');
            return;
        }

        save_section(button, function () {
            set_status(button, 'Approved');
        });
    }

    $('#do_narrative_approve').click(function () {
        approve_section('#do_narrative_approve');
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

    function prepare_report(values, ready, failed, wanted) {

        ApiDataSvc.apiCall('post', 'ready_report_download', values, function (data) {

            var obj = JSON.parse(data);

            if (!obj.success) {
                failed(obj.message);
                return;
            }

            if (obj.url) {
                ready(obj.url);
                return;
            }

            wait_for_report(obj.job_id, ready, failed, wanted);
        }, function () {
            failed('');
        });
    }

    function wait_for_report(job_id, ready, failed, wanted) {

        setTimeout(function () {

            if (!wanted()) {
                return;
            }

            ApiDataSvc.apiCall('post', 'report_job_status', {
                job_id: job_id
            }, function (data) {

                var obj = JSON.parse(data);

                if (!obj.success) {
                    failed(obj.message);
                    return;
                }

                if (obj.job.job_status === 'Completed' && obj.download_url) {
                    ready(obj.download_url);
                    return;
                }

                if (is_active(obj.job)) {
                    wait_for_report(job_id, ready, failed, wanted);
                    return;
                }

                failed(obj.job.last_error || '');
            }, function () {
                wait_for_report(job_id, ready, failed, wanted);
            });
        }, 2000);
    }

    var preview_request = 0;

    $('#tab_reports').on('click', "[data-action='preview_report']", function () {

        var button = this;
        var values = report_values(button, 'pdf');
        var request = ++preview_request;

        values.preview = '1';

        $('#report_preview_modal_title').text($(button).attr('data-name'));
        $('#report_preview_frame').attr('src', 'about:blank').prop('hidden', true);
        $('#report_preview_loading').prop('hidden', false);
        set_loading(button, true);
        modal('report_preview_modal').show();

        prepare_report(values, function (url) {

            if (request !== preview_request) {
                return;
            }

            set_loading(button, false);

            $('#report_preview_frame').prop('hidden', false).one('load', function () {
                $('#report_preview_loading').prop('hidden', true);
            }).attr('src', url);
        }, function (message) {

            set_loading(button, false);

            if (request !== preview_request) {
                return;
            }

            modal('report_preview_modal').hide();
            toastr.error(message || 'The preview could not be prepared. Try again.');
        }, function () {
            return request === preview_request;
        });
    });

    $('#report_preview_modal').on('hidden.bs.modal', function () {
        preview_request++;
        set_loading("#tab_reports [data-action='preview_report']", false);
        $('#report_preview_frame').off('load').attr('src', 'about:blank').prop('hidden', true);
    });

    $('#tab_reports').on('click', "[data-action='download_report']", function () {

        var format = $(this).attr('data-format');
        var values = report_values(this, format);
        var button = $(this).closest('.dropdown').find('[data-bs-toggle]')[0] || this;

        set_loading(button, true);

        var tab = report_tab(format);

        prepare_report(values, function (url) {
            set_loading(button, false);
            show_report_tab(tab, url);
        }, function (message) {
            set_loading(button, false);
            close_report_tab(tab);
            toastr.error(message || 'The report could not be prepared. Try again.');
        }, function () {
            if (tab && tab.closed) {
                set_loading(button, false);
                return false;
            }
            return true;
        });
    });

    var poam = {
        items: [],
        rules: null,
        payload: null,
        evidence: [],
        stale: false
    };
    var POAM_STATUS_CLASS = {
        'Open': 'badge--prospect',
        'In Progress': 'badge--onboarding',
        'Ready for Validation': 'badge--admin',
        'Closed': 'badge--active'
    };
    var POAM_MILESTONE_CLASS = {
        'Not Started': 'badge--inactive',
        'In Progress': 'badge--onboarding',
        'Completed': 'badge--active'
    };

    function poam_badge(value, classes) {
        return '<span class="badge ' + (classes[value] || 'badge--inactive') + '">' + esc(value) + '</span>';
    }

    function poam_date(value) {

        if (!value || String(value).indexOf('0000') === 0) {
            return '';
        }

        var parts = String(value).slice(0, 10).split('-');
        var date = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));

        return date.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });
    }

    function poam_set_empty(selector, text, loading) {
        $(selector).html(loading ? '<i class="fa-regular fa-spinner-third fa-spin" aria-hidden="true"></i> ' + esc(text) : esc(text)).prop('hidden', false);
    }

    function poam_load() {

        poam_set_empty('#poam_empty', 'Loading items…', true);
        $('#poam_table').prop('hidden', true);

        ApiDataSvc.apiCall('post', 'poam_list', { assessment_id: assessment_id }, function (data) {

            var obj = JSON.parse(data);

            if (!obj.success) {
                poam_set_empty('#poam_empty', obj.message, false);
                toastr.error(obj.message);
                return;
            }

            poam.items = obj.items;
            poam.rules = obj.rules;
            poam.stale = false;

            $('#poam_banner').text(obj.rules.banner || '').prop('hidden', !obj.rules.banner);
            $('#poam_counts').text(obj.overview.open + ' open · ' + obj.overview.overdue + ' overdue · ' + obj.overview.unassigned + ' unassigned');

            poam_render();
        }, function () {
            poam_set_empty('#poam_empty', 'The register could not be loaded.', false);
        });
    }

    function poam_visible() {

        var term = $('#poam_search').val().trim().toLowerCase();
        var status = $('#poam_status_filter').val() || '';
        var out = [];

        for (var i = 0; i < poam.items.length; i++) {

            var item = poam.items[i];
            var haystack = [
                item.poam_ref,
                item.source_ref,
                item.source_title,
                item.weakness,
                item.owner_name
            ].join(' ').toLowerCase();

            if (status !== '' && item.poam_status !== status) {
                continue;
            }

            if (term !== '' && haystack.indexOf(term) === -1) {
                continue;
            }

            out.push(item);
        }

        return out;
    }

    function poam_row(item) {

        var owner = item.owner_name ? esc(item.owner_name) : '<span class="poam__muted">Not assigned</span>';
        var scheduled = item.scheduled_completion_date ? '<span class="' + (item.overdue ? 'poam__overdue' : '') + '">' + esc(poam_date(item.scheduled_completion_date)) + '</span>' : '<span class="poam__muted">Not set</span>';

        return '<tr class="rowlink poam__row" tabindex="0" data-id="' + item.id + '">'
            + '<td class="poam__col-id">' + esc(item.poam_ref) + '</td>'
            + '<td class="poam__col-control" title="' + esc(item.source_title) + '">' + esc(item.source_ref) + '</td>'
            + '<td class="poam__clip" title="' + esc(item.weakness) + '">' + esc(item.weakness) + '</td>'
            + '<td class="poam__col-owner poam__clip">' + owner + '</td>'
            + '<td class="poam__col-date">' + scheduled + '</td>'
            + '<td class="poam__col-status">' + poam_badge(item.poam_status, POAM_STATUS_CLASS) + '</td>'
            + '<td class="poam__col-priority">' + esc(item.priority) + '</td>'
            + '</tr>';
    }

    function poam_render() {

        var rows = poam_visible();
        var html = '';

        for (var i = 0; i < rows.length; i++) {
            html += poam_row(rows[i]);
        }

        $('#poam_list').html(html);
        $('#poam_table').prop('hidden', rows.length === 0);

        if (rows.length > 0) {
            $('#poam_empty').prop('hidden', true);
            return;
        }

        poam_set_empty('#poam_empty', poam.items.length === 0 ? 'No items in the register.' : 'No items match.', false);
    }

    $('#do_poam_open').click(function () {
        $('#poam_search').val('');
        $('#poam_status_filter').val('');
        modal('poam_modal').show();
        poam_load();
    });

    $('#poam_search').on('input', function () {
        poam_render();
    });

    $('#poam_status_filter').on('change', function () {
        poam_render();
    });

    $('#poam_list').on('click', '.poam__row', function () {
        poam_open_item($(this).attr('data-id'));
    });

    $('#poam_list').on('keydown', '.poam__row', function (event) {

        if (event.key !== 'Enter') {
            return;
        }

        event.preventDefault();
        poam_open_item($(this).attr('data-id'));
    });

    function poam_open_item(item_id) {

        var row = $('#poam_list .poam__row[data-id="' + item_id + '"]');

        row.addClass('is-loading');

        ApiDataSvc.apiCall('post', 'poam_item_load', { item_id: item_id }, function (data) {

            var obj = JSON.parse(data);

            row.removeClass('is-loading');

            if (!obj.success) {
                toastr.error(obj.message);
                return;
            }

            if (!$('#poam_item_modal').hasClass('show')) {
                modal('poam_item_modal').show();
            }

            poam_fill(obj);
        }, function () {
            row.removeClass('is-loading');
        });
    }

    function poam_reload_item() {

        ApiDataSvc.apiCall('post', 'poam_item_load', { item_id: poam.payload.item.id }, function (data) {

            var obj = JSON.parse(data);

            if (!obj.success) {
                toastr.error(obj.message);
                return;
            }

            poam_fill(obj);
        });
    }

    function poam_is_fedramp() {
        return String(poam.payload.item.framework_key || '').indexOf('fedramp-') === 0;
    }

    function poam_is_closed() {
        return poam.payload.item.poam_status === 'Closed';
    }

    function poam_fill(obj) {

        var item = obj.item;

        poam.payload = obj;
        poam.stale = true;

        $('#poam_item_modal_title').text(item.poam_ref);
        $('#poam_item_control').text(item.source_ref);
        $('#poam_item_title').text(item.source_title);
        $('#poam_item_status').html(poam_badge(item.poam_status, POAM_STATUS_CLASS));
        $('#poam_item_priority').text(item.priority !== '' ? 'Priority: ' + item.priority_label : '').prop('hidden', item.priority === '');
        $('#poam_item_eligibility').text(item.eligibility_label).prop('hidden', item.eligibility_label === '');

        $('#poam_weakness').val(item.weakness_override || '').attr('placeholder', item.source_weakness || '');
        $('#poam_action').val(item.action_override || '').attr('placeholder', item.source_action || '');
        $('#poam_owner').val(item.owner_name || '');
        $('#poam_resources').val(item.resources || '');
        $('#poam_detection').val(item.detection_date || '');
        $('#poam_scheduled').val(item.scheduled_completion_date || '');
        $('#poam_status').val(poam_is_closed() ? 'Open' : item.poam_status);
        $('#poam_comments').val(item.comments || '');

        $('#poam_fedramp').prop('hidden', !poam_is_fedramp());
        $('#poam_risk').val(item.risk_rating || '');
        $('#poam_vendor_dependency').val(parseInt(item.vendor_dependency, 10) === 1 ? '1' : '0');
        $('#poam_vendor_product').val(item.vendor_product || '');
        $('#poam_vendor_checkin').val(item.vendor_checkin_date || '');
        $('#poam_deviation_type').val(item.deviation_type || '');
        $('#poam_deviation_rationale').val(item.deviation_rationale || '');

        $('#poam_item_fields .is-invalid').removeClass('is-invalid');
        $('#poam_item_fields').find('input, textarea, select').prop('disabled', poam_is_closed());
        $('#poam_status').closest('.form-group').prop('hidden', poam_is_closed());

        $('#do_poam_save').prop('hidden', poam_is_closed());
        $('#poam_item_dismiss').text(poam_is_closed() ? 'Close' : 'Cancel');
        $('#do_poam_milestone_add').prop('hidden', poam_is_closed());
        $('#do_poam_evidence_open').prop('hidden', poam_is_closed());
        $('#do_poam_close_open').prop('hidden', !obj.can_close);
        $('#do_poam_reopen_open').prop('hidden', !obj.can_reopen);

        poam_render_milestones();
        poam_render_evidence();
        poam_render_history();
    }

    function poam_value(selector) {
        return String($(selector).val() || '').trim();
    }

    $('#do_poam_save').click(function () {

        var button = this;
        var obj = {
            item_id: poam.payload.item.id,
            weakness_override: poam_value('#poam_weakness'),
            action_override: poam_value('#poam_action'),
            owner_name: poam_value('#poam_owner'),
            resources: poam_value('#poam_resources'),
            detection_date: poam_value('#poam_detection'),
            scheduled_completion_date: poam_value('#poam_scheduled'),
            poam_status: poam_value('#poam_status'),
            comments: poam_value('#poam_comments')
        };

        if (poam_is_fedramp()) {
            obj.risk_rating = poam_value('#poam_risk');
            obj.vendor_dependency = poam_value('#poam_vendor_dependency');
            obj.vendor_product = poam_value('#poam_vendor_product');
            obj.vendor_checkin_date = poam_value('#poam_vendor_checkin');
            obj.deviation_type = poam_value('#poam_deviation_type');
            obj.deviation_rationale = poam_value('#poam_deviation_rationale');
        }

        $('#poam_scheduled').removeClass('is-invalid');

        if (obj.detection_date !== '' && obj.scheduled_completion_date !== '' && obj.scheduled_completion_date < obj.detection_date) {
            $('#poam_scheduled').addClass('is-invalid');
            toastr.error('The scheduled completion date is before the detection date');
            return;
        }

        set_loading(button, true);

        ApiDataSvc.apiCall('post', 'poam_item_save', obj, function (data) {

            var response = JSON.parse(data);

            set_loading(button, false);

            if (!response.success) {
                toastr.error(response.message);
                return;
            }

            toastr.success(response.message);
            poam_reload_item();
        }, function () {
            set_loading(button, false);
        });
    });

    function poam_milestone_view(milestone) {

        var actions = poam_is_closed() ? '' : '<button type="button" class="btn btn--icon btn--sm" data-action="ms_edit" data-id="' + milestone.id + '" aria-label="Edit milestone" title="Edit"><i class="fa-regular fa-pen"></i></button>'
            + '<button type="button" class="btn btn--icon btn--sm" data-action="ms_delete" data-id="' + milestone.id + '" aria-label="Delete milestone" title="Delete"><i class="fa-regular fa-trash"></i></button>';

        return '<tr class="poam__ms" data-id="' + milestone.id + '">'
            + '<td class="poam__ms-text">' + esc(milestone.milestone_text) + '</td>'
            + '<td class="poam__ms-date">' + (milestone.due_date ? esc(poam_date(milestone.due_date)) : '<span class="poam__muted">Not set</span>') + '</td>'
            + '<td class="poam__ms-date">' + esc(poam_date(milestone.completed_date)) + '</td>'
            + '<td class="poam__ms-status">' + poam_badge(milestone.milestone_status, POAM_MILESTONE_CLASS) + '</td>'
            + '<td class="poam__ms-actions">' + actions + '</td>'
            + '</tr>';
    }

    function poam_milestone_form(milestone) {

        var statuses = poam.payload.milestone_statuses;
        var options = '';

        for (var i = 0; i < statuses.length; i++) {
            options += '<option value="' + esc(statuses[i]) + '"' + (statuses[i] === milestone.milestone_status ? ' selected' : '') + '>' + esc(statuses[i]) + '</option>';
        }

        return '<tr class="poam__ms poam__ms--edit" data-id="' + milestone.id + '">'
            + '<td class="poam__ms-text"><input type="text" class="form-control" data-field="milestone_text" aria-label="Milestone" value="' + esc(milestone.milestone_text) + '"></td>'
            + '<td class="poam__ms-date"><input type="date" class="form-control" data-field="due_date" aria-label="Due date" placeholder="Due" value="' + esc(milestone.due_date || '') + '"></td>'
            + '<td class="poam__ms-date"><input type="date" class="form-control" data-field="completed_date" aria-label="Completed date" placeholder="Completed" max="' + esc(new Date().toISOString().slice(0, 10)) + '" value="' + esc(milestone.completed_date || '') + '"></td>'
            + '<td class="poam__ms-status"><select class="form-control" data-field="milestone_status" data-style="form-control" aria-label="Status">' + options + '</select></td>'
            + '<td class="poam__ms-actions">'
            + '<button type="button" class="btn btn--icon btn--sm" data-action="ms_save" data-id="' + milestone.id + '" aria-label="Save milestone" title="Save"><i class="fa-regular fa-check"></i></button>'
            + '<button type="button" class="btn btn--icon btn--sm" data-action="ms_cancel" aria-label="Cancel" title="Cancel"><i class="fa-regular fa-xmark"></i></button>'
            + '</td>'
            + '</tr>';
    }

    function poam_render_milestones() {

        var list = poam.payload.milestones;
        var html = '';

        for (var i = 0; i < list.length; i++) {
            html += poam_milestone_view(list[i]);
        }

        $('#poam_milestones').html(html);
        $('#poam_milestones_table').prop('hidden', list.length === 0);
        $('#poam_milestones_empty').prop('hidden', list.length > 0);
    }

    function poam_find_milestone(milestone_id) {

        var list = poam.payload.milestones;

        for (var i = 0; i < list.length; i++) {
            if (parseInt(list[i].id, 10) === parseInt(milestone_id, 10)) {
                return list[i];
            }
        }

        return null;
    }

    function poam_edit_row(row) {
        date_pickers(row);
        row.find('[data-field="milestone_text"]').trigger('focus');
    }

    $('#do_poam_milestone_add').click(function () {

        poam_render_milestones();

        var row = $(poam_milestone_form({
            id: 0,
            milestone_text: '',
            due_date: '',
            completed_date: '',
            milestone_status: 'Not Started'
        }));

        $('#poam_milestones').append(row);
        $('#poam_milestones_table').prop('hidden', false);
        $('#poam_milestones_empty').prop('hidden', true);
        poam_edit_row(row);
    });

    $('#poam_milestones').on('click', '[data-action="ms_edit"]', function () {

        poam_render_milestones();

        var milestone = poam_find_milestone($(this).attr('data-id'));
        var row = $(poam_milestone_form(milestone));

        $('#poam_milestones .poam__ms[data-id="' + milestone.id + '"]').replaceWith(row);
        poam_edit_row(row);
    });

    $('#poam_milestones').on('click', '[data-action="ms_cancel"]', function () {
        poam_render_milestones();
    });

    $('#poam_milestones').on('click', '[data-action="ms_save"]', function () {

        var button = this;
        var row = $(this).closest('.poam__ms');
        var obj = {
            item_id: poam.payload.item.id,
            milestone_id: $(this).attr('data-id'),
            milestone_text: String(row.find('[data-field="milestone_text"]').val() || '').trim(),
            due_date: String(row.find('[data-field="due_date"]').val() || '').trim(),
            completed_date: String(row.find('[data-field="completed_date"]').val() || '').trim(),
            milestone_status: String(row.find('select[data-field="milestone_status"]').val() || '').trim()
        };

        row.find('[data-field="milestone_text"]').removeClass('is-invalid');

        if (obj.milestone_text === '') {
            row.find('[data-field="milestone_text"]').addClass('is-invalid');
            toastr.error('Describe the milestone');
            return;
        }

        set_loading(button, true);

        ApiDataSvc.apiCall('post', 'poam_milestone_save', obj, function (data) {

            var response = JSON.parse(data);

            set_loading(button, false);

            if (!response.success) {
                toastr.error(response.message);
                return;
            }

            toastr.success(response.message);
            poam_reload_item();
        }, function () {
            set_loading(button, false);
        });
    });

    $('#poam_milestones').on('click', '[data-action="ms_delete"]', function () {

        var button = this;

        set_loading(button, true);

        ApiDataSvc.apiCall('post', 'poam_milestone_delete', { milestone_id: $(this).attr('data-id') }, function (data) {

            var response = JSON.parse(data);

            set_loading(button, false);

            if (!response.success) {
                toastr.error(response.message);
                return;
            }

            toastr.success(response.message);
            poam_reload_item();
        }, function () {
            set_loading(button, false);
        });
    });

    function poam_render_evidence() {

        var list = poam.payload.evidence;
        var html = '';

        for (var i = 0; i < list.length; i++) {
            html += '<li class="evidence__item">'
                + '<div class="poam__ev"><span class="poam__ev-title">' + esc(list[i].evidence_title) + '</span><span class="poam__ev-file">' + esc(list[i].file_name) + '</span></div>'
                + '<span class="badge ' + (list[i].evidence_role === 'Closure' ? 'badge--active' : 'badge--prospect') + '">' + esc(list[i].evidence_role) + '</span>'
                + (poam_is_closed() ? '' : '<button type="button" class="btn btn--secondary btn--sm" data-action="ev_unlink" data-id="' + list[i].id + '">Unlink</button>')
                + '</li>';
        }

        $('#poam_evidence').html(html).prop('hidden', list.length === 0);
        $('#poam_evidence_empty').prop('hidden', list.length > 0);
    }

    $('#poam_evidence').on('click', '[data-action="ev_unlink"]', function () {

        var button = this;

        set_loading(button, true);

        ApiDataSvc.apiCall('post', 'poam_evidence_unlink', { link_id: $(this).attr('data-id') }, function (data) {

            var response = JSON.parse(data);

            set_loading(button, false);

            if (!response.success) {
                toastr.error(response.message);
                return;
            }

            toastr.success(response.message);
            poam_reload_item();
        }, function () {
            set_loading(button, false);
        });
    });

    function poam_linked_ids() {

        var out = [];

        for (var i = 0; i < poam.payload.evidence.length; i++) {
            out.push(String(poam.payload.evidence[i].evidence_id));
        }

        return out;
    }

    function poam_render_pick() {

        var term = $('#poam_evidence_search').val().trim().toLowerCase();
        var linked = poam_linked_ids();
        var html = '';
        var shown = 0;

        for (var i = 0; i < poam.evidence.length; i++) {

            var row = poam.evidence[i];

            if (term !== '' && (row.evidence_title + ' ' + row.file_name).toLowerCase().indexOf(term) === -1) {
                continue;
            }

            shown++;
            html += '<tr class="vault__row">'
                + '<td class="vault__cell-name"><span class="vault__line">'
                + '<span class="vault__title" title="' + esc(row.evidence_title) + '">' + esc(row.evidence_title) + '</span>'
                + '<span class="vault__file" title="' + esc(row.file_name) + '">' + esc(row.file_name) + '</span>'
                + '</span></td>'
                + '<td class="vault__col-date">' + esc(row.date_created_display) + '</td>'
                + '<td class="pick__col-attach">' + (linked.indexOf(String(row.id)) !== -1
                    ? '<span class="badge badge--active">Linked</span>'
                    : '<button type="button" class="btn btn--secondary btn--sm" data-action="ev_link" data-id="' + row.id + '">Link</button>')
                + '</td></tr>';
        }

        $('#poam_pick_list').html(html);
        $('#poam_pick_table').prop('hidden', shown === 0);

        if (shown > 0) {
            $('#poam_pick_empty').prop('hidden', true);
            return;
        }

        poam_set_empty('#poam_pick_empty', poam.evidence.length === 0 ? 'The client has no evidence yet.' : 'No files match.', false);
    }

    $('#do_poam_evidence_open').click(function () {

        $('#poam_evidence_search').val('');
        $('#poam_evidence_role').val('Progress');
        $('#poam_pick_table').prop('hidden', true);
        poam_set_empty('#poam_pick_empty', 'Loading evidence…', true);
        poam.evidence = [];

        modal('poam_evidence_modal').show();

        ApiDataSvc.apiCall('post', 'load_evidence', { client_id: poam.payload.item.client_id }, function (data) {
            poam.evidence = JSON.parse(data);
            poam_render_pick();
        }, function () {
            poam_set_empty('#poam_pick_empty', 'The evidence could not be loaded.', false);
        });
    });

    $('#poam_evidence_search').on('input', function () {
        poam_render_pick();
    });

    $('#poam_pick_list').on('click', '[data-action="ev_link"]', function () {

        var button = this;
        var obj = {
            item_id: poam.payload.item.id,
            evidence_id: $(this).attr('data-id'),
            evidence_role: $('#poam_evidence_role').val()
        };

        set_loading(button, true);

        ApiDataSvc.apiCall('post', 'poam_evidence_link', obj, function (data) {

            var response = JSON.parse(data);

            set_loading(button, false);

            if (!response.success) {
                toastr.error(response.message);
                return;
            }

            toastr.success(response.message);
            modal('poam_evidence_modal').hide();
            poam_reload_item();
        }, function () {
            set_loading(button, false);
        });
    });

    function poam_history_text(row) {

        var label = String(row.field_label || '');
        var change = label.charAt(0).toUpperCase() + label.slice(1);

        if (row.new_value !== null && row.new_value !== '') {
            change += ': ' + (row.old_value !== null && row.old_value !== '' ? row.old_value + ' → ' : '') + row.new_value;
        }

        return change;
    }

    function poam_render_history() {

        var list = poam.payload.history.slice().reverse();
        var html = '';

        for (var i = 0; i < list.length; i++) {
            html += '<li class="poam__history-row">'
                + '<span class="poam__history-when">' + esc(poam_date(list[i].date_created)) + (list[i].changed_by_name ? ' · ' + esc(list[i].changed_by_name) : '') + ' · ' + esc(list[i].change_source) + '</span>'
                + '<span class="poam__history-what">' + esc(poam_history_text(list[i])) + '</span>'
                + (list[i].change_note ? '<span class="poam__history-note">' + esc(list[i].change_note) + '</span>' : '')
                + '</li>';
        }

        $('#poam_history').html(html === '' ? '<li class="poam__none">No changes recorded.</li>' : html);
        $('#poam_history_count').text(list.length);
    }

    $('#poam_item_modal').on('hidden.bs.modal', function () {

        $('#poam_history_wrap').removeClass('show');
        $('#poam_history_toggle').addClass('collapsed').attr('aria-expanded', 'false');

        if (poam.stale && $('#poam_modal').hasClass('show')) {
            poam_load();
        }
    });

    function poam_closure_evidence() {

        var total = 0;

        for (var i = 0; i < poam.payload.evidence.length; i++) {
            if (poam.payload.evidence[i].evidence_role === 'Closure') {
                total++;
            }
        }

        return total;
    }

    $('#do_poam_close_open').click(function () {

        var result = poam.payload.control_result;

        $('#poam_closure_note').val('').removeClass('is-invalid');
        $('#poam_closure_reason').val('').removeClass('is-invalid');
        $('#poam_override_ack').prop('checked', false).removeClass('is-invalid');
        $('#poam_closure_reason_group').prop('hidden', poam_closure_evidence() > 0);
        $('#poam_override_group').prop('hidden', result === 'Implemented');
        $('#poam_override_label').text('Close although the assessment records this control as ' + (result !== '' ? result : 'not assessed'));

        modal('poam_close_modal').show();
    });

    $('#do_poam_close').click(function () {

        var button = this;
        var obj = {
            item_id: poam.payload.item.id,
            closure_note: $('#poam_closure_note').val().trim(),
            closure_reason: $('#poam_closure_reason_group').prop('hidden') ? '' : $('#poam_closure_reason').val().trim(),
            override_ack: $('#poam_override_ack').is(':checked') ? '1' : '0'
        };

        $('#poam_closure_note, #poam_override_ack').removeClass('is-invalid');

        if (obj.closure_note === '') {
            $('#poam_closure_note').addClass('is-invalid');
            toastr.error('Enter a closure note');
            return;
        }

        if (!$('#poam_override_group').prop('hidden') && obj.override_ack !== '1') {
            $('#poam_override_ack').addClass('is-invalid');
            toastr.error('Acknowledge the override to close this item');
            return;
        }

        set_loading(button, true);

        ApiDataSvc.apiCall('post', 'poam_item_close', obj, function (data) {

            var response = JSON.parse(data);

            set_loading(button, false);

            if (!response.success) {
                toastr.error(response.message);
                return;
            }

            toastr.success(response.message);
            modal('poam_close_modal').hide();
            poam_reload_item();
        }, function () {
            set_loading(button, false);
        });
    });

    $('#do_poam_reopen_open').click(function () {
        $('#poam_reopen_note').val('').removeClass('is-invalid');
        modal('poam_reopen_modal').show();
    });

    $('#do_poam_reopen').click(function () {

        var button = this;
        var obj = {
            item_id: poam.payload.item.id,
            reopen_note: $('#poam_reopen_note').val().trim()
        };

        $('#poam_reopen_note').removeClass('is-invalid');

        if (obj.reopen_note === '') {
            $('#poam_reopen_note').addClass('is-invalid');
            toastr.error('Say why this item is being reopened');
            return;
        }

        set_loading(button, true);

        ApiDataSvc.apiCall('post', 'poam_item_reopen', obj, function (data) {

            var response = JSON.parse(data);

            set_loading(button, false);

            if (!response.success) {
                toastr.error(response.message);
                return;
            }

            toastr.success(response.message);
            modal('poam_reopen_modal').hide();
            poam_open_item(response.item_id);
        }, function () {
            set_loading(button, false);
        });
    });

    $('#do_poam_clock_open').click(function () {
        modal('poam_clock_modal').show();
    });

    $('#do_poam_clock_save').click(function () {

        var button = this;
        var input = $('#poam_clock_date')[0];
        var obj = {
            assessment_id: assessment_id,
            poam_clock_date: String($('#poam_clock_date').val() || '').trim()
        };

        set_loading(button, true);

        ApiDataSvc.apiCall('post', 'poam_clock_save', obj, function (data) {

            var response = JSON.parse(data);

            set_loading(button, false);

            if (!response.success) {
                toastr.error(response.message);
                return;
            }

            toastr.success(response.message);
            $('#poam_clock_value').text(obj.poam_clock_date === '' ? 'Not set' : (input._flatpickr ? input._flatpickr.altInput.value : poam_date(obj.poam_clock_date)));
            modal('poam_clock_modal').hide();
        }, function () {
            set_loading(button, false);
        });
    });

});
</script>
