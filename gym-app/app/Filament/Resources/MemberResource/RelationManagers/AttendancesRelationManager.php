<?php

namespace App\Filament\Resources\MemberResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AttendancesRelationManager extends RelationManager
{
    protected static string $relationship = 'attendances';

    protected static ?string $title = 'سجل الحضور';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('check_in_at')->label('اليوم')->date('l Y-m-d'),
                TextColumn::make('check_in_at_time')->label('الدخول')->state(fn ($record) => $record->check_in_at->format('h:i A')),
                TextColumn::make('check_out_at')->label('الخروج')->time('h:i A')->placeholder('داخل الجيم')->badge()
                    ->color(fn ($state) => $state ? 'gray' : 'success'),
                TextColumn::make('duration')->label('المدة')->placeholder('-'),
                TextColumn::make('is_guest')->label('ضيف')->formatStateUsing(fn ($state, $record) => $state ? 'ضيف: '.$record->guest_name : '')
                    ->placeholder(''),
                TextColumn::make('recorder.name')->label('سجله'),
            ]);
    }
}
