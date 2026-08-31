<?php

namespace App\Http\Controllers\Abonnements;

use App\Http\Controllers\Controller;
use App\Models\Abonnement;
use App\Models\Facture;
use App\Models\MoyenPaiement;
use App\Models\PlanAbonnement;
use App\Models\TransactionPaiement;
use App\Services\AbonnementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AbonnementController extends Controller
{
    public function __construct(private readonly AbonnementService $service) {}

    // ── Plans ─────────────────────────────────────────────────────

    // GET /api/v1/plans
    public function indexPlans(Request $request): JsonResponse
    {
        $publicSeulement = !$request->user()?->hasPermission('plans.gerer');
        return $this->ok('Plans d\'abonnement.', $this->service->listerPlans($publicSeulement));
    }

    // POST /api/v1/plans
    public function storePlan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'             => 'required|string|max:60|unique:plans_abonnement,code',
            'nom'              => 'required|string|max:150',
            'description'      => 'nullable|string',
            'prix'             => 'required|numeric|min:0',
            'devise'           => 'required|string|size:3',
            'periodicite'      => 'required|in:mensuel,trimestriel,semestriel,annuel',
            'max_utilisateurs' => 'nullable|integer|min:1',
            'max_etudiants'    => 'nullable|integer|min:1',
            'essai_jours'      => 'integer|min:0',
            'est_public'       => 'boolean',
        ]);
        return $this->ok('Plan créé.', $this->service->creerPlan($data), 201);
    }

    // PUT /api/v1/plans/{id}
    public function updatePlan(Request $request, int $id): JsonResponse
    {
        $plan = PlanAbonnement::findOrFail($id);
        $data = $request->validate([
            'nom'              => 'sometimes|string|max:150',
            'description'      => 'nullable|string',
            'prix'             => 'sometimes|numeric|min:0',
            'max_utilisateurs' => 'nullable|integer|min:1',
            'max_etudiants'    => 'nullable|integer|min:1',
            'est_public'       => 'boolean',
            'est_actif'        => 'boolean',
        ]);
        return $this->ok('Plan mis à jour.', $this->service->mettreAJourPlan($plan, $data));
    }

    // ── Abonnements ───────────────────────────────────────────────

    // GET /api/v1/abonnements
    public function index(Request $request): JsonResponse
    {
        return $this->ok('Liste des abonnements.', $this->service->listerAbonnements($request->all()));
    }

    // GET /api/v1/abonnements/actif
    public function actif(Request $request): JsonResponse
    {
        $abonnement = $this->service->abonnementActif($request->etablissement_id);
        return $this->ok('Abonnement actif.', $abonnement);
    }

    // POST /api/v1/abonnements
    public function souscrire(Request $request): JsonResponse
    {
        $data = $request->validate(['plan_id' => 'required|integer|exists:plans_abonnement,id']);
        try {
            $abonnement = $this->service->souscrire(
                $request->etablissement_id,
                $data['plan_id'],
                $request->user()->id
            );
            return $this->ok('Souscription effectuée.', $abonnement, 201);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), 422);
        }
    }

    // PATCH /api/v1/abonnements/{id}/statut
    public function changerStatut(Request $request, int $id): JsonResponse
    {
        $abonnement = Abonnement::findOrFail($id);
        $data = $request->validate([
            'statut' => 'required|in:actif,suspendu,annule,expire',
            'motif'  => 'nullable|string|max:500',
        ]);
        try {
            $updated = $this->service->changerStatut($abonnement, $data['statut'], $request->user()->id, $data['motif'] ?? null);
            return $this->ok('Statut mis à jour.', $updated);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), 422);
        }
    }

    // ── Factures ─────────────────────────────────────────────────

    // GET /api/v1/factures
    public function indexFactures(Request $request): JsonResponse
    {
        return $this->ok('Factures.', $this->service->listerFactures($request->etablissement_id, $request->all()));
    }

    // POST /api/v1/abonnements/{id}/facture
    public function genererFacture(int $id): JsonResponse
    {
        $abonnement = Abonnement::findOrFail($id);
        $facture    = $this->service->genererFacture($abonnement);
        return $this->ok('Facture générée.', $facture, 201);
    }

    // ── Paiements ─────────────────────────────────────────────────

    // GET /api/v1/moyens-paiement
    public function indexMoyens(): JsonResponse
    {
        return $this->ok('Moyens de paiement disponibles.', $this->service->listerMoyensPaiement());
    }

    // POST /api/v1/paiements/initier
    public function initierPaiement(Request $request): JsonResponse
    {
        $data = $request->validate([
            'abonnement_id'    => 'required|integer|exists:abonnements,id',
            'facture_id'       => 'nullable|integer|exists:factures,id',
            'moyen_paiement_id'=> 'required|integer|exists:moyens_paiement,id',
            'montant'          => 'required|numeric|min:1',
            'devise'           => 'required|string|size:3',
            'numero_telephone' => 'nullable|string|max:30',
        ]);
        $transaction = $this->service->initierPaiement(array_merge($data, [
            'etablissement_id' => $request->etablissement_id,
            'initiee_par'      => $request->user()->id,
        ]));
        return $this->ok('Paiement initié.', $transaction, 201);
    }

    // PATCH /api/v1/paiements/{id}/confirmer
    public function confirmerPaiement(Request $request, int $id): JsonResponse
    {
        $transaction = TransactionPaiement::findOrFail($id);
        $data = $request->validate([
            'statut'           => 'required|in:reussie,echouee,annulee,expiree',
            'reference_externe'=> 'nullable|string|max:150',
            'payload'          => 'nullable|array',
        ]);
        $updated = $this->service->confirmerTransaction(
            $transaction,
            $data['statut'],
            $data['reference_externe'] ?? null,
            $data['payload']           ?? null
        );
        return $this->ok('Transaction mise à jour.', $updated);
    }

    // GET /api/v1/transactions
    public function indexTransactions(Request $request): JsonResponse
    {
        return $this->ok('Transactions.', $this->service->listerTransactions($request->etablissement_id, $request->all()));
    }

    // POST /api/v1/webhooks/paiement (pas d'auth JWT — appelé par la passerelle)
    public function webhook(Request $request, int $moyenId): JsonResponse
    {
        $webhook = $this->service->traiterWebhook([
            'moyen_paiement_id'  => $moyenId,
            'evenement'          => $request->input('event', 'unknown'),
            'reference_externe'  => $request->input('reference'),
            'signature'          => $request->header('X-Signature'),
            'signature_valide'   => false, // TODO : vérifier HMAC selon le fournisseur
            'payload'            => $request->all(),
            'adresse_ip'         => $request->ip(),
        ]);
        return $this->ok('Webhook reçu.', ['webhook_id' => $webhook->id]);
    }

    private function ok(string $msg, mixed $data = null, int $code = 200): JsonResponse
    {
        return response()->json(['succes' => true,  'message' => $msg, 'donnees' => $data], $code);
    }

    private function fail(string $msg, int $code = 400): JsonResponse
    {
        return response()->json(['succes' => false, 'message' => $msg, 'donnees' => null], $code);
    }
}
