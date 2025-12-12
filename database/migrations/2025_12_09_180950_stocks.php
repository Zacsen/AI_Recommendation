<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
       Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->dateTime('datetime_acquired')->default(DB::raw('CURRENT_TIMESTAMP'));
            
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();

            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreign('supplier_id', 'fk_stocks_supplier')
                ->references('id')
                ->on('suppliers')
                ->nullOnDelete();

            $table->integer('quantity')->nullable();
            $table->string('remarks', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
