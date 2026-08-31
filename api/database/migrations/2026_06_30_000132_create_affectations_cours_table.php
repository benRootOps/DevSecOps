<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Module 4 — Cours/UE : affectations enseignant ↔ matière ↔ classe (cf. §12).
return new class extends Migration {
    public function up(): void {
        Schema::create('affectations_cours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('enseignant_id')->constrained('enseignants')->cascadeOnDelete();
            $table->foreignId('matiere_id')->constrained('matieres')->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->smallInteger('charge_horaire')->nullable();
            $table->timestamps();
            $table->unique(['enseignant_id', 'matiere_id', 'classe_id']);
            $table->index('etablissement_id');
        });
    }
    public function down(): void { Schema::dropIfExists('affectations_cours'); }
};
