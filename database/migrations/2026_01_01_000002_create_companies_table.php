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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('urlogo')->nullable();
            $table->string('url_attivazione')->nullable();
            $table->string('email_admin')->nullable();
            $table->string('db_secrete')->nullable();
            $table->string('db_connection')->default('mysql');
            $table->string('db_host')->nullable();
            $table->string('db_port')->default('3306');
            $table->string('db_database')->nullable();
            $table->string('db_username')->nullable();
            $table->string('db_password')->nullable();
            $table->longText('aibackground')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
