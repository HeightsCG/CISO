<?php
/**
 * Brings a database up to date with the files in sql/. Each migration has a
 * check against the live schema; only migrations whose check fails are run,
 * in file order, and the run stops at the first error. Command line only,
 * with APPLICATION_ENV set as for the web app:
 *   php bin/migrate.php              run the pending migrations
 *   php bin/migrate.php --dry-run    only show what would run
 * Take a backup first: several migrations drop columns.
 */
if (php_sapi_name() !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
chdir($root);

if (is_file($root.'/vendor/autoload.php')) {
    require_once $root.'/vendor/autoload.php';
}

spl_autoload_register(function ($class) use ($root) {
    foreach (array(
        $root.'/app/models/'.$class.'.php',
        $root.'/app/'.$class.'.php',
        $root.'/libs/Classes/'.$class.'.php'
    ) as $source) {
        if (file_exists($source)) {
            require_once $source;
            return;
        }
    }
});

if ((string) getenv('APPLICATION_ENV') === '') {
    fwrite(STDERR, "APPLICATION_ENV is not set\n");
    exit(2);
}

$apply = !in_array('--dry-run', $argv, true);
$db = new Database();

$column = function (string $table, string $name) use ($db): bool {
    $q = $db->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c');
    $q->execute(array(
        't' => $table,
        'c' => $name
    ));

    return (int) $q->fetchColumn() > 0;
};

$table = function (string $name) use ($db): bool {
    $q = $db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t');
    $q->execute(array(
        't' => $name
    ));

    return (int) $q->fetchColumn() > 0;
};

$enum_has = function (string $table_name, string $name, string $value) use ($db): bool {
    $q = $db->prepare('SELECT column_type FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c');
    $q->execute(array(
        't' => $table_name,
        'c' => $name
    ));

    return strpos((string) $q->fetchColumn(), "'".$value."'") !== false;
};

/* file => true when already applied. Data-only files count as applied once a later schema change is present. */
$applied = array(
    '2026-08-18-login-security-controls' => function () use ($column) {
        return $column('user_accounts', 'password_changed_at');
    },
    '2026-11-02-ai-assistant' => function () use ($table) {
        return $table('ai_conversations');
    },
    '2026-11-03-ai-assistant-files' => function () use ($column) {
        return $column('ai_conversations', 'container_id');
    },
    '2026-11-04-reports' => function () use ($table, $column) {
        return $table('reports') || $column('standards', 'framework_key');
    },
    '2026-11-05-framework-library' => function () use ($column) {
        return $column('standards', 'framework_key');
    },
    '2026-11-06-assessment-results' => function () use ($column) {
        return $column('assessment_items', 'control_weight');
    },
    '2026-11-07-maturity-tiers' => function () use ($column, $table) {
        return $column('assessments', 'tier_governance') || !$column('assessments', 'posture_text') || $table('report_narratives');
    },
    '2026-11-08-project-frameworks' => function () use ($table, $column) {
        return $table('project_standards') || !$column('assessments', 'posture_text');
    },
    '2026-11-09-remove-writeup-and-project-frameworks' => function () use ($column, $table) {
        return !$column('assessments', 'assessment_scope') && !$column('assessments', 'decisions_text') && !$table('project_standards') && !$table('assessment_documents');
    },
    '2026-11-10-remove-observation-and-implementation' => function () use ($column) {
        return !$column('assessment_items', 'notes') && !$column('assessment_items', 'implementation_description');
    },
    '2026-11-11-remove-milestones' => function () use ($table) {
        return !$table('item_milestones');
    },
    '2026-11-12-objective-descriptions' => function () use ($column) {
        return !$column('clients', 'website');
    },
    '2026-11-13-remove-control-findings' => function () use ($column) {
        return !$column('assessment_items', 'gap_description') && !$column('assessment_items', 'owner_name');
    },
    '2026-11-14-remove-client-website' => function () use ($column) {
        return !$column('clients', 'website');
    },
    '2026-11-15-billing-contact-fields' => function () use ($column) {
        return $column('clients', 'billing_phone');
    },
    '2026-11-16-ongoing-projects' => function () use ($table) {
        return !$table('projects');
    },
    '2026-11-17-remove-project-status' => function () use ($table, $column) {
        return !$table('projects') || !$column('projects', 'project_status');
    },
    '2026-11-18-remove-projects' => function () use ($column) {
        return $column('assessments', 'client_id');
    },
    '2026-11-19-remove-report-builder' => function () use ($table) {
        return !$table('reports') && !$table('report_versions');
    },
    '2026-11-20-remove-maturity-tiers' => function () use ($column) {
        return !$column('assessments', 'tier_governance');
    },
    '2026-11-21-compare-assessments' => function () use ($column) {
        return $column('assessments', 'compare_assessment_id') && !$column('assessments', 'no_baseline');
    },
    '2026-11-22-remove-scope-categories' => function () use ($column) {
        return !$column('assessments', 'scope_categories');
    },
    '2026-11-23-partial-objectives' => function () use ($table) {
        return $table('report_narratives');
    },
    '2026-11-24-report-narratives' => function () use ($table) {
        return $table('report_narratives');
    },
    '2026-11-25-report-workflow' => function () use ($table) {
        return $table('report_jobs');
    },
    '2026-11-26-poam' => function () use ($table, $column) {
        return $table('poam_items') && $column('assessments', 'poam_clock_date');
    },
    '2026-11-27-report-preview-jobs' => function () use ($enum_has) {
        return $enum_has('report_jobs', 'job_type', 'Report');
    },
    '2026-11-28-control-comments' => function () use ($table) {
        return $table('item_comments');
    }
);

$files = array_map(function ($path) {
    return basename($path, '.sql');
}, glob($root.'/sql/*.sql'));
sort($files);

$unknown = array_diff($files, array_keys($applied));

if (count($unknown) > 0) {
    fwrite(STDERR, 'No check defined for: '.implode(', ', $unknown)."\nAdd one to bin/migrate.php before running.\n");
    exit(3);
}

$pending = array();

foreach ($files as $file) {
    $done = $applied[$file]();
    printf("%-12s %s\n", $done ? 'applied' : 'PENDING', $file);
    if (!$done) {
        $pending[] = $file;
    }
}

if (count($pending) === 0) {
    echo "\nThe database is up to date.\n";
    exit(0);
}

if (!$apply) {
    echo "\n".count($pending)." pending (dry run, nothing changed).\n";
    exit(0);
}

foreach ($pending as $file) {
    $sql = preg_replace('/^\s*--.*$/m', '', file_get_contents($root.'/sql/'.$file.'.sql'));
    $statements = array_filter(array_map('trim', preg_split('/;\s*(\r?\n|$)/', $sql)), 'strlen');

    echo "\nRunning ".$file." (".count($statements)." statements)\n";

    foreach ($statements as $index => $statement) {
        try {
            $db->exec($statement);
        } catch (PDOException $e) {
            fwrite(STDERR, '  Failed at statement '.($index + 1).': '.$e->getMessage()."\n  ".substr(preg_replace('/\s+/', ' ', $statement), 0, 200)."\n");
            fwrite(STDERR, "Stopped. Earlier migrations in this run were applied; fix the error and run again.\n");
            exit(1);
        }
    }

    if (!$applied[$file]()) {
        fwrite(STDERR, "  Ran without errors but its check still fails; stopping.\n");
        exit(1);
    }

    echo "  done\n";
}

echo "\nAll pending migrations applied.\n";
exit(0);
