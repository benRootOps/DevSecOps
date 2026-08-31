<?php

namespace App\Services;

use App\Models\Bulletin;
use App\Models\Deliberation;
use App\Models\DeliberationResultat;
use App\Models\DocumentOfficiel;
use App\Models\MoyenneSemestre;
use App\Models\Inscription;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// ══════════════════════════════════════════════════════════════
//  DeliberationService
// ══════════════════════════════════════════════════════════════
class DeliberationService
{
    public function lister(int $etablissementId, array $filtres = []): Collection
    {
        return Deliberation::whereHas('sessionExamen',
                fn($q) => $q->where('etablissement_id', $etablissementId))
            ->when(!empty($filtres['session_examen_id']), fn($q) => $q->where('session_examen_id', $filtres['session_examen_id']))
            ->when(!empty($filtres['classe_id']),         fn($q) => $q->where('classe_id', $filtres['classe_id']))
            ->with(['sessionExamen:id,libelle', 'classe:id,nom'])
            ->orderByDesc('cree_le')
            ->get();
    }

    public function creer(array $data): Deliberation
    {
        // Vérifier qu'il n'existe pas déjà une délibération pour ce couple session/classe
        $existe = Deliberation::where('session_examen_id', $data['session_examen_id'])
            ->where('classe_id', $data['classe_id'])
            ->exists();

        if ($existe) {
            throw new \Exception('Une délibération existe déjà pour cette session et cette classe.');
        }

        return Deliberation::create($data);
    }

    /**
     * Saisit les résultats de délibération pour tous les étudiants d'une classe.
     * Si les moyennes ont été calculées, les décisions peuvent être auto-générées.
     *
     * @param array $resultats [{etudiant_id, decision, observations?}]
     */
    public function saisirResultats(Deliberation $deliberation, array $resultats): Deliberation
    {
        if ($deliberation->est_close) {
            throw new \Exception('Impossible de modifier une délibération clôturée.');
        }

        DB::transaction(function () use ($deliberation, $resultats) {
            foreach ($resultats as $r) {
                // Récupérer la moyenne calculée si disponible
                $moyenne = MoyenneSemestre::where('etudiant_id', $r['etudiant_id'])
                    ->where('session_examen_id', $deliberation->session_examen_id)
                    ->first();

                DeliberationResultat::updateOrCreate(
                    ['deliberation_id' => $deliberation->id, 'etudiant_id' => $r['etudiant_id']],
                    [
                        'decision'       => $r['decision'],
                        'moyenne_finale' => $moyenne?->moyenne_generale,
                        'mention'        => $moyenne?->mention,
                        'credits_valides'=> $moyenne?->credits_obtenus ?? 0,
                        'observations'   => $r['observations'] ?? null,
                    ]
                );
            }
        });

        return $deliberation->fresh(['resultats.etudiant']);
    }

    /**
     * Auto-génère les décisions depuis les moyennes calculées.
     * Admis si moyenne >= 10, Ajourné sinon.
     */
    public function autoGenererDecisions(Deliberation $deliberation): int
    {
        if ($deliberation->est_close) {
            throw new \Exception('Délibération clôturée.');
        }

        $etudiantIds = Inscription::where('classe_id', $deliberation->classe_id)
            ->where('statut', 'Validé')
            ->pluck('etudiant_id');

        $count = 0;
        DB::transaction(function () use ($deliberation, $etudiantIds, &$count) {
            foreach ($etudiantIds as $etudiantId) {
                $moyenne = MoyenneSemestre::where('etudiant_id', $etudiantId)
                    ->where('session_examen_id', $deliberation->session_examen_id)
                    ->first();

                if (!$moyenne) continue;

                $decision = $moyenne->est_valide ? 'Admis' : 'Ajourné';

                DeliberationResultat::updateOrCreate(
                    ['deliberation_id' => $deliberation->id, 'etudiant_id' => $etudiantId],
                    [
                        'decision'       => $decision,
                        'moyenne_finale' => $moyenne->moyenne_generale,
                        'mention'        => $moyenne->mention,
                        'credits_valides'=> $moyenne->credits_obtenus,
                    ]
                );
                $count++;
            }
        });

        return $count;
    }

