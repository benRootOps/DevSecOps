<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Module 2 — Enseignants : diplômes (cf. bd-Edusphere_v2_0, §11).
// Sous-ressource d'un enseignant : isolation tenant assurée via le parent
// (pas de etablissement_id direct ; la colonne `etablissement` = l'école émettrice).
return new class extends Migration {
    public function up(): void {
        Schema::create('enseignant_diplomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enseignant_id')->constrained('enseignants')->cascadeOnDelete();
            $table->string('intitule', 200);
            $table->string('etablissement', 200)->nullable(); // établissement ayant délivré le diplôme
            $table->smallInteger('annee_obtention')->nullable();
            $table->string('document_url', 500)->nullable();
            $table->timestamps();
            $table->index('enseignant_id');
        });
    }
    public function down(): void { Schema::dropIfExists('enseignant_diplomes'); }
};
