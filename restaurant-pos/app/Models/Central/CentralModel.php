<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;

/** موديلات القاعدة المركزية — دايماً على الاتصال المركزي مهما كان المطعم الحالي */
abstract class CentralModel extends Model
{
    public function getConnectionName()
    {
        return config('tenancy.central_connection');
    }
}
