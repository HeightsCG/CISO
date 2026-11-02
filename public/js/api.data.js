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

function answered_with_json(xhr) {
    try {
        var body = JSON.parse(xhr.responseText);
        return body !== null && typeof body === 'object' && 'success' in body;
    } catch (e) {
        return false;
    }
}
