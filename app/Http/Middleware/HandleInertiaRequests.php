<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => fn () => $request->user()?->only('id', 'name', 'email', 'role'),
            ],
            'current_school' => function () use ($request) {
                $schoolId = $request->session()->get('current_school_id');
                if (! $schoolId || ! $request->user()) {
                    return null;
                }

                return \App\Models\School::find($schoolId)?->only('id', 'name', 'kurum_kodu');
            },
            'my_schools' => function () use ($request) {
                if (! $request->user()) {
                    return [];
                }

                return $request->user()->accessibleSchools()->map(fn ($school) => $school->only('id', 'name', 'kurum_kodu'))->all();
            },
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
