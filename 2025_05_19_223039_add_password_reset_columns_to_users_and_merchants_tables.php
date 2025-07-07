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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'password_reset_code')) {
                $table->string('password_reset_code')->nullable();
            }
            if (!Schema::hasColumn('users', 'password_reset_expires_at')) {
                $table->timestamp('password_reset_expires_at')->nullable();
            }
        });

        Schema::table('merchants', function (Blueprint $table) {
            if (!Schema::hasColumn('merchants', 'password_reset_code')) {
                $table->string('password_reset_code')->nullable();
            }
            if (!Schema::hasColumn('merchants', 'password_reset_expires_at')) {
                $table->timestamp('password_reset_expires_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'password_reset_code')) {
                $table->dropColumn('password_reset_code');
            }
            if (Schema::hasColumn('users', 'password_reset_expires_at')) {
                $table->dropColumn('password_reset_expires_at');
            }
        });

        Schema::table('merchants', function (Blueprint $table) {
            if (Schema::hasColumn('merchants', 'password_reset_code')) {
                $table->dropColumn('password_reset_code');
            }
            if (Schema::hasColumn('merchants', 'password_reset_expires_at')) {
                $table->dropColumn('password_reset_expires_at');
            }
        });
    }
};
