<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Module 1 — Structure Académique : classes (cf. bd-Edusphere_v2_0, §10).
return new class extends Migration {
    public function up(): void {
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('niveau_id')->constrained('niveaux')->cascadeOnDelete();
            $table->foreignId('annee_academique_id')->constrained('annees_academiques');
            $table->string('nom', 100);
            $table->smallInteger('capacite_max')->nullable();
            $table->boolean('est_actif')->default(true);
            $table->timestamps();
            $table->index('etablissement_id');
            $table->index('niveau_id');
        });
    }
    public function down(): void { Schema::dropIfExists('classes'); }
};
