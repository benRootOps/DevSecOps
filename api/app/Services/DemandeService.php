<?php

namespace App\Services;

use App\Jobs\LogConnexionJob;
use App\Models\DemandeCompte;
use App\Models\Etablissement;
use App\Models\Role;
use App\Models\Utilisateur;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemandeService
{
    public function __construct(
        private readonly PermissionService $permissionService
    ) {}

    // -------------------------------------------------------
    // SOUMISSION (routes publiques — pas de JWT requis)
    // -------------------------------------------------------

    /**
     * Soumet une demande de création d'établissement.
     * Appelée depuis le formulaire public (site vitrine Edusphere).
     * Aucun compte n'est créé ici — tout attend la validation super admin.
     */
    public function soumettreEtablissement(array $donnees): DemandeCompte
    {
        return DemandeCompte::create([
            'type_demande'     => 'etablissement',
            'donnees'          => $donnees,
            'etablissement_id' => null,
            'statut'           => 'en_attente',
        ]);
    }

    /**
     * Soumet une demande de compte enseignant.
     * Appelée par l'admin universitaire depuis son interface.
     */
    public function soumettreEnseignant(array $donnees, int $etablissementId): DemandeCompte
    {
        return DemandeCompte::create([
            'type_demande'     => 'enseignant',
            'donnees'          => $donnees,
            'etablissement_id' => $etablissementId,
            'statut'           => 'en_attente',
        ]);
    }

    /**
     * Soumet une demande de compte étudiant.
     * Appelée par la secrétaire académique ou l'admin universitaire.
     */
    public function soumettreEtudiant(array $donnees, int $etablissementId): DemandeCompte
    {
        return DemandeCompte::create([
            'type_demande'     => 'etudiant',
            'donnees'          => $donnees,
            'etablissement_id' => $etablissementId,
            'statut'           => 'en_attente',
        ]);
    }

    // -------------------------------------------------------
    // LISTE & DÉTAIL
    // -------------------------------------------------------

    /**
     * Liste les demandes selon le rôle de l'utilisateur connecté.
     *  - Super admin → toutes les demandes d'établissement
     *  - Admin universitaire → demandes enseignants/étudiants de son établissement
     */
    public function lister(
        Utilisateur $demandeur,
        array $filtres = [],
        int $perPage = 20
    ): LengthAwarePaginator {

        $query = DemandeCompte::query();

        if ($demandeur->estSuperAdmin()) {
            // Le super admin ne voit que les demandes d'établissement
            $query->where('type_demande', 'etablissement');
        } else {
            // L'admin universitaire voit les demandes de son établissement
            $query->where('etablissement_id', $demandeur->etablissement_id)
                  ->whereIn('type_demande', ['enseignant', 'etudiant']);
        }

        if (!empty($filtres['statut'])) {
            $query->where('statut', $filtres['statut']);
        }

        if (!empty($filtres['type_demande'])) {
            $query->where('type_demande', $filtres['type_demande']);
        }

        return $query
            ->orderBy('soumis_le', 'desc')
            ->paginate($perPage);
    }

    // -------------------------------------------------------
    // VALIDATION
    // -------------------------------------------------------

    /**
     * Valide une demande et crée le(s) compte(s) correspondant(s).
     * Le validateur définit le mot de passe au moment de la validation.
     *
     * @throws \RuntimeException
     */
    public function valider(
        DemandeCompte $demande,
        string $motDePasse,
        Utilisateur $validateur
    ): array {

        if (!$demande->estEnAttente()) {
            throw new \RuntimeException('Cette demande a déjà été traitée.', 422);
        }

        return DB::transaction(function () use ($demande, $motDePasse, $validateur) {
            return match ($demande->type_demande) {
                'etablissement' => $this->validerEtablissement($demande, $motDePasse, $validateur),
                'enseignant'    => $this->validerEnseignant($demande, $motDePasse, $validateur),
                'etudiant'      => $this->validerEtudiant($demande, $motDePasse, $validateur),
                default         => throw new \RuntimeException("Type de demande inconnu : {$demande->type_demande}", 422),
            };
        });
    }

    /**
     * Valide une demande d'établissement :
     *  1. Crée l'établissement
     *  2. Crée le compte admin universitaire avec le mot de passe défini
     *  3. Met à jour la demande
     */
    private function validerEtablissement(
        DemandeCompte $demande,
        string $motDePasse,
        Utilisateur $validateur
    ): array {

        $donnees = $demande->donnees;

        // 1. Créer l'établissement
        $etablissement = Etablissement::create([
            'nom'       => $donnees['etablissement']['nom'],
            'adresse'   => $donnees['etablissement']['adresse']   ?? null,
            'ville'     => $donnees['etablissement']['ville']      ?? null,
            'pays'      => $donnees['etablissement']['pays']       ?? 'Cameroun',
            'telephone' => $donnees['etablissement']['telephone']  ?? null,
            'email'     => $donnees['etablissement']['email']      ?? null,
            'est_actif' => true,
        ]);

        // 2. Récupérer le rôle admin_universitaire (système global)
        $role = Role::whereNull('etablissement_id')
                    ->where('code', 'admin_universitaire')
                    ->firstOrFail();

        // 3. Créer le compte admin universitaire
        $admin = Utilisateur::create([
            'etablissement_id'  => $etablissement->id,
            'role_id'           => $role->id,
            'nom'               => $donnees['admin']['nom'],
            'prenom'            => $donnees['admin']['prenom'],
            'email'             => $donnees['admin']['email'],
            'telephone'         => $donnees['admin']['telephone'] ?? null,
            'mot_de_passe_hash' => Hash::make($motDePasse),
            'est_actif'         => true,
            'email_verifie'     => true,
        ]);

        // 4. Marquer la demande comme validée
        $demande->update([
            'statut'               => 'validee',
            'traite_par'           => $validateur->id,
            'traite_le'            => now(),
            'utilisateur_cree_id'  => $admin->id,
            'etablissement_cree_id'=> $etablissement->id,
        ]);

        return [
            'etablissement' => $etablissement,
            'admin'         => $admin,
        ];
    }

    /**
     * Valide une demande de compte enseignant.
     */
    private function validerEnseignant(
        DemandeCompte $demande,
        string $motDePasse,
        Utilisateur $validateur
    ): array {

        $donnees = $demande->donnees;

        $role = Role::whereNull('etablissement_id')
                    ->where('code', 'enseignant')
                    ->firstOrFail();

        $utilisateur = Utilisateur::create([
            'etablissement_id'  => $demande->etablissement_id,
            'role_id'           => $role->id,
            'nom'               => $donnees['nom'],
            'prenom'            => $donnees['prenom'],
            'email'             => $donnees['email'],
            'telephone'         => $donnees['telephone'] ?? null,
            'genre'             => $donnees['genre']     ?? null,
            'mot_de_passe_hash' => Hash::make($motDePasse),
            'est_actif'         => true,
            'email_verifie'     => true,
        ]);

        $demande->update([
            'statut'              => 'validee',
            'traite_par'          => $validateur->id,
            'traite_le'           => now(),
            'utilisateur_cree_id' => $utilisateur->id,
        ]);

        return ['utilisateur' => $utilisateur];
    }

    /**
     * Valide une demande de compte étudiant.
     */
    private function validerEtudiant(
        DemandeCompte $demande,
        string $motDePasse,
        Utilisateur $validateur
    ): array {

        $donnees = $demande->donnees;

        $role = Role::whereNull('etablissement_id')
                    ->where('code', 'etudiant')
                    ->firstOrFail();

        $utilisateur = Utilisateur::create([
            'etablissement_id'  => $demande->etablissement_id,
            'role_id'           => $role->id,
            'nom'               => $donnees['nom'],
            'prenom'            => $donnees['prenom'],
            'email'             => $donnees['email'],
            'telephone'         => $donnees['telephone'] ?? null,
            'genre'             => $donnees['genre']     ?? null,
            'mot_de_passe_hash' => Hash::make($motDePasse),
            'est_actif'         => true,
            'email_verifie'     => true,
        ]);

        $demande->update([
            'statut'              => 'validee',
            'traite_par'          => $validateur->id,
            'traite_le'           => now(),
            'utilisateur_cree_id' => $utilisateur->id,
        ]);

        return ['utilisateur' => $utilisateur];
    }

    // -------------------------------------------------------
    // REJET
    // -------------------------------------------------------

    /**
     * Rejette une demande avec un motif obligatoire.
     */
    public function rejeter(
        DemandeCompte $demande,
        string $motif,
        Utilisateur $validateur
    ): DemandeCompte {

        if (!$demande->estEnAttente()) {
            throw new \RuntimeException('Cette demande a déjà été traitée.', 422);
        }

        $demande->update([
            'statut'      => 'rejetee',
            'traite_par'  => $validateur->id,
            'traite_le'   => now(),
            'motif_rejet' => $motif,
        ]);

        return $demande->fresh();
    }

    // -------------------------------------------------------
    // STATISTIQUES
    // -------------------------------------------------------

    public function statistiques(Utilisateur $demandeur): array
    {
        $query = DemandeCompte::query();

        if ($demandeur->estSuperAdmin()) {
            $query->where('type_demande', 'etablissement');
        } else {
            $query->where('etablissement_id', $demandeur->etablissement_id)
                  ->whereIn('type_demande', ['enseignant', 'etudiant']);
        }

        return [
            'en_attente' => (clone $query)->where('statut', 'en_attente')->count(),
            'validees'   => (clone $query)->where('statut', 'validee')->count(),
            'rejetees'   => (clone $query)->where('statut', 'rejetee')->count(),
            'total'      => $query->count(),
        ];
    }
}
