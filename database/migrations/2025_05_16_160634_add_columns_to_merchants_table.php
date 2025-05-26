<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsToMerchantsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->string('phone_number')->nullable()->after('email');
            $table->string('address')->after('phone_number');
            $table->string('reg_number')->after('address');
            $table->string('cac_certificate')->nullable()->after('reg_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn(['phone_number', 'address', 'reg_number', 'cac_certificate']);
        });
    }
}
