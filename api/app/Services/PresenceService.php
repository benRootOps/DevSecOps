<?php

namespace App\Services;

use App\Models\Presence;
use App\Models\PresenceEnseignant;
use App\Models\Seance;
use App\Models\Inscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;

class PresenceService
{
    // ── Saisie présences étudiants ────────────────────────────────

    /**
     * Initialise la feuille de présence d'une séance.
     * Crée une entrée "Présent" pour chaque étudiant inscrit dans la classe.
     * Idempotent : n'écrase pas les entrées existantes.
     */
    public function initialiserFeuille(Seance $seance): Collection
    {
        $etudiantIds = Inscription::where('classe_id', $seance->classe_id)
            ->where('statut', 'Validé')
            ->pluck('etudiant_id');

        DB::transaction(function () use ($seance, $etudiantIds) {
            foreach ($etudiantIds as $etudiantId) {
                Presence::firstOrCreate(
                    ['seance_id' => $seance->id, 'etudiant_id' => $etudiantId],
                    ['statut' => 'Présent']
                );
            }
        });

        return $this->feuille($seance->id);
    }

    /**
     * Retourne la feuille de présence complète d'une séance.
     */
    public function feuille(int $seanceId): Collection
    {
        return Presence::with(['etudiant', 'saisie:id,nom,prenom'])
            ->where('seance_id', $seanceId)
            ->orderBy('etudiant_id')
            ->get();
    }

    /**
     * Saisie en masse : reçoit un tableau [{etudiant_id, statut, motif?}].
     */
    public function saisirEnMasse(Seance $seance, array $presences, int $saisieParId): void
    {
        DB::transaction(function () use ($seance, $presences, $saisieParId) {
            foreach ($presences as $p) {
                Presence::updateOrCreate(
                    ['seance_id' => $seance->id, 'etudiant_id' => $p['etudiant_id']],
                    [
                        'statut'    => $p['statut'],
                        'motif'     => $p['motif'] ?? null,
                        'saisie_par'=> $saisieParId,
                    ]
                );
            }
        });
    }

    /**
     * Modifie la présence d'un étudiant sur une séance.
     */
    public function mettreAJour(int $seanceId, int $etudiantId, array $data, int $saisieParId): Presence
    {
        $presence = Presence::updateOrCreate(
            ['seance_id' => $seanceId, 'etudiant_id' => $etudiantId],
            array_merge($data, ['saisie_par' => $saisieParId])
        );

        return $presence->fresh(['etudiant']);
    }

    // ── Statistiques ──────────────────────────────────────────────

    /**
     * Taux d'assiduité d'un étudiant sur un semestre/classe.
     *
     * @return array{total: int, present: int, absent: int, retard: int, excus: int, taux: float}
     */
    public function statistiquesEtudiant(int $etudiantId, int $classeId, ?int $semestreId = null): array
    {
        $query = Presence::where('etudiant_id', $etudiantId)
            ->whereHas('seance', function ($q) use ($classeId, $semestreId) {
                $q->where('classe_id', $classeId)
                  ->where('est_annule', false)
                  ->when($semestreId, fn($q2) => $q2->where('semestre_id', $semestreId));
            });

        $total   = $query->count();
        $present = (clone $query)->where('statut', 'Présent')->count();
        $retard  = (clone $query)->where('statut', 'Retard')->count();
        $excuse  = (clone $query)->where('statut', 'Excusé')->count();
        $absent  = (clone $query)->where('statut', 'Absent')->count();

        return [
            'total'   => $total,
            'present' => $present,
            'retard'  => $retard,
            'excus'   => $excuse,
            'absent'  => $absent,
            'taux'    => $total > 0 ? round(($present + $retard + $excuse) / $total * 100, 1) : 0,
        ];
    }

    /**
     * Statistiques globales d'une classe sur une séance ou un semestre.
     * Retourne le taux de présence moyen et la liste des étudiants à risque (< seuil%).
     */
    public function statistiquesClasse(int $classeId, int $semestreId, float $seuilAlerte = 75.0): array
    {
        $etudiantIds = Inscription::where('classe_id', $classeId)
            ->where('statut', 'Validé')
            ->pluck('etudiant_id');

        $resultats = [];
        $tauxTotal = 0;

        foreach ($etudiantIds as $etudiantId) {
            $stats = $this->statistiquesEtudiant($etudiantId, $classeId, $semestreId);
            $resultats[] = array_merge(['etudiant_id' => $etudiantId], $stats);
            $tauxTotal += $stats['taux'];
        }

        $count = count($etudiantIds);

        return [
            'taux_moyen'        => $count > 0 ? round($tauxTotal / $count, 1) : 0,
            'etudiants_a_risque'=> array_filter($resultats, fn($r) => $r['taux'] < $seuilAlerte),
            'detail'            => $resultats,
        ];
    }

    // ── Présences enseignants ─────────────────────────────────────

    public function saisirPresenceEnseignant(Seance $seance, array $data, int $saisieParId): PresenceEnseignant
    {
        $presence = PresenceEnseignant::updateOrCreate(
            ['seance_id' => $seance->id, 'enseignant_id' => $data['enseignant_id']],
            [
                'statut'        => $data['statut'],
                'remplacant_id' => $data['remplacant_id'] ?? null,
                'observations'  => $data['observations']  ?? null,
                'saisie_par'    => $saisieParId,
            ]
        );

        return $presence->fresh(['enseignant.utilisateur', 'remplacant.utilisateur']);
    }

    public function presencesEnseignant(int $enseignantId, ?int $semestreId = null): Collection
    {
        return PresenceEnseignant::with('seance.creneau')
            ->where('enseignant_id', $enseignantId)
            ->whereHas('seance', function ($q) use ($semestreId) {
                $q->when($semestreId, fn($q2) => $q2->where('semestre_id', $semestreId));
            })
            ->get();
    }
}
