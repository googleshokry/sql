<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmActivity extends Model
{
    use BelongsToCompany;

    public const TYPES = ['call' => 'مكالمة', 'meeting' => 'اجتماع', 'visit' => 'زيارة', 'email' => 'بريد / واتساب', 'task' => 'مهمة'];

    public const STATUSES = ['pending' => 'قيد الانتظار', 'done' => 'تمت', 'cancelled' => 'ملغاة'];

    protected $guarded = [];

    protected $casts = ['scheduled_at' => 'datetime'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
