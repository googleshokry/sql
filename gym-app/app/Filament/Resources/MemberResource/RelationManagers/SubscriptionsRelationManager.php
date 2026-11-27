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

    protected static ?string $title = 'الاشتراكات';

    protected static ?string $modelLabel = 'اشتراك';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            DatePicker::make('start_date')->label('البداية')->required(),
            DatePicker::make('end_date')->label('النهاية')->required(),
            TextInput::make('price')->label('السعر')->numeric()->required(),
            TextInput::make('discount')->label('الخصم')->numeric(),
            TextInput::make('sessions_total')->label('عدد الحصص')->numeric(),
            TextInput::make('sessions_used')->label('الحصص المستخدمة')->numeric(),
            TextInput::make('freeze_days_allowed')->label('أيام التجميد المسموحة')->numeric(),
            Textarea::make('notes')->label('ملاحظات')->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        $cur = ' '.Setting::currency();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('plan')->withSum('payments', 'amount'))
            ->columns([
                TextColumn::make('plan.name')->label('الباقة')->weight('bold'),
                TextColumn::make('start_date')->label('من')->date('Y-m-d'),
                TextColumn::make('end_date')->label('إلى')->date('Y-m-d'),
                TextColumn::make('days_left')->label('متبقي')->suffix(' يوم'),
                TextColumn::make('sessions')->label('الحصص')
                    ->state(fn (Subscription $r) => $r->sessions_total ? "{$r->sessions_used} / {$r->sessions_total}" : 'غير محدود'),
                TextColumn::make('total')->label('الإجمالي')->numeric(2, locale: 'en')->suffix($cur),
                TextColumn::make('paid_amount')->label('المدفوع')->numeric(2, locale: 'en')->suffix($cur)->color('success'),
                TextColumn::make('remaining_amount')->label('المتبقي')->numeric(2, locale: 'en')->suffix($cur)
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('status')->label('الحالة')->badge()
                    ->formatStateUsing(fn ($state) => Subscription::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => Subscription::STATUS_COLORS[$state] ?? 'gray'),
                TextColumn::make('freeze')->label('التجميد')
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
