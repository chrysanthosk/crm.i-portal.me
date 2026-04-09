<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoyaltyTier extends Model
{
    protected $fillable = ['name', 'points_min', 'benefits', 'sort_order'];

    protected $casts = [
        'points_min' => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * Find the tier that applies for a given points balance.
     * Returns the highest tier whose points_min <= $points.
     */
    public static function forPoints(int $points): ?self
    {
        return static::query()
            ->where('points_min', '<=', $points)
            ->orderByDesc('points_min')
            ->first();
    }

    /**
     * Find the next tier above the given points balance.
     */
    public static function nextTierForPoints(int $points): ?self
    {
        return static::query()
            ->where('points_min', '>', $points)
            ->orderBy('points_min')
            ->first();
    }
}
