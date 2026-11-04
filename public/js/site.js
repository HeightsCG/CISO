"use strict";

function esc(value) {
    return $('<div>').text(value === null || value === undefined ? '' : value).html()
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function standard_display(name, version) {
    name = (name || '').trim();
    version = (version || '').trim();

    if (version === '' || name.toLowerCase().indexOf(version.toLowerCase()) !== -1) {
        return name;
    }

    return name + ' (' + version + ')';
}

function text_cell(data, type) {
    return type === 'display' ? esc(data) : data;
}

function modal(id) {
    return bootstrap.Modal.getOrCreateInstance(document.getElementById(id));
}

function set_loading(target, loading) {
    if (loading) {
        $(target).addClass("is-loading").prop("disabled", true);
    } else {
        $(target).removeClass("is-loading").prop("disabled", false);
    }
}

/**
 * Stacked dialog layering.
 *
 * Bootstrap gives every dialog and every backdrop the same z-index, so a dialog
 * opened from another dialog lands beside its parent rather than over it, and
 * the parent never dims.
 *
 * This adjusts z-index and nothing else. Bootstrap keeps ownership of opacity,
 * the fade classes and the lifetime of every backdrop element - touching any of
 * those is what breaks the ordinary single-dialog case.
 */
$(function () {

    $(document).on('show.bs.modal', '.modal', function () {

        // Dialogs already open underneath this one; the opening dialog has not
        // been given its .show class yet at this point in the lifecycle.
        var beneath = $('.modal.show').length;
        var z = 1055 + (beneath * 20);

        $(this).css('z-index', z);

        // The backdrop for this dialog is created during show, so it exists by
        // the next tick. Anything already tagged belongs to a dialog below.
        window.setTimeout(function () {
            $('.modal-backdrop').not('.modal-stacked').addClass('modal-stacked').css('z-index', z - 1);
        }, 0);
    });

    $(document).on('hidden.bs.modal', '.modal', function () {

        $(this).css('z-index', '');

        var open = $('.modal.show').length;

        if (open === 0) {
            return;
        }

        // Bootstrap clears this whenever any dialog closes, which unlocks page
        // scrolling even though a parent dialog is still open.
        $('body').addClass('modal-open');

        // It also leaves the closed dialog's backdrop behind, which then sits
        // over the parent and dims it a second time. Surplus backdrops are
        // dropped here, after the close has finished, so this never races the
        // one Bootstrap is still creating during an open.
        $('.modal-backdrop').slice(open).remove();
    });

});

function report_tab(format) {
    if (format !== "pdf") {
        return null;
    }
    var tab = window.open("", "_blank");
    if (tab) {
        tab.document.title = "Preparing report";
        tab.document.body.style.fontFamily = "sans-serif";
        tab.document.body.style.padding = "40px";
        tab.document.body.textContent = "Preparing your report...";
    }
    return tab;
}

function show_report_tab(tab, url) {
    if (tab && !tab.closed) {
        tab.location.href = url;
        return;
    }
    window.location.href = url;
}

function close_report_tab(tab) {
    if (tab && !tab.closed) {
        tab.close();
    }
}

$(document).on("keydown", "input", function (event) {

    if (event.key !== "Enter" || event.isComposing || event.defaultPrevented) {
        return;
    }

    var input = $(this);
    var type = (input.attr("type") || "text").toLowerCase();

    if (type === "search" || type === "checkbox" || type === "radio" || type === "file" || type === "button" || type === "submit") {
        return;
    }

    if (input.closest(".bs-searchbox, .iti__dropdown-content, .ql-tooltip, .dropdown-menu, [data-enter=\"off\"]").length > 0) {
        return;
    }

    var scope = input.closest(".modal.show");
    var button = scope.find(".modal-footer .btn--primary:visible:not(:disabled)").first();

    if (scope.length === 0) {
        button = input.closest(".panel, .wizard").find(".panel__actions .btn--primary:visible:not(:disabled)").first();
    }

    if (scope.length === 0 && button.length === 0) {
        button = $(".page").find("#do_save:visible:not(:disabled), .commit__actions .btn--primary:visible:not(:disabled), .savebar .btn--primary:visible:not(:disabled)").first();
    }

    if (button.length === 0) {
        return;
    }

    event.preventDefault();
    button.trigger("click");
});

$.valHooks.hidden = {
    set: function (elem, value) {
        if (elem._flatpickr) {
            elem._flatpickr.setDate(value || null, false, "Y-m-d");
            return true;
        }
        return undefined;
    }
};

function date_pickers(root) {

    if (typeof flatpickr !== "function") {
        return;
    }

    $(root).find('input[type="date"]').each(function () {

        var input = this;

        if (input._flatpickr) {
            return;
        }

        var picker = flatpickr(input, {
            altInput: true,
            altFormat: "M j, Y",
            dateFormat: "Y-m-d",
            disableMobile: true,
            clickOpens: !input.readOnly,
            minDate: input.min || null,
            maxDate: input.max || null,
            static: $(input).closest(".modal").length > 0 && $(input).closest(".form-floating").length === 0,
            locale: {
                firstDayOfWeek: 0
            }
        });

        var shown = picker.altInput;

        shown.readOnly = true;
        shown.placeholder = input.placeholder || "Choose a date";
        $(shown).toggleClass("is-derived", input.readOnly);

        new MutationObserver(function () {
            $(shown).toggleClass("is-invalid", $(input).hasClass("is-invalid"));
        }).observe(input, {
            attributes: true,
            attributeFilter: ["class"]
        });
    });
}

$(function () {
    date_pickers(document);
});

$(document).on("show.bs.modal", function (event) {
    date_pickers(event.target);
});

if (window.self !== window.top && window.location.pathname.indexOf("/billing/") !== 0) {
    window.top.location.reload();
}

$(document).on("click", "html.is-embed a[href^='/']", function () {
    if (this.getAttribute("href").indexOf("/billing/") !== 0) {
        this.target = "_top";
    }
});

(function () {

    var original_set = $.valHooks.select ? $.valHooks.select.set : null;

    $.valHooks.select = $.valHooks.select || {};
    $.valHooks.select.set = function (elem, value) {

        var result = original_set ? original_set(elem, value) : undefined;

        if (result === undefined) {
            $(elem).find("option").each(function () {
                this.selected = $.isArray(value) ? value.indexOf(this.value) !== -1 : this.value === String(value);
            });
            result = true;
        }

        if ($(elem).data("selectpicker")) {
            $(elem).selectpicker("render");
        }

        return result;
    };
})();

function skip_select(select) {
    var el = $(select);
    return el.is("[hidden], [multiple], [data-native]")
        || el.closest(".ledger, .flatpickr-calendar, .assist, .bootstrap-select, .dt-container .dt-length").length > 0;
}

function watch_select(select) {

    if (select._watched) {
        return;
    }

    select._watched = true;

    var busy = false;

    new MutationObserver(function () {

        if (busy || !$(select).data("selectpicker")) {
            return;
        }

        busy = true;

        var value = $(select).val();

        $(select).selectpicker("destroy");
        $(select).selectpicker();
        $(select).selectpicker("val", value);

        setTimeout(function () {
            busy = false;
        }, 0);
    }).observe(select, {
        childList: true,
        subtree: true,
        characterData: true,
        attributes: true,
        attributeFilter: ["disabled"]
    });
}

function dropdowns(root) {

    if (!$.fn.selectpicker) {
        return;
    }

    $(root).find("select").each(function () {

        if (skip_select(this)) {
            return;
        }

        var el = $(this);

        if (!el.data("selectpicker")) {

            var floating = el.parent(".form-floating");

            if (floating.length > 0) {
                floating.removeClass("form-floating").addClass("form-group");
                floating.find("> label").insertBefore(el);
            }

            el.removeClass("form-select form-select-sm form-select-lg form-control");
            el.addClass("selectpicker");

            if (!el.attr("data-style")) {
                el.attr("data-style", "form-control");
            }

            if (!el.attr("data-width")) {
                el.attr("data-width", "100%");
            }

            if (!el.attr("data-container") && el.closest(".modal").length > 0) {
                el.attr("data-container", "body");
            }

            el.selectpicker();
        }

        watch_select(this);
    });
}

$(function () {

    setTimeout(function () {
        dropdowns(document);
    }, 0);

    var pending = null;

    new MutationObserver(function (records) {

        var added = false;

        for (var i = 0; i < records.length && !added; i++) {
            for (var j = 0; j < records[i].addedNodes.length; j++) {
                var node = records[i].addedNodes[j];
                if (node.nodeType === 1 && (node.tagName === "SELECT" || node.querySelector("select"))) {
                    added = true;
                    break;
                }
            }
        }

        if (!added) {
            return;
        }

        clearTimeout(pending);
        pending = setTimeout(function () {
            dropdowns(document);
        }, 0);
    }).observe(document.body, {
        childList: true,
        subtree: true
    });
});

$(document).on("show.bs.modal", function (event) {
    dropdowns(event.target);
});
