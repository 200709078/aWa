<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        $users = User::with('schools:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role']);

        $schools = School::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Users/Index', [
            'users' => $users,
            'schools' => $schools,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $user = User::create([
            'name' => trim($data['name']),
            'email' => trim($data['email']),
            'password' => $data['password'],
            'role' => $data['role'],
        ]);

        $user->schools()->sync($data['school_ids'] ?? []);

        return back()->with('success', 'Kullanıcı eklendi.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);

        if ($user->id === $request->user()->id && $data['role'] !== 'super_admin') {
            return back()->withErrors(['role' => 'Kendi süper admin yetkinizi kaldıramazsınız.']);
        }

        $user->update([
            'name' => trim($data['name']),
            'email' => trim($data['email']),
            'role' => $data['role'],
        ]);

        if (! empty($data['password'])) {
            $user->update(['password' => $data['password']]);
        }

        $user->schools()->sync($data['school_ids'] ?? []);

        return back()->with('success', 'Kullanıcı güncellendi.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'Kendi hesabınızı silemezsiniz.']);
        }

        $user->delete();

        return back()->with('success', $user->name.' silindi.');
    }

    /**
     * @return array{name: string, email: string, password: ?string, role: string, school_ids: int[]}
     */
    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:100', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8', 'max:100'],
            'role' => ['required', 'string', Rule::in(['super_admin', 'school_admin'])],
            'school_ids' => [$request->input('role') === 'school_admin' ? 'required' : 'nullable', 'array', 'min:1'],
            'school_ids.*' => ['integer', 'exists:schools,id'],
        ], [
            'name.required' => 'Ad soyad gerekli.',
            'email.required' => 'E-posta gerekli.',
            'email.email' => 'Geçerli bir e-posta adresi girin.',
            'email.unique' => 'Bu e-posta zaten kayıtlı.',
            'password.required' => 'Şifre gerekli.',
            'password.min' => 'Şifre en az 8 karakter olmalı.',
            'role.required' => 'Rol seçin.',
            'role.in' => 'Geçersiz rol.',
            'school_ids.required' => 'Okul yöneticisi için en az bir okul seçin.',
            'school_ids.min' => 'Okul yöneticisi için en az bir okul seçin.',
        ]);

        return $data;
    }
}
