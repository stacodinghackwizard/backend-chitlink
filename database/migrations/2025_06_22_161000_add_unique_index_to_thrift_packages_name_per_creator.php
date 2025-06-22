<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('thrift_packages', function (Blueprint $table) {
            $table->unique(['name', 'created_by_type', 'created_by_id'], 'unique_package_per_creator');
        });
    }

    public function down(): void
    {
        Schema::table('thrift_packages', function (Blueprint $table) {
            $table->dropUnique('unique_package_per_creator');
        });
    }
}; 