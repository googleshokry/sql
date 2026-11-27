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


    public function table(Table $table): Table
    {
        return $table
            ->heading(__('اشتراكات تنتهي قريباً'))
            ->query(fn () => Subscription::query()->with(['member', 'plan'])
                ->expiringWithin((int) Setting::get('expiry_warning_days'))->orderBy('end_date'))
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading(__('لا توجد اشتراكات تنتهي قريباً'))
            ->columns([
                TextColumn::make('member.name')->label(__('المشترك'))->weight('bold')
                    ->url(fn (Subscription $r) => MemberResource::getUrl('view', ['record' => $r->member_id])),
                TextColumn::make('member.phone')->label(__('الموبايل')),
                TextColumn::make('plan.name')->label(__('الباقة')),
                TextColumn::make('end_date')->label(__('ينتهي'))->date('Y-m-d'),
                TextColumn::make('days_left')->label(__('متبقي'))->suffix(__(' يوم'))->badge()->color('warning'),
            ])
            ->recordActions([
                GymActions::whatsapp(fn (Subscription $r) => $r->member),
                GymActions::renew(fn (Subscription $r) => $r->member),
            ]);
    }
}
