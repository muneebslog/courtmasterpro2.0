<?php

namespace App\Support;

class PlayerNameFormatter
{
    public static function normalizeFlag(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }

    public static function formatWithFlag(string $name, ?string $flag): string
    {
        $normalizedFlag = self::normalizeFlag($flag);

        if ($normalizedFlag === null) {
            return $name;
        }

        return $normalizedFlag.' '.$name;
    }

    /**
     * Remove Unicode regional-indicator flag emojis (e.g. 🇺🇸) from a string.
     * Used for hall displays that show the flag only in the side column.
     */
    public static function stripRegionalIndicatorFlags(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $stripped = preg_replace('/\s*\p{Regional_Indicator}\p{Regional_Indicator}\s*/u', ' ', $value);

        if (! is_string($stripped)) {
            $stripped = $value;
        }

        $collapsed = preg_replace('/[ \t]{2,}/u', ' ', $stripped);

        if (! is_string($collapsed)) {
            $collapsed = $stripped;
        }

        return trim($collapsed);
    }
}
