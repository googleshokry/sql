<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Member extends Authenticatable
{
    use BelongsToGym;
    use HasApiTokens, SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'password' => 'hashed',
        'last_app_login_at' => 'datetime',
        'birth_date' => 'date',
        'joined_at' => 'date',
        'is_blocked' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Member $member) {
            $gym = Gym::find($member->gym_id);
            $count = static::withoutGlobalScopes()->where('gym_id', $member->gym_id)->whereNull('deleted_at')->count();
            if ($gym && ! $gym->withinLimit('max_members', $count)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'name' => __('وصلت للحد الأقصى لعدد المشتركين في باقتك (:max). قم بترقية الباقة.', ['max' => $gym->saasPlan->max_members]),
                ]);
            }

            if (blank($member->code)) {
                $member->code = (string) \App\Support\Sequence::next(static::class, $member->gym_id, 'code', 1001);
            }
            $member->joined_at ??= now()->toDateString();
        });
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class)->latest('start_date');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest('paid_at');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class)->latest('check_in_at');
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(BodyMeasurement::class)->latest('measured_at');
    }

    public function latestSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->ofMany(['end_date' => 'max', 'id' => 'max']);
    }

    /** The subscription that is valid today (active or frozen). */
    public function currentSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->whereNotIn('status', [Subscription::STATUS_CANCELLED])
            ->whereDate('start_date', '<=', today())
            ->whereDate('end_date', '>=', today())
            ->orderByDesc('end_date')
            ->first();
    }

    public function openAttendance(): ?Attendance
    {
        return $this->attendances()->whereNull('check_out_at')->where('is_guest', false)->first();
    }

    public function getPhotoUrlAttribute(): string
    {
        if ($this->photo) {
            return Storage::disk('public')->url($this->photo);
        }

        return \App\Support\Avatar::for($this->name);
    }

    public function getAgeAttribute(): ?int
    {
        return $this->birth_date?->age;
    }

    public function getBalanceAttribute(): float
    {
        return (float) $this->subscriptions()->get()->sum(fn (Subscription $s) => $s->remaining_amount);
    }

    public function whatsappLink(?string $message = null): string
    {
        $phone = preg_replace('/\D/', '', $this->phone);
        if (str_starts_with($phone, '01') && strlen($phone) === 11) {
            $phone = '2'.$phone; // Egypt
        }

        return 'https://wa.me/'.$phone.($message ? '?text='.rawurlencode($message) : '');
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('code', $term)
            ->orWhere('phone', $term)
            ->orWhere('national_id', $term)
            ->orWhere('name', 'like', "%{$term}%"));
    }

    public function scopeWithActiveSubscription(Builder $query): Builder
    {
        return $query->whereHas('subscriptions', fn (Builder $q) => $q->currentlyValid());
    }
}
