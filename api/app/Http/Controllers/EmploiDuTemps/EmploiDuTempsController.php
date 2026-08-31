<?php

namespace App\Http\Controllers\EmploiDuTemps;

use App\Http\Controllers\Controller;
use App\Models\AffectationCours;
use App\Models\ConflitEmploiDuTemps;
use App\Models\CreneauHoraire;
use App\Models\Salle;
use App\Models\Seance;
use App\Services\EmploiDuTempsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmploiDuTempsController extends Controller
{
    public function __construct(private readonly EmploiDuTempsService $service) {}

    // ── Salles ────────────────────────────────────────────────────

    // GET /api/v1/salles
    public function indexSalles(Request $request): JsonResponse
    {
        $salles = $this->service->listerSalles(
            $request->etablissement_id,
            $request->only('disponible')
        );
        return $this->ok('Liste des salles.', $salles);
    }

    // POST /api/v1/salles
    public function storeSalle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nom'            => 'required|string|max:100',
            'batiment'       => 'nullable|string|max:100',
            'capacite'       => 'nullable|integer|min:1',
            'type_salle'     => 'nullable|string|max:50',
            'est_disponible' => 'boolean',
        ]);
        $salle = $this->service->creerSalle($request->etablissement_id, $data);
        return $this->ok('Salle créée avec succès.', $salle, 201);
    }

    // PUT /api/v1/salles/{id}
    public function updateSalle(Request $request, int $id): JsonResponse
    {
        $salle = Salle::findOrFail($id);
        $data  = $request->validate([
            'nom'            => 'sometimes|string|max:100',
            'batiment'       => 'nullable|string|max:100',
            'capacite'       => 'nullable|integer|min:1',
            'type_salle'     => 'nullable|string|max:50',
            'est_disponible' => 'boolean',
        ]);
        return $this->ok('Salle mise à jour.', $this->service->mettreAJourSalle($salle, $data));
    }

    // DELETE /api/v1/salles/{id}
    public function destroySalle(int $id): JsonResponse
    {
        $salle = Salle::findOrFail($id);
        try {
            $this->service->supprimerSalle($salle);
            return $this->ok('Salle supprimée.');
        } catch (\Exception $e) {
            return $this->fail($e->getMessage());
        }
    }

    // ── Créneaux ──────────────────────────────────────────────────

    // GET /api/v1/creneaux
    public function indexCreneaux(Request $request): JsonResponse
    {
        return $this->ok('Créneaux horaires.', $this->service->listerCreneaux($request->etablissement_id));
    }

    // POST /api/v1/creneaux
    public function storeCreneau(Request $request): JsonResponse
    {
        $data = $request->validate([
            'heure_debut' => 'required|date_format:H:i',
            'heure_fin'   => 'required|date_format:H:i|after:heure_debut',
            'libelle'     => 'nullable|string|max:30',
            'ordre'       => 'required|integer|min:1',
        ]);
        return $this->ok('Créneau créé.', $this->service->creerCreneau($request->etablissement_id, $data), 201);
    }

    // DELETE /api/v1/creneaux/{id}
    public function destroyCreneau(int $id): JsonResponse
    {
        $creneau = CreneauHoraire::findOrFail($id);
        if ($creneau->seances()->exists()) {
            return $this->fail('Impossible de supprimer un créneau utilisé par des séances.', 422);
        }
        $creneau->delete();
        return $this->ok('Créneau supprimé.');
    }

    // ── Affectations ──────────────────────────────────────────────

    // GET /api/v1/affectations
    public function indexAffectations(Request $request): JsonResponse
    {
        $affectations = $this->service->listerAffectations(
            $request->etablissement_id,
            $request->only('enseignant_id', 'classe_id')
        );
        return $this->ok('Affectations de cours.', $affectations);
    }

    // POST /api/v1/affectations
    public function storeAffectation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enseignant_id'  => 'required|integer|exists:enseignants,id',
            'matiere_id'     => 'required|integer|exists:matieres,id',
            'classe_id'      => 'required|integer|exists:classes,id',
            'charge_horaire' => 'nullable|integer|min:1',
        ]);
        try {
            $affectation = $this->service->creerAffectation($data);
            return $this->ok('Affectation créée.', $affectation->load(['enseignant.utilisateur', 'matiere', 'classe']), 201);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), 422);
        }
    }

    // DELETE /api/v1/affectations/{id}
    public function destroyAffectation(int $id): JsonResponse
    {
        $affectation = AffectationCours::findOrFail($id);
        try {
            $this->service->supprimerAffectation($affectation);
            return $this->ok('Affectation supprimée.');
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), 422);
        }
    }

    // ── Séances ───────────────────────────────────────────────────

    // GET /api/v1/emploi-du-temps/classe/{classeId}/semestre/{semestreId}
    public function parClasse(int $classeId, int $semestreId): JsonResponse
    {
        $edt = $this->service->emploiDuTempsClasse($classeId, $semestreId);
        return $this->ok('Emploi du temps de la classe.', $edt);
    }

    // GET /api/v1/emploi-du-temps/enseignant/{enseignantId}/semestre/{semestreId}
    public function parEnseignant(int $enseignantId, int $semestreId): JsonResponse
    {
        $edt = $this->service->emploiDuTempsEnseignant($enseignantId, $semestreId);
        return $this->ok('Emploi du temps de l\'enseignant.', $edt);
    }

    // POST /api/v1/seances
    public function storeSeance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'affectation_id'  => 'required|integer|exists:affectations_cours,id',
            'salle_id'        => 'nullable|integer|exists:salles,id',
            'classe_id'       => 'required|integer|exists:classes,id',
            'semestre_id'     => 'required|integer|exists:semestres,id',
            'creneau_id'      => 'required|integer|exists:creneaux_horaires,id',
            'jour_semaine'    => 'required|integer|between:1,6',
            'type_seance'     => 'nullable|string|max:50',
            'date_specifique' => 'nullable|date',
        ]);
        try {
            $seance = $this->service->creerSeance($request->etablissement_id, $data);
            return $this->ok('Séance planifiée.', $seance, 201);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), 422);
        }
    }

    // PUT /api/v1/seances/{id}
    public function updateSeance(Request $request, int $id): JsonResponse
    {
        $seance = Seance::findOrFail($id);
        $data   = $request->validate([
            'salle_id'        => 'nullable|integer|exists:salles,id',
            'creneau_id'      => 'sometimes|integer|exists:creneaux_horaires,id',
            'jour_semaine'    => 'sometimes|integer|between:1,6',
            'type_seance'     => 'nullable|string|max:50',
            'date_specifique' => 'nullable|date',
        ]);
        return $this->ok('Séance mise à jour.', $this->service->mettreAJourSeance($seance, $data));
    }

    // PATCH /api/v1/seances/{id}/annuler
    public function annulerSeance(Request $request, int $id): JsonResponse
    {
        $seance = Seance::findOrFail($id);
        $data   = $request->validate(['motif' => 'required|string|max:500']);
        return $this->ok('Séance annulée.', $this->service->annulerSeance($seance, $data['motif']));
    }

    // ── Conflits ──────────────────────────────────────────────────

    // GET /api/v1/conflits
    public function indexConflits(Request $request): JsonResponse
    {
        $conflits = $this->service->conflitsNonResolus($request->etablissement_id);
        return $this->ok("Conflits non résolus ({$conflits->count()}).", $conflits);
    }

    // PATCH /api/v1/conflits/{id}/resoudre
    public function resoudreConflit(int $id): JsonResponse
    {
        $conflit = ConflitEmploiDuTemps::findOrFail($id);
        return $this->ok('Conflit marqué comme résolu.', $this->service->resoudreConflit($conflit));
    }

    // ── Helper réponse ────────────────────────────────────────────
    private function ok(string $message, mixed $data = null, int $code = 200): JsonResponse
    {
        return response()->json(['succes' => true,  'message' => $message, 'donnees' => $data], $code);
    }

    private function fail(string $message, int $code = 400): JsonResponse
    {
        return response()->json(['succes' => false, 'message' => $message, 'donnees' => null], $code);
    }
}
