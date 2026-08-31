<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Module 1 — Structure Académique : semestres (cf. bd-Edusphere_v2_0, §10).
return new class extends Migration {
    public function up(): void {
        Schema::create('semestres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('annee_academique_id')->constrained('annees_academiques')->cascadeOnDelete();
            $table->string('libelle', 30);
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->boolean('est_actif')->default(false);
            $table->timestamps();
            $table->index('etablissement_id');
            $table->index('annee_academique_id');
        });
    }
    public function down(): void { Schema::dropIfExists('semestres'); }
};
