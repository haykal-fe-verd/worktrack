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
        if (strlen($value) <= 8) {
            return '******';
        }

        return substr($value, 0, 4).'******'.substr($value, -4);
    }
}
