<?php

namespace App\Filament\Platform\Resources;

use App\Filament\Concerns\TranslatesResourceLabels;
use App\Filament\Platform\Resources\CompanyResource\Pages;
use App\Models\Company;
use App\Models\SalesInvoice;
use App\Support\Translate;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Tenants (subscribed companies). */
class CompanyResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = Company::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'شركة';

    protected static ?string $pluralModelLabel = 'الشركات المشتركة';

    protected static ?string $recordTitleAttribute = 'name';

    public const STATUS_COLORS = ['trial' => 'info', 'active' => 'success', 'suspended' => 'danger', 'cancelled' => 'gray'];

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('بيانات الشركة'))->columns(3)->columnSpanFull()->schema([
                TextInput::make('name')->label(__('اسم الشركة / المنشأة'))->required(),
                TextInput::make('email')->label(__('البريد الإلكتروني'))->email(),
                TextInput::make('phone')->label(__('الهاتف')),
                Textarea::make('notes')->label(__('ملاحظات'))->columnSpanFull(),
            ]),
            Section::make(__('الاشتراك'))->columns(3)->columnSpanFull()->schema([
                Select::make('plan_id')->label(__('الباقة'))->relationship('plan', 'name')->required()->preload(),
                Select::make('status')->label(__('الحالة'))->options(Translate::options(Company::STATUSES))->required()->default('trial'),
                Select::make('billing_cycle')->label(__('دورة الفوترة'))->options(['monthly' => __('شهري'), 'yearly' => __('سنوي')])->default('monthly'),
                DateTimePicker::make('trial_ends_at')->label(__('نهاية الفترة التجريبية'))->default(now()->addDays(14)),
                DateTimePicker::make('subscription_ends_at')->label(__('نهاية الاشتراك')),
            ]),
            Section::make(__('مدير الشركة'))->columns(3)->columnSpanFull()->visibleOn('create')->schema([
                TextInput::make('owner_name')->label(__('الاسم'))->required(),
                TextInput::make('owner_email')->label(__('البريد الإلكتروني'))->email()->required()->unique('users', 'email'),
                TextInput::make('owner_password')->label(__('كلمة المرور'))->password()->revealable()->required()->minLength(8),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('plan')->withCount('users'))
            ->columns([
                TextColumn::make('name')->label(__('الشركة'))->searchable()->sortable()->weight('bold')
                    ->description(fn (Company $record) => $record->email),
                TextColumn::make('plan.name')->label(__('الباقة'))->badge()
                    ->formatStateUsing(fn ($state, Company $record) => $record->plan?->displayName()),
                TextColumn::make('status')->label(__('الحالة'))->badge()
                    ->formatStateUsing(fn ($state) => __(Company::STATUSES[$state] ?? $state))
                    ->color(fn ($state) => self::STATUS_COLORS[$state] ?? 'gray'),
                TextColumn::make('ends_at')->label(__('ينتهي في'))
                    ->state(fn (Company $record) => $record->accessEndsAt()?->format('Y-m-d') ?? '-')
                    ->color(fn (Company $record) => $record->hasActiveSubscription() ? null : 'danger'),
                TextColumn::make('users_count')->label(__('المستخدمين'))->sortable(),
                TextColumn::make('invoices')->label(__('الفواتير'))
                    ->state(fn (Company $record) => SalesInvoice::query()->where('company_id', $record->id)->count()),
                TextColumn::make('phone')->label(__('الهاتف'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label(__('تاريخ التسجيل'))->date('Y-m-d')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('الحالة'))->options(Translate::options(Company::STATUSES)),
                SelectFilter::make('plan_id')->label(__('الباقة'))->relationship('plan', 'name'),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                ActionGroup::make([
                    Action::make('extend')->label(__('تجديد / تمديد الاشتراك'))->icon(Heroicon::OutlinedCalendarDays)->color('success')
                        ->schema([
                            Select::make('months')->label(__('المدة'))->required()->default(1)->options([
                                1 => __('شهر'), 3 => __('3 شهور'), 6 => __('6 شهور'), 12 => __('سنة'),
                            ]),
                            Select::make('plan_id')->label(__('الباقة'))->relationship('plan', 'name')->required(),
                        ])
                        ->fillForm(fn (Company $record) => ['months' => 1, 'plan_id' => $record->plan_id])
                        ->action(function (Company $record, array $data) {
                            $from = $record->subscription_ends_at && $record->subscription_ends_at->isFuture() ? $record->subscription_ends_at : now();
                            $record->update([
                                'status' => 'active',
                                'plan_id' => $data['plan_id'],
                                'billing_cycle' => (int) $data['months'] >= 12 ? 'yearly' : 'monthly',
                                'subscription_ends_at' => $from->copy()->addMonthsNoOverflow((int) $data['months']),
                            ]);
                            Notification::make()->title(__('تم تجديد الاشتراك حتى').' '.$record->subscription_ends_at->format('Y-m-d'))->success()->send();
                        }),
                    Action::make('suspend')->label(__('إيقاف'))->icon(Heroicon::OutlinedNoSymbol)->color('danger')->requiresConfirmation()
                        ->visible(fn (Company $record) => $record->status !== 'suspended')
                        ->action(fn (Company $record) => $record->update(['status' => 'suspended'])),
                    Action::make('activate')->label(__('تفعيل'))->icon(Heroicon::OutlinedCheckCircle)->color('success')->requiresConfirmation()
                        ->visible(fn (Company $record) => $record->status === 'suspended')
                        ->action(fn (Company $record) => $record->update(['status' => $record->subscription_ends_at ? 'active' : 'trial'])),
                    EditAction::make(),
                    DeleteAction::make()->modalDescription(__('سيتم حذف الشركة وكل بياناتها نهائياً ولا يمكن التراجع.')),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompanies::route('/'),
            'create' => Pages\CreateCompany::route('/create'),
            'edit' => Pages\EditCompany::route('/{record}/edit'),
        ];
    }
}
