"use strict";

var hostname = window.location.hostname;
var protocol = window.location.protocol;
var url = protocol+"//"+hostname+"/api/";

var ApiDataSvc = {
    baseUrl: url,
    apiCall: apiCall
};

$.ajaxSetup({
    beforeSend: function(xhr, settings) {
        if (settings.type && settings.type.toUpperCase() === 'POST') {
            var csrfMeta = document.querySelector('meta[name="csrf-token"]');
            if (csrfMeta) {
                xhr.setRequestHeader('X-CSRF-TOKEN', csrfMeta.getAttribute('content'));
            }
        }
    }
});

function apiCall(type, endpoint, body, success, callFail) {

    var url = this.baseUrl + endpoint;

    if (type !== 'post') {
        toastr.error('Something went wrong. Please try again.');
        return null;
    }

    if (body && typeof body === 'object' && !(body instanceof FormData)) {
        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (csrfMeta) {
            body.csrf_token = csrfMeta.getAttribute('content');
        }
    }

    var request = $.post(url, body, success);

    page_loading(1);
    request.always(function () {
        page_loading(-1);
    });

    request.fail(function (xhr, status) {
        if (status === 'abort') {
            return;
        }
        if (answered_with_json(xhr) && typeof success === 'function') {
            success(xhr.responseText);
            return;
        }
        if (typeof callFail === 'function') {
            callFail(xhr, status);
            return;
        }
        $('.is-loading').removeClass('is-loading').prop('disabled', false);
        toastr.error(navigator.onLine ? 'The server could not be reached. Please try again.' : 'You appear to be offline. Check your connection and try again.');
    });

    return request;
}

var page_loading_count = 0;

function page_loading(change) {

    var bar = document.getElementById('page_loading');

    if (bar === null) {
        bar = document.createElement('div');
        bar.id = 'page_loading';
        bar.className = 'page-loading';
        bar.setAttribute('role', 'progressbar');
        bar.setAttribute('aria-label', 'Loading');
        document.body.appendChild(bar);
    }

    page_loading_count = Math.max(0, page_loading_count + change);
    bar.classList.toggle('is-active', page_loading_count > 0);
}

function answered_with_json(xhr) {
    try {
        var body = JSON.parse(xhr.responseText);
        return body !== null && typeof body === 'object' && 'success' in body;
    } catch (e) {
        return false;
    }
}
