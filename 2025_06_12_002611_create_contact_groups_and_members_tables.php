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
        // Create contact_groups table
        Schema::create('contact_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('color', 7)->default('#3B82F6'); // Hex color code
            $table->timestamps();

            $table->foreign('merchant_id')->references('id')->on('merchants')->onDelete('cascade');
            $table->index(['merchant_id', 'name']);
        });

        // Create contact_group_members pivot table
        Schema::create('contact_group_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contact_group_id');
            $table->unsignedBigInteger('contact_id');
            $table->timestamps();

            $table->foreign('contact_group_id')->references('id')->on('contact_groups')->onDelete('cascade');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
            
            // Prevent duplicate entries
            $table->unique(['contact_group_id', 'contact_id']);
            $table->index(['contact_group_id']);
            $table->index(['contact_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_group_members');
        Schema::dropIfExists('contact_groups');
    }
};