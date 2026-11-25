<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('workspace.index');
        }

        $testingLoginEnabled = (bool) config('hotel.testing_login_as');
        $testingUsers = $testingLoginEnabled
            ? User::with('department')->where('is_active', true)->orderBy('id')->get()
            : collect();

        return view('auth.login', [
            'locale' => session('locale', 'id'),
            'testingLoginEnabled' => $testingLoginEnabled,
            'testingUsers' => $testingUsers,
        ]);
    }

    public function loginAs(Request $request): RedirectResponse
    {
        abort_unless((bool) config('hotel.testing_login_as'), 404);

        $data = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
        ]);

        $user = User::where('email', $data['email'])->where('is_active', true)->firstOrFail();
        Auth::login($user);
        $request->session()->regenerate();
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);
        session(['locale' => $user->locale ?: 'id']);
        AuditLog::record($user, 'auth.login_as', 'Auth', "Login testing sebagai {$user->name} ({$user->roleLabel()}).");

        return redirect()->route('workspace.index');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            AuditLog::record(null, 'auth.failed', 'Auth', 'Percobaan login gagal untuk email: '.$credentials['email']);

            return back()->withErrors([
                'email' => session('locale', 'id') === 'en'
                    ? 'Invalid email or password.'
                    : 'Email atau kata sandi tidak sesuai.',
            ])->onlyInput('email');
        }

        /** @var User $user */
        $user = Auth::user();
        if (! $user->is_active) {
            Auth::logout();

            return back()->withErrors([
                'email' => session('locale', 'id') === 'en'
                    ? 'This account is inactive. Please contact IT Administrator.'
                    : 'Akun Anda nonaktif. Hubungi IT Administrator.',
            ]);
        }

        $request->session()->regenerate();
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);
        session(['locale' => $user->locale ?: 'id']);
        AuditLog::record($user, 'auth.login', 'Auth', "Login berhasil sebagai {$user->roleLabel()}.");

        return redirect()->intended(route('workspace.index'));
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);

        /** @var User $user */
        $user = $request->user();
        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        AuditLog::record($user, 'auth.password_changed', 'Auth', 'Pengguna memperbarui kata sandi akun.');

        return response()->json([
            'message' => session('locale', 'id') === 'en'
                ? 'Password updated successfully.'
                : 'Kata sandi berhasil diperbarui.',
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        if ($user) {
            AuditLog::record($user, 'auth.logout', 'Auth', 'Pengguna keluar dari sistem.');
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function switchLocale(Request $request, string $locale): RedirectResponse
    {
        if (! in_array($locale, ['id', 'en'], true)) {
            $locale = 'id';
        }

        session(['locale' => $locale]);
        if (Auth::check()) {
            Auth::user()->update(['locale' => $locale]);
        }

        return back();
    }
}
