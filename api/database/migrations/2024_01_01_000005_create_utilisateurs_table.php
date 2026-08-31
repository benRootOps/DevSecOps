<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('utilisateurs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->default(\Illuminate\Support\Str::uuid());
            $table->foreignId('etablissement_id')->nullable()->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles');
            $table->string('nom', 100);
            $table->string('prenom', 100);
            $table->string('email', 150)->unique();
            $table->string('mot_de_passe_hash', 255);
            $table->string('telephone', 30)->nullable();
            $table->string('photo_url', 500)->nullable();
            $table->string('genre', 20)->nullable();
            $table->date('date_naissance')->nullable();
            $table->boolean('est_actif')->default(true);
            $table->boolean('email_verifie')->default(false);
            $table->string('token_verification', 255)->nullable();
            $table->string('token_reset_mdp', 255)->nullable();
            $table->timestamp('token_reset_expire')->nullable();
            $table->timestamp('derniere_connexion')->nullable();
            $table->integer('tentatives_connexion')->default(0);
            $table->timestamp('bloque_jusqu_a')->nullable();
            $table->timestamps();
            $table->index('email');
            $table->index('etablissement_id');
            $table->index('role_id');
        });
    }
    public function down(): void { Schema::dropIfExists('utilisateurs'); }
};
