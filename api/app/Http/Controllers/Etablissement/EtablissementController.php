<?php

namespace App\Http\Controllers\Etablissement;

use App\Http\Controllers\Controller;
use App\Models\Etablissement;
use App\Services\EtablissementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EtablissementController extends Controller
{
    public function __construct(private readonly EtablissementService $etablissementService) {}

    public function index(Request $request): JsonResponse
    {
        $etablissements = $this->etablissementService->lister(
            (int) $request->input('par_page', 20),
            $request->only(['recherche', 'est_actif'])
        );
        return response()->json(['succes' => true, 'donnees' => $etablissements]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['succes' => true, 'donnees' => Etablissement::withCount('utilisateurs')->findOrFail($id)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $donnees = $request->validate([
            'nom'       => ['sometimes','string','max:200'],
            'adresse'   => ['nullable','string'],
            'ville'     => ['nullable','string','max:100'],
            'pays'      => ['nullable','string','max:100'],
            'telephone' => ['nullable','string','max:30'],
            'email'     => ['nullable','email','max:150'],
        ]);
        $etablissement = $this->etablissementService->mettreAJour(Etablissement::findOrFail($id), $donnees);
        return response()->json(['succes' => true, 'message' => 'Établissement mis à jour.', 'donnees' => $etablissement]);
    }

    public function toggleActif(int $id): JsonResponse
    {
        $etablissement = $this->etablissementService->toggleActif(Etablissement::findOrFail($id));
        $statut = $etablissement->est_actif ? 'activé' : 'désactivé';
        return response()->json(['succes' => true, 'message' => "Établissement {$statut}.", 'donnees' => $etablissement]);
    }

    public function statistiques(): JsonResponse
    {
        return response()->json(['succes' => true, 'donnees' => $this->etablissementService->statistiques()]);
    }
}
