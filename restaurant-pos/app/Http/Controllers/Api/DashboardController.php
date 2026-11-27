<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** تطبيق المالك / المدير */
class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $from = Carbon::parse($request->query('from', today()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->query('to', today()->toDateString()))->endOfDay();

        $base = fn () => Order::whereBetween('created_at', [$from, $to])->where('status', '!=', OrderStatus::Cancelled);
        $paid = fn () => $base()->where('is_paid', true);

        $sales = (float) $paid()->sum('total');
        $paidCount = $paid()->count();

        $cost = (float) OrderItem::join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->whereBetween('orders.created_at', [$from, $to])
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->where('orders.is_paid', true)
            ->sum(DB::raw('products.cost * order_items.quantity'));

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'sales' => $sales,
            'orders_count' => $base()->count(),
            'average_order' => $paidCount ? round($sales / $paidCount, 2) : 0,
            'discounts' => (float) $paid()->sum('discount'),
            'delivery_fees' => (float) $paid()->sum('delivery_fee'),
            'estimated_cost' => $cost,
            'estimated_profit' => round($sales - $cost, 2),
            'unpaid' => (float) $base()->where('is_paid', false)->sum('total'),
            'open_orders' => Order::whereIn('status', OrderStatus::open())->count(),
            'cancelled' => Order::whereBetween('created_at', [$from, $to])->where('status', OrderStatus::Cancelled)->count(),
            'by_type' => $base()->select('type', DB::raw('COUNT(*) as count'), DB::raw('SUM(total) as total'))
                ->groupBy('type')->get()->map(fn ($r) => [
                    'type' => $r->type->value, 'label' => $r->type->getLabel(), 'count' => (int) $r->count, 'total' => (float) $r->total,
                ]),
            'by_payment' => $paid()->select('payment_method', DB::raw('SUM(total) as total'))
                ->groupBy('payment_method')->get()->map(fn ($r) => [
                    'method' => $r->payment_method?->value, 'label' => $r->payment_method?->getLabel(), 'total' => (float) $r->total,
                ]),
            'daily' => $paid()->select(DB::raw('DATE(created_at) as day'), DB::raw('SUM(total) as total'), DB::raw('COUNT(*) as count'))
                ->groupBy('day')->orderBy('day')->get()->map(fn ($r) => [
                    'day' => (string) $r->day, 'total' => (float) $r->total, 'count' => (int) $r->count,
                ]),
            'top_products' => OrderItem::join('orders', 'orders.id', '=', 'order_items.order_id')
                ->whereBetween('orders.created_at', [$from, $to])
                ->where('orders.status', '!=', OrderStatus::Cancelled->value)
                ->select('product_name', DB::raw('SUM(quantity) as qty'), DB::raw('SUM(order_items.total) as total'))
                ->groupBy('product_name')->orderByDesc('qty')->limit(10)->get()
                ->map(fn ($r) => ['name' => $r->product_name, 'qty' => (int) $r->qty, 'total' => (float) $r->total]),
            'drivers' => Driver::orderBy('name')->get()->map(fn ($d) => [
                'name' => $d->name,
                'orders' => $base()->where('driver_id', $d->id)->count(),
                'total' => (float) $base()->where('driver_id', $d->id)->sum('total'),
            ])->filter(fn ($d) => $d['orders'] > 0)->values(),
        ];
    }
}
