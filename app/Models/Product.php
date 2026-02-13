<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'purchase_price',
        'purchase_vat_type_id',
        'sell_price',
        'sell_vat_type_id',
        'quantity_stock',
        'quantity_in_box',
        'comment',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'sell_price' => 'decimal:2',
        'quantity_stock' => 'integer',
        'quantity_in_box' => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function purchaseVatType()
    {
        return $this->belongsTo(VatType::class, 'purchase_vat_type_id');
    }

    public function sellVatType()
    {
        return $this->belongsTo(VatType::class, 'sell_vat_type_id');
    }
}
