<?php
/**
 * The public summary for AI assistants (public/llms.txt) must not fall behind the framework
 * library: it states the library's size and names every framework in the public catalogue.
 */
require_once ROOT.'/libs/Classes/FrameworkLibrary.php';

return array(
    'llms.txt states the number of frameworks in the library' => function () {
        $text = file_get_contents(ROOT.'/public/llms.txt');

        Assert::true(strpos($text, count(FrameworkLibrary::all()).' frameworks') !== false, 'llms.txt says "'.count(FrameworkLibrary::all()).' frameworks"');
    },

    'llms.txt names every framework in the public catalogue under its heading' => function () {
        $text = file_get_contents(ROOT.'/public/llms.txt');

        foreach (FrameworkLibrary::catalogue() as $group) {
            $at = strpos($text, "\n".$group['group']."\n");
            Assert::true($at !== false, 'llms.txt has the heading '.$group['group']);

            foreach ($group['names'] as $name) {
                Assert::true(strpos($text, '- '.$name, $at) !== false, 'llms.txt lists '.$name.' under '.$group['group']);
            }
        }
    }
);
