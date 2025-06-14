<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('thrift_packages', function (Blueprint $table) {
            $table->boolean('terms_accepted')->default(false)->after('terms');
        });
    }

    public function down(): void
    {
        Schema::table('thrift_packages', function (Blueprint $table) {
            $table->dropColumn('terms_accepted');
        });
    }
}; 