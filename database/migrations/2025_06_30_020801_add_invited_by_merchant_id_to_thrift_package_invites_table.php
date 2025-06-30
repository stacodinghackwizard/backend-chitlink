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
        Schema::table('thrift_package_invites', function (Blueprint $table) {
            $table->unsignedBigInteger('invited_by_merchant_id')->nullable()->after('invited_by_id');
            // If you want a foreign key constraint:
            // $table->foreign('invited_by_merchant_id')->references('id')->on('merchants')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('thrift_package_invites', function (Blueprint $table) {
            $table->dropColumn('invited_by_merchant_id');
        });
    }
};
