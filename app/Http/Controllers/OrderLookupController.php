<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderLookupController extends Controller
{
    public function show(): View
    {
        return view('orders.lookup');
    }

    public function search(Request $request): View
    {
        $data = $request->validate([
            'order_number' => ['required', 'string'],
            'customer_whatsapp' => ['required', 'string'],
        ]);

        $order = Order::where('order_number', $data['order_number'])
            ->where('customer_whatsapp', $data['customer_whatsapp'])
            ->with('items.product')
            ->first();

        return view('orders.lookup', [
            'order' => $order,
            'searched' => true,
        ]);
    }
}
