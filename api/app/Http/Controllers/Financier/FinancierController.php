<?php

namespace App\Http\Controllers\Financier;

use App\Http\Controllers\Controller;
use App\Models\CategorieFrais;
use App\Models\Frais;
use App\Models\Versement;
use App\Services\FinancierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FinancierController extends Controller
{
    public function __construct(private readonly FinancierService $service) {}

    // ── Catégories ────────────────────────────────────────────

    // GET /api/categories-frais
    public function indexCategories(Request $request): JsonResponse
    {
        return $this->ok('Catégories de frais.', $this->service->listerCategories($request->etablissement_id));
    }

    // POST /api/categories-frais
    public function storeCategorie(Request $request): JsonResponse
    {
        $data = $request->validate([
            'libelle'     => 'required|string|max:150',
            'description' => 'nullable|string',
        ]);
        return $this->ok('Catégorie créée.', $this->service->creerCategorie($request->etablissement_id, $data), 201);
    }

    // PUT /api/categories-frais/{id}
    public function updateCategorie(Request $request, int $id): JsonResponse
    {
        $categorie = CategorieFrais::findOrFail($id);
        $data = $request->validate([
            'libelle'     => 'sometimes|string|max:150',
            'description' => 'nullable|string',
            'est_actif'   => 'boolean',
        ]);
        return $this->ok('Catégorie mise à jour.', $this->service->mettreAJourCategorie($categorie, $data));
    }

    // ── Frais ─────────────────────────────────────────────────

    // GET /api/frais
    public function indexFrais(Request $request): JsonResponse
    {
        $frais = $this->service->listerFrais(
            $request->etablissement_id,
            $request->only('annee_academique_id', 'filiere_id', 'niveau_id')
        );
        return $this->ok('Frais universitaires.', $frais);
    }

    // POST /api/frais
    public function storeFrais(Request $request): JsonResponse
    {
        $data = $request->validate([
            'categorie_frais_id'  => 'required|integer|exists:categories_frais,id',
            'annee_academique_id' => 'required|integer|exists:annees_academiques,id',
            'filiere_id'          => 'nullable|integer|exists:filieres,id',
            'niveau_id'           => 'nullable|integer|exists:niveaux,id',
            'montant_total'       => 'required|numeric|min:1',
            'nombre_tranches'     => 'integer|min:1|max:12',
            'devise'              => 'string|max:10',
            'est_obligatoire'     => 'boolean',
        ]);
        try {
            $frais = $this->service->creerFrais($request->etablissement_id, $data);
            return $this->ok('Frais créés avec tranches générées automatiquement.', $frais, 201);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), 422);
        }
    }

    // PUT /api/frais/{id}
    public function updateFrais(Request $request, int $id): JsonResponse
    {
        $frais = Frais::findOrFail($id);
        $data  = $request->validate([
            'montant_total'   => 'sometimes|numeric|min:1',
            'est_obligatoire' => 'boolean',
            'devise'          => 'string|max:10',
        ]);
        return $this->ok('Frais mis à jour.', $this->service->mettreAJourFrais($frais, $data));
    }

    // ── Situation étudiant ────────────────────────────────────

    // GET /api/etudiants/{id}/situation-financiere?annee_academique_id=1
    public function situationEtudiant(Request $request, int $etudiantId): JsonResponse
    {
        $request->validate(['annee_academique_id' => 'required|integer|exists:annees_academiques,id']);
        $situation = $this->service->situationEtudiant($etudiantId, $request->integer('annee_academique_id'));
        return $this->ok('Situation financière.', $situation);
    }

    // ── Versements ────────────────────────────────────────────

    // GET /api/versements
    public function indexVersements(Request $request): JsonResponse
    {
        $versements = $this->service->listerVersements(
            $request->etablissement_id,
            $request->only('etudiant_id', 'date_debut', 'date_fin', 'par_page')
        );
        return $this->ok('Versements.', $versements);
    }

    // POST /api/versements
    public function storeVersement(Request $request): JsonResponse
    {
        $data = $request->validate([
            'etudiant_id'    => 'required|integer|exists:etudiants,id',
            'tranche_id'     => 'required|integer|exists:tranches_paiement,id',
            'montant_verse'  => 'required|numeric|min:1',
            'mode_paiement'  => 'nullable|string|max:50',
            'reference'      => 'nullable|string|max:100',
            'observations'   => 'nullable|string',
            'date_versement' => 'nullable|date',
        ]);
        try {
            $versement = $this->service->enregistrerVersement($data, Auth::id());
            return $this->ok('Versement enregistré. Reçu généré automatiquement.', $versement, 201);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), 422);
        }
    }

    // ── Rapport financier ─────────────────────────────────────

    // GET /api/rapports/financier?annee_academique_id=1
    public function rapport(Request $request): JsonResponse
    {
        $request->validate(['annee_academique_id' => 'required|integer|exists:annees_academiques,id']);
        $rapport = $this->service->rapport($request->etablissement_id, $request->integer('annee_academique_id'));
        return $this->ok('Rapport financier.', $rapport);
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
