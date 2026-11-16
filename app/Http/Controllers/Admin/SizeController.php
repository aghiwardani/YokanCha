<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Size;
use App\Support\Number;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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

        // Products and recipes refer to the size by name, so a new name is
        // carried into them in the same transaction.
        $touched = DB::transaction(function () use ($size, $data) {
            $size->update(['volume_ml' => $data['volume_ml']]);

            return $size->renameEverywhere($data['name']);
        });

        $message = 'Ukuran berhasil diubah.'.($touched ? " {$touched} produk ikut diperbarui." : '');

        return $this->backToList()->with('status', $message);
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
        // "1.000" ml typed with a thousands dot.
        if ($request->has('volume_ml')) {
            $request->merge(['volume_ml' => Number::normalize($request->input('volume_ml'))]);
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('sizes', 'name')->ignore($size?->getKey())],
            'volume_ml' => ['required', 'integer', 'min:1', 'max:100000'],
        ], [], [
            'name' => 'nama ukuran',
            'volume_ml' => 'isi (ml)',
        ]);
    }
}
