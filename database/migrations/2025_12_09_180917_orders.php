<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->timestamp('datetime_order')->useCurrent();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->integer('num_of_items')->nullable();
            $table->double('total_amount')->nullable();
            $table->string('status', 25)->nullable();
            $table->text('reason_if_cancelled')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
