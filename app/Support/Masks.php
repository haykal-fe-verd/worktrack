<?php

namespace App\Support;

class Masks
{
    /**
     * Mask a sensitive value, keeping the first and last 4 characters
     * visible (or fully masking short values) using a fixed 6-star run
     * so the original length can't be inferred from the mask.
     */
    public static function partial(string $value): string
    {
        $length = strlen($value);

        if ($length <= 8) {
            return '******';
        }

        // Show last 4 chars for normal length, last 7 for very long strings
        $lastChars = $length > 20 ? 7 : 4;

        return substr($value, 0, 4).'******'.substr($value, -$lastChars);
    }
}
