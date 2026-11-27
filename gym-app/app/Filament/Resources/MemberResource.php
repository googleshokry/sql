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

    protected static string|\UnitEnum|null $navigationGroup = \App\Enums\NavGroup::Memberships;

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('مشترك');
    }

    public static function getPluralModelLabel(): string
    {
        return __('المشتركين');
    }

    protected static ?string $recordTitleAttribute = 'name';

    /** Hide "create" once the gym reaches its plan's member limit. */
    public static function canCreate(): bool
    {
        $gym = \App\Support\Tenancy::gym();

        return ! $gym || $gym->withinLimit('max_members', Member::count());
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'code', 'phone', 'national_id'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [__('الكود') => $record->code, __('الموبايل') => $record->phone];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('البيانات الأساسية'))->columnSpanFull()->columns(3)->schema([
                FileUpload::make('photo')->label(__('الصورة'))->image()->avatar()->imageEditor()->directory('members')
                    ->columnSpan(1),
                Grid::make(2)->columnSpan(2)->schema([
                    TextInput::make('name')->label(__('الاسم بالكامل'))->required()->maxLength(255),
                    TextInput::make('code')->label(__('كود العضوية / الكارت'))->scopedUnique(ignoreRecord: true)
                        ->helperText(__('يُولد تلقائياً إذا تُرك فارغاً')),
                    TextInput::make('phone')->label(__('الموبايل'))->tel()->required()->scopedUnique(ignoreRecord: true),
                    TextInput::make('phone2')->label(__('موبايل آخر'))->tel(),
                    Select::make('gender')->label(__('النوع'))->options(['male' => __('ذكر'), 'female' => __('أنثى')])->default('male')->required(),
                    DatePicker::make('birth_date')->label(__('تاريخ الميلاد'))->maxDate(now()),
                ]),
                TextInput::make('national_id')->label(__('الرقم القومي'))->length(14)->scopedUnique(ignoreRecord: true),
                TextInput::make('email')->label(__('البريد الإلكتروني'))->email(),
                TextInput::make('address')->label(__('العنوان')),
                Select::make('trainer_id')->label(__('المدرب المسؤول'))->relationship('trainer', 'name', fn (Builder $query) => $query->where('is_active', true))
                    ->searchable()->preload(),
                Select::make('referral_source')->label(__('عرفنا عن طريق'))->options([
                    'facebook' => __('فيسبوك'), 'instagram' => __('إنستجرام'), 'tiktok' => __('تيك توك'), 'friend' => __('صديق'),
                    'walk_in' => __('مر بالمكان'), 'other' => __('أخرى'),
                ]),
                DatePicker::make('joined_at')->label(__('تاريخ الانضمام'))->default(now()),
            ]),
            Section::make(__('البيانات الصحية والهدف'))->columnSpanFull()->columns(3)->collapsible()->schema([
                TextInput::make('height')->label(__('الطول (سم)'))->numeric(),
                TextInput::make('weight')->label(__('الوزن (كجم)'))->numeric(),
                Select::make('goals')->label(__('الهدف'))->options([
                    'weight_loss' => __('خسارة وزن'), 'muscle_gain' => __('بناء عضلات'), 'fitness' => __('لياقة عامة'),
                    'rehab' => __('تأهيل / علاج طبيعي'), 'other' => __('أخرى'),
                ]),
                Textarea::make('medical_notes')->label(__('ملاحظات طبية (أمراض / إصابات)'))->columnSpanFull(),
            ]),
            Section::make(__('تطبيق الموبايل'))->columnSpanFull()->columns(2)->collapsible()->schema([
                TextInput::make('password')->label(__('كلمة مرور التطبيق'))->password()->revealable()
                    ->minLength(4)->dehydrated(fn (?string $state) => filled($state))
                    ->helperText(__('يدخل المشترك للتطبيق بالموبايل أو كود العضوية وهذه الكلمة. اتركها فارغة للإبقاء على الحالية.')),
                Select::make('locale')->label(__('لغة المشترك'))->options(['ar' => 'العربية', 'en' => 'English'])->default('ar'),
            ]),
            Section::make(__('الطوارئ والملاحظات'))->columnSpanFull()->columns(2)->collapsible()->schema([
                TextInput::make('emergency_contact_name')->label(__('اسم شخص للطوارئ')),
                TextInput::make('emergency_contact_phone')->label(__('رقم الطوارئ'))->tel(),
                Textarea::make('notes')->label(__('ملاحظات'))->columnSpanFull(),
                Toggle::make('is_blocked')->label(__('حظر المشترك (منع الدخول)'))->live(),
                TextInput::make('blocked_reason')->label(__('سبب الحظر'))->visible(fn (Get $get) => $get('is_blocked')),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()->columns(4)->schema([
                ImageEntry::make('photo_url')->hiddenLabel()->circular()->imageSize(140),
                Grid::make(3)->columnSpan(3)->schema([
                    TextEntry::make('name')->label(__('الاسم'))->weight('bold')->size('lg'),
                    TextEntry::make('code')->label(__('كود العضوية'))->badge()->copyable(),
                    TextEntry::make('phone')->label(__('الموبايل'))->url(fn (Member $r) => 'tel:'.$r->phone),
                    TextEntry::make('status')->label(__('حالة الاشتراك'))->badge()
                        ->state(fn (Member $r) => $r->latestSubscription?->status ? Subscription::statuses()[$r->latestSubscription->status] : __('بدون اشتراك'))
                        ->color(fn (Member $r) => Subscription::STATUS_COLORS[$r->latestSubscription?->status] ?? 'gray'),
                    TextEntry::make('latestSubscription.plan.name')->label(__('الباقة الحالية'))->placeholder('-'),
                    TextEntry::make('latestSubscription.end_date')->label(__('تاريخ الانتهاء'))->date('Y-m-d')->placeholder('-')
                        ->helperText(fn (Member $r) => $r->latestSubscription ? __('متبقي :days يوم', ['days' => $r->latestSubscription->days_left]) : null),
                    TextEntry::make('sessions')->label(__('الحصص'))
                        ->state(fn (Member $r) => $r->latestSubscription?->sessions_total
                            ? $r->latestSubscription->sessions_used.' / '.$r->latestSubscription->sessions_total
                            : __('غير محدود')),
                    TextEntry::make('balance')->label(__('المتبقي عليه'))->state(fn (Member $r) => Money::format($r->balance))
                        ->color(fn (Member $r) => $r->balance > 0 ? 'danger' : 'success')->weight('bold'),
                    TextEntry::make('attendances_count')->label(__('مرات الحضور'))->state(fn (Member $r) => $r->attendances()->count()),
                ]),
            ]),
            Section::make(__('بيانات إضافية'))->columnSpanFull()->columns(4)->collapsible()->collapsed()->schema([
                TextEntry::make('national_id')->label(__('الرقم القومي'))->placeholder('-'),
                TextEntry::make('gender')->label(__('النوع'))->formatStateUsing(fn ($state) => $state === 'female' ? __('أنثى') : __('ذكر')),
                TextEntry::make('age')->label(__('السن'))->placeholder('-'),
                TextEntry::make('trainer.name')->label(__('المدرب'))->placeholder('-'),
                TextEntry::make('joined_at')->label(__('تاريخ الانضمام'))->date('Y-m-d'),
                TextEntry::make('phone2')->label(__('موبايل آخر'))->placeholder('-'),
                TextEntry::make('emergency_contact_name')->label(__('الطوارئ'))->placeholder('-')
                    ->helperText(fn (Member $r) => $r->emergency_contact_phone),
                TextEntry::make('address')->label(__('العنوان'))->placeholder('-'),
                TextEntry::make('medical_notes')->label(__('ملاحظات طبية'))->placeholder('-')->columnSpan(2)->color('danger'),
                TextEntry::make('notes')->label(__('ملاحظات'))->placeholder('-')->columnSpan(2),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['latestSubscription.plan']))
            ->columns([
                ImageColumn::make('photo_url')->label(__('الصورة'))->circular()->imageSize(40),
                TextColumn::make('code')->label(__('الكود'))->searchable()->sortable()->badge()->color('gray'),
                TextColumn::make('name')->label(__('الاسم'))->searchable()->sortable()->weight('bold')
                    ->description(fn (Member $r) => $r->is_blocked ? __('محظور') : null),
                TextColumn::make('phone')->label(__('الموبايل'))->searchable(),
                TextColumn::make('national_id')->label(__('الرقم القومي'))->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('latestSubscription.plan.name')->label(__('الباقة'))->placeholder('-'),
                TextColumn::make('latestSubscription.end_date')->label(__('ينتهي'))->date('Y-m-d')->placeholder('-')
                    ->color(fn (Member $r) => $r->latestSubscription && $r->latestSubscription->days_left <= 7 ? 'danger' : null),
                TextColumn::make('latestSubscription.status')->label(__('الحالة'))->badge()->placeholder(__('بدون اشتراك'))
                    ->formatStateUsing(fn ($state) => Subscription::statuses()[$state] ?? $state)
                    ->color(fn ($state) => Subscription::STATUS_COLORS[$state] ?? 'gray'),
                TextColumn::make('trainer.name')->label(__('المدرب'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('gender')->label(__('النوع'))->formatStateUsing(fn ($state) => $state === 'female' ? __('أنثى') : __('ذكر'))
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_blocked')->label(__('محظور'))->boolean()->trueColor('danger')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('joined_at')->label(__('الانضمام'))->date('Y-m-d')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('subscription_status')->label(__('حالة الاشتراك'))
                    ->options(['active' => __('ساري'), 'frozen' => __('مجمد'), 'expired' => __('منتهي'), 'expiring' => __('ينتهي خلال 7 أيام'), 'none' => __('بدون اشتراك')])
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
                SelectFilter::make('gender')->label(__('النوع'))->options(['male' => __('ذكر'), 'female' => __('أنثى')]),
                SelectFilter::make('trainer_id')->label(__('المدرب'))->relationship('trainer', 'name'),
                TernaryFilter::make('is_blocked')->label(__('محظور')),
                TrashedFilter::make(),
            ])
            ->recordActions([
                GymActions::checkIn()->iconButton()->tooltip(__('دخول / خروج')),
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
