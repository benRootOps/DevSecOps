<?php

namespace App\Services;

use App\Models\Etablissement;
use App\Models\Utilisateur;
use Illuminate\Pagination\LengthAwarePaginator;

class EtablissementService
{
    public function __construct(
        private readonly PermissionService $permissionService
    ) {}

    /**
     * Liste paginée des établissements (super admin uniquement).
     */
    public function lister(int $perPage = 20, array $filtres = []): LengthAwarePaginator
    {
        $query = Etablissement::query();

        if (!empty($filtres['recherche'])) {
            $query->where(function ($q) use ($filtres) {
                $q->where('nom',   'ilike', "%{$filtres['recherche']}%")
                  ->orWhere('email','ilike', "%{$filtres['recherche']}%")
                  ->orWhere('ville','ilike', "%{$filtres['recherche']}%");
            });
        }

        if (isset($filtres['est_actif'])) {
            $query->where('est_actif', filter_var($filtres['est_actif'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query
            ->withCount('utilisateurs')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Met à jour un établissement (super admin ou admin de l'établissement).
     */
    public function mettreAJour(Etablissement $etablissement, array $donnees): Etablissement
    {
        $etablissement->update($donnees);
        return $etablissement->fresh();
    }

    /**
     * Active / désactive un établissement.
     * Invalide le cache des permissions de tous ses utilisateurs.
     */
    public function toggleActif(Etablissement $etablissement): Etablissement
    {
        $etablissement->update(['est_actif' => !$etablissement->est_actif]);

        $ids = Utilisateur::where('etablissement_id', $etablissement->id)->pluck('id');
        foreach ($ids as $id) {
            $this->permissionService->invaliderCache($id);
        }

        return $etablissement->fresh();
    }

    /**
     * Statistiques globales plateforme (super admin dashboard).
     */
    public function statistiques(): array
    {
        return [
            'total_etablissements' => Etablissement::count(),
            'actifs'               => Etablissement::where('est_actif', true)->count(),
            'total_utilisateurs'   => Utilisateur::count(),
        ];
    }
}
