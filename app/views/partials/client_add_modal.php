<div class="modal fade" data-bs-backdrop="static" id="client_add_modal" tabindex="-1" aria-labelledby="client_add_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="client_add_title">New Client</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="company_name">Company Name</label>
                    <input type="text" class="form-control" id="company_name">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary me-auto" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="do_client_create" class="btn btn--primary">Create Client</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {

    $('#client_add_modal').on('shown.bs.modal', function () {
        $('#company_name').val('').removeClass('is-invalid').focus();
    });

    $('#do_client_create').click(function () {

        var name = $('#company_name').val().trim();

        if (name === '') {
            $('#company_name').addClass('is-invalid').focus();
            toastr.error('Enter the company name.');
            return;
        }

        set_loading('#do_client_create', true);

        ApiDataSvc.apiCall('post', 'save_client', {
            client_id: 0,
            company_name: name
        }, function (data) {

            var obj = JSON.parse(data);

            if (obj.success) {
                window.location.href = '/clients/detail/id/' + obj.client_id;
                return;
            }

            set_loading('#do_client_create', false);
            toastr.error(obj.message);
        });
    });
});
</script>
