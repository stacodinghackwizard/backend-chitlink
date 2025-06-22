<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('thrift_packages', function (Blueprint $table) {
            $table->string('created_by_type')->nullable()->after('id');
            $table->unsignedBigInteger('created_by_id')->nullable()->after('created_by_type');
        });
    }

    public function down(): void
    {
        Schema::table('thrift_packages', function (Blueprint $table) {
            $table->dropColumn(['created_by_type', 'created_by_id']);
        });
    }
}; 