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
            $size->update([
                'volume_ml' => $data['volume_ml'],
                'extra_matcha_price' => $data['extra_matcha_price'],
                'extra_matcha_percent' => $data['extra_matcha_percent'],
            ]);

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
        foreach (['volume_ml', 'extra_matcha_price', 'extra_matcha_percent'] as $field) {
            if ($request->has($field)) {
                $request->merge([$field => Number::normalize($request->input($field))]);
            }
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('sizes', 'name')->ignore($size?->getKey())],
            'volume_ml' => ['required', 'integer', 'min:1', 'max:100000'],
            // Extra matcha: price added per bottle (empty/0 = not offered) and
            // how much more matcha, as a percent of the recipe's matcha.
            'extra_matcha_price' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'extra_matcha_percent' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ], [], [
            'name' => 'nama ukuran',
            'volume_ml' => 'isi (ml)',
            'extra_matcha_price' => 'harga extra matcha',
            'extra_matcha_percent' => 'matcha tambahan (%)',
        ]);

        $data['extra_matcha_price'] = (int) ($data['extra_matcha_price'] ?? 0);
        $data['extra_matcha_percent'] = (int) ($data['extra_matcha_percent'] ?? 0);

        if ($data['extra_matcha_percent'] > 0 && $data['extra_matcha_price'] === 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'extra_matcha_price' => 'Isi juga harga extra matcha. Kalau 0, pilihan extra matcha tidak muncul di menu.',
            ]);
        }
        if ($data['extra_matcha_price'] > 0 && $data['extra_matcha_percent'] === 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'extra_matcha_percent' => 'Isi juga berapa persen matcha tambahannya, misalnya 50 untuk +50% dari resep.',
            ]);
        }

        return $data;
    }
}
