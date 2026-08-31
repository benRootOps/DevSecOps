<?php

namespace App\Http\Controllers\Utilisateurs;

use App\Http\Controllers\Controller;
use App\Models\Enseignant;
use App\Models\Utilisateur;
use App\Services\UtilisateurService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UtilisateurController extends Controller
{
    public function __construct(private readonly UtilisateurService $service) {}

    // ── Utilisateurs ──────────────────────────────────────────

    // GET /api/utilisateurs
    public function index(Request $request): JsonResponse
    {
        $utilisateurs = $this->service->lister(
            $request->etablissement_id,
            $request->only('role_id', 'est_actif', 'recherche', 'par_page')
        );
        return $this->ok('Utilisateurs.', $utilisateurs);
    }

    // GET /api/utilisateurs/{id}
    public function show(int $id): JsonResponse
    {
        return $this->ok('Utilisateur.', $this->service->trouver($id));
    }

    // POST /api/utilisateurs
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'role_id'            => 'required|integer|exists:roles,id',
            'nom'                => 'required|string|max:100',
            'prenom'             => 'required|string|max:100',
            'email'              => 'required|email|max:150|unique:utilisateurs,email',
            'mot_de_passe'       => 'nullable|string|min:8',
            'telephone'          => 'nullable|string|max:30',
            'genre'              => 'nullable|string|max:20',
            'date_naissance'     => 'nullable|date',
            // Champs enseignant (optionnels, utilisés si rôle = enseignant)
            'matricule'          => 'nullable|string|max:50',
            'specialite'         => 'nullable|string|max:200',
            'grade'              => 'nullable|string|max:100',
            'type_contrat'       => 'nullable|string|max:50',
            'date_prise_service' => 'nullable|date',
        ]);

        try {
            $utilisateur = $this->service->creer($request->etablissement_id, $data);
            return $this->ok(
                'Compte créé. Les identifiants ont été envoyés par email.',
                $utilisateur, 201
            );
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), 422);
        }
    }

    // PUT /api/utilisateurs/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $utilisateur = Utilisateur::findOrFail($id);
        $data = $request->validate([
            'nom'            => 'sometimes|string|max:100',
            'prenom'         => 'sometimes|string|max:100',
            'telephone'      => 'nullable|string|max:30',
            'genre'          => 'nullable|string|max:20',
            'date_naissance' => 'nullable|date',
            'role_id'        => 'sometimes|integer|exists:roles,id',
            // Champs profil enseignant
            'enseignant'     => 'nullable|array',
            'enseignant.specialite'        => 'nullable|string|max:200',
            'enseignant.grade'             => 'nullable|string|max:100',
            'enseignant.type_contrat'      => 'nullable|string|max:50',
            'enseignant.date_prise_service'=> 'nullable|date',
        ]);
        return $this->ok('Utilisateur mis à jour.', $this->service->mettreAJour($utilisateur, $data));
    }

    // POST /api/utilisateurs/{id}/photo
    public function uploadPhoto(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'photo' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);
        $utilisateur = Utilisateur::findOrFail($id);
        $utilisateur = $this->service->uploadPhoto($utilisateur, $request->file('photo'));
        return $this->ok('Photo mise à jour.', ['photo_url' => $utilisateur->photo_url]);
    }

    // PATCH /api/utilisateurs/{id}/toggle-actif
    public function toggleActif(int $id): JsonResponse
    {
        $utilisateur = Utilisateur::findOrFail($id);
        $utilisateur = $this->service->toggleActif($utilisateur);
        $statut = $utilisateur->est_actif ? 'activé' : 'désactivé';
        return $this->ok("Compte {$statut}.", $utilisateur);
    }

    // DELETE /api/utilisateurs/{id}
    public function destroy(int $id): JsonResponse
    {
        $utilisateur = Utilisateur::findOrFail($id);
        // Empêcher la suppression de son propre compte
        if ($utilisateur->id === Auth::id()) {
            return $this->fail('Vous ne pouvez pas supprimer votre propre compte.', 403);
        }
        $this->service->supprimer($utilisateur);
        return $this->ok('Compte supprimé.');
    }

    // ── Mot de passe ──────────────────────────────────────────

    // PATCH /api/utilisateurs/changer-mot-de-passe (utilisateur connecté)
    public function changerMotDePasse(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mot_de_passe_actuel' => 'required|string',
            'nouveau_mot_de_passe'=> 'required|string|min:8|confirmed',
        ]);
        try {
            $this->service->changerMotDePasse(
                Auth::user(),
                $data['mot_de_passe_actuel'],
                $data['nouveau_mot_de_passe']
            );
            return $this->ok('Mot de passe changé avec succès.');
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    // PATCH /api/utilisateurs/{id}/reinitialiser-mdp (admin)
    public function reinitialiserMotDePasse(int $id): JsonResponse
    {
        $utilisateur = Utilisateur::findOrFail($id);
        $this->service->reinitialiserMotDePasse($utilisateur);
        return $this->ok('Mot de passe réinitialisé. Le nouveau mot de passe a été envoyé par email.');
    }

    // ── Vérification email ────────────────────────────────────

    // POST /api/utilisateurs/{id}/envoyer-verification
    public function envoyerVerification(int $id): JsonResponse
    {
        $utilisateur = Utilisateur::findOrFail($id);
        $this->service->envoyerVerificationEmail($utilisateur);
        return $this->ok('Email de vérification envoyé.');
    }

    // GET /api/email/verify/{id}/{token} (lien dans l'email)
    public function verifierEmail(Request $request, int $id): JsonResponse
    {
        $utilisateur = Utilisateur::findOrFail($id);
        try {
            $this->service->verifierEmail($utilisateur, $request->query('token', ''));
            return $this->ok('Email vérifié avec succès.');
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), 422);
        }
    }

    // ── Enseignants ───────────────────────────────────────────

    // GET /api/enseignants
    public function indexEnseignants(Request $request): JsonResponse
    {
        $enseignants = $this->service->listerEnseignants(
            $request->etablissement_id,
            $request->only('est_actif', 'recherche', 'par_page')
        );
        return $this->ok('Enseignants.', $enseignants);
    }

    // GET /api/enseignants/{id}
    public function showEnseignant(int $id): JsonResponse
    {
        return $this->ok('Enseignant.', $this->service->trouverEnseignant($id));
    }

    // POST /api/enseignants/{id}/diplomes
    public function ajouterDiplome(Request $request, int $id): JsonResponse
    {
        $enseignant = Enseignant::findOrFail($id);
        $data = $request->validate([
            'intitule'       => 'required|string|max:200',
            'etablissement'  => 'nullable|string|max:200',
            'annee_obtention'=> 'nullable|integer|min:1950|max:' . date('Y'),
            'document_url'   => 'nullable|string|max:500',
        ]);
        $diplome = $this->service->ajouterDiplome($enseignant, $data);
        return $this->ok('Diplôme ajouté.', $diplome, 201);
    }

    // DELETE /api/diplomes/{id}
    public function supprimerDiplome(int $id): JsonResponse
    {
        $this->service->supprimerDiplome($id);
        return $this->ok('Diplôme supprimé.');
    }

    private function ok(string $msg, mixed $data = null, int $code = 200): JsonResponse
    {
        return response()->json(['succes' => true, 'message' => $msg, 'donnees' => $data], $code);
    }

    private function fail(string $msg, int $code = 400): JsonResponse
    {
        return response()->json(['succes' => false, 'message' => $msg, 'donnees' => null], $code);
    }
}
