<?php

namespace App\Filament\Resources;

use App\Filament\Actions\GymActions;
use App\Filament\Forms\SubscriptionFields;
use App\Filament\Resources\SubscriptionResource\Pages;
use App\Models\Setting;
use App\Models\Subscription;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|\UnitEnum|null $navigationGroup = \App\Enums\NavGroup::Memberships;

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('اشتراك');
    }

    public static function getPluralModelLabel(): string
    {
        return __('الاشتراكات');
    }

    public static function getNavigationBadge(): ?string
    {
        $n = Subscription::expiringWithin((int) Setting::get('expiry_warning_days'))->count();

        return $n ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('اشتراكات تنتهي قريباً');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('member_id')->label(__('المشترك'))->relationship('member', 'name')
                ->getOptionLabelFromRecordUsing(fn ($r) => "{$r->name} ({$r->code} - {$r->phone})")
                ->searchable(['name', 'code', 'phone'])->required()->columnSpanFull(),
            ...SubscriptionFields::make(),
        ]);
    }

    public static function table(Table $table): Table
    {
        $cur = ' '.Setting::currency();

        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['member', 'plan'])->withSum('payments', 'amount'))
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('member.name')->label(__('المشترك'))->searchable()->weight('bold')
                    ->description(fn (Subscription $r) => $r->member?->code.' • '.$r->member?->phone)
                    ->url(fn (Subscription $r) => MemberResource::getUrl('view', ['record' => $r->member_id])),
                TextColumn::make('plan.name')->label(__('الباقة')),
                TextColumn::make('start_date')->label(__('من'))->date('Y-m-d')->sortable(),
                TextColumn::make('end_date')->label(__('إلى'))->date('Y-m-d')->sortable(),
                TextColumn::make('days_left')->label(__('متبقي'))->suffix(__(' يوم'))
                    ->color(fn ($state) => $state <= 7 ? 'danger' : null),
                TextColumn::make('sessions')->label(__('الحصص'))
                    ->state(fn (Subscription $r) => $r->sessions_total ? "{$r->sessions_used} / {$r->sessions_total}" : '∞'),
                TextColumn::make('total')->label(__('الإجمالي'))->numeric(2, locale: 'en')->suffix($cur)->sortable(),
                TextColumn::make('remaining_amount')->label(__('المتبقي'))->numeric(2, locale: 'en')->suffix($cur)
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('status')->label(__('الحالة'))->badge()
                    ->formatStateUsing(fn ($state) => Subscription::statuses()[$state] ?? $state)
                    ->color(fn ($state) => Subscription::STATUS_COLORS[$state] ?? 'gray'),
                TextColumn::make('trainer.name')->label(__('المدرب'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('creator.name')->label(__('بواسطة'))->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('الحالة'))->options(Subscription::statuses())->multiple(),
                SelectFilter::make('plan_id')->label(__('الباقة'))->relationship('plan', 'name'),
                SelectFilter::make('trainer_id')->label(__('المدرب'))->relationship('trainer', 'name'),
                Filter::make('expiring')->label(__('ينتهي خلال أيام التنبيه'))
                    ->query(fn (Builder $query) => $query->expiringWithin((int) Setting::get('expiry_warning_days'))),
                TernaryFilter::make('debt')->label(__('عليه مديونية'))
                    ->queries(
                        true: fn (Builder $query) => $query->where('status', '!=', 'cancelled')
                            ->whereRaw('total > (select coalesce(sum(amount),0) from payments where payments.subscription_id = subscriptions.id)'),
                        false: fn (Builder $query) => $query->whereRaw('total <= (select coalesce(sum(amount),0) from payments where payments.subscription_id = subscriptions.id)'),
                    ),
                Filter::make('dates')->schema([
                    DatePicker::make('from')->label(__('بدأ من')),
                    DatePicker::make('until')->label(__('بدأ حتى')),
                ])->query(fn (Builder $query, array $data) => $query
                    ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('start_date', '>=', $d))
                    ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('start_date', '<=', $d))),
            ])
            ->recordActions([
                GymActions::pay(),
                ActionGroup::make([
                    GymActions::renew(fn (Subscription $r) => $r->member),
                    GymActions::freeze(),
                    GymActions::unfreeze(),
                    GymActions::whatsapp(fn (Subscription $r) => $r->member),
                    GymActions::cancel(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptions::route('/'),
            'create' => Pages\CreateSubscription::route('/create'),
        ];
    }
}
