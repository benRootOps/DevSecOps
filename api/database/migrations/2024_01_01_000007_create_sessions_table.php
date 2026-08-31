<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->default(\Illuminate\Support\Str::uuid());
            $table->foreignId('utilisateur_id')->constrained('utilisateurs')->cascadeOnDelete();
            $table->string('token', 512)->unique();
            $table->ipAddress('ip_adresse')->nullable();
            $table->text('agent_navigateur')->nullable();
            $table->string('appareil', 200)->nullable();
            $table->string('localisation', 200)->nullable();
            $table->boolean('est_active')->default(true);
            $table->timestamp('expire_le');
            $table->timestamp('cree_le')->useCurrent();
            $table->timestamp('ferme_le')->nullable();
            $table->index('token');
            $table->index('utilisateur_id');
        });
    }
    public function down(): void { Schema::dropIfExists('sessions'); }
};
