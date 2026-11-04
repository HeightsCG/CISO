"use strict";

$(function () {

    if ($("#assist").length === 0) {
        return;
    }

    var ACTION_ENDPOINTS = [
        "save_client",
        "delete_client",
        "create_assessment",
        "save_assessment",
        "delete_assessment",
        "complete_assessment",
        "save_item",
        "link_evidence",
        "unlink_evidence",
        "ai_file_evidence",
        "save_folder",
        "delete_folder",
        "move_evidence",
        "save_evidence",
        "save_evidence_private",
        "delete_evidence",
        "save_standard",
        "duplicate_standard",
        "delete_standard",
        "save_control",
        "delete_control",
        "save_profile",
        "save_user",
        "delete_user",
        "reset_user_password",
        "save_company",
        "save_security",
        "remove_logo",
        "save_invoice",
        "send_invoice",
        "resend_invoice",
        "void_invoice",
        "delete_invoice",
        "save_subscription",
        "activate_subscription",
        "cancel_subscription",
        "delete_subscription",
        "portal_link_evidence",
        "portal_unlink_evidence",
        "portal_delete_evidence"
    ];

    var conversation_id = parseInt(window.localStorage.getItem("assist_conversation") || "0", 10);
    var attachments = [];
    var loaded = false;

    $("#assist_toggle").click(function(){
        set_open(!$("#shell").hasClass("has-assist"));
    });

    $("#assist_close").click(function(){
        set_open(false);
    });

    $("#assist_new").click(function(){
        conversation_id = 0;
        window.localStorage.removeItem("assist_conversation");
        $("#assist_conversations").val("0");
        clear_messages();
        $("#assist_input").focus();
    });

    $("#assist_conversations").change(function(){
        conversation_id = parseInt($(this).val(), 10);
        if (conversation_id > 0) {
            window.localStorage.setItem("assist_conversation", conversation_id);
            load_history();
        } else {
            window.localStorage.removeItem("assist_conversation");
            clear_messages();
        }
    });

    $("#assist_send").click(function(){
        send($("#assist_input").val().trim());
    });

    $("#assist_input").keydown(function(e){
        if (e.key === "Enter" && !e.shiftKey) {
            e.preventDefault();
            send($("#assist_input").val().trim());
        }
    });

    $("#assist_attach").click(function(){
        $("#assist_file").click();
    });

    $("#assist_file").change(function(){
        var file = this.files[0];
        $(this).val("");
        if (file) {
            upload(file);
        }
    });

    $("#assist_files").on("click", "[data-remove]", function(){
        var id = parseInt($(this).attr("data-remove"), 10);
        attachments = attachments.filter(function (a) {
            return a.attachment_id !== id;
        });
        render_files();
    });

    $("#assist_messages").on("click", "[data-confirm]", function(){
        confirm_batch($(this).closest(".assist__card"));
    });

    $("#assist_messages").on("click", "[data-decline]", function(){
        decline_batch($(this).closest(".assist__card"));
    });

    $("#assist_messages").on("click", "[data-download]", function(){
        download($(this), parseInt($(this).attr("data-download"), 10), $(this).attr("data-format"));
    });

    $("#assist_messages").on("click", "[data-vault]", function(){
        send('Save "' + $(this).attr("data-vault") + '" to the evidence vault.');
    });

    if ($("#shell").hasClass("has-assist")) {
        load();
    }

    window.assist_ask = function (text) {
        if (!$("#shell").hasClass("has-assist")) {
            set_open(true);
        }
        send(text);
    };

    function set_open(open) {

        $("#shell").toggleClass("has-assist", open);

        var obj = {
            open: open ? "1" : "0"
        };

        ApiDataSvc.apiCall('post', 'ai_panel', obj, function () {
            return;
        });

        if (open && !loaded) {
            load();
        }
    }

    function load() {
        loaded = true;
        load_conversations();
        if (conversation_id > 0) {
            load_history();
        }
    }

    function load_conversations() {

        var obj = {};

        ApiDataSvc.apiCall('post', 'ai_conversations', obj, function (data) {

            var rows = JSON.parse(data);
            var picker = $("#assist_conversations");

            picker.empty();
            picker.append($("<option>").val("0").text("New conversation"));

            rows.forEach(function (row) {
                picker.append($("<option>").val(row.id).text(row.conversation_title));
            });

            picker.val(String(conversation_id));

            if (picker.val() === null) {
                picker.val("0");
            }
        });
    }

    function load_history() {

        var obj = {
            conversation_id: conversation_id
        };

        clear_messages();

        ApiDataSvc.apiCall('post', 'ai_history', obj, function (data) {

            var obj = JSON.parse(data);

            if (!obj.success) {
                conversation_id = 0;
                window.localStorage.removeItem("assist_conversation");
                $("#assist_conversations").val("0");
                return;
            }

            show(obj);
        });
    }

    function send(text) {

        if (text === "" && attachments.length === 0) {
            $("#assist_input").focus();
            return;
        }

        if ($("#assist_send").hasClass("is-loading")) {
            return;
        }

        var obj = {
            conversation_id: conversation_id,
            message: text,
            attachment_ids: attachments.map(function (a) {
                return a.attachment_id;
            }).join(","),
            page_path: window.location.pathname
        };

        var pending = add_item({
            role: "user",
            text: text,
            attachments: attachments.map(function (a) {
                return a.file_name;
            })
        });

        $("#assist_messages .assist__card").remove();
        $("#assist_input").val("");
        attachments = [];
        render_files();
        set_working(true);

        ApiDataSvc.apiCall('post', 'ai_send', obj, function (data) {

            var obj = JSON.parse(data);

            set_working(false);
            pending.remove();

            if (!obj.success) {
                toastr.error(obj.message);
                return;
            }

            var is_new = conversation_id !== obj.conversation_id;

            conversation_id = obj.conversation_id;
            window.localStorage.setItem("assist_conversation", conversation_id);
            show(obj);

            if (is_new) {
                load_conversations();
            }

        }, function () {
            set_working(false);
            toastr.error("The assistant could not be reached. Try again.");
        });
    }

    function upload(file) {

        var form_data = new FormData();
        form_data.append("ai_file", file);

        set_loading("#assist_attach", true);

        $.ajax({
            url: '/api/ai_upload',
            type: 'POST',
            data: form_data,
            processData: false,
            contentType: false,
            success: function (data) {

                var obj = JSON.parse(data);

                set_loading("#assist_attach", false);

                if (obj.success) {
                    attachments.push({
                        attachment_id: obj.attachment_id,
                        file_name: obj.file_name
                    });
                    render_files();
                } else {
                    toastr.error(obj.message);
                }
            },
            error: function () {
                set_loading("#assist_attach", false);
                toastr.error("That file could not be attached");
            }
        });
    }

    function download(button, attachment_id, format) {

        var obj = {
            attachment_id: attachment_id
        };

        set_loading(button, true);

        var tab = report_tab(format);

        ApiDataSvc.apiCall('post', 'ai_file_url', obj, function (data) {

            var obj = JSON.parse(data);

            set_loading(button, false);

            if (obj.success) {
                show_report_tab(tab, obj.url);
            } else {
                close_report_tab(tab);
                toastr.error(obj.message);
            }

        }, function () {
            close_report_tab(tab);
            set_loading(button, false);
            toastr.error("That file could not be opened");
        });
    }

    function confirm_batch(card) {

        var actions = card.data("actions");
        var steps = [];
        var outcomes = {};

        actions.forEach(function (action) {
            outcomes[action.tool_use_id] = {
                succeeded: true,
                outcome: []
            };
            action.calls.forEach(function (call) {
                steps.push({
                    tool_use_id: action.tool_use_id,
                    endpoint: call.endpoint,
                    params: call.params
                });
            });
        });

        for (var i = 0; i < steps.length; i++) {
            if (ACTION_ENDPOINTS.indexOf(steps[i].endpoint) === -1) {
                toastr.error("That change cannot be made from here");
                return;
            }
        }

        set_loading(card.find("[data-confirm]"), true);
        card.find("[data-decline]").prop("disabled", true);

        run_step(card, steps, 0, outcomes);
    }

    function run_step(card, steps, index, outcomes) {

        if (index >= steps.length) {
            finish_batch(card, outcomes);
            return;
        }

        var step = steps[index];
        var obj = $.extend({}, step.params);

        card.find(".assist__card-progress").text("Step " + (index + 1) + " of " + steps.length);

        ApiDataSvc.apiCall('post', step.endpoint, obj, function (data) {

            var result;

            try {
                result = JSON.parse(data);
            } catch (e) {
                result = {
                    success: false,
                    message: "The application gave an unexpected answer"
                };
            }

            if (!result.success) {
                outcomes[step.tool_use_id].succeeded = false;
            }

            if (result.message && outcomes[step.tool_use_id].outcome.indexOf(result.message) === -1) {
                outcomes[step.tool_use_id].outcome.push(result.message);
            }

            run_step(card, steps, index + 1, outcomes);

        }, function (xhr) {
            outcomes[step.tool_use_id].succeeded = false;
            outcomes[step.tool_use_id].outcome.push(xhr && xhr.status ? "The server returned an error (HTTP " + xhr.status + ")" : "The server could not be reached");
            run_step(card, steps, index + 1, outcomes);
        });
    }

    function finish_batch(card, outcomes) {

        var changed = false;

        Object.keys(outcomes).forEach(function (key) {
            outcomes[key].outcome = outcomes[key].outcome.join(" ");
            changed = changed || outcomes[key].succeeded;
        });

        if (changed) {
            $(document).trigger("assist:changed");
        }

        resolve(card, {
            conversation_id: conversation_id,
            confirmed: "1",
            outcomes_json: JSON.stringify(outcomes)
        });
    }

    function decline_batch(card) {

        set_loading(card.find("[data-decline]"), true);
        card.find("[data-confirm]").prop("disabled", true);

        resolve(card, {
            conversation_id: conversation_id,
            confirmed: "0",
            outcomes_json: "{}"
        });
    }

    function resolve(card, obj) {

        set_working(true);

        ApiDataSvc.apiCall('post', 'ai_resolve', obj, function (data) {

            var obj = JSON.parse(data);

            set_working(false);

            if (!obj.success) {
                toastr.error(obj.message);
                return;
            }

            card.remove();
            show(obj);

        }, function () {
            set_working(false);
            toastr.error("The assistant could not be reached. Try again.");
        });
    }

    function show(obj) {

        $("#assist_messages .assist__card").remove();

        obj.items.forEach(function (item) {
            add_item(item);
        });

        if (obj.actions.length > 0) {
            add_card(obj.actions);
        }

        scroll_down();
    }

    function add_item(item) {

        $("#assist_empty").prop("hidden", true);

        var node;

        if (item.role === "file") {
            node = file_item(item);
        } else {
            node = $("<div>").addClass("assist__msg assist__msg--" + item.role);

            if (item.attachments && item.attachments.length > 0) {
                var files = $("<div>").addClass("assist__msg-files");
                item.attachments.forEach(function (name) {
                    files.append($("<span>").addClass("assist__chip").text(name));
                });
                node.append(files);
            }

            if (item.text !== "") {
                node.append($("<div>").addClass("assist__text").html(format(item.text)));
            }
        }

        if ($("#assist_working").length > 0) {
            $("#assist_working").before(node);
        } else {
            $("#assist_messages").append(node);
        }

        scroll_down();

        return node;
    }

    function file_item(item) {

        var node = $("<div>").addClass("assist__file");

        node.append($("<span>").addClass("assist__file-icon").html('<i class="fa-regular fa-file-lines"></i>'));
        node.append($("<span>").addClass("assist__file-name").text(item.text));

        var bar = $("<span>").addClass("assist__file-bar");
        bar.append($("<button>").attr("type", "button").addClass("btn btn--tertiary btn--sm").attr("data-download", item.attachment_id).attr("data-format", item.text.split(".").pop().toLowerCase()).text("Download"));
        bar.append($("<button>").attr("type", "button").addClass("btn btn--tertiary btn--sm").attr("data-vault", item.text).text("Save to vault"));
        node.append(bar);

        return node;
    }

    function add_card(actions) {

        var card = $("<div>").addClass("assist__card");

        card.data("actions", actions);

        actions.forEach(function (action) {

            var section = $("<div>").addClass("assist__card-section");
            var list = $("<dl>").addClass("assist__fields");

            section.append($("<p>").addClass("assist__card-title").text(action.summary));

            action.fields.forEach(function (field) {
                if (field.value !== "") {
                    list.append($("<dt>").text(field.label));
                    list.append($("<dd>").text(field.value));
                }
            });

            section.append(list);
            card.append(section);
        });

        var bar = $("<div>").addClass("assist__card-bar");
        bar.append($("<button>").attr("type", "button").addClass("btn btn--primary btn--sm").attr("data-confirm", "1").text(actions.length > 1 ? "Confirm all" : "Confirm"));
        bar.append($("<button>").attr("type", "button").addClass("btn btn--tertiary btn--sm").attr("data-decline", "1").text("Decline"));
        bar.append($("<span>").addClass("assist__card-progress"));
        card.append(bar);

        $("#assist_messages").append(card);
    }

    function format(text) {
        return esc(text).replace(/(^|[\s(])(\/(?:clients|assessments|standards|portal|billing|settings)(?:\/[a-z_]+)?(?:\/id\/\d+)?)(?=$|[\s).,;:])/g, function (match, lead, path) {
            return lead + '<a href="' + path + '">' + path + '</a>';
        });
    }

    function render_files() {

        var box = $("#assist_files");

        box.empty();

        attachments.forEach(function (a) {
            var chip = $("<span>").addClass("assist__chip").text(a.file_name);
            chip.append($("<button>").attr("type", "button").addClass("assist__chip-remove").attr("data-remove", a.attachment_id).attr("aria-label", "Remove " + a.file_name).html('<i class="fa-regular fa-xmark"></i>'));
            box.append(chip);
        });
    }

    function set_working(working) {

        set_loading("#assist_send", working);
        $("#assist_input").prop("disabled", working);

        if (working) {
            $("#assist_messages").append($("<div>").addClass("assist__working").attr("id", "assist_working").text("Working on it"));
            scroll_down();
        } else {
            $("#assist_working").remove();
        }
    }

    function clear_messages() {
        $("#assist_messages").children().not("#assist_empty").remove();
        $("#assist_empty").prop("hidden", false);
    }

    function scroll_down() {
        var box = document.getElementById("assist_messages");
        box.scrollTop = box.scrollHeight;
    }

});
