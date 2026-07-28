<div class="page">
    <a class="page__back" href="/clients"><i class="fa-regular fa-arrow-left"></i> Back to Clients</a>

    <div class="page__head">
        <div>
            <h1 class="page__title"><?php echo htmlspecialchars($this->client['company_name'], ENT_QUOTES, 'UTF-8'); ?></h1>
        </div>
        <div class="page__actions">
            <a class="btn btn--primary" href="/clients/form/id/<?php echo (int) $this->client['id']; ?>/from/detail"><i class="fa-regular fa-pen"></i> Edit</a>
            <button type="button" class="btn btn--destructive" id="do_delete_open"><i class="fa-regular fa-trash"></i> Delete</button>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="panel record__panel">
                <div class="panel__head">
                    <h2 class="panel__title">Company Details</h2>
                </div>
                <div class="panel__body">
                    <dl class="datalist datalist--record">
                        <dt>Company Name</dt>
                        <dd><?php echo htmlspecialchars($this->client['company_name'], ENT_QUOTES, 'UTF-8'); ?></dd>
                        <dt>Website</dt>
                        <dd><?php echo ($this->client['website'] === '' ? '<span class="roster__none">&mdash;</span>' : '<a href="'.htmlspecialchars($this->client['website'], ENT_QUOTES, 'UTF-8').'" target="_blank" rel="noopener noreferrer">'.htmlspecialchars($this->client['website'], ENT_QUOTES, 'UTF-8').'</a>'); ?></dd>
                    </dl>
                </div>
            </div>
            <div class="panel record__panel">
                <div class="panel__head">
                    <h2 class="panel__title">Address</h2>
                </div>
                <div class="panel__body">
                    <dl class="datalist datalist--record">
                        <dt>Street</dt>
                        <dd><?php echo ($this->client['address_1'] === '' ? '<span class="roster__none">&mdash;</span>' : htmlspecialchars($this->client['address_1'], ENT_QUOTES, 'UTF-8')); ?></dd>
                        <dt>Suite, Floor</dt>
                        <dd><?php echo ($this->client['address_2'] === '' ? '<span class="roster__none">&mdash;</span>' : htmlspecialchars($this->client['address_2'], ENT_QUOTES, 'UTF-8')); ?></dd>
                        <dt>City</dt>
                        <dd><?php echo ($this->client['city'] === '' ? '<span class="roster__none">&mdash;</span>' : htmlspecialchars($this->client['city'], ENT_QUOTES, 'UTF-8')); ?></dd>
                        <dt>State</dt>
                        <dd><?php echo ($this->client['state'] === '' ? '<span class="roster__none">&mdash;</span>' : htmlspecialchars($this->client['state'], ENT_QUOTES, 'UTF-8')); ?></dd>
                        <dt>Postal Code</dt>
                        <dd><?php echo ($this->client['postal_code'] === '' ? '<span class="roster__none">&mdash;</span>' : htmlspecialchars($this->client['postal_code'], ENT_QUOTES, 'UTF-8')); ?></dd>
                        <dt>Country</dt>
                        <dd><?php echo ($this->client['country'] === '' ? '<span class="roster__none">&mdash;</span>' : htmlspecialchars($this->client['country'], ENT_QUOTES, 'UTF-8')); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="panel record__panel">
                <div class="panel__head">
                    <h2 class="panel__title">Primary Contact</h2>
                </div>
                <div class="panel__body">
                    <dl class="datalist datalist--record">
                        <dt>Full Name</dt>
                        <dd><?php echo ($this->client['contact_name'] === '' ? '<span class="roster__none">&mdash;</span>' : htmlspecialchars($this->client['contact_name'], ENT_QUOTES, 'UTF-8')); ?></dd>
                        <dt>Job Title</dt>
                        <dd><?php echo ($this->client['contact_title'] === '' ? '<span class="roster__none">&mdash;</span>' : htmlspecialchars($this->client['contact_title'], ENT_QUOTES, 'UTF-8')); ?></dd>
                        <dt>Email Address</dt>
                        <dd><?php echo ($this->client['contact_email'] === '' ? '<span class="roster__none">&mdash;</span>' : '<a href="mailto:'.htmlspecialchars($this->client['contact_email'], ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars($this->client['contact_email'], ENT_QUOTES, 'UTF-8').'</a>'); ?></dd>
                        <dt>Phone</dt>
                        <dd><?php echo ($this->client['contact_phone'] === '' ? '<span class="roster__none">&mdash;</span>' : htmlspecialchars($this->client['contact_phone'], ENT_QUOTES, 'UTF-8')); ?></dd>
                    </dl>
                </div>
            </div>

        </div>
    </div>
</div>

<div class="modal fade" id="delete_modal" tabindex="-1" aria-labelledby="delete_modal_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="delete_modal_title">Delete Client</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Delete <strong><?php echo htmlspecialchars($this->client['company_name'], ENT_QUOTES, 'UTF-8'); ?></strong>? This cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="do_delete" class="btn btn--destructive">Delete Client</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {

    var client_id = <?php echo (int) $this->client['id']; ?>;

    function set_loading(target, loading) {
        if (loading) {
            $(target).addClass("is-loading").prop("disabled", true);
        } else {
            $(target).removeClass("is-loading").prop("disabled", false);
        }
    }

    $('#do_delete_open').click(function () {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('delete_modal')).show();
    });

    $('#do_delete').click(function () {

        set_loading('#do_delete', true);

        ApiDataSvc.apiCall('post', 'delete_client', { client_id: client_id }, function (data) {

            var obj = JSON.parse(data);

            set_loading('#do_delete', false);

            if (!obj.success) {
                toastr.error(obj.message);
                return;
            }

            window.location.href = '/clients';
        });
    });

});
</script>
