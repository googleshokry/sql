@extends('saas.layout')
@section('title', __('تسجيل مطعم جديد'))

@section('content')
    <div style="max-width:560px;margin:16px auto 0">
        <div class="card">
            <h1 style="margin:0 0 4px;font-size:24px">{{ __('سجل مطعمك') }}</h1>
            <p class="hint" style="font-size:14px;margin:0">{{ __('تجربة مجانية :days يوم. هتقدر تدخل على طول بعد التسجيل.', ['days' => $trialDays]) }}</p>

            <form method="POST" action="{{ route('signup.store') }}" x-data>
                @csrf
                <label for="name">{{ __('اسم المطعم') }}</label>
                <input id="name" name="name" value="{{ old('name') }}" required maxlength="100">
                @error('name')<div class="error">{{ $message }}</div>@enderror

                <label for="slug">{{ __('كود المطعم (رابط الدخول)') }}</label>
                <input id="slug" name="slug" value="{{ old('slug') }}" required dir="ltr" pattern="[a-z0-9]+(-[a-z0-9]+)*" minlength="3" maxlength="30"
                       placeholder="my-restaurant" oninput="this.value=this.value.toLowerCase().replace(/[^a-z0-9-]/g,'');document.getElementById('slug-preview').textContent=this.value||'my-restaurant'">
                <div class="hint" dir="ltr">{{ url('app') }}/<b id="slug-preview">{{ old('slug', 'my-restaurant') }}</b></div>
                <div class="hint">{{ __('حروف إنجليزي صغيرة وأرقام وشرطة فقط. ده الكود اللي هتكتبه في تطبيقات الموبايل.') }}</div>
                @error('slug')<div class="error">{{ $message }}</div>@enderror

                <label for="owner_name">{{ __('اسمك') }}</label>
                <input id="owner_name" name="owner_name" value="{{ old('owner_name') }}" required>
                @error('owner_name')<div class="error">{{ $message }}</div>@enderror

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                    <div>
                        <label for="owner_email">{{ __('البريد') }}</label>
                        <input id="owner_email" name="owner_email" type="email" value="{{ old('owner_email') }}" required dir="ltr">
                        @error('owner_email')<div class="error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label for="owner_phone">{{ __('الموبايل') }}</label>
                        <input id="owner_phone" name="owner_phone" value="{{ old('owner_phone') }}" required dir="ltr" inputmode="tel">
                        @error('owner_phone')<div class="error">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                    <div>
                        <label for="password">{{ __('كلمة المرور') }}</label>
                        <input id="password" name="password" type="password" required minlength="8">
                        @error('password')<div class="error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label for="password_confirmation">{{ __('تأكيد كلمة المرور') }}</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" required minlength="8">
                    </div>
                </div>

                <label for="plan_id">{{ __('الباقة') }}</label>
                <select id="plan_id" name="plan_id" required>
                    @foreach ($plans as $plan)
                        <option value="{{ $plan->id }}" @selected(old('plan_id', $selectedPlan ?: $plans->skip(1)->first()?->id) == $plan->id)>
                            {{ $plan->localized_name }} — {{ number_format($plan->price_monthly) }} {{ __('ج.م / شهر') }}
                        </option>
                    @endforeach
                </select>

                <label style="display:flex;gap:8px;align-items:center;font-weight:400">
                    <input type="checkbox" name="demo" value="1" style="width:auto" {{ old('demo', '1') ? 'checked' : '' }}>
                    {{ __('ضيف منيو وطاولات تجريبية عشان أجرب بسرعة') }}
                </label>

                <button class="btn btn-primary" type="submit" style="width:100%;margin-top:20px">{{ __('إنشاء حساب المطعم') }}</button>
            </form>
        </div>
    </div>
@endsection
