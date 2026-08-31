<?php

namespace App\Http\Controllers\Deliberations;

use App\Http\Controllers\Controller;
use App\Models\Bulletin;
use App\Models\Deliberation;
use App\Models\DocumentOfficiel;

use App\Services\DeliberationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

// ══════════════════════════════════════════════════════════════
//  DeliberationController
// ══════════════════════════════════════════════════════════════
class DeliberationController extends Controller
{
    public function __construct(private readonly DeliberationService $service) {}

    // GET /api/deliberations
    public function index(Request $request): JsonResponse
    {
        $deliberations = $this->service->lister(
            $request->etablissement_id,
            $request->only('session_examen_id', 'classe_id')
        );
        return $this->ok('Délibérations.', $deliberations);
    }

    // POST /api/deliberations
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_examen_id' => 'required|integer|exists:sessions_examen,id',
            'classe_id'         => 'required|integer|exists:classes,id',
            'tenue_le'          => 'nullable|date',
            'president_jury'    => 'nullable|integer|exists:enseignants,id',
            'observations'      => 'nullable|string',
        ]);
        try {
            $deliberation = $this->service->creer($data);
            return $this->ok('Délibération créée.', $deliberation, 201);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), 422);
        }
    }

    // GET /api/deliberations/{id}/resultats
    public function resultats(int $id): JsonResponse
    {
        $resultats = $this->service->resultats($id);
        return $this->ok('Résultats de délibération.', $resultats);
    }

    // POST /api/deliberations/{id}/resultats
    // Body : { resultats: [{etudiant_id, decision, observations?}] }
    public function saisirResultats(Request $request, int $id): JsonResponse
    {
        $deliberation = Deliberation::findOrFail($id);
        $data = $request->validate([
            'resultats'                   => 'required|array|min:1',
            'resultats.*.etudiant_id'     => 'required|integer|exists:etudiants,id',
            'resultats.*.decision'        => 'required|string|in:Admis,Ajourné,Rattrapage,Exclu,Abandonné',
            'resultats.*.observations'    => 'nullable|string',
        ]);
        try {
            $deliberation = $this->service->saisirResultats($deliberation, $data['resultats']);
            return $this->ok('Résultats enregistrés.', $deliberation);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), 422);
        }
    }

    // POST /api/deliberations/{id}/auto-decisions
    public function autoDecisions(int $id): JsonResponse
    {
        $deliberation = Deliberation::findOrFail($id);
        try {
            $count = $this->service->autoGenererDecisions($deliberation);
            return $this->ok("{$count} décision(s) générée(s) automatiquement.", ['count' => $count]);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), 422);
        }
    }

    // PATCH /api/deliberations/{id}/cloturer
    public function cloturer(int $id): JsonResponse
    {
        $deliberation = Deliberation::findOrFail($id);
        try {
            return $this->ok('Délibération clôturée.', $this->service->cloturer($deliberation));
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), 422);
        }
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
