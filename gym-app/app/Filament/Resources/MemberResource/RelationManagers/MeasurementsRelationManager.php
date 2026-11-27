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

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('القياسات والمتابعة');
    }

    protected static function getModelLabel(): ?string
    {
        return __('قياس');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        $n = fn (string $name, string $label) => TextInput::make($name)->label($label)->numeric();

        return $schema->columns(3)->components([
            DatePicker::make('measured_at')->label(__('التاريخ'))->default(now())->required(),
            $n('weight', __('الوزن (كجم)'))->default(fn () => $this->getOwnerRecord()->weight),
            $n('height', __('الطول (سم)'))->default(fn () => $this->getOwnerRecord()->height),
            $n('body_fat', __('نسبة الدهون %')),
            $n('muscle_mass', __('الكتلة العضلية (كجم)')),
            $n('chest', __('الصدر (سم)')),
            $n('waist', __('الوسط (سم)')),
            $n('hips', __('الأرداف (سم)')),
            $n('arms', __('الذراع (سم)')),
            $n('thighs', __('الفخذ (سم)')),
            Textarea::make('notes')->label(__('ملاحظات / برنامج'))->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('measured_at')->label(__('التاريخ'))->date('Y-m-d')->sortable(),
                TextColumn::make('weight')->label(__('الوزن')),
                TextColumn::make('bmi')->label('BMI'),
                TextColumn::make('body_fat')->label(__('دهون %')),
                TextColumn::make('muscle_mass')->label(__('عضلات')),
                TextColumn::make('chest')->label(__('صدر')),
                TextColumn::make('waist')->label(__('وسط')),
                TextColumn::make('arms')->label(__('ذراع')),
                TextColumn::make('notes')->label(__('ملاحظات'))->limit(30),
            ])
            ->headerActions([
                CreateAction::make()->after(fn ($record) => $this->getOwnerRecord()->update(array_filter([
                    'weight' => $record->weight, 'height' => $record->height,
                ]))),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
