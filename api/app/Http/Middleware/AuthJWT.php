<?php

namespace App\Http\Middleware;

use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthJWT
{
    public function __construct(private readonly JwtService $jwtService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $utilisateur = $this->jwtService->utilisateurDepuisToken();

        if (!$utilisateur) {
            return response()->json(['succes' => false, 'message' => 'Token invalide, expiré ou absent.', 'code' => 'UNAUTHENTICATED'], 401);
        }

        if (!$utilisateur->est_actif) {
            return response()->json(['succes' => false, 'message' => 'Ce compte est désactivé.', 'code' => 'ACCOUNT_DISABLED'], 403);
        }

        if ($utilisateur->etablissement_id !== null) {
            $etab = $utilisateur->etablissement;
            if (!$etab || !$etab->estValide()) {
                return response()->json(['succes' => false, 'message' => "L'abonnement de votre établissement est inactif.", 'code' => 'SUBSCRIPTION_INACTIVE'], 403);
            }
        }

        auth()->setUser($utilisateur);
        return $next($request);
    }
}
