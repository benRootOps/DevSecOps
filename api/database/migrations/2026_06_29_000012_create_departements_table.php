<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Module 1 — Structure Académique : départements (cf. bd-Edusphere_v2_0, §10).
return new class extends Migration {
    public function up(): void {
        Schema::create('departements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('faculte_id')->constrained('facultes')->cascadeOnDelete();
            $table->string('nom', 200);
            $table->string('code', 20)->nullable();
            $table->foreignId('chef_id')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->boolean('est_actif')->default(true);
            $table->timestamps();
            $table->index('etablissement_id');
            $table->index('faculte_id');
        });
    }
    public function down(): void { Schema::dropIfExists('departements'); }
};
