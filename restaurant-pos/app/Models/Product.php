<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedName;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasLocalizedName;

    protected $fillable = ['category_id', 'name', 'name_en', 'price', 'cost', 'image', 'is_active'];

    protected $casts = [
        'price' => 'decimal:2',
        'cost' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(fn () => \App\Tenancy\PlanLimits::ensure('products'));
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
