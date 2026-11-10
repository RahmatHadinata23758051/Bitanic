<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->guest(route('login'))->with('error', 'Silakan login terlebih dahulu.');
        }

        $allowedRoles = $roles;

        // Superusers and admins inherit teacher routes within their tenant, but not vice versa.
        if (in_array('guru', $allowedRoles, true)) {
            $allowedRoles[] = 'superuser';
            $allowedRoles[] = 'admin';
            $allowedRoles[] = 'admin_sekolah';
        }

        // Dual-compatibility: Treat 'admin' and 'admin_sekolah' as interchangeable synonyms for tenant admin routes
        if (in_array('admin', $allowedRoles, true) || in_array('admin_sekolah', $allowedRoles, true)) {
            $allowedRoles[] = 'admin';
            $allowedRoles[] = 'admin_sekolah';
        }

        if (! in_array($user->role, array_unique($allowedRoles), true)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Akses ditolak. Peran Anda ('.$user->role.') tidak memiliki izin untuk tindakan ini.',
                ], 403);
            }

            abort(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk mengakses halaman atau aksi ini.');
        }

        return $next($request);
    }
}
