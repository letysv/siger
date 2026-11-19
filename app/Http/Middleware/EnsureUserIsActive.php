<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierra la sesión de las cuentas desactivadas (users.activo = false).
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->activo) {
            Auth::guard('web')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            Auth::forgetGuards();

            if ($request->expectsJson()) {
                abort(403, __('Tu cuenta está inactiva.'));
            }

            return redirect()->route('login')->withErrors([
                'email' => __('Tu cuenta está inactiva. Contacta al administrador del sistema.'),
            ]);
        }

        return $next($request);
    }
}
