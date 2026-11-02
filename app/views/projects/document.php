<div class="page page--document">
    <a class="page__back" href="/projects/assessment/id/<?php echo (int) $this->assessment['id']; ?>"><i class="fa-regular fa-arrow-left"></i> Back to <?php echo htmlspecialchars($this->assessment['assessment_name'], ENT_QUOTES, 'UTF-8'); ?></a>

    <div class="page__head">
        <div>
            <h1 class="page__title"><?php echo htmlspecialchars($this->document['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
            <div class="client__meta">
                <span class="client__segment"><?php echo htmlspecialchars((string) $this->assessment['client_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="client__segment"><?php echo htmlspecialchars(FrameworkLibrary::display_name($this->assessment['standard_name'], $this->assessment['version']), ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="client__segment"><?php echo count($this->sections); ?> sections</span>
            </div>
        </div>
        <div class="page__actions">
            <button type="button" class="btn btn--primary" id="do_document_save"><i class="fa-regular fa-floppy-disk"></i> Save</button>
        </div>
    </div>

    <div class="panel record__panel document__panel">
        <div class="panel__body document__scroll">
            <?php foreach ($this->sections as $i => $section) { ?>
            <div class="row mb-4">
                <div class="col-md-12 form-group">
                    <label for="section_<?php echo (int) $i; ?>"><?php echo htmlspecialchars($section['title'], ENT_QUOTES, 'UTF-8'); ?></label>
                    <textarea class="form-control document__section" id="section_<?php echo (int) $i; ?>" data-key="<?php echo htmlspecialchars($section['key'], ENT_QUOTES, 'UTF-8'); ?>" rows="6"><?php echo htmlspecialchars($section['body'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                    <?php if ($section['updated_on'] !== '') { ?>
                    <p class="field__help">Last saved <?php echo htmlspecialchars($section['updated_on'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php } ?>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {

    var assessment_id = <?php echo (int) $this->assessment['id']; ?>;

    $('#do_document_save').click(function () {

        var sections = {};

        $('.document__section').each(function () {
            sections[$(this).attr('data-key')] = $(this).val().trim();
        });

        set_loading('#do_document_save', true);

        ApiDataSvc.apiCall('post', 'save_document', { assessment_id: assessment_id, sections: sections }, function (data) {

            var obj = JSON.parse(data);

            set_loading('#do_document_save', false);

            if (obj.success) {
                toastr.success(obj.message);
            } else {
                toastr.error(obj.message);
            }
        });
    });

});
</script>
