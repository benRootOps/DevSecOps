<?php

namespace App\Http\Middleware;

use App\Services\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function __construct(private readonly PermissionService $permissionService) {}

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $utilisateur = auth()->user();

        if (!$utilisateur) {
            return response()->json(['succes' => false, 'message' => 'Non authentifié.', 'code' => 'UNAUTHENTICATED'], 401);
        }

        foreach ($permissions as $code) {
            if ($this->permissionService->peut($utilisateur, trim($code))) {
                return $next($request);
            }
        }

        return response()->json([
            'succes'     => false,
            'message'    => 'Vous ne disposez pas des permissions nécessaires.',
            'code'       => 'FORBIDDEN',
            'permission' => implode(' | ', $permissions),
        ], 403);
    }
}
