@php($current = app()->getLocale())
<div style="display:flex;gap:6px;justify-content:center;align-items:center;{{ empty($compact) ? 'margin-top:12px' : '' }}">
    @if(empty($compact))
        @foreach(['ar' => 'العربية', 'en' => 'English'] as $code => $label)
            <a href="{{ route('locale.switch', $code) }}"
               style="padding:4px 12px;border-radius:999px;font-size:13px;text-decoration:none;border:1px solid {{ $current === $code ? '#f97316' : '#d1d5db' }};color:{{ $current === $code ? '#fff' : 'inherit' }};background:{{ $current === $code ? '#f97316' : 'transparent' }}">{{ $label }}</a>
        @endforeach
    @else
        <a href="{{ route('locale.switch', $current === 'ar' ? 'en' : 'ar') }}" title="{{ __('تغيير اللغة') }}"
           style="padding:4px 10px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;border:1px solid #d1d5db;color:inherit">
            {{ $current === 'ar' ? 'EN' : 'ع' }}
        </a>
    @endif
</div>
