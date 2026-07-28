"use strict";

var AddressAutocomplete = (function () {

    var MIN_CHARS = 4;
    var DEBOUNCE_MS = 250;

    function attach(options) {

        var input = $('#' + options.input);

        if (input.length === 0) {
            return;
        }

        var list_id = options.input + '_suggestions';
        var list = $('<ul>')
            .attr('id', list_id)
            .attr('role', 'listbox')
            .addClass('addr-suggest')
            .prop('hidden', true);

        input.closest('.field').addClass('addr-field').append(list);

        input
            .attr('role', 'combobox')
            .attr('aria-expanded', 'false')
            .attr('aria-controls', list_id)
            .attr('aria-autocomplete', 'list')
            .attr('autocomplete', 'off');

        var timer = null;
        var request = null;
        var sequence = 0;
        var items = [];
        var active = -1;

        function close() {
            list.prop('hidden', true).empty();
            input.attr('aria-expanded', 'false').removeAttr('aria-activedescendant');
            items = [];
            active = -1;
        }

        function highlight(index) {

            list.children('li').removeClass('is-active').attr('aria-selected', 'false');

            if (index < 0 || index >= items.length) {
                active = -1;
                input.removeAttr('aria-activedescendant');
                return;
            }

            active = index;

            var option = list.children('li').eq(index);

            option.addClass('is-active').attr('aria-selected', 'true');
            input.attr('aria-activedescendant', option.attr('id'));

            var node = option.get(0);

            if (node && node.scrollIntoView) {
                node.scrollIntoView({ block: 'nearest' });
            }
        }

        function choose(index) {

            if (index < 0 || index >= items.length) {
                return;
            }

            var chosen = items[index];

            input.val(chosen.label);
            close();

            ApiDataSvc.apiCall('post', 'address_place', { place_id: chosen.place_id }, function (data) {

                var obj = JSON.parse(data);

                if (!obj.success) {
                    toastr.error(obj.message);
                    return;
                }

                if (obj.data.street !== '') {
                    input.val(obj.data.street);
                }

                $.each(options.targets, function (key, field_id) {
                    if (obj.data[key] !== '') {
                        $('#' + field_id).val(obj.data[key]);
                    }
                });
            });
        }

        function render(results) {

            list.empty();
            items = results;
            active = -1;

            if (results.length === 0) {
                close();
                return;
            }

            $.each(results, function (index, item) {
                $('<li>')
                    .attr('id', list_id + '_' + index)
                    .attr('role', 'option')
                    .attr('aria-selected', 'false')
                    .addClass('addr-suggest__item')
                    .text(item.label)
                    .appendTo(list);
            });

            list.prop('hidden', false);
            input.attr('aria-expanded', 'true');
        }

        function lookup(query) {

            var token = ++sequence;

            if (request && request.abort) {
                request.abort();
            }

            var body = { query: query };

            if (options.country) {
                body.country = $('#' + options.country).val();
            }

            request = ApiDataSvc.apiCall('post', 'address_suggest', body, function (data) {

                if (token !== sequence) {
                    return;
                }

                var obj = null;

                try {
                    obj = JSON.parse(data);
                } catch (e) {
                    toastr.error('Address lookup returned an invalid response');
                    close();
                    return;
                }

                if (!obj.success) {
                    toastr.error(obj.message);
                    close();
                    return;
                }

                render(obj.data);
            });
        }

        input.on('input', function () {

            var query = $(this).val().trim();

            if (timer !== null) {
                clearTimeout(timer);
            }

            if (query.length < MIN_CHARS) {
                sequence++;
                close();
                return;
            }

            timer = setTimeout(function () {
                lookup(query);
            }, DEBOUNCE_MS);
        });

        input.on('keydown', function (event) {

            if (list.prop('hidden')) {
                return;
            }

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                highlight(active + 1 >= items.length ? 0 : active + 1);
                return;
            }

            if (event.key === 'ArrowUp') {
                event.preventDefault();
                highlight(active - 1 < 0 ? items.length - 1 : active - 1);
                return;
            }

            if (event.key === 'Enter' && active > -1) {
                event.preventDefault();
                choose(active);
                return;
            }

            if (event.key === 'Escape' || event.key === 'Tab') {
                close();
            }
        });

        list.on('mousedown', '.addr-suggest__item', function (event) {
            event.preventDefault();
            choose(list.children('li').index(this));
        });

        list.on('mouseenter', '.addr-suggest__item', function () {
            highlight(list.children('li').index(this));
        });

        input.on('blur', function () {
            setTimeout(close, 150);
        });
    }

    return { attach: attach };

})();
