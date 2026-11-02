<div class="page report">
    <a class="page__back" href="/projects/detail/id/<?php echo (int) $this->project['id']; ?>"><i class="fa-regular fa-arrow-left"></i> Back to <?php echo htmlspecialchars($this->project['project_name'], ENT_QUOTES, 'UTF-8'); ?></a>

    <div class="page__head">
        <div>
            <h1 class="page__title">Report</h1>
            <div class="client__meta">
                <span class="badge" id="stage_badge" hidden></span>
                <span class="client__segment" id="version_label" hidden></span>
                <span class="client__segment"><?php echo htmlspecialchars($this->project['project_name'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>
        <div class="page__actions">
            <button type="button" class="btn btn--primary" id="do_create" hidden><i class="fa-regular fa-plus"></i> Start Report</button>
            <?php if (AiAssistant::configured()) { ?>
            <button type="button" class="btn btn--secondary" id="do_ai" hidden><i class="fa-regular fa-sparkles"></i> Draft with AI</button>
            <?php } ?>
            <button type="button" class="btn btn--secondary" id="do_issue" hidden><i class="fa-regular fa-paper-plane"></i> Issue for Response</button>
            <button type="button" class="btn btn--secondary" id="do_finalise" hidden><i class="fa-regular fa-circle-check"></i> Finalise</button>
            <button type="button" class="btn btn--secondary" id="do_reopen" hidden><i class="fa-regular fa-pen"></i> Back to Draft</button>
            <button type="button" class="btn btn--secondary" id="do_word" hidden><i class="fa-regular fa-file-word"></i> Word</button>
            <button type="button" class="btn btn--secondary" id="do_pdf" hidden><i class="fa-regular fa-file-pdf"></i> PDF</button>
            <button type="button" class="btn btn--secondary" id="do_vault" hidden><i class="fa-regular fa-folder-arrow-down"></i> Save PDF to Vault</button>
        </div>
    </div>

    <div class="panel record__panel" id="report_empty" hidden>
        <div class="panel__body">
            <p class="report__empty">No report has been started for this project.</p>
        </div>
    </div>

    <div class="report__layout" id="report_layout" hidden>
        <div class="panel report__nav">
            <div class="panel__head">
                <h2 class="panel__title">Sections</h2>
            </div>
            <div class="report__nav-list" id="section_nav"></div>
        </div>
        <div class="panel report__editor">
            <div class="panel__head">
                <h2 class="panel__title" id="editor_title"></h2>
                <div class="report__editor-actions">
                    <button type="button" class="btn btn--primary btn--sm" id="do_text_save" hidden>Save</button>
                    <button type="button" class="btn btn--primary btn--sm" id="do_csf_save" hidden>Save</button>
                    <button type="button" class="btn btn--primary btn--sm" id="do_row_add" hidden><i class="fa-regular fa-plus"></i> <span id="row_add_label">Add</span></button>
                </div>
            </div>
            <div class="report__editor-body" id="editor_body"></div>
        </div>
    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" id="row_modal" tabindex="-1" aria-labelledby="row_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="row_modal_title"></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row" id="row_fields"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--destructive me-auto" id="do_row_delete">Remove</button>
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn--primary" id="do_row_save">Save</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" id="confirm_modal" tabindex="-1" aria-labelledby="confirm_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="confirm_modal_title"></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="confirm_modal_text"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn--primary" id="do_confirm">Confirm</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {

    var project_id = <?php echo (int) $this->project['id']; ?>;
    var report = null;
    var current = "details";
    var editing = null;
    var confirm_action = null;

    var SCORES = [
        "1",
        "2",
        "3",
        "4",
        "5"
    ];

    var TEXT_SECTIONS = {
        details: {
            title: "Report details",
            fields: [
                {
                    key: "report_title",
                    label: "Report title",
                    type: "text",
                    col: 12
                },
                {
                    key: "report_date",
                    label: "Report date",
                    type: "date",
                    col: 4
                },
                {
                    key: "period_start",
                    label: "Fieldwork start",
                    type: "date",
                    col: 4
                },
                {
                    key: "period_end",
                    label: "Fieldwork end",
                    type: "date",
                    col: 4
                },
                {
                    key: "regulatory_reference",
                    label: "Regulatory reference",
                    type: "text",
                    col: 12
                },
                {
                    key: "signoff_name",
                    label: "Acknowledgment signer",
                    type: "text",
                    col: 6
                },
                {
                    key: "signoff_title",
                    label: "Signer's title",
                    type: "text",
                    col: 6
                }
            ]
        },
        summary: {
            title: "Executive summary",
            fields: [
                {
                    key: "purpose_text",
                    label: "Purpose",
                    type: "long",
                    rows: 5,
                    col: 12
                },
                {
                    key: "conclusion_text",
                    label: "Overall conclusion",
                    type: "long",
                    rows: 10,
                    col: 12
                }
            ]
        },
        background: {
            title: "Background, scope and approach",
            fields: [
                {
                    key: "background_text",
                    label: "Background",
                    type: "long",
                    rows: 6,
                    col: 12
                },
                {
                    key: "scope_text",
                    label: "Scope",
                    type: "long",
                    rows: 5,
                    col: 12
                },
                {
                    key: "out_of_scope_text",
                    label: "Out of scope",
                    type: "long",
                    rows: 3,
                    col: 12
                },
                {
                    key: "approach_text",
                    label: "Approach (start lines with - for a list)",
                    type: "long",
                    rows: 8,
                    col: 12
                }
            ]
        },
        method: {
            title: "Methodology, limitations and review",
            fields: [
                {
                    key: "methodology_text",
                    label: "Rating methodology",
                    type: "long",
                    rows: 7,
                    col: 12
                },
                {
                    key: "limitations_text",
                    label: "Limitations and responsibilities",
                    type: "long",
                    rows: 6,
                    col: 12
                },
                {
                    key: "review_text",
                    label: "Ongoing review",
                    type: "long",
                    rows: 5,
                    col: 12
                },
                {
                    key: "scan_text",
                    label: "Vulnerability scan introduction",
                    type: "long",
                    rows: 3,
                    col: 12
                }
            ]
        }
    };

    var LIST_SECTIONS = {
        domains: {
            title: "Domains",
            section: "domain",
            data: "domains",
            noun: "Domain",
            columns: [
                [
                    "domain_name",
                    "Domain"
                ],
                [
                    "domain_rating",
                    "Overall"
                ],
                [
                    "finding_count",
                    "Findings"
                ]
            ],
            fields: [
                {
                    key: "domain_name",
                    label: "Domain",
                    type: "text",
                    col: 8
                },
                {
                    key: "domain_rating",
                    label: "Overall assessment",
                    type: "select",
                    options: [
                        "",
                        "Satisfactory",
                        "Satisfactory with exceptions",
                        "Needs improvement",
                        "Unsatisfactory"
                    ],
                    col: 4
                },
                {
                    key: "areas_reviewed",
                    label: "Areas reviewed",
                    type: "text",
                    col: 8
                },
                {
                    key: "csf_refs",
                    label: "NIST CSF 2.0",
                    type: "text",
                    col: 4
                },
                {
                    key: "domain_summary",
                    label: "What was reviewed and what is working",
                    type: "long",
                    rows: 5,
                    col: 12
                }
            ]
        },
        findings: {
            title: "Findings",
            section: "finding",
            data: "findings",
            noun: "Finding",
            columns: [
                [
                    "finding_ref",
                    "Ref."
                ],
                [
                    "finding_title",
                    "Finding"
                ],
                [
                    "rating_label",
                    "Rating"
                ],
                [
                    "response_status",
                    "Response"
                ]
            ],
            fields: [
                {
                    key: "finding_ref",
                    label: "Ref.",
                    type: "text",
                    col: 2,
                    hint: "Auto if blank"
                },
                {
                    key: "finding_title",
                    label: "Finding",
                    type: "text",
                    col: 10
                },
                {
                    key: "domain_id",
                    label: "Domain",
                    type: "domain",
                    col: 6
                },
                {
                    key: "likelihood",
                    label: "Likelihood",
                    type: "select",
                    options: SCORES,
                    col: 3
                },
                {
                    key: "impact",
                    label: "Impact",
                    type: "select",
                    options: SCORES,
                    col: 3
                },
                {
                    key: "observation",
                    label: "Observation",
                    type: "long",
                    rows: 4,
                    col: 12
                },
                {
                    key: "risk_text",
                    label: "Risk",
                    type: "long",
                    rows: 3,
                    col: 12
                },
                {
                    key: "recommendation",
                    label: "Recommendation",
                    type: "long",
                    rows: 3,
                    col: 12
                },
                {
                    key: "csf_refs",
                    label: "NIST CSF 2.0",
                    type: "text",
                    col: 12
                },
                {
                    key: "response_status",
                    label: "Management response",
                    type: "select",
                    options: [
                        "Pending",
                        "Agreed",
                        "Partially agreed",
                        "Not agreed"
                    ],
                    col: 4
                },
                {
                    key: "response_owner",
                    label: "Owner",
                    type: "text",
                    col: 4
                },
                {
                    key: "target_date",
                    label: "Target date",
                    type: "date",
                    col: 4
                },
                {
                    key: "management_response",
                    label: "Response",
                    type: "long",
                    rows: 3,
                    col: 12
                }
            ]
        },
        risks: {
            title: "Risk register",
            section: "risk",
            data: "risks",
            noun: "Risk",
            columns: [
                [
                    "risk_ref",
                    "Ref."
                ],
                [
                    "risk_text",
                    "Risk"
                ],
                [
                    "score",
                    "Score"
                ],
                [
                    "treatment",
                    "Treatment"
                ]
            ],
            fields: [
                {
                    key: "risk_ref",
                    label: "Ref.",
                    type: "text",
                    col: 2,
                    hint: "Auto if blank"
                },
                {
                    key: "risk_text",
                    label: "Risk",
                    type: "text",
                    col: 10
                },
                {
                    key: "domain_id",
                    label: "Domain",
                    type: "domain",
                    col: 6
                },
                {
                    key: "finding_id",
                    label: "Related finding",
                    type: "finding",
                    col: 6
                },
                {
                    key: "threat_refs",
                    label: "Threats",
                    type: "text",
                    col: 4
                },
                {
                    key: "likelihood",
                    label: "Likelihood",
                    type: "select",
                    options: SCORES,
                    col: 4
                },
                {
                    key: "impact",
                    label: "Impact",
                    type: "select",
                    options: SCORES,
                    col: 4
                },
                {
                    key: "treatment",
                    label: "Treatment",
                    type: "text",
                    col: 12
                }
            ]
        },
        threats: {
            title: "Threat analysis",
            section: "threat",
            data: "threats",
            noun: "Threat",
            columns: [
                [
                    "threat_ref",
                    "Ref."
                ],
                [
                    "threat_name",
                    "Threat"
                ],
                [
                    "parties_affected",
                    "Parties affected"
                ]
            ],
            fields: [
                {
                    key: "threat_ref",
                    label: "Ref.",
                    type: "text",
                    col: 2
                },
                {
                    key: "threat_name",
                    label: "Threat",
                    type: "text",
                    col: 10
                },
                {
                    key: "threat_description",
                    label: "Description",
                    type: "text",
                    col: 12
                },
                {
                    key: "parties_affected",
                    label: "Parties affected",
                    type: "text",
                    col: 12
                }
            ]
        },
        assets: {
            title: "Systems and data inventory",
            section: "asset",
            data: "assets",
            noun: "System",
            columns: [
                [
                    "asset_name",
                    "System or service"
                ],
                [
                    "asset_kind",
                    "Type"
                ],
                [
                    "sensitivity",
                    "Sensitivity"
                ]
            ],
            fields: [
                {
                    key: "asset_name",
                    label: "System or service",
                    type: "text",
                    col: 8
                },
                {
                    key: "asset_kind",
                    label: "Type",
                    type: "text",
                    col: 4
                },
                {
                    key: "asset_owner",
                    label: "Owner",
                    type: "text",
                    col: 8
                },
                {
                    key: "sensitivity",
                    label: "Sensitivity",
                    type: "select",
                    options: [
                        "High",
                        "Medium",
                        "Low"
                    ],
                    col: 4
                },
                {
                    key: "data_held",
                    label: "Sensitive information held",
                    type: "text",
                    col: 12
                }
            ]
        },
        scan: {
            title: "Vulnerability scan",
            section: "scan_row",
            data: "scan_rows",
            noun: "Scan Result",
            columns: [
                [
                    "scan_rating",
                    "Rating"
                ],
                [
                    "issue_count",
                    "Issues"
                ],
                [
                    "scan_summary",
                    "Summary"
                ]
            ],
            fields: [
                {
                    key: "scan_rating",
                    label: "Rating",
                    type: "select",
                    options: [
                        "Critical",
                        "High",
                        "Medium",
                        "Low"
                    ],
                    col: 6
                },
                {
                    key: "issue_count",
                    label: "Issues",
                    type: "number",
                    col: 6
                },
                {
                    key: "scan_summary",
                    label: "Summary",
                    type: "text",
                    col: 12
                },
                {
                    key: "disposition",
                    label: "Disposition",
                    type: "text",
                    col: 12
                }
            ]
        },
        xrefs: {
            title: "Regulatory cross-reference",
            section: "xref",
            data: "xrefs",
            noun: "Requirement",
            columns: [
                [
                    "xref_section",
                    "Section"
                ],
                [
                    "requirement",
                    "Requirement"
                ],
                [
                    "where_addressed",
                    "Where addressed"
                ]
            ],
            fields: [
                {
                    key: "xref_section",
                    label: "Section",
                    type: "text",
                    col: 4
                },
                {
                    key: "where_addressed",
                    label: "Where addressed",
                    type: "text",
                    col: 8
                },
                {
                    key: "requirement",
                    label: "Requirement",
                    type: "text",
                    col: 12
                }
            ]
        },
        versions: {
            title: "Document control",
            section: "version",
            data: "versions",
            noun: "Version",
            columns: [
                [
                    "version_label",
                    "Version"
                ],
                [
                    "version_date",
                    "Date"
                ],
                [
                    "version_note",
                    "Description"
                ]
            ],
            fields: [
                {
                    key: "version_label",
                    label: "Version",
                    type: "text",
                    col: 4
                },
                {
                    key: "version_date",
                    label: "Date",
                    type: "date",
                    col: 4
                },
                {
                    key: "version_author",
                    label: "Author",
                    type: "text",
                    col: 4
                },
                {
                    key: "version_note",
                    label: "Description",
                    type: "text",
                    col: 12
                }
            ]
        },
        people: {
            title: "Distribution and team",
            section: "person",
            data: "people",
            noun: "Person",
            columns: [
                [
                    "kind_label",
                    "List"
                ],
                [
                    "person_name",
                    "Name"
                ],
                [
                    "person_title",
                    "Title or role"
                ],
                [
                    "organisation",
                    "Organization"
                ]
            ],
            fields: [
                {
                    key: "person_kind",
                    label: "List",
                    type: "select",
                    options: [
                        "distribution",
                        "team"
                    ],
                    col: 4
                },
                {
                    key: "person_name",
                    label: "Name",
                    type: "text",
                    col: 8
                },
                {
                    key: "person_title",
                    label: "Title or role",
                    type: "text",
                    col: 6
                },
                {
                    key: "organisation",
                    label: "Organization",
                    type: "text",
                    col: 6
                },
                {
                    key: "responsibility",
                    label: "Responsibility (team only)",
                    type: "text",
                    col: 12
                }
            ]
        }
    };

    var NAV = [
        [
            "details",
            "Report details"
        ],
        [
            "summary",
            "Executive summary"
        ],
        [
            "background",
            "Background and scope"
        ],
        [
            "domains",
            "Domains"
        ],
        [
            "findings",
            "Findings"
        ],
        [
            "risks",
            "Risk register"
        ],
        [
            "csf",
            "NIST CSF 2.0 profile"
        ],
        [
            "assets",
            "Systems inventory"
        ],
        [
            "threats",
            "Threat analysis"
        ],
        [
            "scan",
            "Vulnerability scan"
        ],
        [
            "xrefs",
            "Regulatory cross-reference"
        ],
        [
            "method",
            "Methodology and review"
        ],
        [
            "people",
            "Distribution and team"
        ],
        [
            "versions",
            "Document control"
        ]
    ];

    var TIERS = [
        [
            "",
            "Not set"
        ],
        [
            "1",
            "1 Partial"
        ],
        [
            "2",
            "2 Risk Informed"
        ],
        [
            "3",
            "3 Repeatable"
        ],
        [
            "4",
            "4 Adaptive"
        ]
    ];

    load();

    $(document).on("assist:changed", function () {
        load();
    });

    $("#do_create").click(function(){

        set_loading("#do_create", true);

        var obj = {
            project_id: project_id
        };

        ApiDataSvc.apiCall('post', 'report_create', obj, function (data) {
            var obj = JSON.parse(data);
            set_loading("#do_create", false);
            if (obj.success) {
                toastr.success(obj.message);
                load();
            } else {
                toastr.error(obj.message);
            }
        });
    });

    $("#do_ai").click(function(){
        if (typeof window.assist_ask !== "function") {
            toastr.error("The assistant is not available on this page");
            return;
        }
        window.assist_ask("Draft the report for this project (project id " + project_id + "). Read the project, its assessments, the control results and notes, and the evidence. Then fill in the report: details and dates, executive summary, background, scope and approach, a rating and summary for each domain, findings with likelihood and impact, the risk register, the NIST CSF 2.0 profile and the systems inventory. Put everything on cards for me to confirm.");
    });

    $("#section_nav").on("click", "[data-section]", function(){
        current = $(this).attr("data-section");
        render();
    });

    $("#do_text_save").click(function(){

        var obj = {
            report_id: report.report.id
        };

        TEXT_SECTIONS[current].fields.forEach(function (field) {
            obj[field.key] = $("#text_" + field.key).val().trim();
            $("#text_" + field.key).removeClass("is-invalid");
        });

        set_loading("#do_text_save", true);

        ApiDataSvc.apiCall('post', 'report_save_details', obj, function (data) {
            var obj = JSON.parse(data);
            set_loading("#do_text_save", false);
            if (obj.success) {
                toastr.success(obj.message);
                load();
            } else {
                if (obj.field) {
                    $("#text_" + obj.field).addClass("is-invalid");
                }
                toastr.error(obj.message);
            }
        });
    });

    $("#do_csf_save").click(function(){

        var rows = [];

        $("#editor_body [data-csf]").each(function () {
            var fn = $(this).attr("data-csf");
            rows.push({
                report_id: report.report.id,
                csf_function: fn,
                current_tier: $("#csf_current_" + fn).val(),
                target_tier: $("#csf_target_" + fn).val(),
                main_gaps: $("#csf_gaps_" + fn).val().trim()
            });
        });

        set_loading("#do_csf_save", true);
        save_csf(rows, 0);
    });

    $("#do_row_add").click(function(){
        open_row(null);
    });

    $("#editor_body").on("click", "tr[data-row]", function(){
        var id = parseInt($(this).attr("data-row"), 10);
        var list = rows_for(LIST_SECTIONS[current]);
        for (var i = 0; i < list.length; i++) {
            if (parseInt(list[i].id, 10) === id) {
                open_row(list[i]);
                return;
            }
        }
    });

    $("#editor_body").on("keydown", "tr[data-row]", function(e){
        if (e.key === "Enter") {
            $(this).click();
        }
    });

    $("#do_row_save").click(function(){

        var config = LIST_SECTIONS[current];

        var obj = {
            report_id: report.report.id,
            section: config.section,
            id: editing === null ? 0 : editing.id
        };

        config.fields.forEach(function (field) {
            obj[field.key] = $("#row_" + field.key).val().trim();
            $("#row_" + field.key).removeClass("is-invalid");
        });

        set_loading("#do_row_save", true);

        ApiDataSvc.apiCall('post', 'report_save_row', obj, function (data) {
            var obj = JSON.parse(data);
            set_loading("#do_row_save", false);
            if (obj.success) {
                modal("row_modal").hide();
                toastr.success(obj.message);
                load();
            } else {
                if (obj.field) {
                    $("#row_" + obj.field).addClass("is-invalid");
                }
                toastr.error(obj.message);
            }
        });
    });

    $("#do_row_delete").click(function(){
        var config = LIST_SECTIONS[current];
        modal("row_modal").hide();
        ask("Remove " + config.noun, "Remove this " + config.noun.toLowerCase() + " from the report?", function (done) {
            var obj = {
                report_id: report.report.id,
                section: config.section,
                id: editing.id
            };
            ApiDataSvc.apiCall('post', 'report_delete_row', obj, function (data) {
                var obj = JSON.parse(data);
                done();
                if (obj.success) {
                    toastr.success(obj.message);
                    load();
                } else {
                    toastr.error(obj.message);
                }
            });
        });
    });

    $("#do_issue").click(function(){
        ask("Issue for Response", "The client's portal users will see the findings and can enter their responses. The current version is recorded in document control.", function (done) {
            stage_call("report_issue", done);
        });
    });

    $("#do_finalise").click(function(){
        ask("Finalise Report", "The report becomes the final version, and the client can download the PDF from the portal.", function (done) {
            stage_call("report_finalise", done);
        });
    });

    $("#do_reopen").click(function(){
        ask("Back to Draft", "The report returns to draft under the next version number, and clients can no longer respond.", function (done) {
            stage_call("report_reopen", done);
        });
    });

    $("#do_confirm").click(function(){
        set_loading("#do_confirm", true);
        confirm_action(function () {
            set_loading("#do_confirm", false);
            modal("confirm_modal").hide();
        });
    });

    $("#do_word").click(function(){
        download("docx", "#do_word");
    });

    $("#do_pdf").click(function(){
        download("pdf", "#do_pdf");
    });

    $("#do_vault").click(function(){

        set_loading("#do_vault", true);

        var obj = {
            report_id: report.report.id,
            format: "pdf"
        };

        ApiDataSvc.apiCall('post', 'report_save_to_vault', obj, function (data) {
            var obj = JSON.parse(data);
            set_loading("#do_vault", false);
            if (obj.success) {
                toastr.success("Saved to the project's evidence vault");
            } else {
                toastr.error(obj.message);
            }
        });
    });

    function load() {

        var obj = {
            project_id: project_id
        };

        ApiDataSvc.apiCall('post', 'report_load', obj, function (data) {

            var obj = JSON.parse(data);

            if (!obj.success) {
                toastr.error(obj.message);
                return;
            }

            report = obj.report;
            render();
        });
    }

    function render() {

        var has = report !== null;

        $("#report_empty").prop("hidden", has);
        $("#report_layout").prop("hidden", !has);
        $("#do_create").prop("hidden", has);
        $("#do_ai, #do_word, #do_pdf, #do_vault").prop("hidden", !has);

        if (!has) {
            $("#stage_badge, #version_label, #do_issue, #do_finalise, #do_reopen").prop("hidden", true);
            return;
        }

        var stage = report.report.report_stage;

        $("#stage_badge").prop("hidden", false).text(stage).attr("class", "badge " + (stage === "Final" ? "badge--active" : (stage === "Draft" ? "badge--prospect" : "badge--onboarding")));
        $("#version_label").prop("hidden", false).text("Version " + report.report.report_version);
        $("#do_issue").prop("hidden", stage !== "Draft");
        $("#do_finalise").prop("hidden", stage === "Final");
        $("#do_reopen").prop("hidden", stage === "Draft");

        render_nav();

        $("#do_text_save, #do_csf_save, #do_row_add").prop("hidden", true);

        if (TEXT_SECTIONS[current]) {
            render_text(TEXT_SECTIONS[current]);
        } else if (current === "csf") {
            render_csf();
        } else {
            render_list(LIST_SECTIONS[current]);
        }
    }

    function render_nav() {

        var nav = $("#section_nav");
        nav.empty();

        NAV.forEach(function (item) {
            var count = "";
            if (LIST_SECTIONS[item[0]]) {
                count = String(rows_for(LIST_SECTIONS[item[0]]).length);
            }
            var button = $("<button>").attr("type", "button").addClass("report__nav-item").attr("data-section", item[0]).toggleClass("is-active", item[0] === current);
            button.append($("<span>").text(item[1]));
            if (count !== "") {
                button.append($("<span>").addClass("report__nav-count").text(count));
            }
            nav.append(button);
        });
    }

    function render_text(config) {

        $("#editor_title").text(config.title);
        $("#do_text_save").prop("hidden", false);

        var row = $("<div>").addClass("row");

        config.fields.forEach(function (field) {
            row.append(field_control(field, "text_", report.report[field.key]));
        });

        $("#editor_body").empty().append(row);
    }

    function render_list(config) {

        $("#editor_title").text(config.title);
        $("#row_add_label").text("Add " + config.noun);
        $("#do_row_add").prop("hidden", false);

        var list = rows_for(config);
        var body = $("#editor_body").empty();

        if (list.length === 0) {
            body.append($("<p>").addClass("report__empty").text("Nothing added yet."));
            return;
        }

        var table = $("<table>").addClass("data report__table");
        var head = $("<tr>");

        config.columns.forEach(function (column) {
            head.append($("<th>").attr("scope", "col").text(column[1]));
        });

        table.append($("<thead>").append(head));

        var tbody = $("<tbody>");

        list.forEach(function (row) {
            var tr = $("<tr>").attr("data-row", row.id).attr("tabindex", "0");
            config.columns.forEach(function (column) {
                tr.append($("<td>").text(cell_value(row, column[0])));
            });
            tbody.append(tr);
        });

        body.append(table.append(tbody));
    }

    function render_csf() {

        $("#editor_title").text("NIST CSF 2.0 profile");
        $("#do_csf_save").prop("hidden", false);

        var table = $("<table>").addClass("data report__table report__csf");
        table.append($("<thead>").append($("<tr>")
            .append($("<th>").text("Function"))
            .append($("<th>").text("Current tier"))
            .append($("<th>").text("Target tier"))
            .append($("<th>").text("Main gaps"))));

        var tbody = $("<tbody>");

        report.csf.forEach(function (row) {
            var fn = row.csf_function;
            var tr = $("<tr>").attr("data-csf", fn);
            tr.append($("<td>").text(fn));
            tr.append($("<td>").append(tier_select("csf_current_" + fn, row.current_tier, "Current tier for " + fn)));
            tr.append($("<td>").append(tier_select("csf_target_" + fn, row.target_tier, "Target tier for " + fn)));
            tr.append($("<td>").append($("<input>").attr("type", "text").attr("id", "csf_gaps_" + fn).attr("aria-label", "Main gaps for " + fn).addClass("form-control form-control-sm").val(row.main_gaps || "")));
            tbody.append(tr);
        });

        $("#editor_body").empty().append(table.append(tbody));
    }

    function tier_select(id, value, label) {
        var select = $("<select>").attr("id", id).attr("aria-label", label).addClass("form-select form-select-sm");
        TIERS.forEach(function (tier) {
            select.append($("<option>").val(tier[0]).text(tier[1]));
        });
        select.val(value === null ? "" : String(value));
        return select;
    }

    function save_csf(rows, index) {

        if (index >= rows.length) {
            set_loading("#do_csf_save", false);
            toastr.success("Profile saved");
            load();
            return;
        }

        ApiDataSvc.apiCall('post', 'report_save_csf', rows[index], function (data) {
            var obj = JSON.parse(data);
            if (!obj.success) {
                set_loading("#do_csf_save", false);
                toastr.error(obj.message);
                return;
            }
            save_csf(rows, index + 1);
        });
    }

    function open_row(row) {

        var config = LIST_SECTIONS[current];
        editing = row;

        $("#row_modal_title").text((row === null ? "Add " : "Edit ") + config.noun);
        $("#do_row_delete").prop("hidden", row === null);

        var box = $("#row_fields").empty();

        config.fields.forEach(function (field) {
            var value = row === null ? default_value(field) : row[field.key];
            box.append(field_control(field, "row_", value));
        });

        modal("row_modal").show();
    }

    function default_value(field) {
        if (field.key === "likelihood" || field.key === "impact") {
            return "3";
        }
        if (field.key === "response_status") {
            return "Pending";
        }
        if (field.key === "sensitivity") {
            return "Medium";
        }
        if (field.key === "person_kind") {
            return "distribution";
        }
        return "";
    }

    function field_control(field, prefix, value) {

        var id = prefix + field.key;
        var col = $("<div>").addClass("col-md-" + field.col);
        var group = $("<div>").addClass("form-group");
        var control;

        group.append($("<label>").attr("for", id).text(field.label));

        if (field.type === "long") {
            control = $("<textarea>").attr("rows", field.rows || 4);
        } else if (field.type === "select" || field.type === "domain" || field.type === "finding") {
            control = $("<select>").addClass("form-select");
            options_for(field).forEach(function (option) {
                control.append($("<option>").val(option[0]).text(option[1]));
            });
        } else {
            control = $("<input>").attr("type", field.type === "date" ? "date" : (field.type === "number" ? "number" : "text"));
        }

        control.attr("id", id).addClass("form-control").val(value === null || value === undefined ? "" : String(value));
        group.append(control);

        if (field.hint) {
            group.append($("<small>").addClass("form-text").text(field.hint));
        }

        return col.append(group);
    }

    function options_for(field) {

        if (field.type === "domain") {
            var domains = [
                [
                    "",
                    "Not assigned"
                ]
            ];
            report.domains.forEach(function (domain) {
                domains.push([
                    String(domain.id),
                    domain.domain_name
                ]);
            });
            return domains;
        }

        if (field.type === "finding") {
            var findings = [
                [
                    "",
                    "None"
                ]
            ];
            report.findings.forEach(function (finding) {
                findings.push([
                    String(finding.id),
                    finding.finding_ref + " " + finding.finding_title
                ]);
            });
            return findings;
        }

        return field.options.map(function (option) {
            return [
                option,
                option === "" ? "Not set" : option
            ];
        });
    }

    function rows_for(config) {
        if (config.data === "people") {
            return report.distribution.concat(report.team);
        }
        return report[config.data] || [];
    }

    function cell_value(row, key) {
        if (key === "rating_label") {
            return row.rating + " (" + row.score + ")";
        }
        if (key === "score") {
            return row.score + " " + row.rating;
        }
        if (key === "finding_count") {
            return String(row.findings ? row.findings.length : 0);
        }
        if (key === "kind_label") {
            return row.person_kind === "team" ? "Team" : "Distribution";
        }
        return row[key] === null || row[key] === undefined ? "" : String(row[key]);
    }

    function ask(title, text, action) {
        $("#confirm_modal_title").text(title);
        $("#confirm_modal_text").text(text);
        confirm_action = action;
        modal("confirm_modal").show();
    }

    function stage_call(endpoint, done) {

        var obj = {
            report_id: report.report.id
        };

        ApiDataSvc.apiCall('post', endpoint, obj, function (data) {
            var obj = JSON.parse(data);
            done();
            if (obj.success) {
                toastr.success(obj.message);
                load();
            } else {
                toastr.error(obj.message);
            }
        });
    }

    function download(format, button) {

        set_loading(button, true);

        var obj = {
            report_id: report.report.id,
            format: format
        };

        ApiDataSvc.apiCall('post', 'report_download', obj, function (data) {
            var obj = JSON.parse(data);
            set_loading(button, false);
            if (obj.success) {
                window.location.href = obj.url;
            } else {
                toastr.error(obj.message);
            }
        });
    }

});
</script>
