<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
            'order_number' => ['required', 'string', 'max:50'],
            'customer_whatsapp' => ['required', 'string', 'max:30'],
        ]);

        // Order numbers are unique, so fetch by number and then compare the
        // WhatsApp number in normalized form (0812… == +62 812… == 62812…).
        $order = Order::where('order_number', Str::upper(trim($data['order_number'])))
            ->with('items.product')
            ->first();

        if ($order && ! Phone::same($order->customer_whatsapp, $data['customer_whatsapp'])) {
            $order = null;
        }

        return view('orders.lookup', [
            'order' => $order,
            'searched' => true,
        ]);
    }
}
