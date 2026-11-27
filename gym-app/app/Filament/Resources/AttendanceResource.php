<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttendanceResource\Pages;
use App\Models\Attendance;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AttendanceResource extends Resource
{
    protected static ?string $model = Attendance::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'الحضور والانصراف';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'سجل حضور';

    protected static ?string $pluralModelLabel = 'سجل الحضور';

    public static function getNavigationBadge(): ?string
    {
        $n = Attendance::whereNull('check_out_at')->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'متواجدون الآن';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->columnSpanFull()->schema([
                Select::make('member_id')->label('المشترك')->relationship('member', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($r) => "{$r->name} ({$r->code})")
                    ->searchable(['name', 'code', 'phone'])->required(),
                Toggle::make('is_guest')->label('دخول ضيف (دعوة)')->live()->inline(false),
                TextInput::make('guest_name')->label('اسم الضيف')->visible(fn (Get $get) => $get('is_guest')),
                DateTimePicker::make('check_in_at')->label('وقت الدخول')->default(now())->required(),
                DateTimePicker::make('check_out_at')->label('وقت الخروج'),
                TextInput::make('notes')->label('ملاحظات')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('check_in_at', 'desc')
            ->poll('30s')
            ->columns([
                TextColumn::make('member.code')->label('الكود')->badge()->color('gray')->searchable(),
                TextColumn::make('member.name')->label('المشترك')->searchable()->weight('bold')
                    ->description(fn (Attendance $r) => $r->is_guest ? 'ضيف: '.$r->guest_name : null),
                TextColumn::make('check_in_at')->label('الدخول')->dateTime('Y-m-d h:i A')->sortable(),
                TextColumn::make('check_out_at')->label('الخروج')->dateTime('h:i A')->placeholder('داخل الجيم')->badge()
                    ->color(fn ($state) => $state ? 'gray' : 'success'),
                TextColumn::make('duration')->label('المدة')->placeholder('-'),
                TextColumn::make('subscription.plan.name')->label('الباقة')->placeholder('-')->toggleable(),
                TextColumn::make('recorder.name')->label('سجله')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('notes')->label('ملاحظات')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('inside')->label('المتواجدون الآن')->query(fn (Builder $query) => $query->whereNull('check_out_at')),
                Filter::make('today')->label('اليوم')->query(fn (Builder $query) => $query->whereDate('check_in_at', today()))->default(),
                TernaryFilter::make('is_guest')->label('ضيوف'),
                Filter::make('date')->schema([
                    DatePicker::make('from')->label('من'),
                    DatePicker::make('until')->label('إلى'),
                ])->query(fn (Builder $query, array $data) => $query
                    ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('check_in_at', '>=', $d))
                    ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('check_in_at', '<=', $d))),
            ])
            ->recordActions([
                Action::make('checkout')->label('تسجيل خروج')->icon(Heroicon::OutlinedArrowRightStartOnRectangle)
                    ->visible(fn (Attendance $r) => ! $r->check_out_at)
                    ->action(fn (Attendance $r) => $r->update(['check_out_at' => now()])),
                EditAction::make()->visible(fn () => auth()->user()->isManager()),
                DeleteAction::make()->visible(fn () => auth()->user()->isManager()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageAttendances::route('/')];
    }
}
