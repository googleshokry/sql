<?php

namespace App\Filament\Resources\MemberResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AttendancesRelationManager extends RelationManager
{
    protected static string $relationship = 'attendances';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('سجل الحضور');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('check_in_at')->label(__('اليوم'))->date('l Y-m-d'),
                TextColumn::make('check_in_at_time')->label(__('الدخول'))->state(fn ($record) => $record->check_in_at->format('h:i A')),
                TextColumn::make('check_out_at')->label(__('الخروج'))->time('h:i A')->placeholder(__('داخل الجيم'))->badge()
                    ->color(fn ($state) => $state ? 'gray' : 'success'),
                TextColumn::make('duration')->label(__('المدة'))->placeholder('-'),
                TextColumn::make('is_guest')->label(__('ضيف'))->formatStateUsing(fn ($state, $record) => $state ? __('ضيف: :name', ['name' => $record->guest_name]) : '')
                    ->placeholder(''),
                TextColumn::make('recorder.name')->label(__('سجله')),
            ]);
    }
}
