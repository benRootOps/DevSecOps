<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Module 2 — Enseignants : profil enseignant (cf. bd-Edusphere_v2_0, §11).
return new class extends Migration {
    public function up(): void {
        Schema::create('enseignants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('utilisateur_id')->unique()->constrained('utilisateurs')->cascadeOnDelete();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->string('matricule', 50)->nullable();
            $table->string('specialite', 200)->nullable();
            $table->string('grade', 100)->nullable();        // 'Professeur', 'Maître de conf.'...
            $table->string('type_contrat', 50)->nullable();  // 'Permanent', 'Vacataire', 'Invité'...
            $table->date('date_prise_service')->nullable();
            $table->boolean('est_actif')->default(true);
            $table->timestamps();
            $table->unique(['etablissement_id', 'matricule']);
            $table->index('etablissement_id');
        });
    }
    public function down(): void { Schema::dropIfExists('enseignants'); }
};
