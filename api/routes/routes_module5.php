<?php

// ══════════════════════════════════════════════════════════════
//  ROUTES MODULE 5 — à ajouter dans routes/api.php
//  DANS le groupe Route::middleware(['auth:api', 'etablissement.scope'])
//
//  Import à ajouter en haut de api.php :
//  use App\Http\Controllers\Utilisateurs\UtilisateurController;
// ══════════════════════════════════════════════════════════════

// ── MODULE 5 — UTILISATEURS ───────────────────────────────────
// UtilisateurController :
//   index, show, store, update, destroy, toggleActif
//   uploadPhoto
//   changerMotDePasse, reinitialiserMotDePasse
//   envoyerVerification, verifierEmail
//   indexEnseignants, showEnseignant
//   ajouterDiplome, supprimerDiplome

// CRUD Utilisateurs
Route::middleware('permission:utilisateurs.voir')->group(function () {
    Route::get('utilisateurs',       [UtilisateurController::class, 'index']);
    Route::get('utilisateurs/{id}',  [UtilisateurController::class, 'show']);
});

Route::middleware('permission:utilisateurs.creer')
     ->post('utilisateurs', [UtilisateurController::class, 'store']);

Route::middleware('permission:utilisateurs.modifier')->group(function () {
    Route::put('utilisateurs/{id}',                       [UtilisateurController::class, 'update']);
    Route::post('utilisateurs/{id}/photo',                [UtilisateurController::class, 'uploadPhoto']);
    Route::patch('utilisateurs/{id}/toggle-actif',        [UtilisateurController::class, 'toggleActif']);
    Route::patch('utilisateurs/{id}/reinitialiser-mdp',   [UtilisateurController::class, 'reinitialiserMotDePasse']);
    Route::post('utilisateurs/{id}/envoyer-verification', [UtilisateurController::class, 'envoyerVerification']);
});

Route::middleware('permission:utilisateurs.supprimer')
     ->delete('utilisateurs/{id}', [UtilisateurController::class, 'destroy']);

// Changement de mot de passe (utilisateur pour lui-même — pas de permission spéciale)
Route::patch('utilisateurs/changer-mot-de-passe', [UtilisateurController::class, 'changerMotDePasse']);

// Vérification email (lien cliqué depuis l'email — public)
// À déclarer HORS du groupe auth:api
// Route::get('email/verify/{id}', [UtilisateurController::class, 'verifierEmail']);

// ── Enseignants ───────────────────────────────────────────────
Route::middleware('permission:enseignants.voir')->group(function () {
    Route::get('enseignants',       [UtilisateurController::class, 'indexEnseignants']);
    Route::get('enseignants/{id}',  [UtilisateurController::class, 'showEnseignant']);
});

Route::middleware('permission:enseignants.modifier')->group(function () {
    Route::post('enseignants/{id}/diplomes', [UtilisateurController::class, 'ajouterDiplome']);
    Route::delete('diplomes/{id}',           [UtilisateurController::class, 'supprimerDiplome']);
});
