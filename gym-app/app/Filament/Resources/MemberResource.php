<?php

namespace App\Filament\Resources;

use App\Filament\Actions\GymActions;
use App\Filament\Resources\MemberResource\Pages;
use App\Filament\Resources\MemberResource\RelationManagers;
use App\Models\Member;
use App\Models\Subscription;
use App\Support\Money;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MemberResource extends Resource
{
    protected static ?string $model = Member::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|\UnitEnum|null $navigationGroup = 'العضويات';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'مشترك';

    protected static ?string $pluralModelLabel = 'المشتركين';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'code', 'phone', 'national_id'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return ['الكود' => $record->code, 'الموبايل' => $record->phone];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('البيانات الأساسية')->columnSpanFull()->columns(3)->schema([
                FileUpload::make('photo')->label('الصورة')->image()->avatar()->imageEditor()->directory('members')
                    ->columnSpan(1),
                Grid::make(2)->columnSpan(2)->schema([
                    TextInput::make('name')->label('الاسم بالكامل')->required()->maxLength(255),
                    TextInput::make('code')->label('كود العضوية / الكارت')->unique(ignoreRecord: true)
                        ->helperText('يُولد تلقائياً إذا تُرك فارغاً'),
                    TextInput::make('phone')->label('الموبايل')->tel()->required()->unique(ignoreRecord: true),
                    TextInput::make('phone2')->label('موبايل آخر')->tel(),
                    Select::make('gender')->label('النوع')->options(['male' => 'ذكر', 'female' => 'أنثى'])->default('male')->required(),
                    DatePicker::make('birth_date')->label('تاريخ الميلاد')->maxDate(now()),
                ]),
                TextInput::make('national_id')->label('الرقم القومي')->length(14)->unique(ignoreRecord: true),
                TextInput::make('email')->label('البريد الإلكتروني')->email(),
                TextInput::make('address')->label('العنوان'),
                Select::make('trainer_id')->label('المدرب المسؤول')->relationship('trainer', 'name', fn (Builder $query) => $query->where('is_active', true))
                    ->searchable()->preload(),
                Select::make('referral_source')->label('عرفنا عن طريق')->options([
                    'facebook' => 'فيسبوك', 'instagram' => 'إنستجرام', 'tiktok' => 'تيك توك', 'friend' => 'صديق',
                    'walk_in' => 'مر بالمكان', 'other' => 'أخرى',
                ]),
                DatePicker::make('joined_at')->label('تاريخ الانضمام')->default(now()),
            ]),
            Section::make('البيانات الصحية والهدف')->columnSpanFull()->columns(3)->collapsible()->schema([
                TextInput::make('height')->label('الطول (سم)')->numeric(),
                TextInput::make('weight')->label('الوزن (كجم)')->numeric(),
                Select::make('goals')->label('الهدف')->options([
                    'weight_loss' => 'خسارة وزن', 'muscle_gain' => 'بناء عضلات', 'fitness' => 'لياقة عامة',
                    'rehab' => 'تأهيل / علاج طبيعي', 'other' => 'أخرى',
                ]),
                Textarea::make('medical_notes')->label('ملاحظات طبية (أمراض / إصابات)')->columnSpanFull(),
            ]),
            Section::make('الطوارئ والملاحظات')->columnSpanFull()->columns(2)->collapsible()->schema([
                TextInput::make('emergency_contact_name')->label('اسم شخص للطوارئ'),
                TextInput::make('emergency_contact_phone')->label('رقم الطوارئ')->tel(),
                Textarea::make('notes')->label('ملاحظات')->columnSpanFull(),
                Toggle::make('is_blocked')->label('حظر المشترك (منع الدخول)')->live(),
                TextInput::make('blocked_reason')->label('سبب الحظر')->visible(fn (Get $get) => $get('is_blocked')),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()->columns(4)->schema([
                ImageEntry::make('photo_url')->hiddenLabel()->circular()->imageSize(140),
                Grid::make(3)->columnSpan(3)->schema([
                    TextEntry::make('name')->label('الاسم')->weight('bold')->size('lg'),
                    TextEntry::make('code')->label('كود العضوية')->badge()->copyable(),
                    TextEntry::make('phone')->label('الموبايل')->url(fn (Member $r) => 'tel:'.$r->phone),
                    TextEntry::make('status')->label('حالة الاشتراك')->badge()
                        ->state(fn (Member $r) => $r->latestSubscription?->status ? Subscription::STATUSES[$r->latestSubscription->status] : 'بدون اشتراك')
                        ->color(fn (Member $r) => Subscription::STATUS_COLORS[$r->latestSubscription?->status] ?? 'gray'),
                    TextEntry::make('latestSubscription.plan.name')->label('الباقة الحالية')->placeholder('-'),
                    TextEntry::make('latestSubscription.end_date')->label('تاريخ الانتهاء')->date('Y-m-d')->placeholder('-')
                        ->helperText(fn (Member $r) => $r->latestSubscription ? 'متبقي '.$r->latestSubscription->days_left.' يوم' : null),
                    TextEntry::make('sessions')->label('الحصص')
                        ->state(fn (Member $r) => $r->latestSubscription?->sessions_total
                            ? $r->latestSubscription->sessions_used.' / '.$r->latestSubscription->sessions_total
                            : 'غير محدود'),
                    TextEntry::make('balance')->label('المتبقي عليه')->state(fn (Member $r) => Money::format($r->balance))
                        ->color(fn (Member $r) => $r->balance > 0 ? 'danger' : 'success')->weight('bold'),
                    TextEntry::make('attendances_count')->label('مرات الحضور')->state(fn (Member $r) => $r->attendances()->count()),
                ]),
            ]),
            Section::make('بيانات إضافية')->columnSpanFull()->columns(4)->collapsible()->collapsed()->schema([
                TextEntry::make('national_id')->label('الرقم القومي')->placeholder('-'),
                TextEntry::make('gender')->label('النوع')->formatStateUsing(fn ($state) => $state === 'female' ? 'أنثى' : 'ذكر'),
                TextEntry::make('age')->label('السن')->placeholder('-'),
                TextEntry::make('trainer.name')->label('المدرب')->placeholder('-'),
                TextEntry::make('joined_at')->label('تاريخ الانضمام')->date('Y-m-d'),
                TextEntry::make('phone2')->label('موبايل آخر')->placeholder('-'),
                TextEntry::make('emergency_contact_name')->label('الطوارئ')->placeholder('-')
                    ->helperText(fn (Member $r) => $r->emergency_contact_phone),
                TextEntry::make('address')->label('العنوان')->placeholder('-'),
                TextEntry::make('medical_notes')->label('ملاحظات طبية')->placeholder('-')->columnSpan(2)->color('danger'),
                TextEntry::make('notes')->label('ملاحظات')->placeholder('-')->columnSpan(2),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['latestSubscription.plan']))
            ->columns([
                ImageColumn::make('photo_url')->label('الصورة')->circular()->imageSize(40),
                TextColumn::make('code')->label('الكود')->searchable()->sortable()->badge()->color('gray'),
                TextColumn::make('name')->label('الاسم')->searchable()->sortable()->weight('bold')
                    ->description(fn (Member $r) => $r->is_blocked ? 'محظور' : null),
                TextColumn::make('phone')->label('الموبايل')->searchable(),
                TextColumn::make('national_id')->label('الرقم القومي')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('latestSubscription.plan.name')->label('الباقة')->placeholder('-'),
                TextColumn::make('latestSubscription.end_date')->label('ينتهي')->date('Y-m-d')->placeholder('-')
                    ->color(fn (Member $r) => $r->latestSubscription && $r->latestSubscription->days_left <= 7 ? 'danger' : null),
                TextColumn::make('latestSubscription.status')->label('الحالة')->badge()->placeholder('بدون اشتراك')
                    ->formatStateUsing(fn ($state) => Subscription::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => Subscription::STATUS_COLORS[$state] ?? 'gray'),
                TextColumn::make('trainer.name')->label('المدرب')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('gender')->label('النوع')->formatStateUsing(fn ($state) => $state === 'female' ? 'أنثى' : 'ذكر')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_blocked')->label('محظور')->boolean()->trueColor('danger')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('joined_at')->label('الانضمام')->date('Y-m-d')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('subscription_status')->label('حالة الاشتراك')
                    ->options(['active' => 'ساري', 'frozen' => 'مجمد', 'expired' => 'منتهي', 'expiring' => 'ينتهي خلال 7 أيام', 'none' => 'بدون اشتراك'])
                    ->query(function (Builder $query, array $data) {
                        $q = $query;
                        return match ($data['value'] ?? null) {
                            'active' => $q->whereHas('latestSubscription', fn ($s) => $s->where('status', 'active')),
                            'frozen' => $q->whereHas('latestSubscription', fn ($s) => $s->where('status', 'frozen')),
                            'expired' => $q->whereHas('latestSubscription', fn ($s) => $s->whereIn('status', ['expired', 'cancelled'])),
                            'expiring' => $q->whereHas('latestSubscription', fn ($s) => $s->expiringWithin(7)),
                            'none' => $q->doesntHave('subscriptions'),
                            default => $q,
                        };
                    }),
                SelectFilter::make('gender')->label('النوع')->options(['male' => 'ذكر', 'female' => 'أنثى']),
                SelectFilter::make('trainer_id')->label('المدرب')->relationship('trainer', 'name'),
                TernaryFilter::make('is_blocked')->label('محظور'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                GymActions::checkIn()->iconButton()->tooltip('دخول / خروج'),
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    GymActions::renew(),
                    GymActions::whatsapp(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make(), RestoreBulkAction::make()]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\SubscriptionsRelationManager::class,
            RelationManagers\PaymentsRelationManager::class,
            RelationManagers\AttendancesRelationManager::class,
            RelationManagers\MeasurementsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMembers::route('/'),
            'create' => Pages\CreateMember::route('/create'),
            'view' => Pages\ViewMember::route('/{record}'),
            'edit' => Pages\EditMember::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
