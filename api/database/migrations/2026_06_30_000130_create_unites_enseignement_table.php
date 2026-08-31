<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Module 4 — Cours/UE : unités d'enseignement (cf. bd-Edusphere_v2_0, §12).
return new class extends Migration {
    public function up(): void {
        Schema::create('unites_enseignement', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('semestre_id')->constrained('semestres')->cascadeOnDelete();
            $table->foreignId('filiere_id')->constrained('filieres')->cascadeOnDelete();
            $table->foreignId('niveau_id')->constrained('niveaux');
            $table->string('code', 30);
            $table->string('intitule', 200);
            $table->smallInteger('credits')->default(3);
            $table->decimal('coefficient', 4, 2)->default(1);
            $table->boolean('est_actif')->default(true);
            $table->timestamps();
            $table->unique(['semestre_id', 'code']);
            $table->index('etablissement_id');
            $table->index('filiere_id');
        });
    }
    public function down(): void { Schema::dropIfExists('unites_enseignement'); }
};
