<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->float('mba_score')->default(0);
            $table->float('content_score')->default(0);
            $table->float('collab_score')->default(0);
            $table->float('season_score')->default(0);
            $table->float('stock_score')->default(0);
            $table->float('final_score')->default(0);
            $table->timestamps();

            $table->foreign('product_id')
                  ->references('id')
                  ->on('products')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_scores');
    }
};