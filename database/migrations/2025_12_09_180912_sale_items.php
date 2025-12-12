<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->integer('quantity')->nullable();
            $table->double('price')->nullable();
            $table->double('subtotal')->nullable();
            $table->double('discount_percentage')->nullable();
            $table->double('discount_amount')->nullable();
            $table->double('discounted_amount')->nullable();
            $table->double('add_on_fee')->nullable();
            $table->double('payable_amount')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
