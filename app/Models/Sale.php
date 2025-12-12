<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $table = 'sales';
    public $timestamps = false;

    public function items()
    {
        return $this->hasMany(SaleItem::class, 'sale_id');
    }
}