<?php
$shared = require __DIR__.'/sp800171-shared.php';
$tests = $shared('cmmc-l2', function (array $control) {
    return $control['family_code'].'.L2-'.$control['number'];
});

$tests['POA&M rules match 32 CFR 170.21'] = function () {
    $meta = FrameworkBuilder::meta('cmmc-l2');
    $rules = array();

    foreach ($meta['rules'] as $rule) {
        $rules[$rule['id']] = $rule;
    }

    $cfr = Source::normalise(Source::xml_text('cmmc/32-cfr-170.xml'));
    $section = Source::between($cfr, '(iii) None of the following security requirements are included in the POA&M', '(3) Level 3 certification assessment');
    preg_match_all('/\(([A-F])\) ([A-Z]{2}\.L2-3\.\d+\.\d+)/', $section, $m);
    Assert::same($m[2], $rules['poam_eligibility']['excluded'], 'requirements that can never go on a POA&M');
    Assert::same(1, $rules['poam_eligibility']['max_weight'], 'POA&M point limit');
    Assert::same(0.8, $rules['conditional_threshold']['value'], 'Conditional threshold');
    Assert::same(180, $rules['poam_closeout']['days'], 'POA&M closeout days');
    Assert::same(3, $rules['partial_credit']['controls']['IA.L2-3.5.3']['partial'], 'MFA partial value');
    Assert::same(3, $rules['partial_credit']['controls']['SC.L2-3.13.11']['partial'], 'FIPS partial value');
};

return $tests;
