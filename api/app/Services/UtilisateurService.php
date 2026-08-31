<?php

namespace App\Services;

use App\Jobs\EnvoyerCredentialsJob;
use App\Models\Enseignant;
use App\Models\Role;
use App\Models\Utilisateur;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UtilisateurService
{
    // ── Listing ───────────────────────────────────────────────

    public function lister(int $etablissementId, array $filtres = []): LengthAwarePaginator
    {
        return Utilisateur::where('etablissement_id', $etablissementId)
            ->when(!empty($filtres['role_id']),    fn($q) => $q->where('role_id', $filtres['role_id']))
            ->when(!empty($filtres['est_actif']),  fn($q) => $q->where('est_actif', $filtres['est_actif']))
            ->when(!empty($filtres['recherche']),  fn($q) => $q->where(fn($q2) =>
                $q2->where('nom', 'like', "%{$filtres['recherche']}%")
                   ->orWhere('prenom', 'like', "%{$filtres['recherche']}%")
                   ->orWhere('email', 'like', "%{$filtres['recherche']}%")
            ))
            ->with('role:id,nom,code')
            ->orderBy('nom')
            ->paginate($filtres['par_page'] ?? 20);
    }

    public function trouver(int $id): Utilisateur
    {
        return Utilisateur::with([
            'role:id,nom,code',
            'etablissement:id,nom',
        ])->findOrFail($id);
    }

    // ── Création ──────────────────────────────────────────────

    /**
     * Crée un utilisateur et envoie ses credentials par email.
     * Le mot de passe temporaire est généré automatiquement.
     */
    public function creer(int $etablissementId, array $data): Utilisateur
    {
        $mdpTemp = $data['mot_de_passe'] ?? Str::password(12);

        $utilisateur = Utilisateur::create([
            'etablissement_id'  => $etablissementId,
            'role_id'           => $data['role_id'],
            'nom'               => $data['nom'],
            'prenom'            => $data['prenom'],
            'email'             => $data['email'],
            'mot_de_passe_hash' => Hash::make($mdpTemp),
            'telephone'         => $data['telephone']    ?? null,
            'genre'             => $data['genre']        ?? null,
            'date_naissance'    => $data['date_naissance'] ?? null,
            'est_actif'         => true,
            'email_verifie'     => false,
        ]);

        // Envoyer les credentials par email (asynchrone)
        dispatch(new EnvoyerCredentialsJob($utilisateur, $mdpTemp));

        // Si le rôle est enseignant, créer le profil enseignant
        $role = Role::find($data['role_id']);
        if ($role && $role->code === 'enseignant') {
            Enseignant::create([
                'utilisateur_id'    => $utilisateur->id,
                'etablissement_id'  => $etablissementId,
                'matricule'         => $data['matricule']        ?? null,
                'specialite'        => $data['specialite']       ?? null,
                'grade'             => $data['grade']            ?? null,
                'type_contrat'      => $data['type_contrat']     ?? null,
                'date_prise_service'=> $data['date_prise_service'] ?? null,
            ]);
        }

        return $utilisateur->load('role', 'etablissement');
    }

    // ── Mise à jour ───────────────────────────────────────────

    public function mettreAJour(Utilisateur $utilisateur, array $data): Utilisateur
    {
        $utilisateur->update(array_filter([
            'nom'           => $data['nom']           ?? null,
            'prenom'        => $data['prenom']         ?? null,
            'telephone'     => $data['telephone']      ?? null,
            'genre'         => $data['genre']          ?? null,
            'date_naissance'=> $data['date_naissance'] ?? null,
            'role_id'       => $data['role_id']        ?? null,
        ], fn($v) => $v !== null));

        // Mettre à jour le profil enseignant si présent
        if ($utilisateur->enseignant && !empty($data['enseignant'])) {
            $utilisateur->enseignant->update($data['enseignant']);
        }

        return $utilisateur->fresh(['role', 'enseignant']);
    }

    // ── Upload photo profil ───────────────────────────────────

    /**
     * Stocke la photo dans storage/app/public/photos/
     * Accessible via /storage/photos/nom_fichier.jpg
     */
    public function uploadPhoto(Utilisateur $utilisateur, $fichier): Utilisateur
    {
        // Supprimer l'ancienne photo
        if ($utilisateur->photo_url) {
            $ancien = str_replace('/storage/', 'public/', $utilisateur->photo_url);
            Storage::delete($ancien);
        }

        $extension = $fichier->getClientOriginalExtension();
        $nom       = "photos/user_{$utilisateur->id}_" . time() . ".{$extension}";
        $fichier->storeAs('public', $nom);

        $utilisateur->update(['photo_url' => '/storage/' . $nom]);
        return $utilisateur->fresh();
    }

    // ── Mot de passe ──────────────────────────────────────────

    public function changerMotDePasse(Utilisateur $utilisateur, string $ancien, string $nouveau): void
    {
        if (!Hash::check($ancien, $utilisateur->mot_de_passe_hash)) {
            throw new \Exception('Mot de passe actuel incorrect.', 422);
        }

        if (Hash::check($nouveau, $utilisateur->mot_de_passe_hash)) {
            throw new \Exception('Le nouveau mot de passe doit être différent de l\'ancien.', 422);
        }

        $utilisateur->update(['mot_de_passe_hash' => Hash::make($nouveau)]);
    }

    /**
     * Réinitialise le mot de passe (par un admin) et envoie le nouveau par email.
     */
    public function reinitialiserMotDePasse(Utilisateur $utilisateur): string
    {
        $mdpTemp = Str::password(12);
        $utilisateur->update(['mot_de_passe_hash' => Hash::make($mdpTemp)]);
        dispatch(new EnvoyerCredentialsJob($utilisateur, $mdpTemp, true));
        return $mdpTemp;
    }

    // ── Activation / désactivation ────────────────────────────

    public function toggleActif(Utilisateur $utilisateur): Utilisateur
    {
        $utilisateur->update(['est_actif' => !$utilisateur->est_actif]);
        return $utilisateur->fresh('role');
    }

    // ── Vérification email ────────────────────────────────────

    public function envoyerVerificationEmail(Utilisateur $utilisateur): void
    {
        $token = Str::random(64);
        $utilisateur->update([
            'token_verification' => Hash::make($token),
            'email_verifie'      => false,
        ]);
        // TODO : dispatch EnvoyerVerificationEmailJob::dispatch($utilisateur, $token)
    }

    public function verifierEmail(Utilisateur $utilisateur, string $token): void
    {
        if (!$utilisateur->token_verification || !Hash::check($token, $utilisateur->token_verification)) {
            throw new \Exception('Token de vérification invalide.', 422);
        }

        $utilisateur->update([
            'email_verifie'      => true,
            'email_verified_at'  => now(),
            'token_verification' => null,
        ]);
    }

    // ── Suppression ───────────────────────────────────────────

    public function supprimer(Utilisateur $utilisateur): void
    {
        // Supprimer la photo si elle existe
        if ($utilisateur->photo_url) {
            $path = str_replace('/storage/', 'public/', $utilisateur->photo_url);
            Storage::delete($path);
        }

        $utilisateur->delete();
    }

    // ── Enseignants ───────────────────────────────────────────

    public function listerEnseignants(int $etablissementId, array $filtres = []): LengthAwarePaginator
    {
        return Enseignant::where('etablissement_id', $etablissementId)
            ->when(!empty($filtres['est_actif']),  fn($q) => $q->where('est_actif', $filtres['est_actif']))
            ->when(!empty($filtres['recherche']),  fn($q) => $q->whereHas('utilisateur', fn($q2) =>
                $q2->where('nom', 'like', "%{$filtres['recherche']}%")
                   ->orWhere('prenom', 'like', "%{$filtres['recherche']}%")
            ))
            ->with(['utilisateur:id,nom,prenom,email,telephone,photo_url', 'diplomes'])
            ->orderByDesc('cree_le')
            ->paginate($filtres['par_page'] ?? 20);
    }

    public function trouverEnseignant(int $id): Enseignant
    {
        return Enseignant::with([
            'utilisateur:id,nom,prenom,email,telephone,photo_url,genre,date_naissance',
            'diplomes',
        ])->findOrFail($id);
    }

    public function ajouterDiplome(Enseignant $enseignant, array $data): \App\Models\EnseignantDiplome
    {
        return $enseignant->diplomes()->create($data);
    }

    public function supprimerDiplome(int $diplomeId): void
    {
        \App\Models\EnseignantDiplome::findOrFail($diplomeId)->delete();
    }
}
