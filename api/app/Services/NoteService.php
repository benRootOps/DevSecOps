<?php

namespace App\Services;

use App\Models\Examen;
use App\Models\MoyenneSemestre;
use App\Models\MoyenneUe;
use App\Models\Note;
use App\Models\SessionExamen;
use App\Models\UniteEnseignement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class NoteService
{
    // ── Sessions d'examen ─────────────────────────────────────

    public function listerSessions(int $etablissementId, array $filtres = []): Collection
    {
        return SessionExamen::where('etablissement_id', $etablissementId)
            ->when(!empty($filtres['semestre_id']), fn($q) => $q->where('semestre_id', $filtres['semestre_id']))
            ->when(isset($filtres['est_cloturee']),  fn($q) => $q->where('est_cloturee', $filtres['est_cloturee']))
            ->with('semestre:id,libelle')
            ->orderByDesc('cree_le')
            ->get();
    }

    public function creerSession(int $etablissementId, array $data): SessionExamen
    {
        return SessionExamen::create(array_merge($data, [
            'etablissement_id' => $etablissementId,
        ]));
    }

    public function cloturerSession(SessionExamen $session): SessionExamen
    {
        if ($session->est_cloturee) {
            throw new \Exception('Cette session est déjà clôturée.');
        }
        $session->update(['est_cloturee' => true]);
        return $session->fresh();
    }

    // ── Examens ───────────────────────────────────────────────

    public function listerExamens(int $sessionId, array $filtres = []): Collection
    {
        return Examen::where('session_examen_id', $sessionId)
            ->when(!empty($filtres['classe_id']), fn($q) => $q->where('classe_id', $filtres['classe_id']))
            ->with(['matiere:id,intitule,code', 'classe:id,nom', 'salle:id,nom', 'surveillant.utilisateur'])
            ->orderBy('date_examen')
            ->get();
    }

    public function creerExamen(array $data): Examen
    {
        return Examen::create($data);
    }

    public function mettreAJourExamen(Examen $examen, array $data): Examen
    {
        $examen->update($data);
        return $examen->fresh(['matiere', 'classe', 'salle']);
    }

    public function supprimerExamen(Examen $examen): void
    {
        $examen->delete();
    }

    // ── Notes ─────────────────────────────────────────────────

    /**
     * Notes d'une matière pour une classe dans une session.
     */
    public function notesParMatiere(int $matiereId, int $sessionId, int $classeId): Collection
    {
        return Note::where('matiere_id', $matiereId)
            ->where('session_examen_id', $sessionId)
            ->whereHas('etudiant', fn($q) => $q->whereHas('inscriptions',
                fn($q2) => $q2->where('classe_id', $classeId)
            ))
            ->with(['etudiant:id,nom,prenom,matricule', 'saisiePar:id,nom,prenom'])
            ->orderBy('type_note')
            ->get();
    }

    /**
     * Relevé de notes d'un étudiant pour une session.
     */
    public function releveEtudiant(int $etudiantId, int $sessionId): Collection
    {
        return Note::where('etudiant_id', $etudiantId)
            ->where('session_examen_id', $sessionId)
            ->with(['matiere:id,intitule,code,coefficient', 'sessionExamen:id,libelle'])
            ->orderBy('matiere_id')
            ->get();
    }

    /**
     * Saisit ou met à jour une note.
     */
    public function saisirNote(array $data, int $saisiParId): Note
    {
        $session = SessionExamen::findOrFail($data['session_examen_id']);
        if ($session->est_cloturee) {
            throw new \Exception('Impossible de saisir des notes : la session est clôturée.');
        }

        return Note::updateOrCreate(
            [
                'etudiant_id'       => $data['etudiant_id'],
                'matiere_id'        => $data['matiere_id'],
                'session_examen_id' => $data['session_examen_id'],
                'type_note'         => $data['type_note'],
            ],
            [
                'valeur'     => $data['valeur'],
                'bareme'     => $data['bareme'] ?? 20.00,
                'observation'=> $data['observation'] ?? null,
                'saisie_par' => $saisiParId,
                'est_validee'=> false,
            ]
        );
    }

    /**
     * Saisie en masse : [{etudiant_id, valeur, observation?}] pour une matière/session/type_note.
     */
    public function saisirEnMasse(int $matiereId, int $sessionId, string $typeNote, array $notes, int $saisiParId): int
    {
        $session = SessionExamen::findOrFail($sessionId);
        if ($session->est_cloturee) {
            throw new \Exception('Session clôturée — saisie impossible.');
        }

        $count = 0;
        DB::transaction(function () use ($matiereId, $sessionId, $typeNote, $notes, $saisiParId, &$count) {
            foreach ($notes as $n) {
                Note::updateOrCreate(
                    [
                        'etudiant_id'       => $n['etudiant_id'],
                        'matiere_id'        => $matiereId,
                        'session_examen_id' => $sessionId,
                        'type_note'         => $typeNote,
                    ],
                    [
                        'valeur'     => $n['valeur'],
                        'bareme'     => $n['bareme'] ?? 20.00,
                        'observation'=> $n['observation'] ?? null,
                        'saisie_par' => $saisiParId,
                        'est_validee'=> false,
                    ]
                );
                $count++;
            }
        });

        return $count;
    }

    /**
     * Valide toutes les notes d'une matière pour une session.
     */
    public function validerNotes(int $matiereId, int $sessionId, int $valideParId): int
    {
        return Note::where('matiere_id', $matiereId)
            ->where('session_examen_id', $sessionId)
            ->where('est_validee', false)
            ->update(['est_validee' => true, 'valide_par' => $valideParId]);
    }

    // ── Calcul des moyennes ───────────────────────────────────

    /**
     * Calcule et persiste les moyennes UE + semestre pour tous les étudiants
     * d'une classe dans une session.
     *
     * Logique :
     *   Moyenne UE = somme(note_sur_20 × coeff_matière) / somme(coeff_matière)
     *   UE validée si moyenne_UE >= 10
     *   Moyenne semestre = somme(moyenne_UE × credits_UE) / somme(credits_UE)
     */
    public function calculerMoyennes(int $sessionId, int $classeId): array
    {
        $session = SessionExamen::with('semestre')->findOrFail($sessionId);

        // UEs du semestre de cette session
        $ues = UniteEnseignement::where('semestre_id', $session->semestre_id)
            ->with(['matieres:id,unite_id,coefficient'])
            ->get();

        // Étudiants de la classe
        $etudiantIds = \App\Models\Inscription::where('classe_id', $classeId)
            ->where('statut', 'Validé')
            ->pluck('etudiant_id');

        $resultats = ['ues' => 0, 'semestres' => 0];

        DB::transaction(function () use ($session, $ues, $etudiantIds, $classeId, &$resultats) {
            foreach ($etudiantIds as $etudiantId) {
                $totalCreditsUe   = 0;
                $sommePondereeUe  = 0;

                foreach ($ues as $ue) {
                    $sommePonderee = 0;
                    $sommeCoeffs   = 0;

                    foreach ($ue->matieres as $matiere) {
                        // Prendre la note la plus récente (CC ou Examen)
                        $note = Note::where('etudiant_id', $etudiantId)
                            ->where('matiere_id', $matiere->id)
                            ->where('session_examen_id', $session->id)
                            ->whereIn('type_note', ['Examen', 'Rattrapage', 'CC'])
                            ->orderByRaw("FIELD(type_note, 'Rattrapage', 'Examen', 'CC')")
                            ->first();

                        if ($note) {
                            $noteSur20     = $note->bareme > 0 ? $note->valeur * 20 / $note->bareme : 0;
                            $coeff         = $matiere->coefficient ?? 1;
                            $sommePonderee += $noteSur20 * $coeff;
                            $sommeCoeffs   += $coeff;
                        }
                    }

                    $moyenneUe = $sommeCoeffs > 0 ? round($sommePonderee / $sommeCoeffs, 2) : null;
                    $valide    = $moyenneUe !== null && $moyenneUe >= 10;
                    $credits   = $valide ? $ue->credits : 0;

                    MoyenneUe::updateOrCreate(
                        ['etudiant_id' => $etudiantId, 'unite_id' => $ue->id, 'session_examen_id' => $session->id],
                        ['moyenne' => $moyenneUe, 'credits_obtenus' => $credits, 'est_validee' => $valide]
                    );
                    $resultats['ues']++;

                    if ($moyenneUe !== null) {
                        $sommePondereeUe += $moyenneUe * $ue->credits;
                        $totalCreditsUe  += $ue->credits;
                    }
                }

                // Moyenne semestre
                $moyenneSem = $totalCreditsUe > 0 ? round($sommePondereeUe / $totalCreditsUe, 2) : null;
                $creditsObt = MoyenneUe::where('etudiant_id', $etudiantId)
                    ->where('session_examen_id', $session->id)
                    ->sum('credits_obtenus');

                MoyenneSemestre::updateOrCreate(
                    ['etudiant_id' => $etudiantId, 'semestre_id' => $session->semestre_id, 'session_examen_id' => $session->id],
                    [
                        'moyenne_generale'=> $moyenneSem,
                        'total_credits'   => $totalCreditsUe,
                        'credits_obtenus' => $creditsObt,
                        'mention'         => $moyenneSem ? MoyenneSemestre::calculerMention($moyenneSem) : null,
                        'est_valide'      => $moyenneSem !== null && $moyenneSem >= 10,
                    ]
                );
                $resultats['semestres']++;
            }

            // Calcul des rangs
            $this->calculerRangs($session->id, $session->semestre_id);
        });

        return $resultats;
    }

    private function calculerRangs(int $sessionId, int $semestreId): void
    {
        $moyennes = MoyenneSemestre::where('session_examen_id', $sessionId)
            ->where('semestre_id', $semestreId)
            ->whereNotNull('moyenne_generale')
            ->orderByDesc('moyenne_generale')
            ->get();

        foreach ($moyennes as $index => $m) {
            $m->update(['rang' => $index + 1]);
        }
    }

    /**
     * Résultats complets d'une classe (pour affichage tableau de bord).
     */
    public function resultatsClasse(int $sessionId, int $classeId): Collection
    {
        $session = SessionExamen::with('semestre')->findOrFail($sessionId);

        return MoyenneSemestre::where('session_examen_id', $sessionId)
            ->where('semestre_id', $session->semestre_id)
            ->whereHas('etudiant', fn($q) => $q->whereHas('inscriptions',
                fn($q2) => $q2->where('classe_id', $classeId)
            ))
            ->with(['etudiant:id,nom,prenom,matricule'])
            ->orderBy('rang')
            ->get();
    }
}
