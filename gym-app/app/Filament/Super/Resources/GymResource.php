<?php

namespace App\Filament\Super\Resources;

use App\Filament\Super\Resources\GymResource\Pages;
use App\Models\Gym;
use App\Models\GymInvoice;
use App\Models\SaasPlan;
use App\Models\User;
use App\Services\GymOnboarding;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GymResource extends Resource
{
    protected static ?string $model = Gym::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('جيم');
    }

    public static function getPluralModelLabel(): string
    {
        return __('الجيمات (العملاء)');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->columnSpanFull()->schema([
                TextInput::make('name')->label(__('اسم الجيم'))->required(),
                TextInput::make('slug')->label(__('كود الجيم'))->required()->alphaDash()->unique(ignoreRecord: true),
                TextInput::make('phone')->label(__('رقم التليفون')),
                TextInput::make('email')->label(__('البريد'))->email(),
                Select::make('saas_plan_id')->label(__('الباقة'))->relationship('saasPlan', 'name'),
                Select::make('billing_cycle')->label(__('دورة الفوترة'))->options(['monthly' => __('شهري'), 'yearly' => __('سنوي')]),
                Select::make('status')->label(__('الحالة'))->options(Gym::statuses())->required(),
                TextInput::make('suspended_reason')->label(__('سبب الإيقاف')),
                DateTimePicker::make('trial_ends_at')->label(__('نهاية التجربة')),
                DateTimePicker::make('subscription_ends_at')->label(__('نهاية الاشتراك')),
                Select::make('owner_id')->label(__('المالك'))->relationship('owner', 'email')->searchable()
                    ->visibleOn('edit'),
                TextInput::make('owner_email')->label(__('بريد المالك'))->email()->required()->visibleOn('create')->dehydrated(false),
                TextInput::make('owner_name')->label(__('اسم المالك'))->required()->visibleOn('create')->dehydrated(false),
                TextInput::make('owner_password')->label(__('كلمة مرور المالك'))->password()->visibleOn('create')->dehydrated(false)
                    ->helperText(__('مطلوبة فقط لو المالك مستخدم جديد')),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount(['members', 'users'])->with(['saasPlan', 'owner']))
            ->columns([
                TextColumn::make('name')->label(__('الجيم'))->searchable()->weight('bold')
                    ->description(fn (Gym $r) => $r->slug),
                TextColumn::make('owner.email')->label(__('المالك'))->searchable(),
                TextColumn::make('saasPlan.name')->label(__('الباقة')),
                TextColumn::make('status')->label(__('الحالة'))->badge()
                    ->formatStateUsing(fn ($state) => Gym::statuses()[$state] ?? $state)
                    ->color(fn ($state) => Gym::STATUS_COLORS[$state] ?? 'gray'),
                TextColumn::make('access_ends')->label(__('ينتهي'))
                    ->state(fn (Gym $r) => $r->accessEndsAt()?->format('Y-m-d'))
                    ->description(fn (Gym $r) => $r->hasAccess() ? __(':days يوم', ['days' => $r->daysLeft()]) : __('بدون صلاحية')),
                TextColumn::make('members_count')->label(__('المشتركين'))->sortable(),
                TextColumn::make('users_count')->label(__('الموظفين')),
                TextColumn::make('created_at')->label(__('تاريخ التسجيل'))->date('Y-m-d')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('الحالة'))->options(Gym::statuses()),
                SelectFilter::make('saas_plan_id')->label(__('الباقة'))->relationship('saasPlan', 'name'),
            ])
            ->recordActions([
                Action::make('open')->label(__('فتح'))->icon(Heroicon::OutlinedArrowTopRightOnSquare)->color('gray')
                    ->url(fn (Gym $r) => url('/admin/'.$r->slug), true)
                    ->visible(fn (Gym $r) => auth()->user()->canAccessTenant($r)),
                Action::make('payment')->label(__('تسجيل دفع'))->icon(Heroicon::OutlinedBanknotes)->color('success')
                    ->schema(fn (Gym $r) => [
                        Select::make('saas_plan_id')->label(__('الباقة'))->options(SaasPlan::pluck('name', 'id'))->default($r->saas_plan_id)->required(),
                        Select::make('cycle')->label(__('دورة الفوترة'))->options(['monthly' => __('شهري'), 'yearly' => __('سنوي')])->default($r->billing_cycle)->required(),
                        TextInput::make('method')->label(__('طريقة الدفع'))->default('InstaPay'),
                        TextInput::make('reference')->label(__('رقم العملية')),
                    ])
                    ->action(function (Gym $r, array $data) {
                        $invoice = GymInvoice::where('gym_id', $r->id)->where('status', 'unpaid')->latest()->first()
                            ?? GymInvoice::issueFor($r, SaasPlan::find($data['saas_plan_id']), $data['cycle']);
                        $invoice->markPaid($data['method'], $data['reference']);
                        Notification::make()->title(__('تم التفعيل حتى :date', ['date' => $r->fresh()->subscription_ends_at->format('Y-m-d')]))->success()->send();
                    }),
                ActionGroup::make([
                    EditAction::make(),
                    Action::make('extendTrial')->label(__('تمديد التجربة'))->icon(Heroicon::OutlinedClock)
                        ->schema([TextInput::make('days')->label(__('عدد الأيام'))->numeric()->default(7)->required()])
                        ->action(fn (Gym $r, array $data) => $r->forceFill([
                            'status' => Gym::STATUS_TRIAL,
                            'trial_ends_at' => ($r->trial_ends_at?->isFuture() ? $r->trial_ends_at : now())->copy()->addDays((int) $data['days']),
                        ])->save()),
                    Action::make('suspend')->label(__('إيقاف'))->icon(Heroicon::OutlinedNoSymbol)->color('danger')
                        ->visible(fn (Gym $r) => $r->status !== Gym::STATUS_SUSPENDED)
                        ->schema([TextInput::make('reason')->label(__('السبب'))])
                        ->action(fn (Gym $r, array $data) => $r->forceFill(['status' => Gym::STATUS_SUSPENDED, 'suspended_reason' => $data['reason'] ?? null])->save()),
                    Action::make('unsuspend')->label(__('إلغاء الإيقاف'))->icon(Heroicon::OutlinedCheckCircle)->color('success')
                        ->visible(fn (Gym $r) => $r->status === Gym::STATUS_SUSPENDED)
                        ->action(fn (Gym $r) => $r->forceFill([
                            'status' => $r->subscription_ends_at?->isFuture() ? Gym::STATUS_ACTIVE : Gym::STATUS_TRIAL,
                            'suspended_reason' => null,
                        ])->save()),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageGyms::route('/')];
    }

    /** Used by the create action: creates/attaches the owner and seeds the gym. */
    public static function createWithOwner(array $data, array $raw): Gym
    {
        $owner = User::firstOrCreate(['email' => $raw['owner_email']], [
            'name' => $raw['owner_name'],
            'password' => $raw['owner_password'] ?: str()->random(16),
        ]);
        $gym = GymOnboarding::create($owner, $data);
        $gym->forceFill(array_filter([
            'status' => $data['status'] ?? null,
            'trial_ends_at' => $data['trial_ends_at'] ?? null,
            'subscription_ends_at' => $data['subscription_ends_at'] ?? null,
            'email' => $data['email'] ?? null,
        ]))->save();

        return $gym;
    }
}
