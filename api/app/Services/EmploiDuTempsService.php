<?php

namespace App\Services;

use App\Models\AffectationCours;
use App\Models\ConflitEmploiDuTemps;
use App\Models\CreneauHoraire;
use App\Models\Salle;
use App\Models\Seance;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class EmploiDuTempsService
{
    // ── Salles ────────────────────────────────────────────────────

    public function listerSalles(int $etablissementId, array $filtres = []): Collection
    {
        return Salle::where('etablissement_id', $etablissementId)
            ->when(isset($filtres['disponible']), fn($q) => $q->where('est_disponible', $filtres['disponible']))
            ->orderBy('nom')
            ->get();
    }

    public function creerSalle(int $etablissementId, array $data): Salle
    {
        return Salle::create(array_merge($data, ['etablissement_id' => $etablissementId]));
    }

    public function mettreAJourSalle(Salle $salle, array $data): Salle
    {
        $salle->update($data);
        return $salle->fresh();
    }

    public function supprimerSalle(Salle $salle): void
    {
        if ($salle->seances()->where('est_annule', false)->exists()) {
            throw new \Exception('Impossible de supprimer une salle ayant des séances actives.');
        }
        $salle->delete();
    }

    // ── Créneaux ──────────────────────────────────────────────────

    public function listerCreneaux(int $etablissementId): Collection
    {
        return CreneauHoraire::where('etablissement_id', $etablissementId)
            ->orderBy('ordre')
            ->get();
    }

    public function creerCreneau(int $etablissementId, array $data): CreneauHoraire
    {
        return CreneauHoraire::create(array_merge($data, ['etablissement_id' => $etablissementId]));
    }

    // ── Affectations ──────────────────────────────────────────────

    public function listerAffectations(int $etablissementId, array $filtres = []): Collection
    {
        return AffectationCours::with(['enseignant.utilisateur', 'matiere', 'classe'])
            ->whereHas('classe', fn($q) => $q->whereHas('niveau.filiere.departement.faculte',
                fn($q2) => $q2->where('etablissement_id', $etablissementId)
            ))
            ->when(!empty($filtres['enseignant_id']), fn($q) => $q->where('enseignant_id', $filtres['enseignant_id']))
            ->when(!empty($filtres['classe_id']),     fn($q) => $q->where('classe_id', $filtres['classe_id']))
            ->get();
    }

    public function creerAffectation(array $data): AffectationCours
    {
        $existe = AffectationCours::where([
            'enseignant_id' => $data['enseignant_id'],
            'matiere_id'    => $data['matiere_id'],
            'classe_id'     => $data['classe_id'],
        ])->exists();

        if ($existe) {
            throw new \Exception('Cette affectation existe déjà pour cet enseignant, cette matière et cette classe.');
        }

        return AffectationCours::create($data);
    }

    public function supprimerAffectation(AffectationCours $affectation): void
    {
        if ($affectation->seances()->exists()) {
            throw new \Exception('Impossible de supprimer une affectation ayant des séances planifiées.');
        }
        $affectation->delete();
    }

    // ── Séances ───────────────────────────────────────────────────

    /**
     * Emploi du temps d'une classe pour un semestre donné.
     * Retourne les séances groupées par jour.
     */
    public function emploiDuTempsClasse(int $classeId, int $semestreId): array
    {
        $seances = Seance::with(['affectation.matiere', 'affectation.enseignant.utilisateur', 'salle', 'creneau'])
            ->where('classe_id', $classeId)
            ->where('semestre_id', $semestreId)
            ->where('est_annule', false)
            ->orderBy('jour_semaine')
            ->orderBy('creneau_id')
            ->get();

        $groupes = [];
        foreach (Seance::JOURS as $num => $nom) {
            $groupes[$nom] = $seances->where('jour_semaine', $num)->values();
        }

        return $groupes;
    }

    /**
     * Emploi du temps d'un enseignant.
     */
    public function emploiDuTempsEnseignant(int $enseignantId, int $semestreId): array
    {
        $seances = Seance::with(['affectation.matiere', 'salle', 'creneau', 'classe'])
            ->whereHas('affectation', fn($q) => $q->where('enseignant_id', $enseignantId))
            ->where('semestre_id', $semestreId)
            ->where('est_annule', false)
            ->orderBy('jour_semaine')
            ->orderBy('creneau_id')
            ->get();

        $groupes = [];
        foreach (Seance::JOURS as $num => $nom) {
            $groupes[$nom] = $seances->where('jour_semaine', $num)->values();
        }

        return $groupes;
    }

    /**
     * Crée une séance et détecte automatiquement les conflits.
     */
    public function creerSeance(int $etablissementId, array $data): Seance
    {
        return DB::transaction(function () use ($etablissementId, $data) {
            $seance = Seance::create(array_merge($data, [
                'etablissement_id' => $etablissementId,
            ]));

            $this->detecterConflits($seance);

            return $seance->load(['affectation.matiere', 'affectation.enseignant.utilisateur', 'salle', 'creneau']);
        });
    }

    public function mettreAJourSeance(Seance $seance, array $data): Seance
    {
        return DB::transaction(function () use ($seance, $data) {
            $seance->update($data);
            // Réanalyser les conflits après modification
            $seance->conflits()->delete();
            $this->detecterConflits($seance->fresh());
            return $seance->fresh(['affectation.matiere', 'salle', 'creneau', 'conflits']);
        });
    }

    public function annulerSeance(Seance $seance, string $motif): Seance
    {
        $seance->update(['est_annule' => true, 'motif_annulation' => $motif]);
        return $seance->fresh();
    }

    // ── Détection de conflits ─────────────────────────────────────

    /**
     * Détecte trois types de conflits :
     *   1. Salle occupée au même créneau/jour
     *   2. Enseignant déjà planifié au même créneau/jour
     *   3. Classe déjà planifiée au même créneau/jour
     */
    private function detecterConflits(Seance $seance): void
    {
        $base = Seance::where('id', '!=', $seance->id)
            ->where('semestre_id', $seance->semestre_id)
            ->where('jour_semaine', $seance->jour_semaine)
            ->where('creneau_id', $seance->creneau_id)
            ->where('est_annule', false);

        // Conflit de salle
        if ($seance->salle_id) {
            $conflit = (clone $base)->where('salle_id', $seance->salle_id)->first();
            if ($conflit) {
                ConflitEmploiDuTemps::create([
                    'seance_id'    => $seance->id,
                    'type_conflit' => 'Conflit de salle',
                    'detail'       => "La salle est déjà occupée par la séance #{$conflit->id}",
                ]);
            }
        }

        // Conflit enseignant
        $enseignantId = $seance->affectation->enseignant_id;
        $conflitEns = (clone $base)
            ->whereHas('affectation', fn($q) => $q->where('enseignant_id', $enseignantId))
            ->first();
        if ($conflitEns) {
            ConflitEmploiDuTemps::create([
                'seance_id'    => $seance->id,
                'type_conflit' => 'Conflit enseignant',
                'detail'       => "L'enseignant est déjà planifié sur la séance #{$conflitEns->id}",
            ]);
        }

        // Conflit classe
        $conflitClasse = (clone $base)->where('classe_id', $seance->classe_id)->first();
        if ($conflitClasse) {
            ConflitEmploiDuTemps::create([
                'seance_id'    => $seance->id,
                'type_conflit' => 'Conflit de classe',
                'detail'       => "La classe a déjà une séance planifiée (#{$conflitClasse->id})",
            ]);
        }
    }

    public function conflitsNonResolus(int $etablissementId): Collection
    {
        return ConflitEmploiDuTemps::with(['seance.affectation.matiere', 'seance.salle', 'seance.creneau'])
            ->whereHas('seance', fn($q) => $q->where('etablissement_id', $etablissementId))
            ->where('resolu', false)
            ->orderByDesc('cree_le')
            ->get();
    }

    public function resoudreConflit(ConflitEmploiDuTemps $conflit): ConflitEmploiDuTemps
    {
        $conflit->update(['resolu' => true]);
        return $conflit->fresh();
    }
}
