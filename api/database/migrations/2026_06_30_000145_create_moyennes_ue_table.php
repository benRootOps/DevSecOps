<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Module 8 — Moyennes par UE (calculées). Cf. §18 (+ `est_complete` ajouté).
return new class extends Migration {
    public function up(): void {
        Schema::create('moyennes_ue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('etudiant_id')->constrained('etudiants')->cascadeOnDelete();
            $table->foreignId('unite_id')->constrained('unites_enseignement')->cascadeOnDelete();
            $table->foreignId('session_examen_id')->constrained('sessions_examen')->cascadeOnDelete();
            $table->decimal('moyenne', 5, 2)->nullable();
            $table->smallInteger('credits_obtenus')->default(0);
            $table->boolean('est_complete')->default(false);  // false si une note CC/Examen manque
            $table->boolean('est_validee')->default(false);
            $table->timestamp('calcule_le')->nullable();
            $table->timestamps();
            $table->unique(['etudiant_id', 'unite_id', 'session_examen_id'], 'moyennes_ue_unicite');
            $table->index('etablissement_id');
        });
    }
    public function down(): void { Schema::dropIfExists('moyennes_ue'); }
};
