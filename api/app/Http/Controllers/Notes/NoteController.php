<?php

namespace App\Http\Controllers\Notes;

use App\Http\Controllers\Controller;
use App\Models\Examen;
use App\Models\Note;
use App\Models\SessionExamen;
use App\Services\NoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NoteController extends Controller
{
    public function __construct(private readonly NoteService $service) {}

    // ── Sessions d'examen ─────────────────────────────────────

    // GET /api/sessions-examen
    public function indexSessions(Request $request): JsonResponse
    {
        $sessions = $this->service->listerSessions(
            $request->etablissement_id,
            $request->only('semestre_id', 'est_cloturee')
        );
        return $this->ok('Sessions d\'examen.', $sessions);
    }

    // POST /api/sessions-examen
    public function storeSession(Request $request): JsonResponse
    {
        $data = $request->validate([
            'semestre_id'  => 'required|integer|exists:semestres,id',
            'libelle'      => 'required|string|max:100',
            'type_session' => 'nullable|string|max:50',
            'date_debut'   => 'nullable|date',
            'date_fin'     => 'nullable|date|after_or_equal:date_debut',
        ]);
        $session = $this->service->creerSession($request->etablissement_id, $data);
        return $this->ok('Session créée.', $session, 201);
    }

    // PATCH /api/sessions-examen/{id}/cloturer
    public function cloturerSession(int $id): JsonResponse
    {
        $session = SessionExamen::findOrFail($id);
        try {
            return $this->ok('Session clôturée.', $this->service->cloturerSession($session));
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), 422);
        }
    }

    // ── Examens ───────────────────────────────────────────────

    // GET /api/sessions-examen/{id}/examens
    public function indexExamens(Request $request, int $sessionId): JsonResponse
    {
        $examens = $this->service->listerExamens($sessionId, $request->only('classe_id'));
        return $this->ok('Examens.', $examens);
    }

    // POST /api/sessions-examen/{id}/examens
    public function storeExamen(Request $request, int $sessionId): JsonResponse
    {
        $data = $request->validate([
            'matiere_id'    => 'required|integer|exists:matieres,id',
            'classe_id'     => 'required|integer|exists:classes,id',
            'salle_id'      => 'nullable|integer|exists:salles,id',
            'date_examen'   => 'nullable|date',
            'heure_debut'   => 'nullable|date_format:H:i',
            'heure_fin'     => 'nullable|date_format:H:i|after:heure_debut',
            'surveillant_id'=> 'nullable|integer|exists:enseignants,id',
            'coefficient'   => 'nullable|numeric|min:0',
            'bareme'        => 'nullable|numeric|min:1',
            'observations'  => 'nullable|string',
        ]);
        $examen = $this->service->creerExamen(array_merge($data, ['session_examen_id' => $sessionId]));
        return $this->ok('Examen créé.', $examen->load(['matiere', 'classe', 'salle']), 201);
    }

    // PUT /api/examens/{id}
    public function updateExamen(Request $request, int $id): JsonResponse
    {
        $examen = Examen::findOrFail($id);
        $data   = $request->validate([
            'salle_id'      => 'nullable|integer|exists:salles,id',
            'date_examen'   => 'nullable|date',
            'heure_debut'   => 'nullable|date_format:H:i',
            'heure_fin'     => 'nullable|date_format:H:i',
            'surveillant_id'=> 'nullable|integer|exists:enseignants,id',
            'bareme'        => 'nullable|numeric|min:1',
            'observations'  => 'nullable|string',
        ]);
        return $this->ok('Examen modifié.', $this->service->mettreAJourExamen($examen, $data));
    }

    // DELETE /api/examens/{id}
    public function destroyExamen(int $id): JsonResponse
    {
        $this->service->supprimerExamen(Examen::findOrFail($id));
        return $this->ok('Examen supprimé.');
    }

    // ── Notes ─────────────────────────────────────────────────

    // GET /api/notes/matiere/{matiereId}/session/{sessionId}/classe/{classeId}
    public function notesParMatiere(int $matiereId, int $sessionId, int $classeId): JsonResponse
    {
        $notes = $this->service->notesParMatiere($matiereId, $sessionId, $classeId);
        return $this->ok('Notes de la matière.', $notes);
    }

    // GET /api/etudiants/{id}/releve/{sessionId}
    public function releveEtudiant(int $etudiantId, int $sessionId): JsonResponse
    {
        $releve = $this->service->releveEtudiant($etudiantId, $sessionId);
        return $this->ok('Relevé de notes.', $releve);
    }

    // POST /api/notes
    public function saisirNote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'etudiant_id'       => 'required|integer|exists:etudiants,id',
            'matiere_id'        => 'required|integer|exists:matieres,id',
            'session_examen_id' => 'required|integer|exists:sessions_examen,id',
            'type_note'         => 'required|string|in:CC,TP,Examen,Rattrapage',
            'valeur'            => 'required|numeric|min:0',
            'bareme'            => 'nullable|numeric|min:1',
            'observation'       => 'nullable|string',
        ]);
        try {
            $note = $this->service->saisirNote($data, Auth::id());
            return $this->ok('Note saisie.', $note, 201);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), 422);
        }
    }

    // POST /api/notes/masse
    // Body : { matiere_id, session_examen_id, type_note, notes: [{etudiant_id, valeur, bareme?, observation?}] }
    public function saisirEnMasse(Request $request): JsonResponse
    {
        $data = $request->validate([
            'matiere_id'        => 'required|integer|exists:matieres,id',
            'session_examen_id' => 'required|integer|exists:sessions_examen,id',
            'type_note'         => 'required|string|in:CC,TP,Examen,Rattrapage',
            'notes'             => 'required|array|min:1',
            'notes.*.etudiant_id' => 'required|integer|exists:etudiants,id',
            'notes.*.valeur'      => 'required|numeric|min:0',
            'notes.*.bareme'      => 'nullable|numeric|min:1',
            'notes.*.observation' => 'nullable|string',
        ]);
        try {
            $count = $this->service->saisirEnMasse(
                $data['matiere_id'],
                $data['session_examen_id'],
                $data['type_note'],
                $data['notes'],
                Auth::id()
            );
            return $this->ok("{$count} note(s) saisie(s).", ['count' => $count]);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), 422);
        }
    }

    // PATCH /api/notes/valider
    // Body : { matiere_id, session_examen_id }
    public function validerNotes(Request $request): JsonResponse
    {
        $data = $request->validate([
            'matiere_id'        => 'required|integer|exists:matieres,id',
            'session_examen_id' => 'required|integer|exists:sessions_examen,id',
        ]);
        $count = $this->service->validerNotes($data['matiere_id'], $data['session_examen_id'], Auth::id());
        return $this->ok("{$count} note(s) validée(s).", ['count' => $count]);
    }

    // POST /api/sessions-examen/{id}/calculer-moyennes
    // Body : { classe_id }
    public function calculerMoyennes(Request $request, int $sessionId): JsonResponse
    {
        $data = $request->validate(['classe_id' => 'required|integer|exists:classes,id']);
        try {
            $resultats = $this->service->calculerMoyennes($sessionId, $data['classe_id']);
            return $this->ok('Moyennes calculées.', $resultats);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), 422);
        }
    }

    // GET /api/sessions-examen/{id}/resultats/{classeId}
    public function resultatsClasse(int $sessionId, int $classeId): JsonResponse
    {
        $resultats = $this->service->resultatsClasse($sessionId, $classeId);
        return $this->ok('Résultats de la classe.', $resultats);
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
