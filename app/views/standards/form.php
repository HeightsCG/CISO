<div class="page">
    <a class="page__back" href="<?php echo ($this->standard === null ? '/standards' : '/standards/detail/id/'.((int) $this->standard['id'])); ?>"><i class="fa-regular fa-arrow-left"></i> <?php echo ($this->standard === null ? 'Back to Standards' : 'Back to Standard'); ?></a>

    <div class="page__head">
        <div>
            <h1 class="page__title"><?php echo ($this->standard === null ? 'Add Standard' : 'Edit Standard'); ?></h1>
        </div>
    </div>

    <div class="panel">
        <div class="panel__body">
            <div class="record__group">
                <h2 class="record__group-title">Standard</h2>
                <div class="form-row">
                    <div class="field">
                        <label for="standard_name">Name <abbr title="required">*</abbr></label>
                        <input type="text" id="standard_name" placeholder="NIST SP 800-171" value="<?php echo ($this->standard === null ? '' : htmlspecialchars($this->standard['standard_name'], ENT_QUOTES, 'UTF-8')); ?>">
                    </div>
                    <div class="field">
                        <label for="short_code">Short Code</label>
                        <input type="text" id="short_code" placeholder="NIST-800-171" autocapitalize="characters" spellcheck="false" value="<?php echo ($this->standard === null ? '' : htmlspecialchars($this->standard['short_code'], ENT_QUOTES, 'UTF-8')); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="field">
                        <label for="version">Version</label>
                        <input type="text" id="version" placeholder="Rev 3" value="<?php echo ($this->standard === null ? '' : htmlspecialchars($this->standard['version'], ENT_QUOTES, 'UTF-8')); ?>">
                    </div>
                    <div class="field">
                        <label for="standard_status">Status</label>
                        <select id="standard_status" class="form-select">
                            <option value="Active"<?php echo ($this->standard === null || $this->standard['standard_status'] === 'Active' ? ' selected' : ''); ?>>Active</option>
                            <option value="Archived"<?php echo ($this->standard !== null && $this->standard['standard_status'] === 'Archived' ? ' selected' : ''); ?>>Archived</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="record__group">
                <h2 class="record__group-title">Description</h2>
                <div class="field">
                    <label for="description">Description</label>
                    <textarea id="description" rows="4" placeholder="What this framework covers and why the organisation works against it."><?php echo ($this->standard === null ? '' : htmlspecialchars($this->standard['description'], ENT_QUOTES, 'UTF-8')); ?></textarea>
                </div>
            </div>
            <div class="panel__actions">
                <a class="btn btn--secondary" href="<?php echo ($this->standard === null ? '/standards' : '/standards/detail/id/'.((int) $this->standard['id'])); ?>">Cancel</a>
                <button type="button" id="do_save" class="btn btn--primary">Save Standard</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {

    var standard_id = <?php echo ($this->standard === null ? 0 : (int) $this->standard['id']); ?>;

    $('#do_save').click(function () {

        $('.field input, .field textarea').removeClass('is-invalid');

        var values = {
            standard_id: standard_id,
            standard_name: $('#standard_name').val().trim(),
            short_code: $('#short_code').val().trim(),
            version: $('#version').val().trim(),
            description: $('#description').val().trim(),
            standard_status: $('#standard_status').val()
        };

        if (values.standard_name === '') {
            $('#standard_name').addClass('is-invalid').focus();
            toastr.error('Please fix the errors before continuing.');
            return;
        }

        set_loading('#do_save', true);

        ApiDataSvc.apiCall('post', 'save_standard', values, function (data) {

            var obj = JSON.parse(data);

            set_loading('#do_save', false);

            if (obj.success) {

                window.location.href = '/standards/detail/id/' + obj.standard_id;
            } else {
                toastr.error(obj.message);
            }
        });
    });

});
</script>
