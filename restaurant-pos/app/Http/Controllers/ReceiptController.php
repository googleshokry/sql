<?php

namespace App\Http\Controllers;

use App\Models\Order;

class ReceiptController extends Controller
{
    public function customer(Order $order)
    {
        $order->load(['items', 'customer', 'restaurantTable', 'driver', 'user', 'deliveryArea']);

        return view('receipts.customer', ['order' => $order]);
    }

    public function kitchen(Order $order)
    {
        $order->load(['items', 'restaurantTable', 'customer']);

        return view('receipts.kitchen', ['order' => $order]);
    }
}
