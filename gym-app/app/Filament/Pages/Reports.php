<?php

namespace App\Filament\Pages;

use App\Models\Attendance;
use App\Models\Expense;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Subscription;
use App\Models\Trainer;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

class Reports extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|\UnitEnum|null $navigationGroup = \App\Enums\NavGroup::Finance;

    protected static ?int $navigationSort = 5;

    public static function getNavigationLabel(): string
    {
        return __('التقارير');
    }

    public function getTitle(): string
    {
        return __('التقارير المالية والإحصائية');
    }

    protected string $view = 'filament.pages.reports';

    public string $from = '';

    public string $until = '';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isManager();
    }

    public function mount(): void
    {
        $this->from = today()->startOfMonth()->toDateString();
        $this->until = today()->toDateString();
    }

    public function preset(string $p): void
    {
        [$this->from, $this->until] = match ($p) {
            'today' => [today()->toDateString(), today()->toDateString()],
            'yesterday' => [today()->subDay()->toDateString(), today()->subDay()->toDateString()],
            'week' => [today()->startOfWeek(\Carbon\Carbon::SATURDAY)->toDateString(), today()->toDateString()],
            'month' => [today()->startOfMonth()->toDateString(), today()->toDateString()],
            'last_month' => [today()->subMonthNoOverflow()->startOfMonth()->toDateString(), today()->subMonthNoOverflow()->endOfMonth()->toDateString()],
            'year' => [today()->startOfYear()->toDateString(), today()->toDateString()],
            default => [$this->from, $this->until],
        };
    }

    public function getReportProperty(): array
    {
        $range = [$this->from.' 00:00:00', $this->until.' 23:59:59'];

        $payments = Payment::whereBetween('paid_at', $range);
        $byMethod = (clone $payments)->select('method', DB::raw('sum(amount) as total'), DB::raw('count(*) as cnt'))
            ->groupBy('method')->get();
        $byType = (clone $payments)->select('type', DB::raw('sum(amount) as total'))->groupBy('type')->pluck('total', 'type');
        $paymentsTotal = (float) (clone $payments)->sum('amount');

        $sales = Sale::whereBetween('sold_at', $range);
        $salesTotal = (float) (clone $sales)->sum('total');
        $salesCost = (float) SaleItem::join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.gym_id', \App\Support\Tenancy::id())
            ->whereBetween('sales.sold_at', $range)
            ->sum(DB::raw('sale_items.quantity * products.cost_price'));

        $expenses = Expense::whereBetween('spent_at', [$this->from, $this->until]);
        $expensesTotal = (float) (clone $expenses)->sum('amount');
        $expByCat = (clone $expenses)->leftJoin('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->select(DB::raw('coalesce(expense_categories.name, ?) as name'), DB::raw('sum(amount) as total'))
            ->addBinding(__('بدون بند'), 'select')
            ->groupBy('name')->orderByDesc('total')->get();

        $subs = Subscription::whereBetween('subscriptions.created_at', $range)->where('subscriptions.status', '!=', 'cancelled');
        $byPlan = (clone $subs)->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->select('plans.name', DB::raw('count(*) as cnt'), DB::raw('sum(subscriptions.total) as total'))
            ->groupBy('plans.name')->orderByDesc('cnt')->get();

        $trainers = Trainer::query()->get()->map(function (Trainer $t) use ($range) {
            $total = (float) Subscription::where('trainer_id', $t->id)->where('status', '!=', 'cancelled')
                ->whereBetween('created_at', $range)->sum('total');

            return ['name' => $t->name, 'subs' => $total, 'commission' => round($total * (float) $t->commission_percent / 100, 2), 'salary' => (float) $t->salary];
        })->filter(fn ($t) => $t['subs'] > 0 || $t['salary'] > 0)->values();

        $outstanding = Subscription::where('status', '!=', 'cancelled')->withSum('payments', 'amount')->get()
            ->sum(fn (Subscription $s) => $s->remaining_amount);

        $attendance = Attendance::whereBetween('check_in_at', $range);
        $peak = (clone $attendance)->select(DB::raw('HOUR(check_in_at) as h'), DB::raw('count(*) as c'))
            ->groupBy('h')->orderByDesc('c')->first();

        return [
            'payments_total' => $paymentsTotal,
            'by_method' => $byMethod,
            'by_type' => $byType,
            'sales_total' => $salesTotal,
            'sales_profit' => $salesTotal - $salesCost,
            'expenses_total' => $expensesTotal,
            'exp_by_cat' => $expByCat,
            'income' => $paymentsTotal + $salesTotal,
            'net' => $paymentsTotal + $salesTotal - $expensesTotal,
            'new_members' => Member::whereBetween('created_at', $range)->count(),
            'new_subs' => (clone $subs)->count(),
            'by_plan' => $byPlan,
            'attendance' => (clone $attendance)->count(),
            'unique_visitors' => (clone $attendance)->distinct('member_id')->count('member_id'),
            'peak_hour' => $peak ? sprintf('%02d:00', $peak->h) : '-',
            'trainers' => $trainers,
            'outstanding' => $outstanding,
        ];
    }
}
