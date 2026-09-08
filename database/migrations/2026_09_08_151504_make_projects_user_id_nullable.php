<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Uno studio creato da un superadmin non ha proprietario: user_id nullable.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};
