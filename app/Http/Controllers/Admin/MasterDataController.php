<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\Size;
use Illuminate\View\View;

/**
 * One admin page for the master lists: ingredients (Bahan) and sizes (Ukuran).
 */
class MasterDataController extends Controller
{
    public function index(): View
    {
        return view('admin.master.index', [
            'ingredients' => Ingredient::orderBy('name')->get(),
            'sizes' => Size::smallestFirst()->get(),
        ]);
    }
}
