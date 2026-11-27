<?php

namespace App\Filament\Actions;

use App\Filament\Forms\SubscriptionFields;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Subscription;
use App\Services\AttendanceService;
use App\Services\SubscriptionService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

class GymActions
{
    /** Sell / renew a subscription for a member. $member resolves the member from the action's record. */
    public static function renew(?callable $member = null, string $name = 'renew'): Action
    {
        $resolve = $member ?? fn ($record) => $record;

        return Action::make($name)
            ->label(__('اشتراك جديد / تجديد'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('success')
            ->modalWidth('3xl')
            ->schema(fn ($record) => SubscriptionFields::make(true, SubscriptionService::nextStartDate($resolve($record))))
            ->action(function (array $data, $record) use ($resolve) {
                $sub = SubscriptionService::create($resolve($record), $data);
                Notification::make()->title(__('تم إنشاء الاشتراك'))->body(__('ينتهي في :date', ['date' => $sub->end_date->format('Y-m-d')]))->success()->send();
            });
    }

    public static function pay(): Action
    {
        return Action::make('pay')
            ->label(__('تحصيل'))
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('warning')
            ->visible(fn (Subscription $record) => $record->remaining_amount > 0)
            ->schema(fn (Subscription $record) => [
                TextInput::make('amount')->label(__('المبلغ'))->numeric()->required()->minValue(0.01)
                    ->maxValue($record->remaining_amount)->default($record->remaining_amount)
                    ->helperText(__('المتبقي: :amount', ['amount' => number_format($record->remaining_amount, 2).' '.Setting::currency()])),
                Select::make('method')->label(__('طريقة الدفع'))->options(Payment::methods())->default('cash')->required(),
                TextInput::make('notes')->label(__('ملاحظات')),
            ])
            ->action(function (Subscription $record, array $data) {
                $payment = SubscriptionService::addPayment($record, (float) $data['amount'], $data['method'], $data['notes'] ?? null);
                Notification::make()->title(__('تم تسجيل الدفعة :number', ['number' => $payment->receipt_number]))->success()
                    ->actions([Action::make('print')->label(__('طباعة الإيصال'))->url(route('print.receipt', $payment), true)])
                    ->send();
            });
    }

    public static function freeze(): Action
    {
        return Action::make('freeze')
            ->label(__('تجميد'))
            ->icon(Heroicon::OutlinedPauseCircle)
            ->color('info')
            ->visible(fn (Subscription $record) => in_array($record->status, [Subscription::STATUS_ACTIVE, Subscription::STATUS_UPCOMING])
                && $record->freeze_days_left > 0)
            ->schema(fn (Subscription $record) => [
                DatePicker::make('from')->label(__('من تاريخ'))->default(today()->toDateString())->required()->minDate(today()->subDay()),
                TextInput::make('days')->label(__('عدد الأيام'))->numeric()->required()->minValue(1)->maxValue($record->freeze_days_left)
                    ->helperText(__('المتاح: :days يوم', ['days' => $record->freeze_days_left])),
                TextInput::make('reason')->label(__('السبب')),
            ])
            ->action(function (Subscription $record, array $data) {
                $record->freeze((int) $data['days'], Carbon::parse($data['from']), $data['reason'] ?? null);
                Notification::make()->title(__('تم تجميد الاشتراك'))->body(__('تاريخ الانتهاء الجديد :date', ['date' => $record->end_date->format('Y-m-d')]))->success()->send();
            });
    }

    public static function unfreeze(): Action
    {
        return Action::make('unfreeze')
            ->label(__('فك التجميد'))
            ->icon(Heroicon::OutlinedPlayCircle)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(__('سيتم استعادة أيام التجميد غير المستخدمة وتعديل تاريخ الانتهاء.'))
            ->visible(fn (Subscription $record) => $record->frozen_until && $record->frozen_until->gte(today()))
            ->action(function (Subscription $record) {
                $record->unfreeze();
                Notification::make()->title(__('تم فك التجميد'))->success()->send();
            });
    }

    public static function cancel(): Action
    {
        return Action::make('cancel')
            ->label(__('إلغاء'))
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (Subscription $record) => $record->status !== Subscription::STATUS_CANCELLED && auth()->user()->isManager())
            ->action(function (Subscription $record) {
                $record->update(['status' => Subscription::STATUS_CANCELLED]);
                Notification::make()->title(__('تم إلغاء الاشتراك'))->success()->send();
            });
    }

    public static function checkIn(?callable $member = null): Action
    {
        $resolve = $member ?? fn ($record) => $record;

        return Action::make('checkin')
            ->label(fn ($record) => $resolve($record)?->openAttendance() ? __('تسجيل خروج') : __('تسجيل دخول'))
            ->icon(fn ($record) => $resolve($record)?->openAttendance() ? Heroicon::OutlinedArrowRightStartOnRectangle : Heroicon::OutlinedArrowLeftEndOnRectangle)
            ->color(fn ($record) => $resolve($record)?->openAttendance() ? 'gray' : 'primary')
            ->action(function ($record) use ($resolve) {
                $result = AttendanceService::toggle($resolve($record));
                Notification::make()->title($result['message'])->{$result['ok'] ? 'success' : 'danger'}()->send();
            });
    }

    public static function whatsapp(?callable $member = null): Action
    {
        $resolve = $member ?? fn ($record) => $record;

        return Action::make('whatsapp')
            ->label(__('واتساب'))
            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->color('success')
            ->url(function ($record) use ($resolve) {
                /** @var Member $m */
                $m = $resolve($record);
                $sub = $record instanceof Subscription ? $record : $m->latestSubscription;
                $msg = strtr((string) Setting::get('whatsapp_expiry_message'), [
                    '{name}' => $m->name,
                    '{gym}' => Setting::get('gym_name'),
                    '{end_date}' => $sub?->end_date?->format('Y-m-d') ?? '-',
                    '{plan}' => $sub?->plan?->name ?? '',
                ]);

                return $m->whatsappLink($msg);
            }, true);
    }

    /** Generate a new mobile-app PIN for the member and offer to send it on WhatsApp. */
    public static function appAccess(): Action
    {
        return Action::make('appAccess')
            ->label(__('دخول التطبيق'))
            ->icon(Heroicon::OutlinedDevicePhoneMobile)
            ->color('info')
            ->requiresConfirmation()
            ->modalHeading(__('إنشاء كلمة مرور جديدة للتطبيق'))
            ->modalDescription(__('سيتم إنشاء كلمة مرور جديدة وإلغاء دخول المشترك من الأجهزة الأخرى.'))
            ->action(function (Member $record) {
                $pin = (string) random_int(100000, 999999);
                $record->forceFill(['password' => $pin])->save();
                $record->tokens()->delete();

                $message = __('مرحباً :name، بيانات دخولك لتطبيق :gym: كود الجيم :gym_code، كود العضوية :code، كلمة المرور :pin', [
                    'name' => $record->name, 'gym' => Setting::get('gym_name'), 'gym_code' => $record->gym->slug, 'code' => $record->code, 'pin' => $pin,
                ]);

                Notification::make()
                    ->title(__('كلمة المرور الجديدة: :pin', ['pin' => $pin]))
                    ->body(__('كود الجيم: :gym — كود العضوية: :code', ['gym' => $record->gym->slug, 'code' => $record->code]))
                    ->success()->persistent()
                    ->actions([Action::make('send')->label(__('إرسال على واتساب'))->url($record->whatsappLink($message), true)])
                    ->send();
            });
    }
}
