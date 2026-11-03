<div class="page page--assessment">
    <a class="page__back" href="/projects/detail/id/<?php echo (int) $this->assessment['project_id']; ?>"><i class="fa-regular fa-arrow-left"></i> Back to <?php echo htmlspecialchars($this->assessment['project_name'], ENT_QUOTES, 'UTF-8'); ?></a>

    <div class="page__head assess__head">
        <div class="assess__heading">
            <div class="assess__title-row">
                <h1 class="page__title"><?php echo htmlspecialchars($this->assessment['assessment_name'], ENT_QUOTES, 'UTF-8'); ?></h1>
                <span class="badge" id="status_badge"><?php echo htmlspecialchars($this->assessment['assessment_status'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>
        <div class="page__actions assess__actions-bar">
            <a class="btn btn--secondary" href="/projects/evidence/id/<?php echo (int) $this->assessment['project_id']; ?>/from/assessment/ref/<?php echo (int) $this->assessment['id']; ?>"><i class="fa-regular fa-folder-open"></i> Evidence Vault</a>
            <button type="button" class="btn btn--secondary" id="do_details_open"><i class="fa-regular fa-sliders"></i> Details</button>
            <button type="button" class="btn btn--secondary" id="do_narrative_open"><i class="fa-regular fa-pen-line"></i> Narrative</button>
            <?php if ($this->features['governing_document'] !== null) { ?>
            <a class="btn btn--secondary" href="/projects/document/id/<?php echo (int) $this->assessment['id']; ?>"><i class="fa-regular fa-file-lines"></i> <?php echo htmlspecialchars(ucfirst($this->features['governing_document']['short']), ENT_QUOTES, 'UTF-8'); ?></a>
            <?php } ?>
            <button type="button" class="btn btn--secondary" id="do_reports_open"><i class="fa-regular fa-file-chart-column"></i> Reports</button>
            <button type="button" class="btn btn--primary" id="do_complete"><i class="fa-regular fa-circle-check"></i> Mark Complete</button>
            <span class="dropdown">
                <button type="button" class="btn btn--secondary assess__more" data-bs-toggle="dropdown" aria-expanded="false" aria-label="More actions"><i class="fa-regular fa-ellipsis"></i></button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><button type="button" class="dropdown-item dropdown-item--danger" id="do_delete_open"><i class="fa-regular fa-trash"></i> Delete assessment</button></li>
                </ul>
            </span>
        </div>
    </div>

    <div class="notice notice--warn" id="mismatch_notice" role="status" hidden></div>

    <div class="panel record__panel">
        <div class="panel__body">
            <div class="rollup__score" id="score_row" hidden>
                <span class="rollup__score-value" id="score_value"></span>
                <span class="rollup__score-outcome" id="score_outcome"></span>
                <button type="button" class="btn btn--tertiary btn--sm" id="do_checks_open" hidden></button>
            </div>
            <div class="rollup" id="rollup"></div>
            <div class="rollup__progress">
                <div class="progress progress--lg"><div class="progress__bar" id="rollup_bar" style="width:0%"></div></div>
                <span class="progress__label" id="rollup_label">&nbsp;</span>
            </div>
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
                        <option value="Partially Implemented">Partially Implemented</option>
                        <option value="Not Implemented">Not Implemented</option>
                        <option value="Not Applicable">Not Applicable</option>
                    </select>
                </div>
                <div class="roster__filter">
                    <select id="evidence_filter" class="form-select">
                        <option value="">Any</option>
                        <option value="yes">Has evidence</option>
                        <option value="no">No evidence</option>
                    </select>
                </div>
            </div>
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
                <p class="controls__empty" id="items_empty">Loading items&hellip;</p>
            </div>

            <section class="workbench__detail" id="detail" aria-label="Control detail">
                <p class="workbench__empty" id="detail_empty">Select a control to assess it.</p>

                <div class="workbench__form" id="detail_form" hidden>
                    <div class="workbench__scroll">
                        <div class="assess__control-head workbench__head">
                            <p class="assess__eyebrow"><span class="assess__id" id="item_identifier"></span> <span class="assess__sep" aria-hidden="true">&middot;</span> <span class="assess__family" id="item_family"></span></p>
                            <h3 class="assess__title" id="item_title"></h3>
                            <p class="assess__text workbench__requirement" id="item_description"></p>
                            <details class="workbench__guidance" id="item_guidance_wrap">
                                <summary>Assessment guidance</summary>
                                <p class="assess__text" id="item_guidance"></p>
                            </details>
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
                                <label id="objectives_label">Objectives</label>
                                <ul class="objectives" id="item_objectives"></ul>
                            </div>

                            <div class="form-group mb-4">
                                <label id="item_result_label">Result</label>
                                <div class="segmented segmented--wrap" id="item_result" role="group" aria-labelledby="item_result_label">
                                    <button type="button" class="segmented__btn" data-value="Not Assessed" aria-pressed="false">Not Assessed</button>
                                    <button type="button" class="segmented__btn" data-value="Implemented" aria-pressed="false">Implemented</button>
                                    <button type="button" class="segmented__btn" data-value="Partially Implemented" aria-pressed="false">Partially Implemented</button>
                                    <button type="button" class="segmented__btn" data-value="Not Implemented" aria-pressed="false">Not Implemented</button>
                                    <button type="button" class="segmented__btn" data-value="Not Applicable" aria-pressed="false">Not Applicable</button>
                                </div>
                                <p class="field__help" id="result_help" hidden></p>
                            </div>

                            <div class="form-check mb-4" id="partial_row">
                                <input class="form-check-input" type="checkbox" id="item_partial_credit">
                                <label class="form-check-label" for="item_partial_credit" id="partial_label">Partially effective</label>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-12 form-group">
                                    <label for="item_notes">Observation</label>
                                    <textarea class="form-control" id="item_notes" rows="3" placeholder="What was examined, who was interviewed, what was observed."></textarea>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-12 form-group">
                                    <label for="item_implementation">Implementation description</label>
                                    <textarea class="form-control" id="item_implementation" rows="3" placeholder="How the organization meets this today."></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="workbench__section" id="finding_section">
                            <h3 class="record__group-title">Finding</h3>
                            <div class="row mb-4">
                                <div class="col-md-12 form-group">
                                    <label for="item_gap">Gap</label>
                                    <textarea class="form-control" id="item_gap" rows="2" placeholder="What is missing or not working."></textarea>
                                </div>
                            </div>
                            <div class="row mb-4">
                                <div class="col-md-8 form-group">
                                    <label for="item_impact">Business impact</label>
                                    <textarea class="form-control" id="item_impact" rows="2" placeholder="What this exposes the business to."></textarea>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label for="item_risk">Risk</label>
                                    <select class="form-control" id="item_risk">
                                        <option value="">Not rated</option>
                                        <option value="Low">Low</option>
                                        <option value="Moderate">Moderate</option>
                                        <option value="High">High</option>
                                        <option value="Critical">Critical</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row mb-4">
                                <div class="col-md-12 form-group">
                                    <label for="item_recommendation">Recommendation</label>
                                    <textarea class="form-control" id="item_recommendation" rows="2" placeholder="What to do to close the gap."></textarea>
                                </div>
                            </div>
                            <div class="row mb-4">
                                <div class="col-md-8 form-group">
                                    <label for="item_owner">Owner</label>
                                    <input type="text" class="form-control" id="item_owner" placeholder="Person or role responsible">
                                </div>
                                <div class="col-md-4 form-group">
                                    <label for="item_target">Target date</label>
                                    <input type="date" class="form-control" id="item_target">
                                </div>
                            </div>
                            <div class="form-group mb-4">
                                <label>Milestones</label>
                                <div id="item_milestones"></div>
                                <button type="button" class="btn btn--secondary btn--sm" id="do_milestone_add"><i class="fa-regular fa-plus"></i> Add Milestone</button>
                            </div>
                        </div>

                        <div class="workbench__section">
                            <h3 class="record__group-title">Evidence</h3>
                            <ul class="evidence__list" id="item_evidence"></ul>
                            <div class="assess__actions">
                                <button type="button" class="btn btn--secondary btn--sm" id="do_attach_open"><i class="fa-regular fa-link"></i> Attach from Vault</button>
                                <button type="button" class="btn btn--secondary btn--sm" id="do_upload_open"><i class="fa-regular fa-arrow-up-from-bracket"></i> Upload New</button>
                            </div>
                        </div>
                    </div>
                    <div class="workbench__foot">
                        <button type="button" id="do_item_save" class="btn btn--primary">Save</button>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" id="details_modal" tabindex="-1" aria-labelledby="details_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="details_modal_title">Assessment Details</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-4">
                    <div class="col-md-4 form-group">
                        <label for="details_type">Assessment type</label>
                        <select class="form-control" id="details_type">
                            <option value="Baseline">Baseline</option>
                            <option value="Interim">Interim</option>
                            <option value="Final">Final</option>
                        </select>
                    </div>
                    <div class="col-md-8 form-group" id="baseline_group">
                        <label for="details_baseline">Baseline to compare against</label>
                        <select class="form-control" id="details_baseline"></select>
                    </div>
                </div>
                <div class="form-check mb-4" id="no_baseline_row">
                    <input class="form-check-input" type="checkbox" id="details_no_baseline">
                    <label class="form-check-label" for="details_no_baseline">There is no baseline for this assessment</label>
                </div>
                <div class="row mb-4">
                    <div class="col-md-12 form-group">
                        <label for="details_scope">Scope</label>
                        <textarea class="form-control" id="details_scope" rows="3" placeholder="Systems, locations, business units and data in scope."><?php echo htmlspecialchars((string) $this->assessment['assessment_scope'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                </div>
                <div class="row mb-4">
                    <div class="col-md-12 form-group">
                        <label for="details_method">Method</label>
                        <textarea class="form-control" id="details_method" rows="3" placeholder="Interviews, document review, configuration testing, sampling."><?php echo htmlspecialchars((string) $this->assessment['assessment_method'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                </div>
                <?php if (count($this->features['tiers']) > 0) { ?>
                <div class="row mb-4">
                    <div class="col-md-3 form-group">
                        <label for="details_tier_governance">Governance tier</label>
                        <select class="form-control details__tier" id="details_tier_governance" data-current="<?php echo (int) $this->assessment['tier_governance']; ?>"></select>
                    </div>
                    <div class="col-md-3 form-group">
                        <label for="details_target_governance">Target</label>
                        <select class="form-control details__tier" id="details_target_governance" data-current="<?php echo (int) $this->assessment['target_tier_governance']; ?>"></select>
                    </div>
                    <div class="col-md-3 form-group">
                        <label for="details_tier_management">Management tier</label>
                        <select class="form-control details__tier" id="details_tier_management" data-current="<?php echo (int) $this->assessment['tier_management']; ?>"></select>
                    </div>
                    <div class="col-md-3 form-group">
                        <label for="details_target_management">Target</label>
                        <select class="form-control details__tier" id="details_target_management" data-current="<?php echo (int) $this->assessment['target_tier_management']; ?>"></select>
                    </div>
                </div>
                <?php } ?>
                <?php if (count($this->features['categories']) > 0) { ?>
                <div class="form-group mb-4">
                    <label>Categories in scope, in addition to the common criteria</label>
                    <div id="details_categories">
                        <?php foreach ($this->features['categories'] as $i => $category) { ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="category_<?php echo (int) $i; ?>" value="<?php echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?>"<?php echo in_array($category, explode(',', (string) $this->assessment['scope_categories']), true) ? ' checked' : ''; ?>>
                            <label class="form-check-label" for="category_<?php echo (int) $i; ?>"><?php echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?></label>
                        </div>
                        <?php } ?>
                    </div>
                </div>
                <?php } ?>
                <?php if ($this->features['ssi_field'] !== null) { ?>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="details_ssi"<?php echo (int) $this->assessment['ssi_reviewed'] === 1 ? ' checked' : ''; ?>>
                    <label class="form-check-label" for="details_ssi"><?php echo htmlspecialchars($this->features['ssi_field']['label'], ENT_QUOTES, 'UTF-8'); ?></label>
                </div>
                <div class="row mb-4" id="ssi_note_row">
                    <div class="col-md-12 form-group">
                        <label for="details_ssi_note">Review note</label>
                        <textarea class="form-control" id="details_ssi_note" rows="2" placeholder="Who reviewed the SSI requirements, when, and how they were handled."><?php echo htmlspecialchars((string) $this->assessment['ssi_note'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                </div>
                <?php } ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="do_details_save" class="btn btn--primary">Save</button>
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

<div class="modal fade" data-bs-backdrop="static" id="narrative_modal" tabindex="-1" aria-labelledby="narrative_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="narrative_modal_title">Consultant Narrative</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="import__hint">Written by the consultant. These words appear in the reports exactly as entered.</p>
                <div class="row mb-4">
                    <div class="col-md-12 form-group">
                        <label for="narrative_posture">Overall security posture</label>
                        <textarea class="form-control" id="narrative_posture" rows="4"><?php echo htmlspecialchars((string) $this->assessment['posture_text'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                </div>
                <div class="row mb-4">
                    <div class="col-md-6 form-group">
                        <label for="narrative_strengths">Strengths</label>
                        <textarea class="form-control" id="narrative_strengths" rows="5"><?php echo htmlspecialchars((string) $this->assessment['strengths_text'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                    <div class="col-md-6 form-group">
                        <label for="narrative_concerns">Key concerns</label>
                        <textarea class="form-control" id="narrative_concerns" rows="5"><?php echo htmlspecialchars((string) $this->assessment['concerns_text'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                </div>
                <div class="row mb-4">
                    <div class="col-md-6 form-group">
                        <label for="narrative_readiness">Readiness opinion</label>
                        <textarea class="form-control" id="narrative_readiness" rows="4"><?php echo htmlspecialchars((string) $this->assessment['readiness_text'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                    <div class="col-md-6 form-group">
                        <label for="narrative_decisions">Decisions needed from leadership</label>
                        <textarea class="form-control" id="narrative_decisions" rows="4"><?php echo htmlspecialchars((string) $this->assessment['decisions_text'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="do_narrative_save" class="btn btn--primary">Save</button>
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
                <p class="pick__label">Files in <span id="pick_project"></span>&rsquo;s evidence vault <span class="pick__count" id="pick_count"></span></p>
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

<div class="modal fade" data-bs-backdrop="static" id="upload_modal" tabindex="-1" aria-labelledby="upload_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="upload_modal_title">Upload Evidence</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="import__hint">The file is stored in this project&rsquo;s vault and attached to this control. It can be reused on any other control without uploading it again.</p>
                <div class="field">
                    <label for="evidence_title">Title <abbr title="required">*</abbr></label>
                    <input type="text" id="evidence_title" placeholder="Information security policy, signed">
                </div>
                <div class="field">
                    <label for="evidence_description">Description</label>
                    <textarea id="evidence_description" rows="3" placeholder="What this shows and where it came from."></textarea>
                </div>
                <div class="field">
                    <label for="evidence_file">File <abbr title="required">*</abbr></label>
                    <input type="file" id="evidence_file" class="import__file">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="do_upload" class="btn btn--primary">Upload</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" id="delete_modal" tabindex="-1" aria-labelledby="delete_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="delete_modal_title">Delete Assessment</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Delete <strong><?php echo htmlspecialchars($this->assessment['assessment_name'], ENT_QUOTES, 'UTF-8'); ?></strong> and all <?php echo (int) $this->assessment['item_count']; ?> of its items, including results and notes? Evidence itself stays in the project&rsquo;s vault. This cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="do_delete" class="btn btn--destructive">Delete</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {

    var assessment_id = <?php echo (int) $this->assessment['id']; ?>;
    var project_id = <?php echo (int) $this->assessment['project_id']; ?>;
    var project_name = <?php echo json_encode(html_entity_decode((string) $this->assessment['project_name'], ENT_QUOTES, 'UTF-8'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var terms = <?php echo json_encode($this->terminology, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var rolls_up = <?php echo $this->features['rolls_up'] ? 'true' : 'false'; ?>;
    var tiers = <?php echo json_encode((object) $this->features['tiers'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var review = null;
    var partial_labels = <?php echo json_encode((object) $this->features['partial_labels'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var details = <?php echo json_encode(array(
        'assessment_type' => $this->assessment['assessment_type'],
        'baseline_assessment_id' => (int) $this->assessment['baseline_assessment_id'],
        'no_baseline' => (int) $this->assessment['no_baseline']
    ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var items = [];
    var controls = [];
    var objectives_of = {};
    var milestones_of = {};
    var current_item = null;

    var RESULTS = ['Not Assessed', 'Implemented', 'Partially Implemented', 'Not Implemented', 'Not Applicable'];
    var FINDING_RESULTS = ['Partially Implemented', 'Not Implemented'];
    var OBJECTIVE_CHOICES = [
        {
            value: 'Implemented',
            label: 'Met'
        },
        {
            value: 'Not Implemented',
            label: 'Not met'
        },
        {
            value: 'Not Applicable',
            label: 'N/A'
        }
    ];
    var RESULT_CLASS = {
        'Not Assessed': 'badge--inactive',
        'Implemented': 'badge--active',
        'Partially Implemented': 'badge--onboarding',
        'Not Implemented': 'badge--critical',
        'Not Applicable': 'badge--prospect'
    };
    var RESULT_DOT = {
        'Not Assessed': 'is-none',
        'Implemented': 'is-ok',
        'Partially Implemented': 'is-partial',
        'Not Implemented': 'is-gap',
        'Not Applicable': 'is-na'
    };

    function term(key, fallback) {
        return terms && terms[key] ? terms[key] : fallback;
    }

    function capitalise(text) {
        return text.charAt(0).toUpperCase() + text.slice(1);
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
        $('#finding_section').toggle(FINDING_RESULTS.indexOf(item_result) !== -1);
    }

    function current_result() {
        return $('#item_result .segmented__btn.is-on').attr('data-value') || 'Not Assessed';
    }

    $('#item_result').on('click', '.segmented__btn', function () {
        if ($('#item_result').hasClass('is-locked')) {
            return;
        }

        set_result($(this).attr('data-value'));
    });

    function result_from_objectives(results) {

        if (results.length === 0 || results.indexOf('Not Assessed') !== -1) {
            return 'Not Assessed';
        }

        var met = 0;
        var not_met = 0;

        for (var i = 0; i < results.length; i++) {
            if (results[i] === 'Implemented') {
                met++;
            }

            if (results[i] === 'Not Implemented') {
                not_met++;
            }
        }

        if (met === 0 && not_met === 0) {
            return 'Not Applicable';
        }

        if (not_met === 0) {
            return 'Implemented';
        }

        return met === 0 ? 'Not Implemented' : 'Partially Implemented';
    }

    function result_badge(value) {
        return '<span class="badge ' + (RESULT_CLASS[value] || 'badge--inactive') + '">' + esc(value) + '</span>';
    }

    function size_label(bytes) {
        var n = parseInt(bytes, 10) || 0;
        if (n < 1024) { return n + ' B'; }
        if (n < 1048576) { return Math.round(n / 1024) + ' KB'; }
        return (n / 1048576).toFixed(1) + ' MB';
    }

    function rollup() {

        var counts = {};
        var evidenced = 0;

        for (var i = 0; i < RESULTS.length; i++) {
            counts[RESULTS[i]] = 0;
        }

        for (var j = 0; j < controls.length; j++) {
            counts[controls[j].item_result] = (counts[controls[j].item_result] || 0) + 1;

            if (parseInt(controls[j].evidence_count, 10) > 0) {
                evidenced++;
            }
        }

        var total = controls.length;
        var assessed = total - counts['Not Assessed'];
        var pct = total > 0 ? Math.round(assessed / total * 100) : 0;
        var html = '';

        for (var k = 0; k < RESULTS.length; k++) {
            html += '<span class="rollup__stat"><span class="rollup__dot ' + (RESULT_DOT[RESULTS[k]] || '') + '"></span>'
                + '<span class="rollup__n">' + counts[RESULTS[k]] + '</span>'
                + '<span class="rollup__label">' + esc(RESULTS[k]) + '</span></span>';
        }

        html += '<span class="rollup__stat rollup__stat--split"><i class="fa-regular fa-paperclip"></i>'
            + '<span class="rollup__n">' + evidenced + '</span>'
            + '<span class="rollup__label">with evidence</span></span>';

        $('#rollup').html(html);
        $('#rollup_bar').css('width', pct + '%');
        $('#rollup_label').text(assessed + ' of ' + total + ' ' + term('controls', 'controls') + ' assessed · ' + pct + '%');

        sync_complete(counts['Not Assessed']);
    }

    function sync_complete(remaining) {

        if ($('#status_badge').text().trim() === 'Complete') {
            $('#do_complete').prop('disabled', true).attr('title', 'This assessment is complete');
            return;
        }

        $('#do_complete').prop('disabled', false).attr('title', remaining > 0
            ? remaining + ' ' + (remaining === 1 ? term('control', 'control') : term('controls', 'controls')) + ' still unanswered'
            : '');
    }

    function set_status(assessment_status) {
        $('#status_badge').text(assessment_status)
            .removeClass('badge--active badge--onboarding badge--prospect')
            .addClass(RESULT_CLASS[assessment_status] || 'badge--prospect');
    }

    function tier_text(tier) {
        return tier.level > 0 ? tier.label : 'not set';
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
        var blocking = 0;
        var warnings = 0;
        var mismatch = '';

        for (var i = 0; i < review.issues.length; i++) {
            if (review.issues[i].severity === 'block') {
                blocking++;
            } else {
                warnings++;
            }

            if (review.issues[i].code === 'framework_mismatch') {
                mismatch = review.issues[i].message;
            }
        }

        $('#mismatch_notice').text(mismatch).prop('hidden', mismatch === '');

        if (score) {
            var value = '';

            if (score.outcome && score.outcome.status === 'not_started' || score.counts['Not Assessed'] === score.in_scope) {
                value = capitalise(score.model === 'points' ? term('score', 'score') : 'assessment') + ': not started';
            } else if (score.model === 'points') {
                value = capitalise(term('score', 'score')) + ': ' + score.points.score + ' of ' + score.points.maximum + (score.complete ? '' : ' so far');
            } else if (score.model === 'maturity') {
                value = 'Governance: ' + tier_text(score.maturity.governance) + ' (target ' + tier_text(score.maturity.target_governance) + ') · Management: ' + tier_text(score.maturity.management) + ' (target ' + tier_text(score.maturity.target_management) + ')';
            } else {
                value = score.percentage + '% of applicable ' + term('controls', 'controls') + ' implemented' + (score.complete ? '' : ' so far');
            }

            $('#score_value').text(value);
            $('#score_outcome').text(outcome_text(score.outcome))
                .attr('title', score.outcome && score.outcome.reasons.length ? score.outcome.reasons.join('\n') : '')
                .toggleClass('is-ok', !!score.outcome && score.outcome.status !== 'not_eligible');
        }

        $('#do_checks_open').prop('hidden', false).text(blocking + warnings === 0
            ? 'All checks pass'
            : (blocking > 0 ? blocking + ' to fix' : '') + (blocking > 0 && warnings > 0 ? ' · ' : '') + (warnings > 0 ? warnings + (warnings === 1 ? ' warning' : ' warnings') : ''));
        $('#score_row').prop('hidden', false);
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

    $('#do_checks_open').click(function () {
        set_loading('#do_checks_open', true);
        refresh_checks(function () {
            set_loading('#do_checks_open', false);
            show_checks(review ? review.issues : []);
        });
    });

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

            html += '<tr class="item__row' + (current_item && parseInt(current_item.id, 10) === parseInt(it.id, 10) ? ' is-selected' : '') + '" data-id="' + it.id + '" data-family="' + esc(family) + '"'
                + ' data-result="' + esc(it.item_result) + '"'
                + ' data-evidence="' + (parseInt(it.evidence_count, 10) > 0 ? 'yes' : 'no') + '" tabindex="0">'
                + '<td class="controls__id">' + esc(it.control_identifier) + '</td>'
                + '<td><span class="controls__title">' + esc(it.control_title) + '</span>' + objective_summary(it) + '</td>'
                + '<td>' + result_badge(it.item_result) + '</td>'
                + '<td class="item__evidence">' + (parseInt(it.evidence_count, 10) > 0
                    ? '<span class="badge badge--active">' + it.evidence_count + '</span>'
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
                    && (result === '' || row.attr('data-result') === result)
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

        ApiDataSvc.apiCall('post', 'load_items', { assessment_id: assessment_id }, function (data) {

            split(JSON.parse(data));

            ApiDataSvc.apiCall('post', 'load_milestones', { assessment_id: assessment_id }, function (rows) {

                var list = JSON.parse(rows);

                milestones_of = {};

                for (var i = 0; i < list.length; i++) {
                    if (!milestones_of[list[i].assessment_item_id]) {
                        milestones_of[list[i].assessment_item_id] = [];
                    }

                    milestones_of[list[i].assessment_item_id].push(list[i]);
                }

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
            });
        });
    }

    $('#item_search').on('input', filter);
    $('#family_filter, #result_filter, #evidence_filter').on('change', filter);

    function render_objectives(list) {

        var html = '';

        for (var i = 0; i < list.length; i++) {

            var buttons = '';

            for (var c = 0; c < OBJECTIVE_CHOICES.length; c++) {
                var on = list[i].item_result === OBJECTIVE_CHOICES[c].value;

                buttons += '<button type="button" class="segmented__btn' + (on ? ' is-on' : '') + '" data-value="' + OBJECTIVE_CHOICES[c].value + '" aria-pressed="' + (on ? 'true' : 'false') + '">' + OBJECTIVE_CHOICES[c].label + '</button>';
            }

            html += '<li class="objectives__item" data-id="' + list[i].id + '">'
                + '<div class="objectives__text"><span class="objectives__id">' + esc(list[i].control_identifier) + '</span> ' + esc(list[i].control_title) + '</div>'
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
            $('#item_result').addClass('is-locked');
            $('#result_help').prop('hidden', false).text('Excluded controls are reported as not applicable.');
            return;
        }

        if (list.length > 0 && rolls_up) {
            set_result(result_from_objectives(list.map(function (o) {
                return o.result;
            })));
            $('#item_result').addClass('is-locked');
            $('#result_help').prop('hidden', false).text('Worked out from the ' + term('objectives', 'objectives') + ': implemented only when every one is met.');
            return;
        }

        $('#item_result').removeClass('is-locked');
        $('#result_help').prop('hidden', true).text('');
    }

    $('#item_objectives').on('click', '.segmented__btn', function () {

        var group = $(this).closest('.segmented');
        var was_on = $(this).hasClass('is-on');

        group.find('.segmented__btn').removeClass('is-on').attr('aria-pressed', 'false');

        if (!was_on) {
            $(this).addClass('is-on').attr('aria-pressed', 'true');
        }

        sync_locked_result();
    });

    $('#item_applicability').on('change', function () {
        $('#justification_group').toggle($(this).val() === 'Excluded');
        sync_locked_result();
    });

    function milestone_row(milestone) {
        return '<div class="row mb-2 milestone" data-id="' + (milestone.id || 0) + '">'
            + '<div class="col-md-7 form-group"><input type="text" class="form-control milestone__text" aria-label="Milestone" placeholder="Milestone" value="' + esc(milestone.milestone_text || '') + '"></div>'
            + '<div class="col-md-3 form-group"><input type="date" class="form-control milestone__due" aria-label="Due date" value="' + esc(milestone.due_date || '') + '"></div>'
            + '<div class="col-md-1 form-group milestone__done"><input class="form-check-input" type="checkbox" aria-label="Done"' + (milestone.milestone_status === 'Done' ? ' checked' : '') + '></div>'
            + '<div class="col-md-1 form-group"><button type="button" class="btn btn--tertiary btn--sm" data-action="remove-milestone" aria-label="Remove milestone"><i class="fa-regular fa-xmark"></i></button></div>'
            + '</div>';
    }

    $('#do_milestone_add').click(function () {
        $('#item_milestones').append(milestone_row({}));
        $('#item_milestones .milestone:last .milestone__text').focus();
    });

    $('#item_milestones').on('click', '[data-action="remove-milestone"]', function () {
        $(this).closest('.milestone').remove();
    });

    function milestone_values() {
        return $('#item_milestones .milestone').map(function () {
            return {
                id: $(this).attr('data-id'),
                text: $(this).find('.milestone__text').val().trim(),
                due: $(this).find('.milestone__due').val(),
                done: $(this).find('.milestone__done input').is(':checked') ? '1' : '0'
            };
        }).get();
    }

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
        $('#item_guidance').text(guidance);
        $('#item_guidance_wrap').toggle(guidance !== '').prop('open', false);

        $('#applicability_row').toggle(flags.applicability === true);
        $('#item_applicability').val(current_item.applicability || 'Applicable');
        $('#item_justification').val(current_item.applicability_justification || '');
        $('#justification_group').toggle(current_item.applicability === 'Excluded');

        $('#objectives_group').toggle(list.length > 0);
        $('#objectives_label').text(capitalise(term('objectives', 'objectives')));
        render_objectives(list);

        $('#partial_row').toggle(partial !== null);
        $('#partial_label').text(partial === null ? '' : (partial_labels[current_item.control_identifier] || 'Partially effective') + ': subtract ' + partial + ' points instead of ' + weight);
        $('#item_partial_credit').prop('checked', parseInt(current_item.partial_credit, 10) === 1);

        set_result(current_item.item_result);
        sync_locked_result();

        $('#item_notes').val(current_item.notes || '');
        $('#item_implementation').val(current_item.implementation_description || '');
        $('#item_gap').val(current_item.gap_description || '');
        $('#item_impact').val(current_item.business_impact || '');
        $('#item_risk').val(current_item.risk_rating || '');
        $('#item_recommendation').val(current_item.recommendation || '');
        $('#item_owner').val(current_item.owner_name || '');
        $('#item_target').val(current_item.target_date || '');

        var milestones = milestones_of[current_item.id] || [];
        var rows = '';

        for (var m = 0; m < milestones.length; m++) {
            rows += milestone_row(milestones[m]);
        }

        $('#item_milestones').html(rows);

        $('#detail_empty').prop('hidden', true);
        $('#detail_form').prop('hidden', false);
        $('#detail .workbench__scroll').scrollTop(0);

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

    $('#do_item_save').click(function () {

        if (!current_item) {
            return;
        }

        if ($('#applicability_row').css('display') !== 'none' && $('#item_applicability').val() === 'Excluded' && $('#item_justification').val().trim() === '') {
            $('#item_justification').addClass('is-invalid').focus();
            toastr.error('Give a justification for excluding this control.');
            return;
        }

        $('#item_justification').removeClass('is-invalid');
        set_loading('#do_item_save', true);

        var values = {
            item_id: current_item.id,
            item_result: current_result(),
            notes: $('#item_notes').val().trim(),
            implementation_description: $('#item_implementation').val().trim(),
            gap_description: $('#item_gap').val().trim(),
            business_impact: $('#item_impact').val().trim(),
            risk_rating: $('#item_risk').val(),
            recommendation: $('#item_recommendation').val().trim(),
            owner_name: $('#item_owner').val().trim(),
            target_date: $('#item_target').val(),
            partial_credit: $('#item_partial_credit').is(':checked') ? '1' : '0',
            applicability: $('#item_applicability').val(),
            applicability_justification: $('#item_justification').val().trim(),
            objectives: objective_values(),
            milestones: milestone_values(),
            milestones_sent: '1'
        };

        ApiDataSvc.apiCall('post', 'save_item', values, function (data) {

            var obj = JSON.parse(data);

            set_loading('#do_item_save', false);

            if (obj.success) {
                toastr.success(obj.message);
                set_status(obj.assessment_status);
                load(true);
            } else {
                toastr.error(obj.message);
            }
        });
    });

    function load_item_evidence() {

        if (!current_item) {
            return;
        }

        ApiDataSvc.apiCall('post', 'load_item_evidence', { item_id: current_item.id }, function (data) {

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

            $('#item_evidence').html(html === '' ? '<li class="evidence__none">No evidence attached to this control yet.</li>' : html);
        });
    }

    $('#item_evidence').on('click', '[data-action="detach"]', function () {

        var evidence_id = $(this).attr('data-id');

        ApiDataSvc.apiCall('post', 'unlink_evidence', { item_id: current_item.id, evidence_id: evidence_id }, function (data) {

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
        $('#pick_project').text(project_name);

        pick.folders = [];
        pick.evidence = [];
        pick.folder = 0;
        pick.sort = 'date_created';
        pick.dir = 'desc';

        $('#vault_list').empty();
        pick_render_tree();
        pick_render();

        ApiDataSvc.apiCall('post', 'load_folders', { project_id: project_id }, function (data) {
            pick.folders = JSON.parse(data);
            pick_render_tree();
        });

        ApiDataSvc.apiCall('post', 'load_evidence', { project_id: project_id }, function (data) {
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

    function pick_folder_name() {

        for (var i = 0; i < pick.folders.length; i++) {
            if (parseInt(pick.folders[i].id, 10) === pick.folder) {
                return pick.folders[i].folder_name;
            }
        }

        return 'This vault';
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
            return project_name + ' has no evidence yet. Close this and choose Upload New \u2014 the file is saved to this project\u2019s vault and attached here in one step.';
        }

        if (term !== '') {
            return 'No files match \u201c' + term + '\u201d.';
        }

        return pick_folder_name() + ' has no evidence yet.';
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

        ApiDataSvc.apiCall('post', 'link_evidence', { item_id: current_item.id, evidence_id: $(this).attr('data-id') }, function (data) {

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

    $('#do_upload_open').click(function () {

        $('#evidence_title, #evidence_description').val('').removeClass('is-invalid');
        $('#evidence_file').val('');
        modal('upload_modal').show();
    });

    $('#do_upload').click(function () {

        var file = document.getElementById('evidence_file').files[0];
        var title = $('#evidence_title').val().trim();

        if (title === '') {
            $('#evidence_title').addClass('is-invalid').focus();
            toastr.error('Give the evidence a title.');
            return;
        }

        if (!file) {
            toastr.error('Choose a file to upload.');
            return;
        }

        var form_data = new FormData();

        form_data.append('evidence_file', file);
        form_data.append('project_id', project_id);
        form_data.append('evidence_title', title);
        form_data.append('description', $('#evidence_description').val().trim());
        form_data.append('csrf_token', $('meta[name="csrf-token"]').attr('content'));

        set_loading('#do_upload', true);

        $.ajax({
            url: '/api/upload_evidence',
            type: 'POST',
            data: form_data,
            processData: false,
            contentType: false,
            success: function (data) {

                var obj = JSON.parse(data);

                if (obj.success) {

                    ApiDataSvc.apiCall('post', 'link_evidence', { item_id: current_item.id, evidence_id: obj.evidence_id }, function (linked) {

                        var res = JSON.parse(linked);

                        set_loading('#do_upload', false);

                        if (res.success) {
                            modal('upload_modal').hide();
                            toastr.success(res.message);
                            load_item_evidence();
                            load(true);
                        } else {
                            toastr.error(res.message);
                        }
                    });
                } else {
                    set_loading('#do_upload', false);
                    toastr.error(obj.message);
                }
            },
            error: function () {
                set_loading('#do_upload', false);
                toastr.error('That file could not be uploaded.');
            }
        });
    });

    function sync_details_form() {

        var type = $('#details_type').val();
        var none = $('#details_no_baseline').is(':checked');

        $('#baseline_group').toggle(type !== 'Baseline' && !none);
        $('#no_baseline_row').toggle(type !== 'Baseline');
        $('#ssi_note_row').toggle($('#details_ssi').is(':checked'));
    }

    $('#details_type, #details_no_baseline, #details_ssi').on('change', sync_details_form);

    $('.details__tier').each(function () {

        var options = '<option value="0">Not set</option>';

        for (var level in tiers) {
            options += '<option value="' + level + '">Tier ' + level + ': ' + esc(tiers[level]) + '</option>';
        }

        $(this).html(options).val($(this).attr('data-current'));
    });

    $('#do_details_open').click(function () {

        $('#details_type').val(details.assessment_type);
        $('#details_no_baseline').prop('checked', details.no_baseline === 1);
        $('#details_baseline').html('<option value="0">Loading&hellip;</option>');
        sync_details_form();
        modal('details_modal').show();

        ApiDataSvc.apiCall('post', 'load_baselines', { assessment_id: assessment_id }, function (data) {

            var list = JSON.parse(data);
            var options = '<option value="0">' + (list.length === 0 ? 'No baseline assessments for this client and framework' : 'Choose a baseline&hellip;') + '</option>';

            for (var i = 0; i < list.length; i++) {
                options += '<option value="' + list[i].id + '">' + esc(list[i].assessment_name) + ' (' + esc(list[i].project_name) + ', ' + esc(list[i].created_on) + ')</option>';
            }

            $('#details_baseline').html(options).val(String(details.baseline_assessment_id));

            if ($('#details_baseline').val() === null) {
                $('#details_baseline').val('0');
            }
        });
    });

    $('#do_details_save').click(function () {

        var values = {
            assessment_id: assessment_id,
            assessment_type: $('#details_type').val(),
            baseline_assessment_id: $('#details_baseline').val() || '0',
            no_baseline: $('#details_no_baseline').is(':checked') ? '1' : '0',
            assessment_scope: $('#details_scope').val().trim(),
            assessment_method: $('#details_method').val().trim(),
            scope_categories: $('#details_categories input:checked').map(function () {
                return $(this).val();
            }).get(),
            ssi_reviewed: $('#details_ssi').is(':checked') ? '1' : '0',
            tier_governance: $('#details_tier_governance').val() || '0',
            tier_management: $('#details_tier_management').val() || '0',
            target_tier_governance: $('#details_target_governance').val() || '0',
            target_tier_management: $('#details_target_management').val() || '0',
            ssi_note: $('#details_ssi_note').length ? $('#details_ssi_note').val().trim() : ''
        };

        set_loading('#do_details_save', true);

        ApiDataSvc.apiCall('post', 'save_assessment_details', values, function (data) {

            var obj = JSON.parse(data);

            set_loading('#do_details_save', false);

            if (obj.success) {
                window.location.reload();
            } else {
                toastr.error(obj.message);
            }
        });
    });

    $('#do_narrative_open').click(function () {
        modal('narrative_modal').show();
    });

    $('#do_narrative_save').click(function () {

        var values = {
            assessment_id: assessment_id,
            posture_text: $('#narrative_posture').val().trim(),
            strengths_text: $('#narrative_strengths').val().trim(),
            concerns_text: $('#narrative_concerns').val().trim(),
            readiness_text: $('#narrative_readiness').val().trim(),
            decisions_text: $('#narrative_decisions').val().trim()
        };

        set_loading('#do_narrative_save', true);

        ApiDataSvc.apiCall('post', 'save_assessment_narrative', values, function (data) {

            var obj = JSON.parse(data);

            set_loading('#do_narrative_save', false);

            if (obj.success) {
                toastr.success(obj.message);
                modal('narrative_modal').hide();
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

        ApiDataSvc.apiCall('post', 'delete_assessment', { assessment_id: assessment_id }, function (data) {

            var obj = JSON.parse(data);

            set_loading('#do_delete', false);

            if (obj.success) {
                window.location.href = '/projects/detail/id/' + project_id;
            } else {
                toastr.error(obj.message);
            }
        });
    });

    $('#status_badge').addClass(RESULT_CLASS[$('#status_badge').text().trim()] || 'badge--prospect');

    load();

});
</script>

<div class="modal fade" data-bs-backdrop="static" id="reports_modal" tabindex="-1" aria-labelledby="reports_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="reports_modal_title">Download a Report</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-4">
                    <div class="col-md-8 form-group">
                        <label for="ready_type">Report</label>
                        <select class="form-control" id="ready_type"></select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="ready_format">Format</label>
                        <select class="form-control" id="ready_format"></select>
                    </div>
                </div>
                <p class="field__help" id="ready_description"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn--primary" id="do_ready_download">Download</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {

    var assessment_id = <?php echo (int) $this->assessment['id']; ?>;
    var reports = <?php echo json_encode(ReadyReports::catalog_for('assessment'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var FORMAT_NAMES = {
        pdf: "PDF",
        docx: "Word",
        xlsx: "Excel"
    };

    reports.forEach(function (report) {
        $("#ready_type").append($("<option>").val(report.key).text(report.name));
    });

    $("#do_reports_open").click(function(){
        show_formats();
        modal("reports_modal").show();
    });

    $("#ready_type").change(function(){
        show_formats();
    });

    $("#do_ready_download").click(function(){

        var report = current();

        var obj = {
            report_type: report.key,
            target_id: assessment_id,
            format: $("#ready_format").val()
        };

        set_loading("#do_ready_download", true);

        var tab = report_tab($("#ready_format").val());

        ApiDataSvc.apiCall('post', 'ready_report_download', obj, function (data) {
            var obj = JSON.parse(data);
            set_loading("#do_ready_download", false);
            if (obj.success) {
                show_report_tab(tab, obj.url);
            } else {
                close_report_tab(tab);
                toastr.error(obj.message);
            }
        }, function () {
            set_loading("#do_ready_download", false);
            close_report_tab(tab);
            toastr.error("The report could not be prepared. Try again.");
        });
    });

    function current() {
        var key = $("#ready_type").val();
        return reports.filter(function (report) {
            return report.key === key;
        })[0];
    }

    function show_formats() {
        var report = current();
        var select = $("#ready_format").empty();
        report.formats.forEach(function (format) {
            select.append($("<option>").val(format).text(FORMAT_NAMES[format]));
        });
        $("#ready_description").text(report.description);
    }

});
</script>
