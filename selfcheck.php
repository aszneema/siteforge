<?php
// Quick sanity check for the text rewriting helpers: php selfcheck.php
PHP_SAPI === 'cli' || exit;
require __DIR__ . '/lib.php';

$check = fn (bool $ok, string $what) => $ok || exit("FAIL: $what\n");

$check(slugify("Sara's Bakery!") === 'sara-s-bakery', 'slug from a name');
$check(slugify('مخبز') === 'site', 'slug falls back for non-Latin names');
$check(route_forms('<form method="get" class="x">') === '<form method="post" class="x">', 'forms post to the inbox');
$check(route_forms('<form>', 'https://a.b/site.php/s/index.html') === '<form method="post" action="https://a.b/site.php/s/index.html">', 'downloaded forms post to the live inbox');
$check(route_forms('<form action="https://x.com" method="get">') === '<form action="https://x.com" method="get">', 'forms with their own action are left alone');
$check(fonts_url(".a{font-family:'Playfair Display'}") === 'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700;800&display=swap', 'only used fonts load');
$check(fonts_url('body{color:red}') === '', 'no fonts, no link');
echo "All checks passed\n";
