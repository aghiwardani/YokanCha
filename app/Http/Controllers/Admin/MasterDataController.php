<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Size;
use Illuminate\View\View;

/**
 * One admin page for the master lists: products (Produk), ingredients (Bahan)
 * and sizes (Ukuran). The product form itself stays on its own page.
 */
class MasterDataController extends Controller
{
    public function index(): View
    {
        return view('admin.master.index', [
            'products' => Product::orderBy('sort_order')->orderBy('name')->get(),
            'ingredients' => Ingredient::orderBy('name')->get(),
            'sizes' => Size::smallestFirst()->get(),
        ]);
    }
}
