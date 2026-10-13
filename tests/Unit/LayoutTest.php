<?php

use statikbe\trinity\layout\Layout;

describe('allowedLayouts', function() {
    it('allows every layout on a full-width page', function() {
        expect(layout()->allowedLayouts(1))->toBe(array_keys(Layout::LAYOUTS));
    });

    it('allows every layout on a 3/4 page, as 1/3 of 3/4 is exactly the 1/4 minimum', function() {
        expect(layout()->allowedLayouts(3 / 4))->toBe(array_keys(Layout::LAYOUTS));
    });

    it('only allows full and halves on a 2/3 page', function() {
        expect(layout()->allowedLayouts(2 / 3))->toBe(['full', 'halves']);
    });

    it('follows minColumnWidth', function() {
        expect(layout(['minColumnWidth' => 1 / 2])->allowedLayouts(1))->toBe(['full', 'halves']);
    });
});

describe('columnWidths', function() {
    it('scales the layout by the page width', function() {
        expect(layout()->columnWidths('thirdTwoThirds', 3 / 4))->toEqualWithDelta([1 / 4, 1 / 2], 0.0001);
    });

    it('treats an unknown layout as full', function() {
        expect(layout()->columnWidths('nope'))->toBe([1.0]);
    });
});

describe('isTypeAllowed', function() {
    $config = ['minWidthPerType' => ['cellFaq' => 1 / 2], 'maxWidthPerType' => ['cellQuote' => 1 / 2]];

    it('checks the minimum width per type', function() use ($config) {
        expect(layout($config)->isTypeAllowed('cellFaq', 1 / 3))->toBeFalse()
            ->and(layout($config)->isTypeAllowed('cellFaq', 1 / 2))->toBeTrue();
    });

    it('checks the maximum width per type', function() use ($config) {
        expect(layout($config)->isTypeAllowed('cellQuote', 2 / 3))->toBeFalse()
            ->and(layout($config)->isTypeAllowed('cellQuote', 1 / 2))->toBeTrue();
    });

    it('allows other types at any width', function() use ($config) {
        expect(layout($config)->isTypeAllowed('cellText', 1 / 4))->toBeTrue()
            ->and(layout($config)->isTypeAllowed('cellText', 1))->toBeTrue();
    });

    it('counts 1/2 of a 2/3 page as 1/3', function() {
        expect(layout(['minWidthPerType' => ['cellImage' => 1 / 3]])->isTypeAllowed('cellImage', 1 / 2 * 2 / 3))->toBeTrue();
    });
});

describe('blocked combinations', function() {
    it('blocks a pair in either order', function() {
        $layout = layout(['blockedCombinations' => [['cellQuote', 'cellFaq']]]);
        expect($layout->isCombinationAllowed('cellQuote', 'cellFaq'))->toBeFalse()
            ->and($layout->isCombinationAllowed('cellFaq', 'cellQuote'))->toBeFalse()
            ->and($layout->isCombinationAllowed('cellQuote', 'cellText'))->toBeTrue();
    });

    it('checks every pair in a row', function() {
        $layout = layout(['blockedCombinations' => [['cellQuote', 'cellFaq']]]);
        expect($layout->areCombinationsAllowed(['cellQuote', 'cellText', 'cellFaq']))->toBeFalse()
            ->and($layout->areCombinationsAllowed(['cellQuote', 'cellText', 'cellText']))->toBeTrue();
    });

    it('allows at most one of a type blocked with itself', function() {
        $layout = layout(['blockedCombinations' => [['cellQuote', 'cellQuote']]]);
        expect($layout->areCombinationsAllowed(['cellQuote', 'cellQuote']))->toBeFalse()
            ->and($layout->areCombinationsAllowed(['cellQuote', 'cellText']))->toBeTrue();
    });
});

describe('mobileOrder', function() {
    it('returns null when every column is on auto', function() {
        expect(layout()->mobileOrder([0, 0, 0]))->toBeNull();
    });

    it('fills auto columns around the requested positions, left to right', function() {
        expect(layout()->mobileOrder([0, 0, 1]))->toBe([2, 3, 1]);
    });

    it('gives a tie to the left column and the next free position to the other', function() {
        expect(layout()->mobileOrder([1, 1, 0]))->toBe([1, 2, 3]);
    });

    it('takes the nearest free position before when none is free after', function() {
        expect(layout()->mobileOrder([3, 3, 0]))->toBe([3, 2, 1]);
    });

    it('treats a position past the number of columns as last', function() {
        expect(layout()->mobileOrder([3, 0]))->toBe([2, 1]);
    });
});

