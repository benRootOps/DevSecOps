<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Module 1 — Structure Académique : niveaux (cf. bd-Edusphere_v2_0, §10).
return new class extends Migration {
    public function up(): void {
        Schema::create('niveaux', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('filiere_id')->constrained('filieres')->cascadeOnDelete();
            $table->string('libelle', 30);
            $table->smallInteger('ordre');
            $table->timestamps();
            $table->index('etablissement_id');
            $table->index('filiere_id');
        });
    }
    public function down(): void { Schema::dropIfExists('niveaux'); }
};
