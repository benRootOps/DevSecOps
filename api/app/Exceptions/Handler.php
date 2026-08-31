<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontFlash = ['mot_de_passe', 'mot_de_passe_hash'];

    public function render($request, Throwable $e): JsonResponse|\Illuminate\Http\Response
    {
        if ($request->is('api/*')) return $this->renderJson($e);
        return parent::render($request, $e);
    }

    private function renderJson(Throwable $e): JsonResponse
    {
        if ($e instanceof ValidationException) {
            return response()->json(['succes' => false, 'message' => 'Données invalides.', 'erreurs' => $e->errors()], 422);
        }
        if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
            return response()->json(['succes' => false, 'message' => 'Ressource introuvable.'], 404);
        }
        if ($e instanceof AuthenticationException) {
            return response()->json(['succes' => false, 'message' => 'Non authentifié.'], 401);
        }
        $statut = method_exists($e, 'getStatusCode') ? $e->getStatusCode()
                : ($e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500);
        return response()->json([
            'succes'  => false,
            'message' => app()->isProduction() ? 'Une erreur interne est survenue.' : $e->getMessage(),
        ], $statut ?: 500);
    }
}
