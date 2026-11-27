<?php

namespace App\Filament\Resources\MemberResource\RelationManagers;

use App\Filament\Actions\GymActions;
use App\Models\Setting;
use App\Models\Subscription;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SubscriptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'subscriptions';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('الاشتراكات');
    }

    protected static function getModelLabel(): ?string
    {
        return __('اشتراك');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            DatePicker::make('start_date')->label(__('البداية'))->required(),
            DatePicker::make('end_date')->label(__('النهاية'))->required(),
            TextInput::make('price')->label(__('السعر'))->numeric()->required(),
            TextInput::make('discount')->label(__('الخصم'))->numeric(),
            TextInput::make('sessions_total')->label(__('عدد الحصص'))->numeric(),
            TextInput::make('sessions_used')->label(__('الحصص المستخدمة'))->numeric(),
            TextInput::make('freeze_days_allowed')->label(__('أيام التجميد المسموحة'))->numeric(),
            Textarea::make('notes')->label(__('ملاحظات'))->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        $cur = ' '.Setting::currency();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('plan')->withSum('payments', 'amount'))
            ->columns([
                TextColumn::make('plan.name')->label(__('الباقة'))->weight('bold'),
                TextColumn::make('start_date')->label(__('من'))->date('Y-m-d'),
                TextColumn::make('end_date')->label(__('إلى'))->date('Y-m-d'),
                TextColumn::make('days_left')->label(__('متبقي'))->suffix(__(' يوم')),
                TextColumn::make('sessions')->label(__('الحصص'))
                    ->state(fn (Subscription $r) => $r->sessions_total ? "{$r->sessions_used} / {$r->sessions_total}" : __('غير محدود')),
                TextColumn::make('total')->label(__('الإجمالي'))->numeric(2, locale: 'en')->suffix($cur),
                TextColumn::make('paid_amount')->label(__('المدفوع'))->numeric(2, locale: 'en')->suffix($cur)->color('success'),
                TextColumn::make('remaining_amount')->label(__('المتبقي'))->numeric(2, locale: 'en')->suffix($cur)
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('status')->label(__('الحالة'))->badge()
                    ->formatStateUsing(fn ($state) => Subscription::statuses()[$state] ?? $state)
                    ->color(fn ($state) => Subscription::STATUS_COLORS[$state] ?? 'gray'),
                TextColumn::make('freeze')->label(__('التجميد'))
                    ->state(fn (Subscription $r) => $r->freeze_days_used.' / '.$r->freeze_days_allowed),
            ])
            ->headerActions([GymActions::renew(fn () => $this->getOwnerRecord(), 'newSubscription')])
            ->recordActions([
                GymActions::pay(),
                ActionGroup::make([
                    GymActions::freeze(),
                    GymActions::unfreeze(),
                    EditAction::make()->visible(fn () => auth()->user()->isManager()),
                    GymActions::cancel(),
                ]),
            ]);
    }
}
