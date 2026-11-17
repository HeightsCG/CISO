<?php
/**
 * Public framework pages (/compliance/<slug>): every catalogue entry has a page with a unique
 * slug, a search-length description and FAQ, the sitemap lists every page, and no page carries
 * requirement text (pages hold section names and counts only, so licensed text never leaks).
 */
require_once ROOT.'/libs/Classes/FrameworkLibrary.php';

$slugs = function () {
    $slugs = array();

    foreach (FrameworkLibrary::catalogue() as $group) {
        foreach ($group['names'] as $name) {
            $slugs[] = FrameworkLibrary::slug($name);
        }
    }

    return $slugs;
};

return array(
    'every catalogue entry has a page with a unique slug' => function () use ($slugs) {
        $all = $slugs();

        Assert::same(count($all), count(array_unique($all)), 'unique slugs');

        foreach ($all as $slug) {
            $page = FrameworkLibrary::page($slug);
            Assert::true($page !== null, $slug.' builds');
            Assert::true(mb_strlen($page['description']) <= 160, $slug.' description fits a search snippet');
            Assert::true(count($page['faq']) >= 3, $slug.' has FAQ');
            Assert::true($page['requirements'] > 0, $slug.' has requirements');
        }

        Assert::true(FrameworkLibrary::page('no-such-framework') === null, 'unknown slug');
    },

    'pages carry section names and counts, never requirement text' => function () use ($slugs) {
        foreach ($slugs() as $slug) {
            foreach (FrameworkLibrary::page($slug)['parts'] as $part) {
                foreach ($part['families'] as $family) {
                    Assert::same(array('code', 'name', 'count'), array_keys($family), $slug.' sections hold names and counts only');
                }

                Assert::true(!isset($part['controls_text']) && !array_key_exists('text', $part), $slug.' holds no requirement text');
            }
        }
    },

    'the committed public catalogue matches a rebuild from the definitions' => function () {
        Assert::true(FrameworkBuilder::encode(FrameworkBuilder::catalogue()) === file_get_contents(ROOT.'/frameworks/catalogue/catalogue.json'), 'frameworks/catalogue/catalogue.json differs from a rebuild; run php frameworks/build.php');
    },

    'the sitemap lists the hub and every framework page' => function () use ($slugs) {
        $sitemap = file_get_contents(ROOT.'/public/sitemap.xml');

        Assert::true(strpos($sitemap, '<loc>https://ciso.aero/compliance</loc>') !== false, 'hub in sitemap');

        foreach ($slugs() as $slug) {
            Assert::true(strpos($sitemap, '<loc>https://ciso.aero/compliance/'.$slug.'</loc>') !== false, $slug.' in sitemap');
        }
    }
);
