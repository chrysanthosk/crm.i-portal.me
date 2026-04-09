<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientLoyalty extends Model
{
    protected $table      = 'client_loyalty';
    protected $primaryKey = 'client_id';
    public $incrementing  = false;

    protected $fillable = ['client_id', 'points_balance'];

    protected $casts = [
        'points_balance' => 'integer',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function currentTier(): ?LoyaltyTier
    {
        return LoyaltyTier::forPoints($this->points_balance ?? 0);
    }

    public function nextTier(): ?LoyaltyTier
    {
        return LoyaltyTier::nextTierForPoints($this->points_balance ?? 0);
    }

    public function pointsToNextTier(): ?int
    {
        $next = $this->nextTier();
        if (!$next) {
            return null;
        }
        return max(0, $next->points_min - ($this->points_balance ?? 0));
    }
}
