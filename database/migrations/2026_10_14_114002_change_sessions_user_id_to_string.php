<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table): void {
            $table->dropIndex(['user_id']);
            $table->string('user_id')->nullable()->change();
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        // Email-based user IDs cannot be safely converted back to integers.
    }
};
