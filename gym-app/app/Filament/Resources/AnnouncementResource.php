<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ManagerOnly;
use App\Filament\Resources\AnnouncementResource\Pages;
use App\Models\Announcement;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class AnnouncementResource extends Resource
{
    use ManagerOnly;

    protected static ?string $model = Announcement::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|\UnitEnum|null $navigationGroup = \App\Enums\NavGroup::Memberships;

    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return __('إعلان');
    }

    public static function getPluralModelLabel(): string
    {
        return __('إعلانات التطبيق');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->columnSpanFull()->schema([
                TextInput::make('title')->label(__('العنوان (عربي)'))->required(),
                TextInput::make('title_en')->label(__('العنوان (إنجليزي)')),
                Textarea::make('body')->label(__('المحتوى (عربي)'))->rows(4),
                Textarea::make('body_en')->label(__('المحتوى (إنجليزي)'))->rows(4),
                DateTimePicker::make('published_at')->label(__('يظهر من'))->default(now()),
                DateTimePicker::make('expires_at')->label(__('يختفي في')),
                FileUpload::make('image')->label(__('الصورة'))->image()->directory('announcements'),
                Toggle::make('is_active')->label(__('مفعل'))->default(true)->inline(false),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                ImageColumn::make('image')->label(__('الصورة')),
                TextColumn::make('title')->label(__('العنوان'))->searchable()
                    ->description(fn (Announcement $r) => $r->title_en),
                TextColumn::make('published_at')->label(__('يظهر من'))->dateTime('Y-m-d H:i'),
                TextColumn::make('expires_at')->label(__('يختفي في'))->dateTime('Y-m-d H:i')->placeholder('-'),
                ToggleColumn::make('is_active')->label(__('مفعل')),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageAnnouncements::route('/')];
    }
}
