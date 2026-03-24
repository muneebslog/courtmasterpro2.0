<?php

namespace App\Support;

final class BracketStageNaming
{
    public static function isPowerOfTwo(int $value): bool
    {
        return $value > 0 && ($value & ($value - 1)) === 0;
    }

    public static function stageNameForPlayers(int $players): string
    {
        return match ($players) {
            2 => (string) __('Final'),
            4 => (string) __('Semi Final'),
            8 => (string) __('Quarter Final'),
            16 => (string) __('Round of 16'),
            32 => (string) __('Round of 32'),
            64 => (string) __('Round of 64'),
            default => (string) __('Round of :players', ['players' => (string) $players]),
        };
    }
}
