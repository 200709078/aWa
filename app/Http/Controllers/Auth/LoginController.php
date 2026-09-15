<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'E-posta adresi gerekli.',
            'email.email' => 'Geçerli bir e-posta adresi girin.',
            'password.required' => 'Şifre gerekli.',
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return back()->withErrors([
                'email' => 'Girdiğiniz bilgiler kayıtlarımızla eşleşmiyor.',
            ])->onlyInput('email');
        }

        $schools = $user->accessibleSchools();

        if ($schools->isEmpty()) {
            return back()->withErrors([
                'email' => 'Bu kullanıcıya tanımlı okul yok. Yöneticinizle görüşün.',
            ])->onlyInput('email');
        }

        if ($schools->count() === 1) {
            Auth::login($user);
            $request->session()->regenerate();
            $request->session()->put('current_school_id', $schools->first()->id);

            return redirect()->intended('/');
        }

        $request->session()->put('pending_login_user_id', $user->id);

        return redirect()->route('schools.select');
    }

    public function showSchool(Request $request): Response|RedirectResponse
    {
        $user = Auth::user() ?? User::find($request->session()->get('pending_login_user_id'));

        if (! $user) {
            return redirect()->route('login');
        }

        $schools = $user->accessibleSchools();

        if ($schools->isEmpty()) {
            Auth::logout();

            return redirect()->route('login')->withErrors([
                'email' => 'Bu kullanıcıya tanımlı okul yok. Yöneticinizle görüşün.',
            ]);
        }

        return Inertia::render('Auth/SelectSchool', [
            'userName' => $user->name,
            'schools' => $schools->map(fn ($school) => [
                'id' => $school->id,
                'name' => $school->name,
                'kurum_kodu' => $school->kurum_kodu,
            ])->all(),
        ]);
    }

    public function storeSchool(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
        ], [
            'school_id.required' => 'Okul seçin.',
            'school_id.exists' => 'Seçilen okul bulunamadı.',
        ]);

        $user = Auth::user() ?? User::find($request->session()->get('pending_login_user_id'));

        if (! $user || ! $user->accessibleSchools()->pluck('id')->contains($data['school_id'])) {
            return back()->withErrors(['school_id' => 'Bu okula giriş yetkiniz yok.']);
        }

        $wasAuthenticated = Auth::check();

        if (! $wasAuthenticated) {
            Auth::login($user);
            $request->session()->regenerate();
        }

        $request->session()->put('current_school_id', $data['school_id']);
        $request->session()->forget('pending_login_user_id');

        return $wasAuthenticated ? back() : redirect()->intended('/');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
