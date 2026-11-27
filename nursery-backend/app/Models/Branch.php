<?php

namespace App\Models;

use App\Models\Concerns\BelongsToNursery;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Branch extends Model
{
    use BelongsToNursery, HasFactory, HasTranslations;

    public array $translatable = ['name'];

    protected $fillable = ['nursery_id', 'name', 'address', 'phone', 'lat', 'lng'];
}