    public function cloturer(Deliberation $deliberation): Deliberation
    {
        if ($deliberation->est_close) {
            throw new \Exception('Délibération déjà clôturée.');
        }
        $deliberation->update(['est_close' => true]);
        return $deliberation->fresh();
    }

    public function resultats(int $deliberationId): Collection
    {
        return DeliberationResultat::where('deliberation_id', $deliberationId)
            ->with(['etudiant:id,nom,prenom,matricule'])
            ->orderBy('moyenne_finale', 'desc')
            ->get();
    }
}


// ══════════════════════════════════════════════════════════════
//  BulletinService
// ══════════════════════════════════════════════════════════════
class BulletinService
{
    /**
     * Génère les bulletins pour tous les étudiants d'une classe.
     * En pratique, "générer" = créer l'entrée en BD (le PDF est généré par un Job séparé).
     */
    public function genererPourClasse(int $sessionId, int $classeId, int $genereParId): int
    {
        $session = \App\Models\SessionExamen::with('semestre')->findOrFail($sessionId);

        $etudiantIds = Inscription::where('classe_id', $classeId)
            ->where('statut', 'Validé')
            ->pluck('etudiant_id');

        $count = 0;
        DB::transaction(function () use ($session, $etudiantIds, $genereParId, &$count) {
            foreach ($etudiantIds as $etudiantId) {
                Bulletin::updateOrCreate(
                    [
                        'etudiant_id'       => $etudiantId,
                        'semestre_id'       => $session->semestre_id,
                        'session_examen_id' => $session->id,
                        'type_bulletin'     => 'Semestriel',
                    ],
                    [
                        'genere_le'  => now(),
                        'genere_par' => $genereParId,
                        'est_publie' => false,
                    ]
                );
                $count++;
                // TODO : dispatch GenerateBulletinPdfJob::dispatch($etudiantId, $session->id)
            }
        });

        return $count;
    }

    public function genererPourEtudiant(int $etudiantId, int $sessionId, int $genereParId): Bulletin
    {
        $session = \App\Models\SessionExamen::with('semestre')->findOrFail($sessionId);

        return Bulletin::updateOrCreate(
            [
                'etudiant_id'       => $etudiantId,
                'semestre_id'       => $session->semestre_id,
                'session_examen_id' => $session->id,
                'type_bulletin'     => 'Semestriel',
            ],
            [
                'genere_le'  => now(),
                'genere_par' => $genereParId,
                'est_publie' => false,
            ]
        );
    }

    public function publier(Bulletin $bulletin): Bulletin
    {
        $bulletin->update(['est_publie' => true]);
        return $bulletin->fresh();
    }

    public function lister(int $etablissementId, array $filtres = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return Bulletin::whereHas('etudiant',
                fn($q) => $q->where('etablissement_id', $etablissementId))
            ->when(!empty($filtres['semestre_id']),       fn($q) => $q->where('semestre_id', $filtres['semestre_id']))
            ->when(!empty($filtres['session_examen_id']), fn($q) => $q->where('session_examen_id', $filtres['session_examen_id']))
            ->when(isset($filtres['est_publie']),         fn($q) => $q->where('est_publie', $filtres['est_publie']))
            ->with(['etudiant:id,nom,prenom,matricule', 'semestre:id,libelle'])
            ->orderByDesc('genere_le')
            ->paginate($filtres['par_page'] ?? 20);
    }

    // ── Documents officiels ───────────────────────────────────

    public function creerDocument(array $data, int $genereParId): DocumentOfficiel
    {
        $numero = 'DOC-' . now()->year . '-' . strtoupper(Str::random(8));

        return DocumentOfficiel::create(array_merge($data, [
            'numero_document' => $numero,
            'genere_par'      => $genereParId,
            'genere_le'       => now(),
        ]));
    }

    public function validerDocument(DocumentOfficiel $document, int $valideParId): DocumentOfficiel
    {
        $document->update(['est_valide' => true, 'valide_par' => $valideParId]);
        return $document->fresh();
    }

    public function documentsEtudiant(int $etudiantId): Collection
    {
        return DocumentOfficiel::where('etudiant_id', $etudiantId)
            ->with(['anneeAcademique:id,libelle', 'generePar:id,nom,prenom'])
            ->orderByDesc('genere_le')
            ->get();
    }
}
