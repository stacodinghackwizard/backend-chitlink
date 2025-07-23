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
        Schema::table('thrift_admins', function (Blueprint $table) {
            $table->unsignedBigInteger('merchant_id')->nullable()->after('user_id');
            $table->string('admin_type')->default('user'); // 'user' or 'merchant'
            $table->foreign('merchant_id')->references('id')->on('merchants')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('thrift_admins', function (Blueprint $table) {
            $table->dropForeign(['merchant_id']);
            $table->dropColumn(['merchant_id', 'admin_type']);
        });
    }
};
