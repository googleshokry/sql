<?php

namespace App\Filament\Actions;

use App\Exceptions\BusinessException;
use App\Filament\Resources\InstallmentPlanResource;
use App\Filament\Resources\PurchaseInvoiceResource;
use App\Filament\Resources\PurchaseReturnResource;
use App\Filament\Resources\SalesInvoiceResource;
use App\Filament\Resources\SalesReturnResource;
use App\Filament\Support\DocumentResource;
use App\Models\Customer;
use App\Models\InstallmentPlan;
use App\Models\Payment;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use App\Models\SalesInvoice;
use App\Models\Supplier;
use App\Models\Treasury;
use App\Services\Posting;
use App\Support\Tenancy;
use App\Support\Translate;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class DocumentActions
{
    /** @param class-string<DocumentResource> $resource */
    public static function forTable(string $resource): array
    {
        return array_values(array_filter([
            static::post($resource),
            static::print($resource),
            static::registerPayment($resource),
            static::convert($resource),
        ]));
    }

    public static function forView(string $resource): array
    {
        return array_values(array_filter([
            static::post($resource),
            static::registerPayment($resource),
            static::print($resource),
            static::convert($resource),
            static::installmentPlan($resource),
            static::quotationStatus($resource),
            static::unpost($resource),
        ]));
    }

    public static function run(callable $callback, ?string $success = null): mixed
    {
        try {
            $result = $callback();
            if ($success) {
                Notification::make()->title($success)->success()->send();
            }

            return $result;
        } catch (BusinessException $e) {
            Notification::make()->title(__('تعذّر تنفيذ العملية'))->body($e->getMessage())->danger()->persistent()->send();

            return null;
        }
    }

    public static function post(string $resource): ?Action
    {
        if (! $resource::isPostable()) {
            return null;
        }

        return Action::make('post')
            ->label(__('ترحيل'))
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(__('سيتم تحديث المخزون وإنشاء القيود المحاسبية تلقائياً ولن يمكن تعديل المستند بعد الترحيل.'))
            ->visible(fn (Model $record) => ! $record->isPosted())
            ->action(fn (Model $record) => static::run(fn () => Posting::post($record), __('تم الترحيل بنجاح')));
    }

    public static function unpost(string $resource): ?Action
    {
        if (! $resource::isPostable()) {
            return null;
        }

        return Action::make('unpost')
            ->label(__('إلغاء الترحيل'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(__('سيتم عكس حركات المخزون وحذف القيود المحاسبية المرتبطة بهذا المستند.'))
            ->visible(fn (Model $record) => $record->isPosted() && auth()->user()?->hasRole('manager', 'accountant'))
            ->action(fn (Model $record) => static::run(fn () => Posting::unpost($record), __('تم إلغاء الترحيل')));
    }

    public static function print(string $resource): ?Action
    {
        if (! $resource::getPrintKey()) {
            return null;
        }

        return Action::make('print')
            ->label(__('طباعة'))
            ->icon(Heroicon::OutlinedPrinter)
            ->color('gray')
            ->url(fn (Model $record) => route('print.document', [$resource::getPrintKey(), $record]))
            ->openUrlInNewTab();
    }

    public static function registerPayment(string $resource): ?Action
    {
        if (! $resource::isPayable()) {
            return null;
        }

        return Action::make('registerPayment')
            ->label(fn (Model $record) => $record instanceof SalesInvoice ? __('تحصيل دفعة') : __('سداد دفعة'))
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('warning')
            ->visible(fn (Model $record) => $record->isPosted() && $record->remainingAmount() > 0)
            ->fillForm(fn (Model $record) => [
                'amount' => $record->remainingAmount(),
                'date' => now()->toDateString(),
                'treasury_id' => Treasury::query()->where('is_default', true)->value('id'),
                'method' => 'cash',
            ])
            ->schema([
                TextInput::make('amount')->label(__('المبلغ'))->numeric()->required()->minValue(0.01),
                Select::make('treasury_id')->label(__('الخزينة / البنك'))->options(Treasury::query()->where('is_active', true)->pluck('name', 'id'))->required(),
                Select::make('method')->label(__('طريقة الدفع'))->options(Translate::options(Payment::METHODS))->required(),
                DatePicker::make('date')->label(__('التاريخ'))->required(),
                TextInput::make('reference')->label(__('مرجع / رقم العملية')),
            ])
            ->action(function (Model $record, array $data) {
                $isSale = $record instanceof SalesInvoice;
                Payment::create($data + [
                    'direction' => $isSale ? 'in' : 'out',
                    'party_type' => $isSale ? Customer::class : Supplier::class,
                    'party_id' => $isSale ? $record->customer_id : $record->supplier_id,
                    'payable_type' => $record->getMorphClass(),
                    'payable_id' => $record->id,
                    'notes' => ($isSale ? __('تحصيل من فاتورة ') : __('سداد فاتورة ')).$record->number,
                ]);
                Notification::make()->title(__('تم تسجيل الدفعة'))->success()->send();
            });
    }

    public static function convert(string $resource): ?Action
    {
        $model = $resource::getModel();

        [$label, $callback, $target] = match ($model) {
            Quotation::class => [__('تحويل لفاتورة مبيعات'), fn ($r) => Posting::quotationToInvoice($r), SalesInvoiceResource::class],
            PurchaseOrder::class => [__('تحويل لفاتورة مشتريات'), fn ($r) => Posting::purchaseOrderToInvoice($r), PurchaseInvoiceResource::class],
            SalesInvoice::class => [__('إنشاء مرتجع'), fn ($r) => Posting::salesInvoiceToReturn($r), SalesReturnResource::class],
            PurchaseInvoice::class => [__('إنشاء مرتجع'), fn ($r) => Posting::purchaseInvoiceToReturn($r), PurchaseReturnResource::class],
            default => [null, null, null],
        };

        if (! $label) {
            return null;
        }

        return Action::make('convert')
            ->label($label)
            ->icon(Heroicon::OutlinedArrowPathRoundedSquare)
            ->color('info')
            ->requiresConfirmation()
            ->visible(fn (Model $record) => in_array($model, [Quotation::class, PurchaseOrder::class])
                ? ! in_array($record->status, ['invoiced', 'received', 'rejected'])
                : $record->isPosted())
            ->action(function (Model $record, $livewire) use ($callback, $target) {
                $new = static::run(fn () => $callback($record));
                if ($new) {
                    Notification::make()->title(__('تم إنشاء المستند ').$new->number.__(' كمسودة - راجعه ثم قم بترحيله'))->success()->send();
                    $livewire->redirect($target::getUrl('edit', ['record' => $new]));
                }
            });
    }

    public static function installmentPlan(string $resource): ?Action
    {
        if ($resource::getModel() !== SalesInvoice::class) {
            return null;
        }

        return Action::make('installmentPlan')
            ->label(__('تقسيط الفاتورة'))
            ->icon(Heroicon::OutlinedCalendarDays)
            ->color('primary')
            ->visible(fn (SalesInvoice $record) => Tenancy::company()?->hasFeature('installments') && $record->isPosted() && $record->customer_id && ! $record->installmentPlan && $record->remainingAmount() > 0)
            ->fillForm(fn (SalesInvoice $record) => [
                'total_amount' => $record->remainingAmount(),
                'down_payment' => 0,
                'installments_count' => 6,
                'interest_percent' => 0,
                'period' => 'monthly',
                'start_date' => now()->addMonth()->startOfMonth()->toDateString(),
            ])
            ->schema([
                TextInput::make('total_amount')->label(__('المبلغ المقسط'))->numeric()->disabled()->dehydrated(),
                TextInput::make('down_payment')->label(__('مقدم (يُسجَّل كسند قبض منفصل)'))->numeric()->disabled()->default(0)->dehydrated(),
                TextInput::make('installments_count')->label(__('عدد الأقساط'))->numeric()->integer()->minValue(1)->required(),
                TextInput::make('interest_percent')->label(__('نسبة الفائدة %'))->numeric(),
                Select::make('period')->label(__('الدورية'))->options(['monthly' => __('شهري'), 'weekly' => __('أسبوعي')])->required(),
                DatePicker::make('start_date')->label(__('تاريخ أول قسط'))->required(),
                TextInput::make('guarantor_name')->label(__('اسم الضامن')),
                TextInput::make('guarantor_phone')->label(__('هاتف الضامن')),
            ])
            ->action(function (SalesInvoice $record, array $data, $livewire) {
                $plan = InstallmentPlan::create($data + ['customer_id' => $record->customer_id, 'sales_invoice_id' => $record->id]);
                Notification::make()->title(__('تم إنشاء خطة التقسيط ').$plan->number)->success()->send();
                $livewire->redirect(InstallmentPlanResource::getUrl('view', ['record' => $plan]));
            });
    }

    public static function quotationStatus(string $resource): ?Action
    {
        if (! in_array($resource::getModel(), [Quotation::class, PurchaseOrder::class])) {
            return null;
        }

        return Action::make('changeStatus')
            ->label(__('تغيير الحالة'))
            ->icon(Heroicon::OutlinedTag)
            ->color('gray')
            ->visible(fn (Model $record) => ! in_array($record->status, ['invoiced', 'received']))
            ->schema([
                Select::make('status')->label(__('الحالة'))->required()->options([
                    'draft' => __('مسودة'), 'sent' => __('مُرسل'), 'accepted' => __('مقبول'), 'rejected' => __('مرفوض'),
                ]),
            ])
            ->fillForm(fn (Model $record) => ['status' => $record->status])
            ->action(fn (Model $record, array $data) => $record->update($data));
    }
}
