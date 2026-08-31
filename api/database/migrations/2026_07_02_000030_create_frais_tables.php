<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Module 11 — Financier scolarité (cf. bd-Edusphere_v2_0, §22) : catégories, frais, tranches.
return new class extends Migration {
    public function up(): void {
        Schema::create('categories_frais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->string('libelle', 150);
            $table->text('description')->nullable();
            $table->boolean('est_actif')->default(true);
            $table->timestamps();
            $table->index('etablissement_id');
        });

        Schema::create('frais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('categorie_frais_id')->constrained('categories_frais');
            $table->foreignId('filiere_id')->nullable()->constrained('filieres')->nullOnDelete();
            $table->foreignId('niveau_id')->nullable()->constrained('niveaux')->nullOnDelete();
            $table->foreignId('annee_academique_id')->constrained('annees_academiques');
            $table->decimal('montant_total', 12, 2);
            $table->smallInteger('nombre_tranches')->default(1);
            $table->string('devise', 10)->default('XAF');
            $table->boolean('est_obligatoire')->default(true);
            $table->timestamps();
            $table->index('etablissement_id');
        });

        Schema::create('tranches_paiement', function (Blueprint $table) {
            $table->id();
            $table->foreignId('frais_id')->constrained('frais')->cascadeOnDelete();
            $table->smallInteger('numero');
            $table->string('libelle', 100)->nullable();
            $table->decimal('montant', 12, 2);
            $table->date('date_echeance')->nullable();
            $table->timestamps();
            $table->unique(['frais_id', 'numero']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('tranches_paiement');
        Schema::dropIfExists('frais');
        Schema::dropIfExists('categories_frais');
    }
};
