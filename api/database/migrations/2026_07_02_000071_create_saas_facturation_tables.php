<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// Module 23 — SaaS : abonnements, historique, factures, transactions, webhooks.
// « L'école paie la plateforme » (à distinguer du module financier scolarité).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abonnements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->default(Str::uuid());
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans_abonnement')->restrictOnDelete();
            $table->string('statut', 20)->default('en_attente'); // en_attente/actif/suspendu/expire/annule
            $table->decimal('montant', 12, 2)->default(0);       // instantané du prix
            $table->string('devise', 3)->default('XAF');
            $table->date('date_debut');
            $table->date('date_fin');
            $table->boolean('renouvellement_auto')->default(false);
            $table->foreignId('souscrit_par')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->timestamp('annule_le')->nullable();
            $table->text('motif_annulation')->nullable();
            $table->timestamps();
            $table->index('etablissement_id');

            // Un seul abonnement actif par établissement : colonne générée qui ne
            // vaut etablissement_id QUE si statut='actif' (sinon NULL) + index
            // unique dessus. Équivalent MySQL de l'index partiel Postgres d'origine
            // (MySQL n'autorise pas les index partiels, mais autorise plusieurs
            // NULL dans un index unique).
            $table->unsignedBigInteger('etablissement_actif_id')
                ->storedAs("CASE WHEN statut = 'actif' THEN etablissement_id ELSE NULL END")
                ->nullable();
        });

        Schema::table('abonnements', function (Blueprint $table) {
            $table->unique('etablissement_actif_id', 'abonnements_un_actif_par_etab');
        });

        Schema::create('historique_abonnements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('abonnement_id')->constrained('abonnements')->cascadeOnDelete();
            $table->string('ancien_statut', 20)->nullable();
            $table->string('nouveau_statut', 20);
            $table->text('motif')->nullable();
            $table->foreignId('effectue_par')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->timestamp('created_at')->nullable(); // NULL effectue_par = transition système
            $table->index('abonnement_id');
        });

        Schema::create('factures', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->default(Str::uuid());
            $table->string('numero_facture', 50)->unique();
            $table->foreignId('etablissement_id')->constrained('etablissements')->restrictOnDelete();
            $table->foreignId('abonnement_id')->nullable()->constrained('abonnements')->nullOnDelete();
            $table->string('statut', 25)->default('emise'); // brouillon/emise/payee/partiellement_payee/impayee/annulee
            $table->decimal('montant_ht', 12, 2)->default(0);
            $table->decimal('taux_taxe', 5, 2)->default(0);
            $table->decimal('montant_taxe', 12, 2)->default(0);
            $table->decimal('montant_ttc', 12, 2)->default(0);
            $table->string('devise', 3)->default('XAF');
            $table->date('date_emission')->useCurrent();
            $table->date('date_echeance')->nullable();
            $table->timestamp('payee_le')->nullable();
            $table->string('fichier_url', 500)->nullable();
            $table->timestamps();
            $table->index('etablissement_id');
        });

        Schema::create('transactions_paiement', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->default(Str::uuid());
            $table->string('reference_interne', 80)->unique();  // idempotence back
            $table->string('reference_externe', 150)->nullable(); // renvoyée par la passerelle
            $table->string('type_transaction', 20)->default('paiement'); // paiement/remboursement
            $table->foreignId('transaction_parent_id')->nullable()->constrained('transactions_paiement')->restrictOnDelete();
            $table->foreignId('etablissement_id')->constrained('etablissements')->restrictOnDelete();
            $table->foreignId('abonnement_id')->nullable()->constrained('abonnements')->nullOnDelete();
            $table->foreignId('facture_id')->nullable()->constrained('factures')->nullOnDelete();
            $table->foreignId('moyen_paiement_id')->constrained('moyens_paiement')->restrictOnDelete();
            $table->decimal('montant', 14, 2);
            $table->string('devise', 3)->default('XAF');
            $table->string('statut', 20)->default('initiee'); // initiee/en_attente/reussie/echouee/annulee/expiree/remboursee
            $table->string('numero_telephone', 30)->nullable();
            $table->text('message_passerelle')->nullable();
            $table->json('payload_passerelle')->nullable(); // réponse brute (audit)
            $table->foreignId('initiee_par')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->timestamp('initiee_le')->useCurrent();
            $table->timestamp('confirmee_le')->nullable();
            $table->timestamps();
            $table->index('etablissement_id');
            // Anti-rejeu : une réf passerelle ne s'enregistre qu'une fois par moyen.
            $table->unique(['moyen_paiement_id', 'reference_externe'],'transaction_ref_externe_unique');
        });

        Schema::create('webhooks_paiement', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->default(Str::uuid());
            $table->foreignId('moyen_paiement_id')->constrained('moyens_paiement')->restrictOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions_paiement')->nullOnDelete();
            $table->string('evenement', 80);
            $table->string('reference_externe', 150)->nullable();
            $table->string('signature', 512)->nullable();
            $table->boolean('est_signature_valide')->default(false);
            $table->boolean('est_traite')->default(false);
            $table->json('payload');
            $table->ipAddress('adresse_ip')->nullable();
            $table->timestamp('recu_le')->useCurrent();
            $table->timestamp('traite_le')->nullable();
            // Idempotence stricte : un même événement n'est traité qu'une fois.
            $table->unique(['moyen_paiement_id', 'reference_externe', 'evenement'],'webhooks_evenement_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhooks_paiement');
        Schema::dropIfExists('transactions_paiement');
        Schema::dropIfExists('factures');
        Schema::dropIfExists('historique_abonnements');
        Schema::dropIfExists('abonnements');
    }
};