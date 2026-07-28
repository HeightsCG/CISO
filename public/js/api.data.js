"use strict";

var hostname = window.location.hostname;
var protocol = window.location.protocol;
var url = protocol+"//"+hostname+"/api/";

var storage = window.localStorage;

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

    var url  = this.baseUrl + endpoint;
    if (type === 'post' && body && typeof body === 'object' && !(body instanceof FormData)) {
        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (csrfMeta) {
            body.csrf_token = csrfMeta.getAttribute('content');
        }
    }

    switch (type) {
        case 'get':
            $.get(url, body, success, callFail);
            break;

        case 'post':
            $.post(url, body, success, callFail);
            break;

        case 'put':
            $.put(url, body, success, callFail);
            break;

        case 'delete':
            $.delete(url, body, success, callFail);
            break;

        default:
            callFailed();
    }

    function callFailed() {
        var conn = checkConnection();
        sweetAlert("Connection Error", "Please try again later..."+conn, "error");
    }
    
    function checkConnection() {
        var online = navigator.onLine;
        return online;
    }

}
