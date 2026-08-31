<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Module 8 — Examens : sessions d'examen (cf. bd-Edusphere_v2_0, §17).
return new class extends Migration {
    public function up(): void {
        Schema::create('sessions_examen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('semestre_id')->constrained('semestres')->cascadeOnDelete();
            $table->string('libelle', 100);
            $table->string('type_session', 50)->default('Session normale'); // Session normale / Rattrapage
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->boolean('est_cloturee')->default(false);
            $table->timestamps();
            $table->index('etablissement_id');
            $table->index('semestre_id');
        });
    }
    public function down(): void { Schema::dropIfExists('sessions_examen'); }
};
