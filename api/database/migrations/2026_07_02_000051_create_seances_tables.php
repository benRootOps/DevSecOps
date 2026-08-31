<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Module 6 — Emploi du temps : séances (§15) + conflits détectés.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('affectation_id')->constrained('affectations_cours')->cascadeOnDelete();
            $table->foreignId('salle_id')->nullable()->constrained('salles')->nullOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('semestre_id')->constrained('semestres')->cascadeOnDelete();
            $table->foreignId('creneau_id')->constrained('creneaux_horaires');
            $table->smallInteger('jour_semaine'); // 1=Lundi … 6=Samedi
            $table->string('type_seance', 50)->nullable(); // Cours / TD / TP / Examen
            $table->date('date_specifique')->nullable();
            $table->boolean('est_annule')->default(false);
            $table->text('motif_annulation')->nullable();
            $table->timestamps();
            $table->index('etablissement_id');
            $table->index(['semestre_id', 'jour_semaine', 'creneau_id']);
        });

        Schema::create('conflits_emploi_temps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seance_id')->constrained('seances')->cascadeOnDelete();
            $table->string('type_conflit', 80)->nullable();
            $table->text('detail')->nullable();
            $table->boolean('resolu')->default(false);
            $table->timestamps();
            $table->index('seance_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conflits_emploi_temps');
        Schema::dropIfExists('seances');
    }
};
