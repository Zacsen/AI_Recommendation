<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->string('unit', 23)->nullable();
            $table->double('price')->nullable();
            $table->string('allow_upload', 25)->nullable();
            $table->integer('low_stock_warning_threshold')->nullable();
            $table->float('bulk_discount')->nullable();
            $table->integer('bulk_discount_minimum')->nullable();
            $table->double('layout_fee')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
