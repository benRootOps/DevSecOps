<?php

// ══════════════════════════════════════════════════════════════
//  ROUTES MODULES C, D, E — à ajouter dans routes/api.php
//  DANS le groupe Route::middleware(['auth:api', 'etablissement.scope'])
//
//  Imports à ajouter en haut de api.php :
//  use App\Http\Controllers\Notes\NoteController;
//  use App\Http\Controllers\Deliberations\DeliberationController;
//  use App\Http\Controllers\Deliberations\BulletinController;
//  use App\Http\Controllers\Financier\FinancierController;
// ══════════════════════════════════════════════════════════════

// ── MODULE C — NOTES & ÉVALUATIONS ───────────────────────────
// NoteController :
//   indexSessions, storeSession, cloturerSession
//   indexExamens, storeExamen, updateExamen, destroyExamen
//   notesParMatiere, releveEtudiant
//   saisirNote, saisirEnMasse, validerNotes
//   calculerMoyennes, resultatsClasse

// Sessions d'examen
Route::middleware('permission:examens.voir')
     ->get('sessions-examen', [NoteController::class, 'indexSessions']);
Route::middleware('permission:examens.creer')
     ->post('sessions-examen', [NoteController::class, 'storeSession']);
Route::middleware('permission:examens.cloturer')
     ->patch('sessions-examen/{id}/cloturer', [NoteController::class, 'cloturerSession']);

// Examens
Route::middleware('permission:examens.voir')
     ->get('sessions-examen/{id}/examens', [NoteController::class, 'indexExamens']);
Route::middleware('permission:examens.creer')
     ->post('sessions-examen/{id}/examens', [NoteController::class, 'storeExamen']);
Route::middleware('permission:examens.modifier')->group(function () {
    Route::put('examens/{id}',    [NoteController::class, 'updateExamen']);
    Route::delete('examens/{id}', [NoteController::class, 'destroyExamen']);
});

// Notes — saisie et lecture
Route::middleware('permission:notes.voir')->group(function () {
    Route::get('notes/matiere/{matiereId}/session/{sessionId}/classe/{classeId}',
               [NoteController::class, 'notesParMatiere']);
    Route::get('etudiants/{id}/releve/{sessionId}',
               [NoteController::class, 'releveEtudiant']);
    Route::get('sessions-examen/{id}/resultats/{classeId}',
               [NoteController::class, 'resultatsClasse']);
});

Route::middleware('permission:notes.saisir')->group(function () {
    Route::post('notes',       [NoteController::class, 'saisirNote']);
    Route::post('notes/masse', [NoteController::class, 'saisirEnMasse']);
});

Route::middleware('permission:notes.valider')
     ->patch('notes/valider', [NoteController::class, 'validerNotes']);

// Calcul automatique des moyennes
Route::middleware('permission:notes.valider')
     ->post('sessions-examen/{id}/calculer-moyennes', [NoteController::class, 'calculerMoyennes']);


// ── MODULE D — DÉLIBÉRATIONS & BULLETINS ─────────────────────
// DeliberationController : index, store, resultats, saisirResultats, autoDecisions, cloturer
// BulletinController     : index, genererClasse, genererEtudiant, publier,
//                          documentsEtudiant, creerDocument, validerDocument

// Délibérations
Route::middleware('permission:deliberations.voir')->group(function () {
    Route::get('deliberations',                [DeliberationController::class, 'index']);
    Route::get('deliberations/{id}/resultats', [DeliberationController::class, 'resultats']);
});

Route::middleware('permission:deliberations.creer')->group(function () {
    Route::post('deliberations',                        [DeliberationController::class, 'store']);
    Route::post('deliberations/{id}/resultats',         [DeliberationController::class, 'saisirResultats']);
    Route::post('deliberations/{id}/auto-decisions',    [DeliberationController::class, 'autoDecisions']);
});

Route::middleware('permission:deliberations.cloturer')
     ->patch('deliberations/{id}/cloturer', [DeliberationController::class, 'cloturer']);

// Bulletins
Route::middleware('permission:bulletins.voir')
     ->get('bulletins', [BulletinController::class, 'index']);

Route::middleware('permission:bulletins.generer')->group(function () {
    Route::post('bulletins/generer-classe',    [BulletinController::class, 'genererClasse']);
    Route::post('bulletins/generer-etudiant',  [BulletinController::class, 'genererEtudiant']);
});

Route::middleware('permission:bulletins.publier')
     ->patch('bulletins/{id}/publier', [BulletinController::class, 'publier']);

// Documents officiels
Route::middleware('permission:documents.voir')
     ->get('etudiants/{id}/documents', [BulletinController::class, 'documentsEtudiant']);

Route::middleware('permission:documents.generer')
     ->post('documents-officiels', [BulletinController::class, 'creerDocument']);

Route::middleware('permission:documents.valider')
     ->patch('documents-officiels/{id}/valider', [BulletinController::class, 'validerDocument']);


// ── MODULE E — FINANCIER SCOLARITÉ ───────────────────────────
// FinancierController :
//   indexCategories, storeCategorie, updateCategorie
//   indexFrais, storeFrais, updateFrais
//   situationEtudiant
//   indexVersements, storeVersement
//   rapport

// Catégories de frais
Route::middleware('permission:financier.voir')
     ->get('categories-frais', [FinancierController::class, 'indexCategories']);
Route::middleware('permission:financier.frais.creer')
     ->post('categories-frais', [FinancierController::class, 'storeCategorie']);
Route::middleware('permission:financier.frais.modifier')
     ->put('categories-frais/{id}', [FinancierController::class, 'updateCategorie']);

// Frais universitaires
Route::middleware('permission:financier.voir')
     ->get('frais', [FinancierController::class, 'indexFrais']);
Route::middleware('permission:financier.frais.creer')
     ->post('frais', [FinancierController::class, 'storeFrais']);
Route::middleware('permission:financier.frais.modifier')
     ->put('frais/{id}', [FinancierController::class, 'updateFrais']);

// Situation financière étudiant
Route::middleware('permission:financier.versement.voir')
     ->get('etudiants/{id}/situation-financiere', [FinancierController::class, 'situationEtudiant']);

// Versements
Route::middleware('permission:financier.versement.voir')
     ->get('versements', [FinancierController::class, 'indexVersements']);
Route::middleware('permission:financier.saisir')
     ->post('versements', [FinancierController::class, 'storeVersement']);

// Rapport financier
Route::middleware('permission:financier.rapports')
     ->get('rapports/financier', [FinancierController::class, 'rapport']);
