<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Module 1 — Structure Académique : filières (cf. bd-Edusphere_v2_0, §10).
return new class extends Migration {
    public function up(): void {
        Schema::create('filieres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('departement_id')->constrained('departements')->cascadeOnDelete();
            $table->string('nom', 200);
            $table->string('code', 30)->nullable();
            $table->string('type_formation', 50)->nullable(); // 'Licence', 'Master', 'BTS', 'Ingénieur'...
            $table->smallInteger('duree_annees')->default(3);
            $table->boolean('est_actif')->default(true);
            $table->timestamps();
            $table->index('etablissement_id');
            $table->index('departement_id');
        });
    }
    public function down(): void { Schema::dropIfExists('filieres'); }
};
