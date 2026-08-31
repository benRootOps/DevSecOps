<?php

namespace App\Http\Controllers\Deliberations;

use App\Http\Controllers\Controller;
use App\Models\Bulletin;
use App\Models\DocumentOfficiel;
use App\Services\DeliberationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BulletinController extends Controller
{
    public function __construct(private readonly DeliberationService $service) {}

    // GET /api/bulletins
    public function index(Request $request): JsonResponse
    {
        $bulletins = $this->service->lister(
            $request->etablissement_id,
            $request->only('semestre_id', 'session_examen_id', 'est_publie', 'par_page')
        );
        return $this->ok('Bulletins.', $bulletins);
    }

    // POST /api/bulletins/generer-classe
    // Body : { session_examen_id, classe_id }
    public function genererClasse(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_examen_id' => 'required|integer|exists:sessions_examen,id',
            'classe_id'         => 'required|integer|exists:classes,id',
        ]);
        $count = $this->service->genererPourClasse(
            $data['session_examen_id'],
            $data['classe_id'],
            Auth::id()
        );
        return $this->ok("{$count} bulletin(s) généré(s).", ['count' => $count]);
    }

    // POST /api/bulletins/generer-etudiant
    // Body : { etudiant_id, session_examen_id }
    public function genererEtudiant(Request $request): JsonResponse
    {
        $data = $request->validate([
            'etudiant_id'       => 'required|integer|exists:etudiants,id',
            'session_examen_id' => 'required|integer|exists:sessions_examen,id',
        ]);
        $bulletin = $this->service->genererPourEtudiant(
            $data['etudiant_id'],
            $data['session_examen_id'],
            Auth::id()
        );
        return $this->ok('Bulletin généré.', $bulletin, 201);
    }

    // PATCH /api/bulletins/{id}/publier
    public function publier(int $id): JsonResponse
    {
        $bulletin = Bulletin::findOrFail($id);
        return $this->ok('Bulletin publié.', $this->service->publier($bulletin));
    }

    // ── Documents officiels ───────────────────────────────────

    // GET /api/etudiants/{id}/documents
    public function documentsEtudiant(int $etudiantId): JsonResponse
    {
        $documents = $this->service->documentsEtudiant($etudiantId);
        return $this->ok('Documents officiels.', $documents);
    }

    // POST /api/documents-officiels
    public function creerDocument(Request $request): JsonResponse
    {
        $data = $request->validate([
            'etudiant_id'          => 'required|integer|exists:etudiants,id',
            'type_document'        => 'required|string|max:100',
            'annee_academique_id'  => 'nullable|integer|exists:annees_academiques,id',
            'observations'         => 'nullable|string',
        ]);
        $document = $this->service->creerDocument(
            array_merge($data, ['etablissement_id' => $request->etablissement_id]),
            Auth::id()
        );
        return $this->ok('Document créé.', $document, 201);
    }

    // PATCH /api/documents-officiels/{id}/valider
    public function validerDocument(int $id): JsonResponse
    {
        $document = DocumentOfficiel::findOrFail($id);
        return $this->ok('Document validé.', $this->service->validerDocument($document, Auth::id()));
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
