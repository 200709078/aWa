<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSchoolContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $accessible = $user->accessibleSchools()->pluck('id')->all();
        $current = $request->session()->get('current_school_id');

        if ($current && in_array($current, $accessible, true)) {
            return $next($request);
        }

        if (count($accessible) === 1) {
            $request->session()->put('current_school_id', $accessible[0]);

            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Devam etmek için okul seçin.'], 409);
        }

        return redirect()->route('schools.select');
    }
}
