<?php

namespace App\Support;

use Illuminate\Http\Request;

class PerPage
{
    /**
     * @var array<int, int>
     */
    public const OPTIONS = [10, 20, 50, 100];

    public const DEFAULT = 10;

    /**
     * Resolve a validated `per_page` value from the request, falling back
     * to the default whenever it's missing or not one of the allowed
     * options — never trusts an arbitrary user-supplied page size.
     */
    public static function resolve(Request $request): int
    {
        $perPage = (int) $request->input('per_page', self::DEFAULT);

        return in_array($perPage, self::OPTIONS, true) ? $perPage : self::DEFAULT;
    }
}
