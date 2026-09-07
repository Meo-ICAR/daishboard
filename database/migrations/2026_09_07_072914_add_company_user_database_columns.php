<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
        });

        Schema::table('dashboard_widgets', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('dashboard_id')->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
        });

        Schema::table('dashboards', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->string('database', 255)->nullable()->after('company_id');
        });

        Schema::table('menu_categories', function (Blueprint $table) {
            $table->string('database', 255)->nullable()->after('name');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->string('database', 255)->nullable()->after('name');
        });

        Schema::table('schema_legends', function (Blueprint $table) {
            $table->string('database', 255)->nullable()->after('connection');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('dashboard_widgets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('dashboards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
            $table->dropColumn('database');
        });

        Schema::table('menu_categories', function (Blueprint $table) {
            $table->dropColumn('database');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('database');
        });

        Schema::table('schema_legends', function (Blueprint $table) {
            $table->dropColumn('database');
        });
    }
};
