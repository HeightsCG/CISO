<div class="page page--invoice">

    <header class="headline">
        <div class="headline__text">
            <h1 class="headline__title"><?php echo ($this->invoice === null ? 'New Invoice' : 'Edit Draft'); ?></h1>
        </div>
        <div class="headline__actions">
            <a class="btn btn--secondary btn--pill" href="<?php echo ($this->client_id > 0 ? '/clients/detail/id/'.$this->client_id : '/clients'); ?>"><i class="fa-regular fa-chevron-left"></i> Back</a>
            <button type="button" id="do_save" class="btn btn--primary btn--pill">Save Invoice</button>
            <?php if ($this->invoice !== null) { ?>
            <div class="dropdown">
                <button type="button" class="btn btn--round" data-bs-toggle="dropdown" aria-expanded="false" aria-label="More actions"><i class="fa-regular fa-ellipsis"></i></button>
                <ul class="dropdown-menu dropdown-menu-end headline__menu">
                    <li><a class="dropdown-item" href="/billing/invoice/id/<?php echo (int) $this->invoice['id']; ?>"><i class="fa-regular fa-xmark"></i> Discard Changes</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><button type="button" class="dropdown-item headline__danger" id="do_delete_open"><i class="fa-regular fa-trash"></i> Delete Invoice</button></li>
                </ul>
            </div>
            <?php } ?>
        </div>
    </header>

<?php require Main::app_path().'/app/views/partials/invoice_editor.php'; ?>
</div>

<?php require Main::app_path().'/app/views/partials/invoice_editor_script.php'; ?>
