<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $fillable = [
        'name',
        'category_id',
        'price',
        'vat_type_id',
        'duration',
        'waiting',
        'gender',
        'comment',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'duration' => 'integer',
        'waiting' => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    public function vatType()
    {
        return $this->belongsTo(VatType::class, 'vat_type_id');
    }
}
