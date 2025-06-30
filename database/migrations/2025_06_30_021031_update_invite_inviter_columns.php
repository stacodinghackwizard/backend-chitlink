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
            // Remove or comment out the dropForeign line if you are not sure the key exists
            // $table->dropForeign(['invited_by_id']);
            $table->unsignedBigInteger('invited_by_id')->nullable()->change();
            // Only add the merchant column if it doesn't exist
            if (!Schema::hasColumn('thrift_package_invites', 'invited_by_merchant_id')) {
                $table->unsignedBigInteger('invited_by_merchant_id')->nullable()->after('invited_by_id');
                $table->foreign('invited_by_merchant_id')->references('id')->on('merchants')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('thrift_package_invites', function (Blueprint $table) {
            $table->dropForeign(['invited_by_merchant_id']);
            $table->dropColumn('invited_by_merchant_id');
            $table->unsignedBigInteger('invited_by_id')->nullable(false)->change();
            $table->foreign('invited_by_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
