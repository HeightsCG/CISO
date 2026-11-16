<?php
/**
 * OWASP Application Security Verification Standard 5.0.0, from the JSON release the OWASP
 * ASVS project publishes. Shared by owasp-asvs-l1.php, owasp-asvs-l2.php and
 * owasp-asvs-l3.php.
 *
 * - Groups are the 17 chapters (V1 Encoding and Sanitization ...).
 * - Controls are the requirements (V1.1.1 ...) at or below the chosen level: each
 *   requirement names the lowest level (1, 2 or 3) that includes it, and the levels are
 *   cumulative, so Level 2 is every Level 1 and Level 2 requirement.
 * - Each control carries its `level` and its section (V1.1 and the section's name).
 */
return function (int $level): array {
    $data = json_decode(file_get_contents(Source::path('owasp-asvs/OWASP_Application_Security_Verification_Standard_5.0.0_en.json')), true);

    if (($data['Version'] ?? '') !== '5.0.0') {
        throw new RuntimeException('The ASVS release is not version 5.0.0');
    }

    $families = array();

    foreach ($data['Requirements'] as $chapter) {
        $controls = array();

        foreach ($chapter['Items'] as $section) {
            foreach ($section['Items'] as $requirement) {
                $own = (int) $requirement['L'];

                if ($own < 1 || $own > 3) {
                    throw new RuntimeException($requirement['Shortcode'].' has no level');
                }

                if ($own > $level) {
                    continue;
                }

                $controls[] = array(
                    'id' => $requirement['Shortcode'],
                    'number' => $requirement['Shortcode'],
                    'text' => Source::normalise($requirement['Description']),
                    'type' => 'requirement',
                    'level' => $own,
                    'section' => $section['Shortcode'],
                    'section_name' => Source::normalise($section['Name']),
                    'weight' => null,
                    'partial_weight' => null,
                    'objectives' => array()
                );
            }
        }

        if (count($controls) > 0) {
            $families[] = array(
                'number' => $chapter['Shortcode'],
                'code' => $chapter['Shortcode'],
                'name' => Source::normalise($chapter['Name']),
                'controls' => $controls
            );
        }
    }

    return $families;
};
