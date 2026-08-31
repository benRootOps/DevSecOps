<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Module 5 — Étudiants : inscriptions (cf. bd-Edusphere_v2_0, §13).
// etablissement_id dénormalisé pour le scope tenant uniforme.
return new class extends Migration {
    public function up(): void {
        Schema::create('inscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('etudiant_id')->constrained('etudiants')->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('annee_academique_id')->constrained('annees_academiques');
            $table->date('date_inscription')->nullable();
            $table->string('statut', 50)->default('En attente');        // En attente / Validé / Suspendu / Abandonné
            $table->string('type_inscription', 50)->default('Nouvelle inscription'); // Nouvelle inscription / Réinscription
            $table->foreignId('valide_par')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->text('observations')->nullable();
            $table->timestamps();
            $table->unique(['etudiant_id', 'annee_academique_id']);
            $table->index('etablissement_id');
            $table->index('classe_id');
        });
    }
    public function down(): void { Schema::dropIfExists('inscriptions'); }
};
