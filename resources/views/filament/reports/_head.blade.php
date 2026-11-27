@php($s = \App\Models\Setting::values())
<div class="erp-print-head" style="display:none">
    <h2 style="margin:0">{{ $s['company_name'] }}</h2>
    <div>{{ $title }} — {{ __('من') }} {{ $this->from }} {{ __('إلى') }} {{ $this->to }}</div>
</div>
<style>
    @media print {
        .fi-sidebar, .fi-topbar, .fi-header-actions, .erp-filters, .fi-page-header-main-ctn > .fi-header .fi-ac { display: none !important; }
        .erp-print-head { display: block !important; text-align: center; margin-bottom: 10px; }
        .fi-main { padding: 0 !important; }
    }
</style>
