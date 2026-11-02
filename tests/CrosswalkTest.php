<?php
/**
 * The crosswalk must match a rebuild from its mapping files, the files must
 * match their checksums, and coverage must hold where NIST maps every control.
 */
require_once ROOT.'/frameworks/lib/Crosswalk.php';

$crosswalk = function () {
    return json_decode(file_get_contents(ROOT.'/frameworks/crosswalk/crosswalk.json'), true);
};

$linked = function (string $set, int $side) use ($crosswalk) {
    $ids = array();

    foreach ($crosswalk()['links'] as $link) {
        if ($link[0] === $set) {
            $ids[$link[$side]] = true;
        }
    }

    return $ids;
};

$controls = function (string $key) {
    $ids = array();

    foreach (json_decode(file_get_contents(ROOT.'/frameworks/'.$key.'.json'), true)['families'] as $family) {
        foreach ($family['controls'] as $control) {
            $ids[] = $control['id'];
        }
    }

    return $ids;
};

return array(
    'committed crosswalk matches a rebuild' => function () {
        $built = FrameworkBuilder::encode(Crosswalk::build());
        Assert::true($built === file_get_contents(ROOT.'/frameworks/crosswalk/crosswalk.json'), 'frameworks/crosswalk/crosswalk.json differs from a rebuild; run php frameworks/build.php');
    },

    'mapping files match their checksums' => function () {
        foreach (Crosswalk::config()['sources'] as $source) {
            if (isset($source['file'])) {
                Assert::same($source['sha256'], hash_file('sha256', Source::path($source['file'])), $source['file'].' checksum');
            }
        }
    },

    'every allowed unresolved reference is explained' => function () {
        $config = Crosswalk::config();

        foreach ($config['unresolved_allowed'] as $entry) {
            Assert::true(isset($config['unresolved_notes'][$entry]), 'no note for '.$entry);
        }
    },

    'every SP 800-171 Rev 2 requirement maps to Rev 3 unless withdrawn with no successor' => function () use ($linked, $controls) {
        $nco = array();

        foreach (Source::xlsx_sheet('crosswalk/sp800-171r2-to-r3-analysis.xlsx', 'Change Analysis 800-171 R2-R3') as $row) {
            if (trim($row['H'] ?? '') === 'Withdrawn' && !preg_match('/03\.\d{2}/', $row['I'] ?? '')) {
                $nco[] = trim($row['C']);
            }
        }

        $missing = array_values(array_diff($controls('nist-800-171-r2'), array_keys($linked('171r2-171r3', 1))));
        sort($missing);
        sort($nco);
        Assert::same($nco, $missing, 'Rev 2 requirements with no Rev 3 link');
    },

    'every SP 800-171 Rev 3 requirement traces to SP 800-53' => function () use ($linked, $controls) {
        $missing = array_diff($controls('nist-800-171-r3'), array_keys($linked('53-171r3', 2)));
        Assert::same(array(), array_values($missing), 'Rev 3 requirements with no SP 800-53 source');
    },

    'every CMMC requirement links to SP 800-171 Rev 2' => function () use ($linked, $controls) {
        Assert::same(array(), array_values(array_diff($controls('cmmc-l2'), array_keys($linked('cmmcl2-171r2', 1)))), 'CMMC L2');
        Assert::same(array(), array_values(array_diff($controls('cmmc-l1'), array_keys($linked('cmmcl1-171r2', 1)))), 'CMMC L1');
    },

    'every FedRAMP control links to SP 800-53' => function () use ($linked, $controls) {
        foreach (array('low' => 'fedramplow-53', 'moderate' => 'fedrampmod-53', 'high' => 'fedramphigh-53') as $level => $set) {
            Assert::same(count($controls('fedramp-'.$level)), count($linked($set, 1)), 'FedRAMP '.$level);
        }
    },

    'every set has links' => function () use ($crosswalk) {
        $data = $crosswalk();

        foreach ($data['sets'] as $set) {
            Assert::true(($data['counts'][$set['id']] ?? 0) > 0, 'set '.$set['id'].' is empty');
        }
    }
);
