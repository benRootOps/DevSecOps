<?php

namespace App\Services;

use App\Models\CategorieFrais;
use App\Models\Frais;
use App\Models\Recu;
use App\Models\TranchePaiement;
use App\Models\Versement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FinancierService
{
    // ── Catégories ────────────────────────────────────────────

    public function listerCategories(int $etablissementId): Collection
    {
        return CategorieFrais::where('etablissement_id', $etablissementId)
            ->orderBy('libelle')->get();
    }

    public function creerCategorie(int $etablissementId, array $data): CategorieFrais
    {
        return CategorieFrais::create(array_merge($data, ['etablissement_id' => $etablissementId]));
    }

    public function mettreAJourCategorie(CategorieFrais $categorie, array $data): CategorieFrais
    {
        $categorie->update($data);
        return $categorie->fresh();
    }

    // ── Frais ─────────────────────────────────────────────────

    public function listerFrais(int $etablissementId, array $filtres = []): Collection
    {
        return Frais::where('etablissement_id', $etablissementId)
            ->when(!empty($filtres['annee_academique_id']), fn($q) => $q->where('annee_academique_id', $filtres['annee_academique_id']))
            ->when(!empty($filtres['filiere_id']),          fn($q) => $q->where('filiere_id', $filtres['filiere_id']))
            ->when(!empty($filtres['niveau_id']),           fn($q) => $q->where('niveau_id', $filtres['niveau_id']))
            ->with(['categorie:id,libelle', 'tranches', 'filiere:id,nom', 'niveau:id,libelle'])
            ->orderByDesc('cree_le')
            ->get();
    }

    public function creerFrais(int $etablissementId, array $data): Frais
    {
        return DB::transaction(function () use ($etablissementId, $data) {
            $frais = Frais::create(array_merge($data, ['etablissement_id' => $etablissementId]));

            // Générer les tranches automatiquement si nombre_tranches > 1
            $nb      = $frais->nombre_tranches;
            $montant = round($frais->montant_total / $nb, 2);

            for ($i = 1; $i <= $nb; $i++) {
                // Ajuster la dernière tranche pour les arrondis
                $m = ($i === $nb)
                    ? $frais->montant_total - ($montant * ($nb - 1))
                    : $montant;

                TranchePaiement::create([
                    'frais_id' => $frais->id,
                    'numero'   => $i,
                    'libelle'  => "Tranche {$i}",
                    'montant'  => $m,
                ]);
            }

            return $frais->load('tranches');
        });
    }

    public function mettreAJourFrais(Frais $frais, array $data): Frais
    {
        $frais->update($data);
        return $frais->fresh(['tranches', 'categorie']);
    }

    // ── Situation financière d'un étudiant ────────────────────

    /**
     * Retourne la situation financière complète d'un étudiant
     * pour une année académique donnée.
     */
    public function situationEtudiant(int $etudiantId, int $anneeAcademiqueId): array
    {
        // Trouver les frais applicables à l'étudiant (via filiere/niveau)
        $etudiant = \App\Models\Etudiant::with(['inscriptions' => fn($q) => $q
                ->where('statut', 'Validé')
                ->with('classe.niveau.filiere')
            ])->findOrFail($etudiantId);

        $inscription = $etudiant->inscriptions->first();
        $niveauId    = $inscription?->classe?->niveau_id;
        $filiereId   = $inscription?->classe?->niveau?->filiere_id;

        // Frais applicables
        $fraisList = Frais::where('annee_academique_id', $anneeAcademiqueId)
            ->where(fn($q) => $q
                ->whereNull('filiere_id')->whereNull('niveau_id')
                ->orWhere('filiere_id', $filiereId)
                ->orWhere('niveau_id', $niveauId)
            )
            ->with(['tranches' => fn($q) => $q->with(['versements' => fn($v) =>
                $v->where('etudiant_id', $etudiantId)
            ])])
            ->get();

        $totalDu     = 0;
        $totalVerse  = 0;
        $tranches    = [];

        foreach ($fraisList as $frais) {
            foreach ($frais->tranches as $tranche) {
                $verse        = $tranche->versements->sum('montant_verse');
                $totalDu     += $tranche->montant;
                $totalVerse  += $verse;
                $tranches[]   = [
                    'frais_libelle'  => $frais->categorie->libelle ?? '—',
                    'tranche_id'     => $tranche->id,
                    'numero'         => $tranche->numero,
                    'libelle'        => $tranche->libelle,
                    'montant'        => $tranche->montant,
                    'montant_verse'  => $verse,
                    'reste'          => max(0, $tranche->montant - $verse),
                    'est_solde'      => $verse >= $tranche->montant,
                    'date_echeance'  => $tranche->date_echeance,
                    'versements'     => $tranche->versements,
                ];
            }
        }

        return [
            'etudiant'      => $etudiant->only('id', 'nom', 'prenom', 'matricule'),
            'total_du'      => $totalDu,
            'total_verse'   => $totalVerse,
            'reste_a_payer' => max(0, $totalDu - $totalVerse),
            'est_a_jour'    => $totalVerse >= $totalDu,
            'tranches'      => $tranches,
        ];
    }

    // ── Versements ────────────────────────────────────────────

    public function listerVersements(int $etablissementId, array $filtres = []): LengthAwarePaginator
    {
        return Versement::whereHas('etudiant',
                fn($q) => $q->where('etablissement_id', $etablissementId))
            ->when(!empty($filtres['etudiant_id']),    fn($q) => $q->where('etudiant_id', $filtres['etudiant_id']))
            ->when(!empty($filtres['date_debut']),     fn($q) => $q->where('date_versement', '>=', $filtres['date_debut']))
            ->when(!empty($filtres['date_fin']),       fn($q) => $q->where('date_versement', '<=', $filtres['date_fin']))
            ->with(['etudiant:id,nom,prenom,matricule', 'tranche.frais.categorie', 'recu'])
            ->orderByDesc('date_versement')
            ->paginate($filtres['par_page'] ?? 20);
    }

    public function enregistrerVersement(array $data, int $enregistreParId): Versement
    {
        $tranche = TranchePaiement::findOrFail($data['tranche_id']);

        return DB::transaction(function () use ($data, $enregistreParId, $tranche) {
            $versement = Versement::create(array_merge($data, [
                'enregistre_par' => $enregistreParId,
                'date_versement' => $data['date_versement'] ?? now()->toDateString(),
            ]));

            // Générer le reçu automatiquement
            $this->genererRecu($versement, $enregistreParId);

            return $versement->load(['tranche.frais.categorie', 'etudiant', 'recu']);
        });
    }

    // ── Reçus ─────────────────────────────────────────────────

    private function genererRecu(Versement $versement, int $genereParId): Recu
    {
        $annee  = now()->year;
        $count  = Recu::whereYear('genere_le', $annee)->count() + 1;
        $numero = sprintf('REC-%d-%05d', $annee, $count);

        return Recu::create([
            'versement_id' => $versement->id,
            'numero_recu'  => $numero,
            'genere_par'   => $genereParId,
            // TODO : dispatch GenerateRecuPdfJob::dispatch($versement->id)
        ]);
    }

    // ── Rapports ──────────────────────────────────────────────

    /**
     * Rapport financier global par établissement pour une année académique.
     */
    public function rapport(int $etablissementId, int $anneeAcademiqueId): array
    {
        $fraisIds = Frais::where('etablissement_id', $etablissementId)
            ->where('annee_academique_id', $anneeAcademiqueId)
            ->pluck('id');

        $trancheIds = TranchePaiement::whereIn('frais_id', $fraisIds)->pluck('id');

        $totalAttendu = TranchePaiement::whereIn('id', $trancheIds)->sum('montant');
        $totalCollecte = Versement::whereIn('tranche_id', $trancheIds)->sum('montant_verse');

        // Versements par mode de paiement
        $parMode = Versement::whereIn('tranche_id', $trancheIds)
            ->selectRaw('mode_paiement, SUM(montant_verse) as total, COUNT(*) as nb')
            ->groupBy('mode_paiement')
            ->get();

        // Versements par mois
        $parMois = Versement::whereIn('tranche_id', $trancheIds)
            ->selectRaw('MONTH(date_versement) as mois, SUM(montant_verse) as total')
            ->groupByRaw('MONTH(date_versement)')
            ->orderByRaw('MONTH(date_versement)')
            ->get();

        return [
            'total_attendu'   => $totalAttendu,
            'total_collecte'  => $totalCollecte,
            'reste'           => max(0, $totalAttendu - $totalCollecte),
            'taux_recouvrement' => $totalAttendu > 0
                ? round($totalCollecte / $totalAttendu * 100, 1) : 0,
            'par_mode_paiement' => $parMode,
            'par_mois'          => $parMois,
        ];
    }
}
