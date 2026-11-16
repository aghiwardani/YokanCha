<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Size;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SizeController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        Size::create($this->validated($request));

        return $this->backToList()->with('status', 'Ukuran berhasil ditambahkan.');
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

        return $this->backToList()->with('status', 'Ukuran berhasil diubah.');
    }

    public function destroy(Size $size): RedirectResponse
    {
        if ($size->isUsed()) {
            return $this->backToList()->with('error', "{$size->name} masih dipakai produk, jadi tidak bisa dihapus. Hilangkan centangnya di produk dulu.");
        }

        $size->delete();

        return $this->backToList()->with('status', 'Ukuran berhasil dihapus.');
    }

    private function backToList(): RedirectResponse
    {
        return redirect()->to(route('admin.master').'#ukuran');
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
