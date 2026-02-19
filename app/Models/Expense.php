<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $fillable = [
        'date',
        'name',
        'amount_paid',
        'payment_type',
        'cheque_no',
        'invoice_reason',
    ];

    protected $casts = [
        'date' => 'date',
        'amount_paid' => 'decimal:2',
    ];
}
