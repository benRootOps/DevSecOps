<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Module 9 — Délibérations (cf. bd-Edusphere_v2_0, §19).
return new class extends Migration {
    public function up(): void {
        Schema::create('deliberations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('session_examen_id')->constrained('sessions_examen')->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('semestre_id')->constrained('semestres')->cascadeOnDelete();
            $table->date('tenue_le')->nullable();
            $table->foreignId('president_jury')->nullable()->constrained('enseignants')->nullOnDelete();
            $table->string('proces_verbal_url', 500)->nullable();
            $table->text('observations')->nullable();
            $table->boolean('compensation_appliquee')->default(false); // notes ≥ seuil comblées
            $table->boolean('est_close')->default(false);
            $table->timestamps();
            $table->unique(['classe_id', 'semestre_id', 'session_examen_id'], 'deliberations_unicite');
            $table->index('etablissement_id');
        });
    }
    public function down(): void { Schema::dropIfExists('deliberations'); }
};
