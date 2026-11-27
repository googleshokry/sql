<?php

namespace App\Filament\Resources\MemberResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MeasurementsRelationManager extends RelationManager
{
    protected static string $relationship = 'measurements';

    protected static ?string $title = 'القياسات والمتابعة';

    protected static ?string $modelLabel = 'قياس';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        $n = fn (string $name, string $label) => TextInput::make($name)->label($label)->numeric();

        return $schema->columns(3)->components([
            DatePicker::make('measured_at')->label('التاريخ')->default(now())->required(),
            $n('weight', 'الوزن (كجم)')->default(fn () => $this->getOwnerRecord()->weight),
            $n('height', 'الطول (سم)')->default(fn () => $this->getOwnerRecord()->height),
            $n('body_fat', 'نسبة الدهون %'),
            $n('muscle_mass', 'الكتلة العضلية (كجم)'),
            $n('chest', 'الصدر (سم)'),
            $n('waist', 'الوسط (سم)'),
            $n('hips', 'الأرداف (سم)'),
            $n('arms', 'الذراع (سم)'),
            $n('thighs', 'الفخذ (سم)'),
            Textarea::make('notes')->label('ملاحظات / برنامج')->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('measured_at')->label('التاريخ')->date('Y-m-d')->sortable(),
                TextColumn::make('weight')->label('الوزن'),
                TextColumn::make('bmi')->label('BMI'),
                TextColumn::make('body_fat')->label('دهون %'),
                TextColumn::make('muscle_mass')->label('عضلات'),
                TextColumn::make('chest')->label('صدر'),
                TextColumn::make('waist')->label('وسط'),
                TextColumn::make('arms')->label('ذراع'),
                TextColumn::make('notes')->label('ملاحظات')->limit(30),
            ])
            ->headerActions([
                CreateAction::make()->after(fn ($record) => $this->getOwnerRecord()->update(array_filter([
                    'weight' => $record->weight, 'height' => $record->height,
                ]))),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
