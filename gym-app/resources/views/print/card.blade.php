@extends('print.layout')
@php($s = \App\Models\Setting::allSettings())
@section('title', 'كارنيه '.$member->name)
@push('styles')
<style>
    .card { width: 86mm; height: 54mm; border-radius: 10px; overflow: hidden; background: linear-gradient(135deg, #111827, #374151); color: #fff; padding: 10px 12px; display: flex; flex-direction: column; justify-content: space-between; margin: 0 auto; }
    .card .top { display: flex; gap: 10px; align-items: center; }
    .card img.photo { width: 64px; height: 64px; border-radius: 50%; object-fit: cover; border: 3px solid #f59e0b; }
    .card .gym { color: #f59e0b; font-weight: 800; font-size: 15px; }
    .card .name { font-weight: 700; font-size: 14px; }
    .card .bc { background: #fff; border-radius: 6px; padding: 4px; text-align: center; color: #111; }
    .card .bc svg { height: 32px; width: 100%; }
</style>
@endpush
@section('content')
    <div class="card">
        <div class="top">
            <img class="photo" src="{{ $member->photo_url }}" alt="">
            <div>
                <div class="gym">{{ $s['gym_name'] }}</div>
                <div class="name">{{ $member->name }}</div>
                <div style="font-size:11px">كود: {{ $member->code }}</div>
                @if ($member->latestSubscription)
                    <div style="font-size:11px">{{ $member->latestSubscription->plan?->name }} — حتى {{ $member->latestSubscription->end_date->format('Y-m-d') }}</div>
                @endif
            </div>
        </div>
        <div class="bc">{!! $barcode !!}<div style="font-size:10px;letter-spacing:2px">{{ $member->code }}</div></div>
    </div>
@endsection
