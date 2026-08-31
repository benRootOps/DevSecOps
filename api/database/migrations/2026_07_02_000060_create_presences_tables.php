<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Module 7 — Présences (§16) : étudiants + enseignants.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('seance_id')->constrained('seances')->cascadeOnDelete();
            $table->foreignId('etudiant_id')->constrained('etudiants')->cascadeOnDelete();
            $table->string('statut', 30)->default('Présent'); // Présent / Absent / Retard / Excusé
            $table->text('motif')->nullable();
            $table->foreignId('saisie_par')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->timestamps();
            $table->unique(['seance_id', 'etudiant_id']);
            $table->index('etablissement_id');
        });

        Schema::create('presences_enseignants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('seance_id')->constrained('seances')->cascadeOnDelete();
            $table->foreignId('enseignant_id')->constrained('enseignants')->cascadeOnDelete();
            $table->string('statut', 30)->default('Présent'); // Présent / Absent / Remplacé
            $table->foreignId('remplacant_id')->nullable()->constrained('enseignants')->nullOnDelete();
            $table->text('observations')->nullable();
            $table->foreignId('saisie_par')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->timestamps();
            $table->unique(['seance_id', 'enseignant_id']);
            $table->index('etablissement_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presences_enseignants');
        Schema::dropIfExists('presences');
    }
};
