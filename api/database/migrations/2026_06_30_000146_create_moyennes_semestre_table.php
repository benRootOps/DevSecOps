<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Module 8 — Moyennes semestrielles (calculées). Cf. §18
// (+ `moyenne_sur_5`, `grade` lettre et `est_complete` ajoutés pour nos règles).
return new class extends Migration {
    public function up(): void {
        Schema::create('moyennes_semestre', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('etudiant_id')->constrained('etudiants')->cascadeOnDelete();
            $table->foreignId('semestre_id')->constrained('semestres')->cascadeOnDelete();
            $table->foreignId('session_examen_id')->constrained('sessions_examen')->cascadeOnDelete();
            $table->decimal('moyenne_generale', 5, 2)->nullable();
            $table->decimal('moyenne_sur_5', 5, 2)->nullable();
            $table->string('grade', 5)->nullable();          // grade lettre (A+, A, B+...)
            $table->smallInteger('total_credits')->default(0);
            $table->smallInteger('credits_obtenus')->default(0);
            $table->smallInteger('rang')->nullable();
            $table->string('mention', 50)->nullable();
            $table->boolean('est_complete')->default(false); // false si un relevé est incomplet → non admissible
            $table->boolean('est_valide')->default(false);
            $table->timestamp('calcule_le')->nullable();
            $table->timestamps();
            $table->unique(['etudiant_id', 'semestre_id', 'session_examen_id'], 'moyennes_semestre_unicite');
            $table->index('etablissement_id');
        });
    }
    public function down(): void { Schema::dropIfExists('moyennes_semestre'); }
};
