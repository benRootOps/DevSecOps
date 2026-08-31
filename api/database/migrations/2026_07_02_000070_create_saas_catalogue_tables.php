<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// Module 23 — SaaS : catalogues globaux (plans d'abonnement + moyens de paiement).
// Ces tables ne sont PAS « tenantées » : elles sont gérées par le super-admin.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans_abonnement', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->default(Str::uuid());
            $table->string('code', 60)->unique();          // 'starter', 'pro', 'campus'...
            $table->string('nom', 150);
            $table->text('description')->nullable();
            $table->decimal('prix', 12, 2)->default(0);
            $table->string('devise', 3)->default('XAF');
            $table->string('periodicite', 20);             // mensuel/trimestriel/semestriel/annuel
            $table->integer('duree_jours')->nullable();
            $table->integer('max_utilisateurs')->nullable();
            $table->integer('max_etudiants')->nullable();  // NULL = illimité
            $table->smallInteger('essai_jours')->default(0);
            $table->boolean('est_public')->default(true);
            $table->boolean('est_actif')->default(true);
            $table->timestamps();
        });

        Schema::create('moyens_paiement', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->default(Str::uuid());
            $table->string('code', 60)->unique();          // 'mtn_momo','visa','paypal'...
            $table->string('nom', 120);
            $table->string('type', 40);                    // mobile_money/carte_bancaire/...
            $table->string('fournisseur', 120)->nullable();
            $table->string('portee', 30)->default('international'); // zone de disponibilité
            $table->json('devises_supportees')->nullable(); // codes ISO-4217 ; NULL = toutes
            $table->json('configuration')->nullable();      // paramètres NON sensibles — pas de valeur par défaut sur colonne JSON en MySQL, gérer '{}' côté application
            $table->boolean('est_actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moyens_paiement');
        Schema::dropIfExists('plans_abonnement');
    }
};
