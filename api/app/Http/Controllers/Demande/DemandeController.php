<?php

namespace App\Http\Controllers\Demande;

use App\Http\Controllers\Controller;
use App\Http\Requests\Demande\DemandeEtablissementRequest;
use App\Http\Requests\Demande\DemandeMembreRequest;
use App\Http\Requests\Demande\RejeterDemandeRequest;
use App\Http\Requests\Demande\ValiderDemandeRequest;
use App\Models\DemandeCompte;
use App\Services\DemandeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DemandeController extends Controller
{
    public function __construct(private readonly DemandeService $demandeService) {}

    // -------------------------------------------------------
    // SOUMISSION (routes publiques)
    // -------------------------------------------------------

    /**
     * POST /api/v1/demandes/etablissement  (PUBLIC — sans JWT)
     * L'université remplit le formulaire sur le site Edusphere.
     */
    public function soumettreEtablissement(DemandeEtablissementRequest $request): JsonResponse
    {
        $demande = $this->demandeService->soumettreEtablissement($request->validated());

        return response()->json([
            'succes'  => true,
            'message' => 'Votre demande a bien été reçue. Vous serez contacté après validation.',
            'donnees' => ['uuid' => $demande->uuid],
        ], 201);
    }

    /**
     * POST /api/v1/demandes/enseignant  (admin universitaire)
     * POST /api/v1/demandes/etudiant    (admin universitaire / secrétaire)
     */
    public function soumettreEnseignant(DemandeMembreRequest $request): JsonResponse
    {
        $etablissementId = $request->attributes->get('etablissement_id');
        $demande = $this->demandeService->soumettreEnseignant(
            $request->validated(),
            $etablissementId
        );

        return response()->json([
            'succes'  => true,
            'message' => 'Demande de compte enseignant soumise. En attente de validation.',
            'donnees' => ['uuid' => $demande->uuid, 'statut' => $demande->statut],
        ], 201);
    }

    public function soumettreEtudiant(DemandeMembreRequest $request): JsonResponse
    {
        $etablissementId = $request->attributes->get('etablissement_id');
        $demande = $this->demandeService->soumettreEtudiant(
            $request->validated(),
            $etablissementId
        );

        return response()->json([
            'succes'  => true,
            'message' => 'Demande de compte étudiant soumise. En attente de validation.',
            'donnees' => ['uuid' => $demande->uuid, 'statut' => $demande->statut],
        ], 201);
    }

    // -------------------------------------------------------
    // LISTE & DÉTAIL
    // -------------------------------------------------------

    /**
     * GET /api/v1/demandes
     * Super admin → demandes établissement
     * Admin universitaire → demandes enseignants/étudiants de son espace
     */
    public function index(Request $request): JsonResponse
    {
        $filtres = $request->only(['statut', 'type_demande']);
        $perPage = (int) $request->input('par_page', 20);

        $demandes = $this->demandeService->lister(auth()->user(), $filtres, $perPage);

        return response()->json([
            'succes'  => true,
            'donnees' => $demandes,
        ]);
    }

    /**
     * GET /api/v1/demandes/{id}
     */
    public function show(int $id): JsonResponse
    {
        $demande = DemandeCompte::findOrFail($id);
        $this->autoriserAcces($demande);

        return response()->json([
            'succes'  => true,
            'donnees' => $demande,
        ]);
    }

    /**
     * GET /api/v1/demandes/statistiques
     */
    public function statistiques(): JsonResponse
    {
        $stats = $this->demandeService->statistiques(auth()->user());

        return response()->json([
            'succes'  => true,
            'donnees' => $stats,
        ]);
    }

    // -------------------------------------------------------
    // VALIDATION & REJET
    // -------------------------------------------------------

    /**
     * POST /api/v1/demandes/{id}/valider
     * Le validateur définit le mot de passe au moment de la validation.
     */
    public function valider(ValiderDemandeRequest $request, int $id): JsonResponse
    {
        $demande = DemandeCompte::findOrFail($id);
        $this->autoriserAcces($demande);

        try {
            $resultat = $this->demandeService->valider(
                $demande,
                $request->input('mot_de_passe'),
                auth()->user()
            );

            return response()->json([
                'succes'  => true,
                'message' => 'Demande validée. Le compte a été créé avec succès.',
                'donnees' => $resultat,
            ]);

        } catch (\RuntimeException $e) {
            return response()->json([
                'succes'  => false,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 422);
        }
    }

    /**
     * POST /api/v1/demandes/{id}/rejeter
     */
    public function rejeter(RejeterDemandeRequest $request, int $id): JsonResponse
    {
        $demande = DemandeCompte::findOrFail($id);
        $this->autoriserAcces($demande);

        try {
            $demande = $this->demandeService->rejeter(
                $demande,
                $request->input('motif'),
                auth()->user()
            );

            return response()->json([
                'succes'  => true,
                'message' => 'Demande rejetée.',
                'donnees' => ['statut' => $demande->statut, 'motif_rejet' => $demande->motif_rejet],
            ]);

        } catch (\RuntimeException $e) {
            return response()->json([
                'succes'  => false,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 422);
        }
    }

    // -------------------------------------------------------
    // Helper — isolation multi-tenant
    // -------------------------------------------------------

    /**
     * Vérifie que l'utilisateur connecté a le droit de voir/traiter cette demande.
     * Super admin → uniquement demandes 'etablissement'
     * Admin universitaire → uniquement demandes de son établissement
     */
    private function autoriserAcces(DemandeCompte $demande): void
    {
        $user = auth()->user();

        if ($user->estSuperAdmin()) {
            if ($demande->type_demande !== 'etablissement') {
                abort(403, 'Accès non autorisé à cette demande.');
            }
            return;
        }

        if ($demande->etablissement_id !== $user->etablissement_id) {
            abort(403, 'Accès non autorisé à cette demande.');
        }
    }
}
