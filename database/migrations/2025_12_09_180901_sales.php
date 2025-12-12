<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
 

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->dateTime('datetime_sold')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->string('customer_name')->nullable();
            $table->integer('num_of_items')->nullable();
            $table->double('total_amount')->nullable();
            $table->double('cash_tend')->nullable();
            $table->double('cash_change')->nullable();
            $table->unsignedBigInteger('cashier_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
