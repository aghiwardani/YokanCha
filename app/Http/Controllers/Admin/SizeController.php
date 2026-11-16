<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Size;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SizeController extends Controller
{
    public function index(): View
    {
        return view('admin.sizes.index', [
            'sizes' => Size::smallestFirst()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Size::create($this->validated($request));

        return redirect()->route('admin.sizes')->with('status', 'Ukuran berhasil ditambahkan.');
    }

    public function update(Request $request, Size $size): RedirectResponse
    {
        $data = $this->validated($request, $size);

        // Products, recipes and past orders refer to the size by name, so a
        // size that is already sold keeps its name; its volume can change.
        if ($data['name'] !== $size->name && $size->isUsed()) {
            throw ValidationException::withMessages([
                'name' => 'Ukuran ini sudah dipakai produk, jadi namanya tidak bisa diubah.',
            ]);
        }

        $size->update($data);

        return redirect()->route('admin.sizes')->with('status', 'Ukuran berhasil diubah.');
    }

    private function validated(Request $request, ?Size $size = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('sizes', 'name')->ignore($size?->getKey())],
            'volume_ml' => ['required', 'integer', 'min:1', 'max:100000'],
        ], [], [
            'name' => 'nama ukuran',
            'volume_ml' => 'isi (ml)',
        ]);
    }
}
