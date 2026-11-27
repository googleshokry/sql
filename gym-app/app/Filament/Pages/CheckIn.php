<?php

namespace App\Filament\Pages;

use App\Models\Attendance;
use App\Models\Member;
use App\Models\Subscription;
use App\Services\AttendanceService;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class CheckIn extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static string|\UnitEnum|null $navigationGroup = 'الحضور والانصراف';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'تسجيل الدخول والخروج';

    protected static ?string $title = 'تسجيل الدخول والخروج';

    protected static ?string $slug = 'check-in';

    protected string $view = 'filament.pages.check-in';

    public string $search = '';

    public ?int $memberId = null;

    /** @var array{ok:bool, action:?string, message:string}|null */
    public ?array $result = null;

    public array $matches = [];

    public function submit(): void
    {
        $term = trim($this->search);
        $this->reset(['result', 'matches', 'memberId']);
        if ($term === '') {
            return;
        }

        $found = Member::search($term)->limit(10)->get();

        if ($found->isEmpty()) {
            $this->result = ['ok' => false, 'action' => null, 'message' => 'لا يوجد مشترك بهذا الكود / الرقم'];
        } elseif ($found->count() > 1 && ! $found->firstWhere('code', $term)) {
            $this->matches = $found->map(fn (Member $m) => ['id' => $m->id, 'name' => $m->name, 'code' => $m->code, 'phone' => $m->phone])->all();
        } else {
            $this->process($found->firstWhere('code', $term) ?? $found->first());
        }

        $this->search = '';
    }

    public function select(int $id): void
    {
        $this->matches = [];
        $this->process(Member::findOrFail($id));
    }

    public function force(): void
    {
        if (! $this->memberId || ! auth()->user()->isManager()) {
            return;
        }
        $this->result = AttendanceService::checkIn(Member::findOrFail($this->memberId), true);
        unset($this->result['attendance']);
    }

    public function checkout(int $attendanceId): void
    {
        Attendance::whereKey($attendanceId)->whereNull('check_out_at')->update(['check_out_at' => now()]);
    }

    protected function process(Member $member): void
    {
        $this->memberId = $member->id;
        $this->result = AttendanceService::toggle($member);
        unset($this->result['attendance']);
        $this->dispatch('checkin-result', ok: $this->result['ok']);
    }

    public function getMemberProperty(): ?Member
    {
        return $this->memberId ? Member::with('latestSubscription.plan')->find($this->memberId) : null;
    }

    public function getSubscriptionProperty(): ?Subscription
    {
        return $this->member?->currentSubscription() ?? $this->member?->latestSubscription;
    }

    public function getInsideProperty(): Collection
    {
        return Attendance::with('member')->whereNull('check_out_at')->latest('check_in_at')->get();
    }

    public function getTodayCountProperty(): int
    {
        return Attendance::whereDate('check_in_at', today())->count();
    }
}
