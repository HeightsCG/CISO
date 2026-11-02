<div class="page report">
    <a class="page__back" href="/portal/project/id/<?php echo (int) $this->project['id']; ?>"><i class="fa-regular fa-arrow-left"></i> Back to <?php echo htmlspecialchars($this->project['project_name'], ENT_QUOTES, 'UTF-8'); ?></a>

    <div class="page__head">
        <div>
            <h1 class="page__title" id="report_title">Report</h1>
            <div class="client__meta">
                <span class="badge" id="stage_badge" hidden></span>
                <span class="client__segment" id="version_label" hidden></span>
                <span class="client__segment"><?php echo htmlspecialchars($this->client['company_name'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>
        <div class="page__actions">
            <button type="button" class="btn btn--primary" id="do_download" hidden><i class="fa-regular fa-file-pdf"></i> Download Final Report</button>
        </div>
    </div>

    <div class="panel record__panel report__layout report__layout--single">
        <div class="panel__head">
            <h2 class="panel__title">Findings</h2>
            <span class="report__nav-count" id="intro"></span>
        </div>
        <div class="findings__list" id="findings"></div>
    </div>
</div>

<script>
$(document).ready(function () {

    var project_id = <?php echo (int) $this->project['id']; ?>;
    var can_respond = false;

    var RESPONSES = [
        "Agreed",
        "Partially agreed",
        "Not agreed"
    ];

    load();

    $("#findings").on("click", "[data-save]", function(){

        var id = $(this).attr("data-save");
        var button = $(this);

        var obj = {
            project_id: project_id,
            finding_id: id,
            response_status: $("#status_" + id).val(),
            management_response: $("#response_" + id).val().trim(),
            response_owner: $("#owner_" + id).val().trim(),
            target_date: $("#target_" + id).val()
        };

        $("#status_" + id + ", #target_" + id).removeClass("is-invalid");

        if (obj.response_status === "") {
            $("#status_" + id).addClass("is-invalid");
            toastr.error("Choose Agreed, Partially agreed or Not agreed.");
            return;
        }

        set_loading(button, true);

        ApiDataSvc.apiCall('post', 'portal_report_respond', obj, function (data) {
            var obj = JSON.parse(data);
            set_loading(button, false);
            if (obj.success) {
                toastr.success(obj.message);
            } else {
                if (obj.field) {
                    $("#" + (obj.field === "response_status" ? "status_" : "target_") + id).addClass("is-invalid");
                }
                toastr.error(obj.message);
            }
        });
    });

    $("#do_download").click(function(){

        set_loading("#do_download", true);

        var obj = {
            project_id: project_id
        };

        ApiDataSvc.apiCall('post', 'portal_report_download', obj, function (data) {
            var obj = JSON.parse(data);
            set_loading("#do_download", false);
            if (obj.success) {
                window.location.href = obj.url;
            } else {
                toastr.error(obj.message);
            }
        });
    });

    function load() {

        var obj = {
            project_id: project_id
        };

        ApiDataSvc.apiCall('post', 'portal_report_load', obj, function (data) {

            var obj = JSON.parse(data);

            if (!obj.success || obj.report === null) {
                $("#findings").empty().append($("<p>").addClass("report__empty finding-card").text("The report is not available yet."));
                return;
            }

            var report = obj.report;
            can_respond = report.can_respond;

            $("#report_title").text(report.report_title);
            $("#stage_badge").prop("hidden", false).text(report.report_stage).attr("class", "badge " + (report.report_stage === "Final" ? "badge--active" : "badge--onboarding"));
            $("#version_label").prop("hidden", false).text("Version " + report.report_version);
            $("#do_download").prop("hidden", report.report_stage !== "Final");
            $("#intro").text(can_respond ? "Review each finding and enter your response, owner and target date." : "This report is final.");

            render(report);
        });
    }

    function render(report) {

        var box = $("#findings").empty();
        var domains = {};

        report.domains.forEach(function (domain) {
            domains[domain.id] = domain.domain_name;
        });

        if (report.findings.length === 0) {
            box.append($("<p>").addClass("report__empty finding-card").text("There are no findings in this report."));
            return;
        }

        report.findings.forEach(function (finding) {
            box.append(card(finding, domains));
        });
    }

    function card(finding, domains) {

        var id = finding.id;
        var node = $("<div>").addClass("finding-card");
        var head = $("<div>").addClass("finding-card__head");

        head.append($("<h3>").addClass("finding-card__title").text(finding.finding_ref + " " + finding.finding_title));
        head.append($("<span>").addClass("badge " + (finding.rating === "High" ? "badge--critical" : (finding.rating === "Medium" ? "badge--onboarding" : "badge--inactive"))).text(finding.rating + " (" + finding.score + ")"));
        node.append(head);

        if (domains[finding.domain_id]) {
            node.append($("<p>").addClass("report__nav-count").text(domains[finding.domain_id]));
        }

        [
            [
                "Observation",
                finding.observation
            ],
            [
                "Risk",
                finding.risk_text
            ],
            [
                "Recommendation",
                finding.recommendation
            ]
        ].forEach(function (part) {
            if (part[1]) {
                node.append($("<p>").addClass("finding-card__label").text(part[0]));
                node.append($("<p>").addClass("finding-card__text").text(part[1]));
            }
        });

        if (!can_respond) {
            node.append($("<p>").addClass("finding-card__label").text("Management response"));
            node.append($("<p>").addClass("finding-card__text").text(response_text(finding)));
            return node;
        }

        var row = $("<div>").addClass("row");

        var status = $("<select>").attr("id", "status_" + id).addClass("form-select form-control");
        status.append($("<option>").val("").text("Choose a response"));
        RESPONSES.forEach(function (option) {
            status.append($("<option>").val(option).text(option));
        });
        status.val(finding.response_status === "Pending" ? "" : finding.response_status);

        row.append(group(4, "status_" + id, "Response", status));
        row.append(group(4, "owner_" + id, "Owner", $("<input>").attr("type", "text").attr("id", "owner_" + id).addClass("form-control").val(finding.response_owner || "")));
        row.append(group(4, "target_" + id, "Target date", $("<input>").attr("type", "date").attr("id", "target_" + id).addClass("form-control").val(finding.target_date || "")));
        row.append(group(12, "response_" + id, "Comments and planned actions", $("<textarea>").attr("id", "response_" + id).attr("rows", "3").addClass("form-control").val(finding.management_response || "")));

        node.append(row);
        node.append($("<button>").attr("type", "button").addClass("btn btn--primary btn--sm").attr("data-save", id).text("Save Response"));

        return node;
    }

    function group(width, id, label, control) {
        var col = $("<div>").addClass("col-md-" + width);
        var wrap = $("<div>").addClass("form-group");
        wrap.append($("<label>").attr("for", id).text(label));
        wrap.append(control);
        return col.append(wrap);
    }

    function response_text(finding) {
        var parts = [];
        if (finding.response_status !== "Pending") {
            parts.push(finding.response_status + ".");
        }
        if (finding.management_response) {
            parts.push(finding.management_response);
        }
        if (finding.response_owner || finding.target_date) {
            var owner = [
                finding.response_owner,
                finding.target_date
            ];
            parts.push("Owner / target: " + owner.filter(Boolean).join(" / "));
        }
        return parts.length > 0 ? parts.join(" ") : "No response recorded.";
    }

});
</script>
