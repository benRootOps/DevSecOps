<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Module 1 — Structure Académique : années académiques (cf. bd-Edusphere_v2_0, §10).
return new class extends Migration {
    public function up(): void {
        Schema::create('annees_academiques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->string('libelle', 20);
            $table->date('date_debut');
            $table->date('date_fin');
            $table->boolean('est_active')->default(false);
            $table->timestamps();
            $table->unique(['etablissement_id', 'libelle']);
            $table->index('etablissement_id');
        });
    }
    public function down(): void { Schema::dropIfExists('annees_academiques'); }
};
