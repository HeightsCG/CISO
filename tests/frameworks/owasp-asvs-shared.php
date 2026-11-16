<?php
/**
 * OWASP ASVS 5.0.0 checks, shared by the three levels. The parser walks the JSON release;
 * these count the requirements per level a second way and hold the levels to each other.
 */
return function (string $key, int $level, int $expected): array {
    $definition = function (string $key) {
        return json_decode(file_get_contents(ROOT.'/frameworks/'.$key.'.json'), true);
    };

    $ids = function (array $d): array {
        $ids = array();

        foreach ($d['families'] as $family) {
            foreach ($family['controls'] as $control) {
                $ids[] = $control['id'];
            }
        }

        return $ids;
    };

    return array(
        'Level '.$level.' holds '.$expected.' requirements' => function () use ($definition, $key, $expected) {
            Assert::same($expected, $definition($key)['counts']['controls'], 'requirements');
        },

        'every requirement is at or below Level '.$level.' and counted from the release' => function () use ($definition, $key, $level) {
            $release = json_decode(file_get_contents(Source::path('owasp-asvs/OWASP_Application_Security_Verification_Standard_5.0.0_en.json')), true);
            preg_match_all('/"Shortcode": ?"(V\d+\.\d+\.\d+)",[^}]*?"L": ?"(\d)"/s', json_encode($release, JSON_PRETTY_PRINT), $m, PREG_SET_ORDER);
            $wanted = array();

            foreach ($m as $row) {
                if ((int) $row[2] <= $level) {
                    $wanted[] = $row[1];
                }
            }

            $built = array();

            foreach ($definition($key)['families'] as $family) {
                foreach ($family['controls'] as $control) {
                    Assert::true($control['level'] <= $level, $control['id'].' level');
                    Assert::true(strpos($control['id'], $family['code'].'.') === 0, $control['id'].' is in chapter '.$family['code']);
                    Assert::true(strpos($control['id'], $control['section'].'.') === 0, $control['id'].' is in section '.$control['section']);
                    $built[] = $control['id'];
                }
            }

            Assert::same($wanted, $built, 'requirements at or below the level');
        },

        'each level contains the one below it' => function () use ($definition, $ids, $level) {
            if ($level === 1) {
                return;
            }

            $lower = $ids($definition('owasp-asvs-l'.($level - 1)));
            $this_level = $ids($definition('owasp-asvs-l'.$level));
            Assert::same(array(), array_values(array_diff($lower, $this_level)), 'Level '.($level - 1).' requirements missing from Level '.$level);
        }
    );
};
