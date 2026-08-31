<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function login(LoginRequest $request): JsonResponse 
{ 
    try { 
        $resultat = $this->authService->login(
            $request->input('email'), 
            $request->input('mot_de_passe'), 
            $request
        ); 
        return response()->json([
            'succes' => true, 
            'message' => 'Connexion réussie.', 
            'donnees' => $resultat
        ]); 
    } catch (\RuntimeException $e) { 
        $code = $e->getCode();

        // Si le code n'est pas un code HTTP valide, on met 500
        if ($code < 100 || $code > 599) {
            $code = 500;
        }

        // Cas spécial : erreur MySQL 1045 = Access Denied
        if ($code === 1045) {
            return response()->json([
                'succes' => false, 
                'message' => 'Erreur de connexion à la base de données'
            ], 500);
        }

        return response()->json([
            'succes' => false, 
            'message' => $e->getMessage()
        ], $code ?: 401); 
    } 
}}