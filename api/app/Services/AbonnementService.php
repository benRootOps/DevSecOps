<?php

namespace App\Services;

use App\Models\Abonnement;
use App\Models\Facture;
use App\Models\HistoriqueAbonnement;
use App\Models\MoyenPaiement;
use App\Models\PlanAbonnement;
use App\Models\TransactionPaiement;
use App\Models\WebhookPaiement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AbonnementService
{
    // ── Plans ─────────────────────────────────────────────────────

    public function listerPlans(bool $publicSeulement = true): Collection
    {
        return PlanAbonnement::when($publicSeulement, fn($q) => $q->where('est_public', true))
            ->where('est_actif', true)
            ->orderBy('prix')
            ->get();
    }

    public function creerPlan(array $data): PlanAbonnement
    {
        return PlanAbonnement::create(array_merge($data, ['uuid' => Str::uuid()]));
    }

    public function mettreAJourPlan(PlanAbonnement $plan, array $data): PlanAbonnement
    {
        $plan->update($data);
        return $plan->fresh();
    }

    // ── Abonnements ───────────────────────────────────────────────

    public function abonnementActif(int $etablissementId): ?Abonnement
    {
        return Abonnement::with('plan')
            ->where('etablissement_id', $etablissementId)
            ->where('statut', 'actif')
            ->first();
    }

    public function listerAbonnements(array $filtres = []): LengthAwarePaginator
    {
        return Abonnement::with(['plan:id,code,nom', 'etablissement:id,nom'])
            ->when(!empty($filtres['etablissement_id']), fn($q) => $q->where('etablissement_id', $filtres['etablissement_id']))
            ->when(!empty($filtres['statut']),           fn($q) => $q->where('statut', $filtres['statut']))
            ->orderByDesc('cree_le')
            ->paginate($filtres['par_page'] ?? 20);
    }

    /**
     * Souscrit un établissement à un plan.
     * Gère la période d'essai et crée la première facture.
     */
    public function souscrire(int $etablissementId, int $planId, int $souscritParId): Abonnement
    {
        $plan = PlanAbonnement::findOrFail($planId);

        // Vérifier qu'il n'y a pas déjà un abonnement actif (MySQL : index non partiel)
        $actif = $this->abonnementActif($etablissementId);
        if ($actif) {
            throw new \Exception('Cet établissement a déjà un abonnement actif.');
        }

        return DB::transaction(function () use ($etablissementId, $plan, $souscritParId) {
            $debut   = now()->toDateString();
            $essai   = $plan->essai_jours > 0;
            $statut  = $essai ? 'actif' : 'en_attente';
            $duree   = $this->dureeJours($plan->periodicite);
            $fin     = now()->addDays($essai ? $plan->essai_jours : $duree)->toDateString();

            $abonnement = Abonnement::create([
                'uuid'             => Str::uuid(),
                'etablissement_id' => $etablissementId,
                'plan_id'          => $plan->id,
                'statut'           => $statut,
                'montant'          => $plan->prix,
                'devise'           => $plan->devise,
                'date_debut'       => $debut,
                'date_fin'         => $fin,
                'souscrit_par'     => $souscritParId,
            ]);

            $this->journaliserTransition($abonnement, null, $statut, 'Souscription initiale', $souscritParId);

            // Générer la facture uniquement si pas d'essai
            if (!$essai) {
                $this->genererFacture($abonnement);
            }

            return $abonnement->load('plan', 'etablissement');
        });
    }

    /**
     * Change le statut d'un abonnement (activer, suspendre, annuler, expirer).
     */
    public function changerStatut(Abonnement $abonnement, string $nouveauStatut, ?int $userId = null, ?string $motif = null): Abonnement
    {
        if (!in_array($nouveauStatut, Abonnement::STATUTS)) {
            throw new \Exception("Statut invalide : {$nouveauStatut}");
        }

        return DB::transaction(function () use ($abonnement, $nouveauStatut, $userId, $motif) {
            $ancienStatut = $abonnement->statut;
            $updates = ['statut' => $nouveauStatut];

            if ($nouveauStatut === 'annule') {
                $updates['annule_le']       = now();
                $updates['motif_annulation'] = $motif;
            }

            $abonnement->update($updates);
            $this->journaliserTransition($abonnement, $ancienStatut, $nouveauStatut, $motif, $userId);

            return $abonnement->fresh('plan');
        });
    }

    // ── Factures ─────────────────────────────────────────────────

    public function listerFactures(int $etablissementId, array $filtres = []): LengthAwarePaginator
    {
        return Facture::where('etablissement_id', $etablissementId)
            ->when(!empty($filtres['statut']), fn($q) => $q->where('statut', $filtres['statut']))
            ->orderByDesc('date_emission')
            ->paginate($filtres['par_page'] ?? 20);
    }

    public function genererFacture(Abonnement $abonnement): Facture
    {
        $taux      = 19.25; // TVA Cameroun (%)
        $montantHt = $abonnement->montant;
        $taxe      = round($montantHt * $taux / 100, 2);
        $ttc       = $montantHt + $taxe;

        return Facture::create([
            'uuid'             => Str::uuid(),
            'numero_facture'   => $this->genererNumeroFacture(),
            'etablissement_id' => $abonnement->etablissement_id,
            'abonnement_id'    => $abonnement->id,
            'statut'           => 'emise',
            'montant_ht'       => $montantHt,
            'taux_taxe'        => $taux,
            'montant_taxe'     => $taxe,
            'montant_ttc'      => $ttc,
            'devise'           => $abonnement->devise,
            'date_echeance'    => now()->addDays(30)->toDateString(),
        ]);
    }

    // ── Transactions ──────────────────────────────────────────────

    /**
     * Initie un paiement (avant l'appel à la passerelle).
     * Génère la référence interne idempotente.
     */
    public function initierPaiement(array $data): TransactionPaiement
    {
        return TransactionPaiement::create(array_merge($data, [
            'uuid'               => Str::uuid(),
            'reference_interne'  => 'TX-' . strtoupper(Str::random(12)),
            'type_transaction'   => 'paiement',
            'statut'             => 'initiee',
            'initiee_le'         => now(),
        ]));
    }

    /**
     * Met à jour une transaction après retour de la passerelle.
     * Si réussie : marque la facture payée + active l'abonnement.
     */
    public function confirmerTransaction(TransactionPaiement $transaction, string $statut, ?string $refExterne = null, ?array $payload = null): TransactionPaiement
    {
        return DB::transaction(function () use ($transaction, $statut, $refExterne, $payload) {
            $transaction->update([
                'statut'             => $statut,
                'reference_externe'  => $refExterne,
                'payload_passerelle' => $payload,
                'confirmee_le'       => $statut === 'reussie' ? now() : null,
            ]);

            if ($statut === 'reussie') {
                // Marquer la facture payée
                if ($transaction->facture_id) {
                    Facture::find($transaction->facture_id)?->update([
                        'statut'   => 'payee',
                        'payee_le' => now(),
                    ]);
                }
                // Activer l'abonnement
                if ($transaction->abonnement_id) {
                    $abonnement = Abonnement::find($transaction->abonnement_id);
                    if ($abonnement && $abonnement->statut === 'en_attente') {
                        $this->changerStatut($abonnement, 'actif', null, 'Paiement confirmé');
                    }
                }
            }

            return $transaction->fresh(['abonnement', 'facture', 'moyenPaiement']);
        });
    }

    /**
     * Traite un webhook entrant (anti-rejeu via UNIQUE constraint).
     */
    public function traiterWebhook(array $data): WebhookPaiement
    {
        $webhook = WebhookPaiement::create([
            'uuid'                => Str::uuid(),
            'moyen_paiement_id'   => $data['moyen_paiement_id'],
            'evenement'           => $data['evenement'],
            'reference_externe'   => $data['reference_externe'] ?? null,
            'signature'           => $data['signature']         ?? null,
            'est_signature_valide'=> $data['signature_valide']  ?? false,
            'payload'             => $data['payload'],
            'adresse_ip'          => $data['adresse_ip']        ?? null,
        ]);

        // TODO : dispatch WebhookPaiementJob pour traitement asynchrone
        // ProcessWebhookJob::dispatch($webhook);

        return $webhook;
    }

    public function listerTransactions(int $etablissementId, array $filtres = []): LengthAwarePaginator
    {
        return TransactionPaiement::with(['moyenPaiement:id,nom,code', 'facture:id,numero_facture'])
            ->where('etablissement_id', $etablissementId)
            ->when(!empty($filtres['statut']),           fn($q) => $q->where('statut', $filtres['statut']))
            ->when(!empty($filtres['type_transaction']), fn($q) => $q->where('type_transaction', $filtres['type_transaction']))
            ->orderByDesc('initiee_le')
            ->paginate($filtres['par_page'] ?? 20);
    }

    public function listerMoyensPaiement(bool $actifsSeulement = true): Collection
    {
        return MoyenPaiement::when($actifsSeulement, fn($q) => $q->where('est_actif', true))
            ->orderBy('type')
            ->orderBy('nom')
            ->get();
    }

    // ── Helpers privés ────────────────────────────────────────────

    private function journaliserTransition(Abonnement $abonnement, ?string $ancien, string $nouveau, ?string $motif, ?int $userId): void
    {
        HistoriqueAbonnement::create([
            'abonnement_id' => $abonnement->id,
            'ancien_statut' => $ancien,
            'nouveau_statut'=> $nouveau,
            'motif'         => $motif,
            'effectue_par'  => $userId,
        ]);
    }

    private function genererNumeroFacture(): string
    {
        $annee  = now()->year;
        $dernier = Facture::whereYear('date_emission', $annee)->count() + 1;
        return sprintf('FAC-%d-%05d', $annee, $dernier);
    }

    private function dureeJours(string $periodicite): int
    {
        return match ($periodicite) {
            'mensuel'       => 30,
            'trimestriel'   => 90,
            'semestriel'    => 180,
            'annuel'        => 365,
            default         => 30,
        };
    }
}
