<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EtablissementScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $utilisateur = auth()->user();
        if ($utilisateur && $utilisateur->etablissement_id !== null) {
            $request->attributes->set('etablissement_id', $utilisateur->etablissement_id);
        }
        return $next($request);
    }
}
