<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

final class TrustLocalProxies extends TrustProxies
{
    protected $proxies = '*';

    protected function setTrustedProxyIpAddresses(Request $request)
    {
        if (app()->environment(['local', 'testing'])) {
            parent::setTrustedProxyIpAddresses($request);
        }
    }
}
