<?php

namespace App\Http\Responses;

use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * بعد تسجيل الدخول: نرجع للرابط المطلوب فقط لو جوه نفس اللوحة/المطعم
 * (عشان رابط من مطعم أو لوحة تانية في نفس المتصفح ما يتفتحش)
 */
class PanelLoginResponse implements LoginResponse
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        $home = Filament::getUrl();
        $base = rtrim(url(Filament::getCurrentPanel()->getPath()), '/');
        $intended = $request->session()->pull('url.intended');

        $sameArea = is_string($intended) && ($intended === $base || str_starts_with($intended, $base.'/'));

        return redirect()->to($sameArea ? $intended : $home);
    }
}
