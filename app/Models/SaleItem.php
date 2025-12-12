<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    protected $table = 'sale_items';
    public $timestamps = false;

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}