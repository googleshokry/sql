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
            ->label('اشتراك جديد / تجديد')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('success')
            ->modalWidth('3xl')
            ->schema(fn ($record) => SubscriptionFields::make(true, SubscriptionService::nextStartDate($resolve($record))))
            ->action(function (array $data, $record) use ($resolve) {
                $sub = SubscriptionService::create($resolve($record), $data);
                Notification::make()->title('تم إنشاء الاشتراك')->body('ينتهي في '.$sub->end_date->format('Y-m-d'))->success()->send();
            });
    }

    public static function pay(): Action
    {
        return Action::make('pay')
            ->label('تحصيل')
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('warning')
            ->visible(fn (Subscription $record) => $record->remaining_amount > 0)
            ->schema(fn (Subscription $record) => [
                TextInput::make('amount')->label('المبلغ')->numeric()->required()->minValue(0.01)
                    ->maxValue($record->remaining_amount)->default($record->remaining_amount)
                    ->helperText('المتبقي: '.number_format($record->remaining_amount, 2).' '.Setting::currency()),
                Select::make('method')->label('طريقة الدفع')->options(Payment::METHODS)->default('cash')->required(),
                TextInput::make('notes')->label('ملاحظات'),
            ])
            ->action(function (Subscription $record, array $data) {
                $payment = SubscriptionService::addPayment($record, (float) $data['amount'], $data['method'], $data['notes'] ?? null);
                Notification::make()->title('تم تسجيل الدفعة '.$payment->receipt_number)->success()
                    ->actions([Action::make('print')->label('طباعة الإيصال')->url(route('print.receipt', $payment), true)])
                    ->send();
            });
    }

    public static function freeze(): Action
    {
        return Action::make('freeze')
            ->label('تجميد')
            ->icon(Heroicon::OutlinedPauseCircle)
            ->color('info')
            ->visible(fn (Subscription $record) => in_array($record->status, [Subscription::STATUS_ACTIVE, Subscription::STATUS_UPCOMING])
                && $record->freeze_days_left > 0)
            ->schema(fn (Subscription $record) => [
                DatePicker::make('from')->label('من تاريخ')->default(today()->toDateString())->required()->minDate(today()->subDay()),
                TextInput::make('days')->label('عدد الأيام')->numeric()->required()->minValue(1)->maxValue($record->freeze_days_left)
                    ->helperText('المتاح: '.$record->freeze_days_left.' يوم'),
                TextInput::make('reason')->label('السبب'),
            ])
            ->action(function (Subscription $record, array $data) {
                $record->freeze((int) $data['days'], Carbon::parse($data['from']), $data['reason'] ?? null);
                Notification::make()->title('تم تجميد الاشتراك')->body('تاريخ الانتهاء الجديد '.$record->end_date->format('Y-m-d'))->success()->send();
            });
    }

    public static function unfreeze(): Action
    {
        return Action::make('unfreeze')
            ->label('فك التجميد')
            ->icon(Heroicon::OutlinedPlayCircle)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription('سيتم استعادة أيام التجميد غير المستخدمة وتعديل تاريخ الانتهاء.')
            ->visible(fn (Subscription $record) => $record->frozen_until && $record->frozen_until->gte(today()))
            ->action(function (Subscription $record) {
                $record->unfreeze();
                Notification::make()->title('تم فك التجميد')->success()->send();
            });
    }

    public static function cancel(): Action
    {
        return Action::make('cancel')
            ->label('إلغاء')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (Subscription $record) => $record->status !== Subscription::STATUS_CANCELLED && auth()->user()->isManager())
            ->action(function (Subscription $record) {
                $record->update(['status' => Subscription::STATUS_CANCELLED]);
                Notification::make()->title('تم إلغاء الاشتراك')->success()->send();
            });
    }

    public static function checkIn(?callable $member = null): Action
    {
        $resolve = $member ?? fn ($record) => $record;

        return Action::make('checkin')
            ->label(fn ($record) => $resolve($record)?->openAttendance() ? 'تسجيل خروج' : 'تسجيل دخول')
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
            ->label('واتساب')
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
}
