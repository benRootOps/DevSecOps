<?php

namespace App\Http\Controllers\Presences;

use App\Http\Controllers\Controller;
use App\Models\Seance;
use App\Services\PresenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// ══════════════════════════════════════════════════════════════
//  PresenceController — Module B
// ══════════════════════════════════════════════════════════════
class PresenceController extends Controller
{
    public function __construct(private readonly PresenceService $service) {}

    // GET /api/v1/seances/{id}/presences
    public function feuille(int $seanceId): JsonResponse
    {
        $feuille = $this->service->feuille($seanceId);
        return $this->ok('Feuille de présence.', $feuille);
    }

    // POST /api/v1/seances/{id}/presences/initialiser
    public function initialiser(int $seanceId): JsonResponse
    {
        $seance  = Seance::findOrFail($seanceId);
        $feuille = $this->service->initialiserFeuille($seance);
        return $this->ok('Feuille de présence initialisée.', $feuille);
    }

    // POST /api/v1/seances/{id}/presences/masse
    // Body : { presences: [{etudiant_id, statut, motif?}] }
    public function saisirEnMasse(Request $request, int $seanceId): JsonResponse
    {
        $data = $request->validate([
            'presences'              => 'required|array|min:1',
            'presences.*.etudiant_id'=> 'required|integer|exists:etudiants,id',
            'presences.*.statut'     => 'required|string|in:Présent,Absent,Retard,Excusé',
            'presences.*.motif'      => 'nullable|string|max:500',
        ]);

        $seance = Seance::findOrFail($seanceId);
        $this->service->saisirEnMasse($seance, $data['presences'], $request->user()->id);
        return $this->ok('Présences enregistrées.', $this->service->feuille($seanceId));
    }

    // PATCH /api/v1/seances/{seanceId}/presences/{etudiantId}
    public function update(Request $request, int $seanceId, int $etudiantId): JsonResponse
    {
        $data = $request->validate([
            'statut' => 'required|string|in:Présent,Absent,Retard,Excusé',
            'motif'  => 'nullable|string|max:500',
        ]);
        $presence = $this->service->mettreAJour($seanceId, $etudiantId, $data, $request->user()->id);
        return $this->ok('Présence mise à jour.', $presence);
    }

    // GET /api/v1/etudiants/{id}/statistiques-presences
    public function statistiquesEtudiant(Request $request, int $etudiantId): JsonResponse
    {
        $request->validate([
            'classe_id'   => 'required|integer|exists:classes,id',
            'semestre_id' => 'nullable|integer|exists:semestres,id',
        ]);
        $stats = $this->service->statistiquesEtudiant(
            $etudiantId,
            $request->integer('classe_id'),
            $request->integer('semestre_id')
        );
        return $this->ok('Statistiques de présence.', $stats);
    }

    // GET /api/v1/classes/{id}/statistiques-presences
    public function statistiquesClasse(Request $request, int $classeId): JsonResponse
    {
        $request->validate([
            'semestre_id'   => 'required|integer|exists:semestres,id',
            'seuil_alerte'  => 'nullable|numeric|min:0|max:100',
        ]);
        $stats = $this->service->statistiquesClasse(
            $classeId,
            $request->integer('semestre_id'),
            $request->float('seuil_alerte', 75.0)
        );
        return $this->ok('Statistiques de la classe.', $stats);
    }

    // POST /api/v1/seances/{id}/presence-enseignant
    public function saisirEnseignant(Request $request, int $seanceId): JsonResponse
    {
        $data = $request->validate([
            'enseignant_id' => 'required|integer|exists:enseignants,id',
            'statut'        => 'required|string|in:Présent,Absent,Remplacé',
            'remplacant_id' => 'nullable|integer|exists:enseignants,id',
            'observations'  => 'nullable|string|max:1000',
        ]);
        $seance   = Seance::findOrFail($seanceId);
        $presence = $this->service->saisirPresenceEnseignant($seance, $data, $request->user()->id);
        return $this->ok('Présence enseignant enregistrée.', $presence);
    }

    // GET /api/v1/enseignants/{id}/presences
    public function presencesEnseignant(Request $request, int $enseignantId): JsonResponse
    {
        $presences = $this->service->presencesEnseignant(
            $enseignantId,
            $request->integer('semestre_id') ?: null
        );
        return $this->ok('Présences de l\'enseignant.', $presences);
    }

    private function ok(string $msg, mixed $data = null, int $code = 200): JsonResponse
    {
        return response()->json(['succes' => true, 'message' => $msg, 'donnees' => $data], $code);
    }
}
