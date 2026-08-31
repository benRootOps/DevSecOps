<?php

namespace App\Services;

use App\Models\Utilisateur;
use Tymon\JWTAuth\Facades\JWTAuth; // <-- Facade et pas Contracts
use Tymon\JWTAuth\Exceptions\TokenExpiredException; // <-- Exceptions et pas Contracts
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;

class JwtService
{
    public function genererToken(Utilisateur $utilisateur): string
    {
        return JWTAuth::fromUser($utilisateur);
    }

    public function genererRefreshToken(Utilisateur $utilisateur): string
    {
        return JWTAuth::claims(['type' => 'refresh'])->fromUser($utilisateur);
    }

    public function utilisateurDepuisToken(): ?Utilisateur
    {
        try {
            return JWTAuth::parseToken()->authenticate();
        } catch (TokenExpiredException|TokenInvalidException|JWTException $e) { // <-- Ajoute $e
            return null;
        }
    }

    public function invaliderToken(): bool
    {
        try {
            JWTAuth::parseToken()->invalidate();
            return true;
        } catch (JWTException $e) {
            return false;
        }
    }

    public function rafraichirToken(): ?string
    {
        try {
            return JWTAuth::parseToken()->refresh();
        } catch (JWTException $e) {
            return null;
        }
    }
}