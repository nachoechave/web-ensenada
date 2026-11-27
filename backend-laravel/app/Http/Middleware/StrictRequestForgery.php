<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Exceptions\OriginMismatchException;

class StrictRequestForgery extends PreventRequestForgery
{
    /** CSRF must also run in feature tests; Laravel normally skips it there. */
    protected function runningUnitTests(): bool
    {
        return false;
    }

    protected function hasValidOrigin($request): bool
    {
        if ($request->hasHeader('Origin') && $request->header('Origin') !== rtrim(config('app.url'), '/')) {
            throw new OriginMismatchException;
        }

        return parent::hasValidOrigin($request);
    }
}
