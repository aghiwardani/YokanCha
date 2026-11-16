<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SweetnessLevel;
use App\Support\Number;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SweetnessLevelController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        SweetnessLevel::create($this->validated($request));

        return $this->backToList()->with('status', 'Tingkat manis berhasil ditambahkan.');
    }

    public function update(Request $request, SweetnessLevel $level): RedirectResponse
    {
        $data = $this->validated($request, $level);

        // Products refer to the level by name, so a new name is carried into
        // them in the same transaction.
        $touched = DB::transaction(function () use ($level, $data) {
            $level->update(['percent' => $data['percent']]);

            return $level->renameEverywhere($data['name']);
        });

        $message = 'Tingkat manis berhasil diubah.'.($touched ? " {$touched} produk ikut diperbarui." : '');

        return $this->backToList()->with('status', $message);
    }

    public function destroy(SweetnessLevel $level): RedirectResponse
    {
        if ($level->isUsed()) {
            return $this->backToList()->with('error', "{$level->name} masih dipakai produk, jadi tidak bisa dihapus. Hilangkan centangnya di produk dulu.");
        }

        $level->delete();

        return $this->backToList()->with('status', 'Tingkat manis berhasil dihapus.');
    }

    private function backToList(): RedirectResponse
    {
        return redirect()->to(route('admin.master').'#manis');
    }

    private function validated(Request $request, ?SweetnessLevel $level = null): array
    {
        if ($request->has('percent')) {
            $request->merge(['percent' => Number::normalize($request->input('percent'))]);
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('sweetness_levels', 'name')->ignore($level?->getKey())],
            'percent' => ['required', 'integer', 'min:0', 'max:300'],
        ], [], [
            'name' => 'nama tingkat manis',
            'percent' => 'persen gula',
        ]);
    }
}
