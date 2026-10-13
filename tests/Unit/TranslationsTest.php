<?php

/**
 * Every message in the trinity category, in PHP (Craft::t) and the control panel JS (Craft.t), has a translation in every
 * language, and the JS ones are registered for the JS.
 */
function trinityMessages(string $pattern, string $glob): array
{
    $messages = [];
    foreach (glob(dirname(__DIR__, 2) . '/src/' . $glob, GLOB_BRACE) as $file) {
        preg_match_all($pattern, file_get_contents($file), $matches);
        array_push($messages, ...$matches[1]);
    }
    return array_values(array_unique($messages));
}

$php = trinityMessages("/Craft::t\\('trinity', '((?:[^'\\\\]|\\\\.)*)'/", '{*,*/*,*/*/*}.php');
$js = trinityMessages("/Craft\\.t\\('trinity', '((?:[^'\\\\]|\\\\.)*)'/", 'web/assets/cp/dist/*.js');

it('finds the messages', function() use ($php, $js) {
    expect(count($php))->toBeGreaterThan(10)->and(count($js))->toBeGreaterThan(10);
});

it('translates every message', function(string $language) use ($php, $js) {
    $translations = require dirname(__DIR__, 2) . "/src/translations/{$language}/trinity.php";
    $missing = array_diff(array_unique([...$php, ...$js]), array_keys($translations));
    expect($missing)->toBe([]);
})->with(['nl', 'fr']);

it('registers every JS message for the JS', function() use ($js) {
    $bundle = file_get_contents(dirname(__DIR__, 2) . '/src/web/assets/cp/TrinityCpAsset.php');
    foreach ($js as $message) {
        expect($bundle)->toContain("'{$message}'");
    }
});
