<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends Model
{
    protected $fillable = ['name'];

    public function salePayments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }
}
