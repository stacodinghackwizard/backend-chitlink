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
        // Thrift Packages
        Schema::create('thrift_packages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id');
            $table->string('name');
            $table->decimal('total_amount', 15, 2);
            $table->integer('duration_days');
            $table->integer('slots');
            $table->text('terms')->nullable();
            $table->enum('status', ['draft', 'pending', 'ongoing', 'completed', 'rejected'])->default('draft');
            $table->timestamps();

            $table->foreign('merchant_id')->references('id')->on('merchants')->onDelete('cascade');
        });

        // Contributors (pivot)
        Schema::create('thrift_contributors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('thrift_package_id');
            $table->unsignedBigInteger('contact_id');
            $table->enum('status', ['pending', 'confirmed', 'rejected'])->default('pending');
            $table->timestamps();

            $table->foreign('thrift_package_id')->references('id')->on('thrift_packages')->onDelete('cascade');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
            $table->unique(['thrift_package_id', 'contact_id']);
        });

        // Slots
        Schema::create('thrift_slots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('thrift_package_id');
            $table->unsignedBigInteger('contributor_id');
            $table->string('slot_no', 10);
            $table->enum('status', ['pending', 'paid', 'collected'])->default('pending');
            $table->timestamps();

            $table->foreign('thrift_package_id')->references('id')->on('thrift_packages')->onDelete('cascade');
            $table->foreign('contributor_id')->references('id')->on('thrift_contributors')->onDelete('cascade');
            $table->unique(['thrift_package_id', 'slot_no']);
        });

        // Transactions
        Schema::create('thrift_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('thrift_package_id');
            $table->unsignedBigInteger('slot_id');
            $table->unsignedBigInteger('contributor_id');
            $table->decimal('amount', 15, 2);
            $table->enum('type', ['contribution', 'payout']);
            $table->timestamp('transacted_at');
            $table->timestamps();

            $table->foreign('thrift_package_id')->references('id')->on('thrift_packages')->onDelete('cascade');
            $table->foreign('slot_id')->references('id')->on('thrift_slots')->onDelete('cascade');
            $table->foreign('contributor_id')->references('id')->on('thrift_contributors')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('thrift_transactions');
        Schema::dropIfExists('thrift_slots');
        Schema::dropIfExists('thrift_contributors');
        Schema::dropIfExists('thrift_packages');
    }
}; 