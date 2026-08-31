<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Module 6 — Emploi du temps : salles (§14) + créneaux horaires (§15).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->string('nom', 100);
            $table->string('batiment', 100)->nullable();
            $table->smallInteger('capacite')->nullable();
            $table->string('type_salle', 50)->nullable(); // Amphithéâtre / Salle de cours / TP...
            $table->boolean('est_disponible')->default(true);
            $table->timestamps();
            $table->index('etablissement_id');
        });

        Schema::create('creneaux_horaires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->string('libelle', 30)->nullable();
            $table->smallInteger('ordre');
            $table->timestamps();
            $table->index('etablissement_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creneaux_horaires');
        Schema::dropIfExists('salles');
    }
};
