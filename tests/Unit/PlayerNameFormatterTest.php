<?php

use App\Support\PlayerNameFormatter;

test('stripRegionalIndicatorFlags removes country flag emojis and normalizes spaces', function (): void {
    $input = "Eden Snider — \u{1F1FA}\u{1F1F8} Gavin / \u{1F1FA}\u{1F1F8} Shellie";
    expect(PlayerNameFormatter::stripRegionalIndicatorFlags($input))
        ->toBe('Eden Snider — Gavin / Shellie');
});

test('stripRegionalIndicatorFlags removes multiple adjacent flags', function (): void {
    $us = "\u{1F1FA}\u{1F1F8}";
    expect(PlayerNameFormatter::stripRegionalIndicatorFlags("A {$us} {$us} B"))->toBe('A B');
});

test('stripRegionalIndicatorFlags returns empty string for null or blank', function (): void {
    expect(PlayerNameFormatter::stripRegionalIndicatorFlags(null))->toBe('');
    expect(PlayerNameFormatter::stripRegionalIndicatorFlags(''))->toBe('');
});

test('stripRegionalIndicatorFlags leaves plain text unchanged', function (): void {
    expect(PlayerNameFormatter::stripRegionalIndicatorFlags('Ali / Hassan'))->toBe('Ali / Hassan');
});
