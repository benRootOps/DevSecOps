<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Demande\DemandeController;
use App\Http\Controllers\Etablissement\EtablissementController;
use App\Http\Controllers\Permission\RoleController;
use App\Http\Controllers\Utilisateurs\UtilisateurController;
use App\Http\Controllers\EmploiDuTemps\EmploiDuTempsController;
use App\Http\Controllers\Presences\PresenceController;
use App\Http\Controllers\Notes\NoteController;
use App\Http\Controllers\Deliberations\DeliberationController;
use App\Http\Controllers\Deliberations\BulletinController;
use App\Http\Controllers\Financier\FinancierController;
use App\Http\Controllers\Abonnements\AbonnementController;


Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});
/*
|--------------------------------------------------------------------------
| Routes publiques (sans JWT)
|--------------------------------------------------------------------------
*/
Route::post('auth/login', [LoginController::class, 'login']);
Route::post('demandes/etablissement', [DemandeController::class, 'soumettreEtablissement']);
Route::get('email/verify/{id}', [UtilisateurController::class, 'verifierEmail'])->name('email.verify');
Route::post('webhooks/paiement/{moyenId}', [AbonnementController::class, 'recevoirWebhook']);

/*
|--------------------------------------------------------------------------
| Routes protégées
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'etablissement.scope'])->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('logout',  [LoginController::class, 'logout']);
        Route::post('refresh', [LoginController::class, 'refresh']);
        Route::get('me',       [LoginController::class, 'moi']);
    });

    Route::prefix('demandes')->group(function () {
        Route::middleware('permission:utilisateurs.creer')->group(function () {
            Route::get('/',              [DemandeController::class, 'index']);
            Route::get('statistiques',   [DemandeController::class, 'statistiques']);
            Route::get('{id}',           [DemandeController::class, 'show']);
        });

        Route::middleware('permission:enseignants.creer')
             ->post('enseignant', [DemandeController::class, 'soumettreEnseignant']);

        Route::middleware('permission:etudiants.creer')
             ->post('etudiant',   [DemandeController::class, 'soumettreEtudiant']);

        Route::middleware('permission:utilisateurs.creer')->group(function () {
            Route::post('{id}/valider', [DemandeController::class, 'valider']);
            Route::post('{id}/rejeter', [DemandeController::class, 'rejeter']);
        });
    });

    // ── ÉTABLISSEMENTS ────────────────────────────────────────
    Route::middleware('permission:etablissements.voir')->group(function () {
        Route::get('etablissements',              [EtablissementController::class, 'index']);
        Route::get('etablissements/statistiques', [EtablissementController::class, 'statistiques']);
        Route::get('etablissements/{id}',         [EtablissementController::class, 'show']);
    });

    Route::middleware('permission:etablissements.modifier')->group(function () {
        Route::put('etablissements/{id}',                [EtablissementController::class, 'update']);
        Route::patch('etablissements/{id}/toggle-actif', [EtablissementController::class, 'toggleActif']);
    });

    // ── RÔLES & PERMISSIONS ───────────────────────────────────
    Route::middleware('permission:utilisateurs.permissions')
         ->get('permissions', [RoleController::class, 'toutesLesPermissions']);

    Route::middleware('permission:utilisateurs.voir')->group(function () {
        Route::get('roles',      [RoleController::class, 'index']);
        Route::get('roles/{id}', [RoleController::class, 'show']);
    });

    Route::middleware('permission:utilisateurs.permissions')->group(function () {
        Route::post('roles',        [RoleController::class, 'store']);
        Route::put('roles/{id}',    [RoleController::class, 'update']);
        Route::delete('roles/{id}', [RoleController::class, 'destroy']);

        Route::get('utilisateurs/{id}/permissions',           [RoleController::class, 'permissionsUtilisateur']);
        Route::post('utilisateurs/{id}/permissions',          [RoleController::class, 'setPermissionUtilisateur']);
        Route::delete('utilisateurs/{id}/permissions/{code}', [RoleController::class, 'resetPermissionUtilisateur']);
    });

    // ── UTILISATEURS ──────────────────────────────────────────
    Route::middleware('permission:utilisateurs.voir')->group(function () {
        Route::get('utilisateurs',      [UtilisateurController::class, 'index']);
        Route::get('utilisateurs/{id}', [UtilisateurController::class, 'show']);
    });

    Route::middleware('permission:utilisateurs.creer')->post('utilisateurs', [UtilisateurController::class, 'store']);

    Route::middleware('permission:utilisateurs.modifier')->group(function () {
        Route::put('utilisateurs/{id}',                       [UtilisateurController::class, 'update']);
        Route::post('utilisateurs/{id}/photo',                [UtilisateurController::class, 'uploadPhoto']);
        Route::patch('utilisateurs/{id}/toggle-actif',        [UtilisateurController::class, 'toggleActif']);
        Route::patch('utilisateurs/{id}/reinitialiser-mdp',   [UtilisateurController::class, 'reinitialiserMotDePasse']);
        Route::post('utilisateurs/{id}/envoyer-verification', [UtilisateurController::class, 'envoyerVerification']);
    });

    Route::middleware('permission:utilisateurs.supprimer')->delete('utilisateurs/{id}', [UtilisateurController::class, 'destroy']);
    Route::patch('utilisateurs/changer-mot-de-passe', [UtilisateurController::class, 'changerMotDePasse']);

    Route::middleware('permission:enseignants.voir')->group(function () {
        Route::get('enseignants',      [UtilisateurController::class, 'indexEnseignants']);
        Route::get('enseignants/{id}', [UtilisateurController::class, 'showEnseignant']);
    });

    Route::middleware('permission:enseignants.modifier')->group(function () {
        Route::post('enseignants/{id}/diplomes', [UtilisateurController::class, 'ajouterDiplome']);
        Route::delete('diplomes/{id}',           [UtilisateurController::class, 'supprimerDiplome']);
    });

    // ⚠️ Nouveau — manquait : liste des étudiants (même style que enseignants)
    Route::middleware('permission:etudiants.voir')->group(function () {
        Route::get('etudiants',      [UtilisateurController::class, 'indexEtudiants']);
        Route::get('etudiants/{id}', [UtilisateurController::class, 'showEtudiant']);
    });

    // ── MODULE A — EMPLOI DU TEMPS (manquait entièrement) ────
    Route::middleware('permission:emploi_temps.voir')->group(function () {
        Route::get('salles',      [EmploiDuTempsController::class, 'indexSalles']);
        Route::get('creneaux',    [EmploiDuTempsController::class, 'indexCreneaux']);
        Route::get('affectations',[EmploiDuTempsController::class, 'indexAffectations']);
        Route::get('emploi-du-temps/classe/{classeId}/semestre/{semestreId}',       [EmploiDuTempsController::class, 'parClasse']);
        Route::get('emploi-du-temps/enseignant/{enseignantId}/semestre/{semestreId}', [EmploiDuTempsController::class, 'parEnseignant']);
        Route::get('conflits',    [EmploiDuTempsController::class, 'indexConflits']);
    });

    Route::middleware('permission:emploi_temps.creer')->group(function () {
        Route::post('salles',   [EmploiDuTempsController::class, 'storeSalle']);
        Route::post('creneaux', [EmploiDuTempsController::class, 'storeCreneau']);
        Route::post('seances',  [EmploiDuTempsController::class, 'storeSeance']);
    });

    Route::middleware('permission:emploi_temps.modifier')->group(function () {
        Route::put('salles/{id}',            [EmploiDuTempsController::class, 'updateSalle']);
        Route::put('seances/{id}',           [EmploiDuTempsController::class, 'updateSeance']);
        Route::patch('seances/{id}/annuler', [EmploiDuTempsController::class, 'annulerSeance']);
        Route::patch('conflits/{id}/resoudre', [EmploiDuTempsController::class, 'resoudreConflit']);
    });

    Route::middleware('permission:emploi_temps.supprimer')->group(function () {
        Route::delete('salles/{id}',   [EmploiDuTempsController::class, 'destroySalle']);
        Route::delete('creneaux/{id}', [EmploiDuTempsController::class, 'destroyCreneau']);
    });

    Route::middleware('permission:cours.affecter')->group(function () {
        Route::post('affectations',        [EmploiDuTempsController::class, 'storeAffectation']);
        Route::delete('affectations/{id}', [EmploiDuTempsController::class, 'destroyAffectation']);
    });

    // ── MODULE B — PRÉSENCES (manquait entièrement) ──────────
    Route::middleware('permission:presences.voir')->group(function () {
        Route::get('seances/{id}/presences',      [PresenceController::class, 'parSeance']);
        Route::get('enseignants/{id}/presences',  [PresenceController::class, 'presencesEnseignant']);
    });

    Route::middleware('permission:presences.saisir')->group(function () {
        Route::post('seances/{id}/presences/initialiser', [PresenceController::class, 'initialiser']);
        Route::post('seances/{id}/presences/masse',       [PresenceController::class, 'saisirEnMasse']);
        Route::patch('seances/{seanceId}/presences/{etudiantId}', [PresenceController::class, 'majPresence']);
        Route::post('seances/{id}/presence-enseignant',   [PresenceController::class, 'saisirPresenceEnseignant']);
    });

    Route::middleware('permission:presences.statistiques')->group(function () {
        Route::get('etudiants/{id}/statistiques-presences', [PresenceController::class, 'statistiquesEtudiant']);
        Route::get('classes/{id}/statistiques-presences',   [PresenceController::class, 'statistiquesClasse']);
    });

    // ── MODULE C — NOTES & ÉVALUATIONS ────────────────────────
    Route::middleware('permission:examens.voir')->get('sessions-examen', [NoteController::class, 'indexSessions']);
    Route::middleware('permission:examens.creer')->post('sessions-examen', [NoteController::class, 'storeSession']);
    Route::middleware('permission:examens.cloturer')->patch('sessions-examen/{id}/cloturer', [NoteController::class, 'cloturerSession']);

    Route::middleware('permission:examens.voir')->get('sessions-examen/{id}/examens', [NoteController::class, 'indexExamens']);
    Route::middleware('permission:examens.creer')->post('sessions-examen/{id}/examens', [NoteController::class, 'storeExamen']);
    Route::middleware('permission:examens.modifier')->group(function () {
        Route::put('examens/{id}',    [NoteController::class, 'updateExamen']);
        Route::delete('examens/{id}', [NoteController::class, 'destroyExamen']);
    });

    Route::middleware('permission:notes.voir')->group(function () {
        Route::get('notes/matiere/{matiereId}/session/{sessionId}/classe/{classeId}', [NoteController::class, 'notesParMatiere']);
        Route::get('etudiants/{id}/releve/{sessionId}', [NoteController::class, 'releveEtudiant']);
        Route::get('sessions-examen/{id}/resultats/{classeId}', [NoteController::class, 'resultatsClasse']);
    });

    Route::middleware('permission:notes.saisir')->group(function () {
        Route::post('notes',       [NoteController::class, 'saisirNote']);
        Route::post('notes/masse', [NoteController::class, 'saisirEnMasse']);
    });

    Route::middleware('permission:notes.valider')->group(function () {
        Route::patch('notes/valider', [NoteController::class, 'validerNotes']);
        Route::post('sessions-examen/{id}/calculer-moyennes', [NoteController::class, 'calculerMoyennes']);
    });

    // ── MODULE D — DÉLIBÉRATIONS & BULLETINS ─────────────────
    Route::middleware('permission:deliberations.voir')->group(function () {
        Route::get('deliberations',                [DeliberationController::class, 'index']);
        Route::get('deliberations/{id}/resultats', [DeliberationController::class, 'resultats']);
    });

    Route::middleware('permission:deliberations.creer')->group(function () {
        Route::post('deliberations',                     [DeliberationController::class, 'store']);
        Route::post('deliberations/{id}/resultats',      [DeliberationController::class, 'saisirResultats']);
        Route::post('deliberations/{id}/auto-decisions', [DeliberationController::class, 'autoDecisions']);
    });

    Route::middleware('permission:deliberations.cloturer')
         ->patch('deliberations/{id}/cloturer', [DeliberationController::class, 'cloturer']);

    Route::middleware('permission:bulletins.voir')->get('bulletins', [BulletinController::class, 'index']);

    Route::middleware('permission:bulletins.generer')->group(function () {
        Route::post('bulletins/generer-classe',   [BulletinController::class, 'genererClasse']);
        Route::post('bulletins/generer-etudiant', [BulletinController::class, 'genererEtudiant']);
    });

    Route::middleware('permission:bulletins.publier')
         ->patch('bulletins/{id}/publier', [BulletinController::class, 'publier']);

    Route::middleware('permission:documents.voir')
         ->get('etudiants/{id}/documents', [BulletinController::class, 'documentsEtudiant']);

    Route::middleware('permission:documents.generer')
         ->post('documents-officiels', [BulletinController::class, 'creerDocument']);

    Route::middleware('permission:documents.valider')
         ->patch('documents-officiels/{id}/valider', [BulletinController::class, 'validerDocument']);

    // ── MODULE E — FINANCIER SCOLARITÉ ────────────────────────
    Route::middleware('permission:financier.voir')->get('categories-frais', [FinancierController::class, 'indexCategories']);
    Route::middleware('permission:financier.frais.creer')->post('categories-frais', [FinancierController::class, 'storeCategorie']);
    Route::middleware('permission:financier.frais.modifier')->put('categories-frais/{id}', [FinancierController::class, 'updateCategorie']);

    Route::middleware('permission:financier.voir')->get('frais', [FinancierController::class, 'indexFrais']);
    Route::middleware('permission:financier.frais.creer')->post('frais', [FinancierController::class, 'storeFrais']);
    Route::middleware('permission:financier.frais.modifier')->put('frais/{id}', [FinancierController::class, 'updateFrais']);

    Route::middleware('permission:financier.versement.voir')
         ->get('etudiants/{id}/situation-financiere', [FinancierController::class, 'situationEtudiant']);

    Route::middleware('permission:financier.versement.voir')->get('versements', [FinancierController::class, 'indexVersements']);
    Route::middleware('permission:financier.saisir')->post('versements', [FinancierController::class, 'storeVersement']);

    Route::middleware('permission:financier.rapports')->get('rapports/financier', [FinancierController::class, 'rapport']);

    // ── MODULE F — ABONNEMENTS SAAS (manquait entièrement) ───
    Route::middleware('permission:plans.voir')->get('plans', [AbonnementController::class, 'indexPlans']);
    Route::middleware('permission:plans.gerer')->group(function () {
        Route::post('plans',       [AbonnementController::class, 'storePlan']);
        Route::put('plans/{id}',   [AbonnementController::class, 'updatePlan']);
    });

    Route::middleware('permission:paiements.voir')->get('moyens-paiement', [AbonnementController::class, 'indexMoyensPaiement']);

    Route::middleware('permission:abonnements.voir')->group(function () {
        Route::get('abonnements',        [AbonnementController::class, 'index']);
        Route::get('abonnements/actif',  [AbonnementController::class, 'actif']);
    });

    Route::middleware('permission:abonnements.souscrire')->post('abonnements', [AbonnementController::class, 'souscrire']);
    Route::middleware('permission:abonnements.gerer')->patch('abonnements/{id}/statut', [AbonnementController::class, 'changerStatut']);

    Route::middleware('permission:factures.voir')->get('factures', [AbonnementController::class, 'indexFactures']);
    Route::middleware('permission:factures.generer')->post('abonnements/{id}/facture', [AbonnementController::class, 'genererFacture']);

    Route::middleware('permission:paiements.voir')->get('transactions', [AbonnementController::class, 'indexTransactions']);
    Route::middleware('permission:paiements.initier')->group(function () {
        Route::post('paiements/initier',        [AbonnementController::class, 'initierPaiement']);
        Route::patch('paiements/{id}/confirmer', [AbonnementController::class, 'confirmerPaiement']);
    });
});