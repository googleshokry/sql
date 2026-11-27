<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverLocation extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['driver_id', 'latitude', 'longitude', 'accuracy', 'speed'];

    protected $casts = ['latitude' => 'float', 'longitude' => 'float', 'created_at' => 'datetime'];
}