describe('breakpoint', function() {
    it('is the row width at which the narrowest column reaches columnMinWidth', function() {
        expect(layout()->breakpoint('halves'))->toBe(560)
            ->and(layout()->breakpoint('thirds'))->toBe(864)
            ->and(layout()->breakpoint('thirdTwoThirds'))->toBe(864)
            ->and(layout()->breakpoint('twoThirdsThird'))->toBe(864);
    });

    it('follows columnMinWidth and columnGap', function() {
        expect(layout(['columnMinWidth' => 200, 'columnGap' => 32])->breakpoint('halves'))->toBe(432);
    });
});

describe('imageSizes', function() {
    // Characterisation: sizes as rendered by the base install before the extraction (/nl/gridbuilder/ showcase pages)
    $config = ['pageWidths' => ['entryTypes' => ['pageWithSidebar' => 2 / 3]], 'narrowFromViewport' => 980];
    $base = '(max-width: 479px) 95vw, (min-width: 480px) and (max-width: 659px) 448px, ';

    it('sizes a column for every container width', function(string $layout, float $fraction, float $pageWidth, string $expected) use ($config, $base) {
        expect(layout($config)->imageSizes($layout, $fraction, $pageWidth)['sizes'])->toBe($base . $expected);
    })->with([
        'halves, full page' => ['halves', 1 / 2, 1, '(min-width: 660px) and (max-width: 819px) 290px, (min-width: 820px) and (max-width: 979px) 370px, (min-width: 980px) and (max-width: 1199px) 450px, (min-width: 1200px) 560px'],
        'thirds, full page' => ['thirds', 1 / 3, 1, '(min-width: 660px) and (max-width: 819px) 628px, (min-width: 820px) and (max-width: 979px) 788px, (min-width: 980px) and (max-width: 1199px) 284px, (min-width: 1200px) 357px'],
        'full, 2/3 page' => ['full', 1, 2 / 3, '(min-width: 660px) and (max-width: 819px) 628px, (min-width: 820px) and (max-width: 979px) 788px, (min-width: 980px) and (max-width: 1199px) 616px, (min-width: 1200px) 763px'],
        'halves, 2/3 page' => ['halves', 1 / 2, 2 / 3, '(min-width: 660px) and (max-width: 819px) 290px, (min-width: 820px) and (max-width: 979px) 370px, (min-width: 980px) and (max-width: 1199px) 284px, (min-width: 1200px) 358px'],
        'halves, 3/4 page' => ['halves', 1 / 2, 3 / 4, '(min-width: 660px) and (max-width: 819px) 290px, (min-width: 820px) and (max-width: 979px) 370px, (min-width: 980px) and (max-width: 1199px) 326px, (min-width: 1200px) 408px'],
        'thirds, 3/4 page' => ['thirds', 1 / 3, 3 / 4, '(min-width: 660px) and (max-width: 819px) 628px, (min-width: 820px) and (max-width: 979px) 788px, (min-width: 980px) and (max-width: 1199px) 699px, (min-width: 1200px) 256px'],
        'thirdTwoThirds 2/3 column, 3/4 page' => ['thirdTwoThirds', 2 / 3, 3 / 4, '(min-width: 660px) and (max-width: 819px) 628px, (min-width: 820px) and (max-width: 979px) 788px, (min-width: 980px) and (max-width: 1199px) 699px, (min-width: 1200px) 560px'],
    ]);

    it('lists every srcset width once', function() {
        expect(layout()->imageSizes('full', 1)['srcsetSizes'])->toBe(['448w', '628w', '788w', '948w', '1168w']);
    });

    it('uses mobileSize below the first container', function() {
        expect(layout(['mobileSize' => '100vw'])->imageSizes('full', 1)['sizes'])->toStartWith('(max-width: 479px) 100vw, ');
    });
});

describe('page widths', function() {
    $config = ['pageWidths' => ['entryTypes' => ['pageWithSidebar' => 2 / 3], 'sections' => ['news' => 3 / 4]]];

    it('prefers the entry type over the section', function() use ($config) {
        expect(layout($config)->configuredPageWidth('pageWithSidebar', 'news'))->toEqualWithDelta(2 / 3, 0.0001)
            ->and(layout($config)->configuredPageWidth('article', 'news'))->toBe(0.75)
            ->and(layout($config)->configuredPageWidth('article', 'pages'))->toBe(1.0);
    });

    it('lists every width once, widest first, including extra ones', function() use ($config) {
        expect(layout($config)->pageWidths([0.75, 1 / 2]))->toEqualWithDelta([1, 0.75, 2 / 3, 0.5], 0.0001);
    });
});

describe('formatWidth', function() {
    it('formats fractions', function(float $width, string $expected) {
        expect(layout()->formatWidth($width))->toBe($expected);
    })->with([
        [1, 'full'],
        [1 / 2, '1/2'],
        [2 / 3, '2/3'],
        [1 / 4, '1/4'],
        [3 / 8, '3/8'],
        [4 / 9, '4/9'],
        [0.37, '0.37'],
    ]);
});
