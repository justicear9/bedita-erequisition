<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInventoryManager
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role !== UserRole::InventoryManager) {
            abort(403, 'Head of Inventory access required.');
        }

        return $next($request);
    }
}
