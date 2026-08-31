<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('etablissements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->default(\Illuminate\Support\Str::uuid());
            $table->string('nom', 200);
            $table->text('adresse')->nullable();
            $table->string('ville', 100)->nullable();
            $table->string('pays', 100)->default('Cameroun');
            $table->string('telephone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->boolean('est_actif')->default(true);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('etablissements'); }
};
