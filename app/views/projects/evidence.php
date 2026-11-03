<div class="page">
    <a class="page__back" href="<?php echo (Main::get_param('from') === 'assessment' && Main::get_param('ref') ? '/projects/assessment/id/'.((int) Main::get_param('ref')) : '/projects/detail/id/'.((int) $this->project['id'])); ?>"><i class="fa-regular fa-arrow-left"></i> Back to <?php echo (Main::get_param('from') === 'assessment' && Main::get_param('ref') ? 'Assessment' : htmlspecialchars($this->project['project_name'], ENT_QUOTES, 'UTF-8')); ?></a>

    <div class="page__head">
        <div>
            <h1 class="page__title"><?php echo ($this->project['client_name'] === null ? htmlspecialchars($this->project['project_name'], ENT_QUOTES, 'UTF-8') : htmlspecialchars($this->project['client_name'], ENT_QUOTES, 'UTF-8')); ?> &middot; Evidence Vault</h1>
            <div class="client__meta">
                <span class="client__segment"><?php echo htmlspecialchars($this->project['project_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="client__segment" id="vault_count">&nbsp;</span>
            </div>
        </div>
    </div>

    <?php require __DIR__.'/vault.php'; ?>
</div>
