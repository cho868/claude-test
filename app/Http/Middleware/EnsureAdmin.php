<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    /**
     * 管理者(is_admin)のみ通す。
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user() && $request->user()->is_admin, 403, '管理者専用ページです。');

        return $next($request);
    }
}
