<?php

namespace App\Filament\Widgets;

use App\Filament\Actions\GymActions;
use App\Filament\Resources\MemberResource;
use App\Models\Setting;
use App\Models\Subscription;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class ExpiringSubscriptions extends TableWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'اشتراكات تنتهي قريباً';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Subscription::query()->with(['member', 'plan'])
                ->expiringWithin((int) Setting::get('expiry_warning_days'))->orderBy('end_date'))
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('لا توجد اشتراكات تنتهي قريباً')
            ->columns([
                TextColumn::make('member.name')->label('المشترك')->weight('bold')
                    ->url(fn (Subscription $r) => MemberResource::getUrl('view', ['record' => $r->member_id])),
                TextColumn::make('member.phone')->label('الموبايل'),
                TextColumn::make('plan.name')->label('الباقة'),
                TextColumn::make('end_date')->label('ينتهي')->date('Y-m-d'),
                TextColumn::make('days_left')->label('متبقي')->suffix(' يوم')->badge()->color('warning'),
            ])
            ->recordActions([
                GymActions::whatsapp(fn (Subscription $r) => $r->member),
                GymActions::renew(fn (Subscription $r) => $r->member),
            ]);
    }
}
