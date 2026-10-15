<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('utilisateurs', function (Blueprint $table) {
            $table->string('mot_de_passe', 255)->change();
        });
    }

    public function down(): void
    {
        // Keep the wider column so password hashes are never truncated on rollback.
    }
};
