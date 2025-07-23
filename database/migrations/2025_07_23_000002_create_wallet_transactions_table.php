<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wallet_id');
            $table->string('type');
            $table->decimal('amount', 20, 2);
            $table->string('reference')->nullable();
            $table->string('status')->default('pending');
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->foreign('wallet_id')->references('id')->on('wallets')->onDelete('cascade');
        });
    }
    public function down()
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
