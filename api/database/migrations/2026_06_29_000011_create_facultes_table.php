<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Module 1 — Structure Académique : facultés (cf. bd-Edusphere_v2_0, §10).
return new class extends Migration {
    public function up(): void {
        Schema::create('facultes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->string('nom', 200);
            $table->string('code', 20)->nullable();
            $table->boolean('est_actif')->default(true);
            $table->timestamps();
            $table->index('etablissement_id');
        });
    }
    public function down(): void { Schema::dropIfExists('facultes'); }
};
