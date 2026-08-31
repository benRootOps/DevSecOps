<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Contrat d'API §3 : le tenant expose un branding (logo + couleurs).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('etablissements', function (Blueprint $table) {
            $table->string('logo_url', 500)->nullable();
            $table->string('primary_color', 30)->nullable();
            $table->string('secondary_color', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('etablissements', function (Blueprint $table) {
            $table->dropColumn(['logo_url', 'primary_color', 'secondary_color']);
        });
    }
};
