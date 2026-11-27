@php($other = app()->getLocale() === 'ar' ? 'en' : 'ar')
<a href="{{ route('locale', $other) }}"
   style="display:inline-flex;align-items:center;gap:.35rem;padding:.35rem .75rem;border-radius:.5rem;font-size:.875rem;font-weight:600;border:1px solid rgba(127,127,127,.3);text-decoration:none;margin-inline:.5rem;justify-content:center"
   title="{{ $other === 'en' ? 'Switch to English' : 'التحويل للعربية' }}">
    🌐 {{ $other === 'en' ? 'English' : 'العربية' }}
</a>
