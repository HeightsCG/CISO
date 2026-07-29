"use strict";

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
