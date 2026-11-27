<?php

namespace App\Models\Concerns;

/** الاسم حسب لغة المستخدم (name_en لو اللغة إنجليزي وموجود) */
trait HasLocalizedName
{
    public function getLocalizedNameAttribute(): string
    {
        return app()->getLocale() === 'en' && filled($this->name_en) ? $this->name_en : $this->name;
    }
}
