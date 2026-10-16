<?php

use statikbe\trinity\layout\LayoutConfig;

it('uses the defaults for missing settings', function() {
    $config = LayoutConfig::fromArray([]);
    expect($config->minColumnWidth)->toBe(0.25)
        ->and($config->narrowFromViewport)->toBe(980)
        ->and($config->columnGap)->toBe(48)
        ->and($config->warnings)->toBe([]);
});

it('accepts a single blocked pair without the outer brackets', function() {
    expect(LayoutConfig::fromArray(['blockedCombinations' => ['cellQuote', 'cellFaq']])->blockedCombinations)
        ->toBe([['cellQuote', 'cellFaq']]);
});

it('skips invalid blocked combinations with a warning', function() {
    $config = LayoutConfig::fromArray(['blockedCombinations' => [['cellQuote', 'cellFaq'], ['cellText'], 'cellImage']]);
    expect($config->blockedCombinations)->toBe([['cellQuote', 'cellFaq']])
        ->and($config->warnings)->toHaveCount(2);
});

it('skips page widths outside minColumnWidth to 1 with a warning', function() {
    $config = LayoutConfig::fromArray(['pageWidths' => ['entryTypes' => ['a' => 2 / 3, 'b' => 1 / 8, 'c' => 1.5, 'd' => 'wide']]]);
    expect(array_keys($config->pageWidths['entryTypes']))->toBe(['a'])
        ->and($config->pageWidths['sections'])->toBe([])
        ->and($config->warnings)->toHaveCount(3);
});

it('sorts containers by viewport width', function() {
    expect(array_keys(LayoutConfig::fromArray(['containers' => [1200 => 1168, 480 => 448]])->containers))->toBe([480, 1200]);
});

it('knows the cell types that can only be full width', function() {
    $config = LayoutConfig::fromArray(['minWidthPerType' => ['cellOverview' => 1, 'cellSum' => 1 / 3 + 2 / 3, 'cellCard' => 1 / 2]]);
    expect($config->isFullWidthOnly('cellOverview'))->toBeTrue()
        ->and($config->isFullWidthOnly('cellSum'))->toBeTrue()
        ->and($config->isFullWidthOnly('cellCard'))->toBeFalse()
        ->and($config->isFullWidthOnly('cellText'))->toBeFalse();
});

it('keeps column gaps of known layouts and reports the others', function() {
    $config = LayoutConfig::fromArray(['columnGaps' => ['thirds' => 24, 'quarters' => 16, 'halves' => 'wide']]);
    expect($config->columnGaps)->toBe(['thirds' => 24])
        ->and($config->warnings)->toHaveCount(2);
});
