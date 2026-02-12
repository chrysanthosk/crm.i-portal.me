<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VatType extends Model
{
    protected $table = 'vat_types';

    protected $fillable = [
        'name',
        'vat_percent',
    ];

    protected $casts = [
        'vat_percent' => 'decimal:2',
    ];

    public function services()
    {
        return $this->hasMany(Service::class, 'vat_type_id');
    }
}
