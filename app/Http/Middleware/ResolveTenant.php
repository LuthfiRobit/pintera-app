<?php

namespace App\Http\Middleware;

use App\Models\Lembaga;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if ($user && $user->widestScopeLevel() === 'yayasan' && $request->has('switch_lembaga')) {
            $value = $request->query('switch_lembaga');

            if ($value === 'all') {
                session()->forget('active_lembaga_id');
            } elseif (Lembaga::whereKey($value)->exists()) {
                session(['active_lembaga_id' => (int) $value]);
            }
        }

        return $next($request);
    }
}
