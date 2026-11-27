<?php

namespace App\Filament\Platform\Resources\Tenants;

use App\Filament\Platform\Resources\Tenants\Pages\ManageTenants;
use App\Models\Central\Payment;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Tenancy\TenantRegistrar;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** المطاعم المشتركة */
class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('مطعم');
    }

    public static function getPluralModelLabel(): string
    {
        return __('المطاعم');
    }

    public static function planOptions(): array
    {
        return Plan::orderBy('sort')->get()->mapWithKeys(fn ($p) => [$p->id => $p->localized_name])->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label(__('اسم المطعم'))->required()->maxLength(100),
            TextInput::make('slug')->label(__('كود المطعم'))->required()
                ->rules(fn (?Tenant $record) => TenantRegistrar::slugRules($record))
                ->disabledOn('edit')->dehydrated(fn (string $operation) => $operation === 'create')
                ->helperText(__('حروف إنجليزي صغيرة وأرقام وشرطة فقط')),
            TextInput::make('owner_name')->label(__('اسم صاحب المطعم'))->required(),
            TextInput::make('owner_email')->label(__('البريد'))->email()->required(),
            TextInput::make('owner_phone')->label(__('الموبايل'))->tel(),
            Select::make('plan_id')->label(__('الباقة'))->options(fn () => static::planOptions())->required(),
            TextInput::make('password')->label(__('كلمة مرور صاحب المطعم'))->password()->revealable()
                ->required()->minLength(8)->visibleOn('create'),
            Select::make('status')->label(__('الحالة'))->visibleOn('create')->required()->default('trial')->live()
                ->options(['trial' => __('فترة تجريبية'), 'active' => __('مشترك')]),
            TextInput::make('months')->label(__('مدة الاشتراك (شهور)'))->numeric()->default(1)->minValue(1)
                ->visible(fn ($get, string $operation) => $operation === 'create' && $get('status') === 'active'),
            \Filament\Forms\Components\Toggle::make('demo')->label(__('بيانات تجريبية (منيو وطاولات)'))->default(true)->visibleOn('create'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('plan'))
            ->columns([
                TextColumn::make('name')->label(__('المطعم'))->searchable(['name', 'slug', 'owner_email', 'owner_phone'])->weight('bold')
                    ->description(fn (Tenant $r) => $r->slug),
                TextColumn::make('plan.name')->label(__('الباقة'))
                    ->formatStateUsing(fn ($state, Tenant $r) => $r->plan?->localized_name),
                TextColumn::make('status')->label(__('الحالة'))->badge()
                    ->state(fn (Tenant $r) => $r->accessState())
                    ->formatStateUsing(fn (string $state) => Tenant::stateLabel($state))
                    ->color(fn (string $state) => Tenant::stateColor($state)),
                TextColumn::make('ends_at')->label(__('ينتهي'))
                    ->state(fn (Tenant $r) => $r->endsAt()?->format('Y-m-d'))
                    ->description(fn (Tenant $r) => $r->canOperate() ? __('متبقي :days يوم', ['days' => $r->daysLeft()]) : null),
                TextColumn::make('owner_name')->label(__('صاحب المطعم'))->description(fn (Tenant $r) => $r->owner_phone ?? $r->owner_email),
                TextColumn::make('created_at')->label(__('تاريخ التسجيل'))->date('Y-m-d')->sortable(),
            ])
            ->filters([
                SelectFilter::make('state')->label(__('الحالة'))
                    ->options(['trial' => __('فترة تجريبية'), 'active' => __('مشترك'), 'expired' => __('منتهي'), 'suspended' => __('موقوف')])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'trial' => $query->where('status', 'trial')->where('trial_ends_at', '>', now()),
                        'active' => $query->where('status', 'active')->where('subscription_ends_at', '>', now()),
                        'suspended' => $query->where('status', 'suspended'),
                        'expired' => $query->where(fn ($q) => $q
                            ->where(fn ($q) => $q->where('status', 'trial')->where('trial_ends_at', '<=', now()))
                            ->orWhere(fn ($q) => $q->where('status', 'active')->where('subscription_ends_at', '<=', now()))),
                        default => $query,
                    }),
                SelectFilter::make('plan_id')->label(__('الباقة'))->options(fn () => static::planOptions()),
            ])
            ->recordActions([
                Action::make('renew')->label(__('تجديد / تفعيل'))->icon(Heroicon::OutlinedCheckBadge)->color('success')
                    ->fillForm(fn (Tenant $r) => ['plan_id' => $r->plan_id, 'months' => 1, 'method' => 'cash',
                        'amount' => $r->plan?->priceFor(1)])
                    ->schema([
                        Select::make('plan_id')->label(__('الباقة'))->options(fn () => static::planOptions())->required()->live()
                            ->afterStateUpdated(fn ($state, $get, $set) => $set('amount', Plan::find($state)?->priceFor((int) $get('months')))),
                        Select::make('months')->label(__('المدة'))->required()->live()
                            ->options([1 => __('شهر'), 3 => __('3 شهور'), 6 => __('6 شهور'), 12 => __('سنة')])
                            ->afterStateUpdated(fn ($state, $get, $set) => $set('amount', Plan::find($get('plan_id'))?->priceFor((int) $state))),
                        TextInput::make('amount')->label(__('المبلغ المدفوع'))->numeric()->required()->minValue(0),
                        Select::make('method')->label(__('طريقة الدفع'))->required()
                            ->options(collect(['cash', 'instapay', 'wallet', 'bank'])->mapWithKeys(fn ($m) => [$m => Payment::methodLabel($m)])),
                        TextInput::make('reference')->label(__('رقم العملية / المرجع')),
                    ])
                    ->action(function (Tenant $r, array $data) {
                        $payment = $r->payments()->create([
                            'plan_id' => $data['plan_id'], 'months' => (int) $data['months'], 'amount' => $data['amount'],
                            'method' => $data['method'], 'reference' => $data['reference'] ?? null, 'status' => 'pending',
                            'notes' => __('تفعيل يدوي من إدارة المنصة'),
                        ]);
                        $payment->markPaid();
                        Notification::make()->success()->title(__('تم تفعيل الاشتراك حتى :date', ['date' => $r->fresh()->subscription_ends_at->format('Y-m-d')]))->send();
                    }),
                ActionGroup::make([
                    Action::make('open')->label(__('فتح لوحة المطعم'))->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                        ->url(fn (Tenant $r) => $r->url('login'))->openUrlInNewTab(),
                    EditAction::make(),
                    Action::make('extendTrial')->label(__('مد الفترة التجريبية'))->icon(Heroicon::OutlinedClock)
                        ->visible(fn (Tenant $r) => $r->status === 'trial')
                        ->schema([TextInput::make('days')->label(__('عدد الأيام'))->numeric()->required()->default(7)->minValue(1)])
                        ->action(fn (Tenant $r, array $data) => $r->update([
                            'trial_ends_at' => ($r->trial_ends_at?->isFuture() ? $r->trial_ends_at : now())->copy()->addDays((int) $data['days']),
                        ])),
                    Action::make('suspend')->label(__('إيقاف'))->icon(Heroicon::OutlinedPauseCircle)->color('danger')
                        ->visible(fn (Tenant $r) => $r->status !== 'suspended')
                        ->schema([TextInput::make('reason')->label(__('السبب'))])
                        ->action(fn (Tenant $r, array $data) => $r->update(['status' => 'suspended', 'suspended_reason' => $data['reason'] ?? null])),
                    Action::make('unsuspend')->label(__('إلغاء الإيقاف'))->icon(Heroicon::OutlinedPlayCircle)->color('success')
                        ->visible(fn (Tenant $r) => $r->status === 'suspended')->requiresConfirmation()
                        ->action(fn (Tenant $r) => $r->update([
                            'status' => $r->subscription_ends_at ? 'active' : 'trial', 'suspended_reason' => null,
                        ])),
                    Action::make('delete')->label(__('حذف المطعم نهائياً'))->icon(Heroicon::OutlinedTrash)->color('danger')
                        ->modalDescription(__('هيتم حذف قاعدة بيانات المطعم بالكامل (الطلبات والمنيو والمستخدمين). العملية لا يمكن التراجع عنها.'))
                        ->schema([TextInput::make('confirm')->label(fn (Tenant $r) => __('اكتب كود المطعم للتأكيد: :slug', ['slug' => $r->slug]))->required()])
                        ->action(function (Tenant $r, array $data) {
                            if (($data['confirm'] ?? '') !== $r->slug) {
                                Notification::make()->danger()->title(__('الكود غير مطابق'))->send();

                                return;
                            }
                            app(TenantRegistrar::class)->delete($r);
                            Notification::make()->success()->title(__('تم حذف المطعم'))->send();
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageTenants::route('/')];
    }
}
