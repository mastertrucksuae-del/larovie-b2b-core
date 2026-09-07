<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class BusinessAccount extends Authenticatable implements AuthenticatableContract
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_PENDING => 'Pending review',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_REJECTED => 'Rejected',
    ];

    protected $guarded = ['id'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'password' => 'hashed',
        'approved_at' => 'datetime',
    ];

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Inquiries submitted while signed in to this account. */
    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class);
    }

    /**
     * Every inquiry belonging to this customer, for reporting.
     *
     * Wider than the `inquiries()` relation on purpose. Inquiries predate
     * accounts and the cart still allows guest checkout, so a customer's real
     * history is split between rows carrying this account id and older rows that
     * only ever recorded an email address. Email is unique on this table, so
     * matching on it is safe, and without this the account view would report
     * zero for a customer with a long history.
     */
    public function matchedInquiries(): Builder
    {
        return Inquiry::query()
            ->where(fn (Builder $q) => $q
                ->where('business_account_id', $this->id)
                ->orWhere(fn (Builder $inner) => $inner
                    ->whereNull('business_account_id')
                    ->whereRaw('LOWER(customer_email) = ?', [mb_strtolower((string) $this->email)])));
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Whether this buyer may submit an inquiry.
     *
     * Pricing is public — the gate is on ordering. Registering is the whole gate
     * when manual review is switched off; when it is on, review has to mean
     * something, so the account must actually be approved. A rejected applicant
     * can never order either way.
     */
    public function canSubmitInquiry(): bool
    {
        if ($this->isRejected()) {
            return false;
        }

        return ! Setting::current()->require_account_review || $this->isApproved();
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** Admin-guarded download URL for the uploaded trade licence, if any. */
    public function getTradeLicenceUrlAttribute(): ?string
    {
        return $this->trade_licence_path
            ? route('business-account.licence', $this)
            : null;
    }
}
