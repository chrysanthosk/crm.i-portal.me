<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    protected $fillable = [
        'appointment_id',
        'client_id',
        'services_subtotal',
        'services_vat',
        'products_subtotal',
        'products_vat',
        'total_vat',
        'grand_total',
        'voided_at',
        'voided_by',
        'void_reason',
    ];

    protected $casts = [
        'services_subtotal' => 'decimal:2',
        'services_vat'      => 'decimal:2',
        'products_subtotal' => 'decimal:2',
        'products_vat'      => 'decimal:2',
        'total_vat'         => 'decimal:2',
        'grand_total'       => 'decimal:2',
        'voided_at'         => 'datetime',
    ];

    // ---------- Relationships ----------

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function saleServices(): HasMany
    {
        return $this->hasMany(SaleService::class);
    }

    public function saleProducts(): HasMany
    {
        return $this->hasMany(SaleProduct::class);
    }

    public function salePayments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    // ---------- Query Scopes ----------

    /** Exclude voided transactions. */
    public function scopeNotVoided(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }

    /** Only voided transactions. */
    public function scopeVoided(Builder $query): Builder
    {
        return $query->whereNotNull('voided_at');
    }

    /** Filter to a specific calendar date (uses created_at). */
    public function scopeForDate(Builder $query, \DateTimeInterface|string $date): Builder
    {
        return $query->whereDate('created_at', $date);
    }

    /** Filter to a date range (inclusive, uses created_at). */
    public function scopeBetweenDates(Builder $query, \DateTimeInterface|string $from, \DateTimeInterface|string $to): Builder
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    /** Filter by payment method presence. */
    public function scopeForPaymentMethod(Builder $query, int $paymentMethodId): Builder
    {
        return $query->whereHas('salePayments', fn ($q) => $q->where('payment_method_id', $paymentMethodId));
    }

    /** Filter by staff presence across service and product lines. */
    public function scopeForStaff(Builder $query, int $staffId): Builder
    {
        return $query->where(function (Builder $q) use ($staffId) {
            $q->whereHas('saleServices', fn ($s) => $s->where('staff_id', $staffId))
              ->orWhereHas('saleProducts', fn ($p) => $p->where('staff_id', $staffId));
        });
    }

    // ---------- Computed Attributes ----------

    /** Sum of all payment amounts on this sale. */
    public function getPaidAmountAttribute(): float
    {
        if ($this->relationLoaded('salePayments')) {
            return (float) $this->salePayments->sum('amount');
        }

        return (float) $this->salePayments()->sum('amount');
    }

    /** Outstanding balance (grand_total minus paid; never negative). */
    public function getBalanceDueAttribute(): float
    {
        return max(0.0, round((float) $this->grand_total - $this->paid_amount, 2));
    }

    /** 'paid' when fully settled, 'partial' otherwise. */
    public function getPaymentStatusAttribute(): string
    {
        return $this->balance_due <= 0.00001 ? 'paid' : 'partial';
    }

    /** Whether this sale has been voided. */
    public function getIsVoidedAttribute(): bool
    {
        return !is_null($this->voided_at);
    }

    /**
     * Resolve the best available display name for the customer on this sale.
     *
     * Priority:
     *  1. Directly linked client (sales.client_id)
     *  2. Client linked to the appointment (appointments.client_id)
     *  3. Legacy appointment.client_name string
     *  4. 'Walk-in'
     */
    public function getClientNameAttribute(): string
    {
        if ($this->client_id) {
            $client = $this->relationLoaded('client')
                ? $this->client
                : $this->client()->first();

            if ($client) {
                $name = trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? ''));
                if ($name !== '') {
                    return $name;
                }
            }
        }

        if ($this->appointment_id) {
            $appt = $this->relationLoaded('appointment')
                ? $this->appointment
                : $this->appointment()->with('client')->first();

            if ($appt) {
                if ($appt->client) {
                    $name = trim(($appt->client->first_name ?? '') . ' ' . ($appt->client->last_name ?? ''));
                    if ($name !== '') {
                        return $name;
                    }
                }
                if (!empty($appt->client_name)) {
                    return $appt->client_name;
                }
            }
        }

        return 'Walk-in';
    }

    /**
     * Best mobile number for the customer on this sale.
     * Returns an empty string when none is found.
     */
    public function getClientMobileAttribute(): string
    {
        if ($this->client_id) {
            $client = $this->relationLoaded('client')
                ? $this->client
                : $this->client()->first();

            if ($client && !empty($client->mobile)) {
                return (string) $client->mobile;
            }
        }

        if ($this->appointment_id) {
            $appt = $this->relationLoaded('appointment')
                ? $this->appointment
                : $this->appointment()->with('client')->first();

            if ($appt?->client && !empty($appt->client->mobile)) {
                return (string) $appt->client->mobile;
            }
        }

        return '';
    }
}
