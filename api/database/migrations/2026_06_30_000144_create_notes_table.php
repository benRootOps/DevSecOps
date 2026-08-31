<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Module 8 — Notes : notes saisies (CC / Examen / TP / Rattrapage) — cf. §18.
// Workflow : saisie_par → valide_par → est_validee. Seules les notes validées comptent.
return new class extends Migration {
    public function up(): void {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('etudiant_id')->constrained('etudiants')->cascadeOnDelete();
            $table->foreignId('matiere_id')->constrained('matieres')->cascadeOnDelete();
            $table->foreignId('session_examen_id')->constrained('sessions_examen')->cascadeOnDelete();
            $table->string('type_note', 50)->default('Examen'); // CC / TP / Examen / Rattrapage
            $table->decimal('valeur', 5, 2);
            $table->decimal('bareme', 5, 2)->default(20);
            $table->text('observation')->nullable();
            $table->foreignId('saisie_par')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->foreignId('valide_par')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->boolean('est_validee')->default(false);
            $table->timestamps();
            $table->unique(['etudiant_id', 'matiere_id', 'session_examen_id', 'type_note'], 'notes_unicite');
            $table->index('etablissement_id');
        });
    }
    public function down(): void { Schema::dropIfExists('notes'); }
};
