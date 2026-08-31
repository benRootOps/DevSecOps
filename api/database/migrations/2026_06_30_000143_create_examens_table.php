<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Module 8 — Examens : épreuves planifiées (cf. bd-Edusphere_v2_0, §17).
return new class extends Migration {
    public function up(): void {
        Schema::create('examens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('session_examen_id')->constrained('sessions_examen')->cascadeOnDelete();
            $table->foreignId('matiere_id')->constrained('matieres')->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->unsignedBigInteger('salle_id')->nullable(); // FK vers salles (#6 Benjo) à ajouter quand livré
            $table->date('date_examen')->nullable();
            $table->time('heure_debut')->nullable();
            $table->time('heure_fin')->nullable();
            $table->foreignId('surveillant_id')->nullable()->constrained('enseignants')->nullOnDelete();
            $table->decimal('coefficient', 4, 2)->nullable();
            $table->decimal('bareme', 5, 2)->default(20);
            $table->text('observations')->nullable();
            $table->timestamps();
            $table->index('etablissement_id');
            $table->index('session_examen_id');
        });
    }
    public function down(): void { Schema::dropIfExists('examens'); }
};
