<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Module 9 — Bulletins (cf. §20). Métadonnées seulement ; le PDF sera généré à la demande.
return new class extends Migration {
    public function up(): void {
        Schema::create('bulletins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('etudiant_id')->constrained('etudiants')->cascadeOnDelete();
            $table->foreignId('semestre_id')->constrained('semestres')->cascadeOnDelete();
            $table->foreignId('session_examen_id')->constrained('sessions_examen')->cascadeOnDelete();
            $table->string('type_bulletin', 50)->default('Semestriel'); // Semestriel / Annuel
            $table->string('fichier_url', 500)->nullable();
            $table->timestamp('genere_le')->nullable();
            $table->foreignId('genere_par')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->boolean('est_publie')->default(false);
            $table->timestamp('publie_le')->nullable();
            $table->timestamps();
            $table->unique(['etudiant_id', 'semestre_id', 'session_examen_id', 'type_bulletin'], 'bulletins_unicite');
            $table->index('etablissement_id');
        });
    }
    public function down(): void { Schema::dropIfExists('bulletins'); }
};
