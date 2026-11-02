<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('staff.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        // Demo accounts from the local seeder must never work on a live site.
        if (app()->isProduction() && str_ends_with(strtolower($credentials['email']), '@expo.test')) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Invalid staff credentials.']);
        }

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended(route('staff.dashboard'));
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => 'Invalid staff credentials.']);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('staff.login');
    }
}
