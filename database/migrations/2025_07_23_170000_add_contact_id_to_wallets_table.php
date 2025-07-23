<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->unsignedBigInteger('contact_id')->nullable()->after('merchant_id');
            // If you want to enforce foreign key constraint:
            // $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropColumn('contact_id');
        });
    }
};
