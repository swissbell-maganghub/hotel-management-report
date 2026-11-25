<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            $locale = in_array($user->locale, ['id', 'en'], true)
                ? $user->locale
                : (session('locale', 'id') === 'en' ? 'en' : 'id');
            $message = $locale === 'en'
                ? 'Your account has been deactivated by the IT Administrator.'
                : 'Akun Anda telah dinonaktifkan oleh IT Administrator.';

            AuditLog::record($user, 'auth.forced_logout', 'Auth', 'Sesi dihentikan karena akun dinonaktifkan.');
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            session(['locale' => $locale]);

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 401);
            }

            return redirect()->route('login')->withErrors([
                'email' => $message,
            ]);
        }

        return $next($request);
    }
}
