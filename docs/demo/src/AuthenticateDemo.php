<?php

namespace KanbanDemo;

use Closure;
use Illuminate\Support\Facades\Auth;

class AuthenticateDemo
{
    public function handle($request, Closure $next)
    {
        // This standalone localhost demo contains fictional records only.
        Auth::setUser(User::firstOrFail());

        return $next($request);
    }
}
