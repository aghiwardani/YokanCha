<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\OrderItemIngredient;
use App\Models\PoPeriodIngredient;
use App\Models\ProductRecipe;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class IngredientController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data) {
            $this->clearOtherSugar($data['is_sugar'], null);
            Ingredient::create($data);
        });

        return $this->backToList()->with('status', 'Bahan berhasil ditambahkan.');
    }

    public function update(Request $request, Ingredient $ingredient): RedirectResponse
    {
        $data = $this->validated($request, $ingredient);

        DB::transaction(function () use ($data, $ingredient) {
            $this->clearOtherSugar($data['is_sugar'], $ingredient->getKey());
            $ingredient->update($data);
        });

        return $this->backToList()->with('status', 'Bahan berhasil diubah.');
    }

    public function destroy(Ingredient $ingredient): RedirectResponse
    {
        // Recipes, period stock and order snapshots cascade on delete; removing a
        // used ingredient would silently change stock math and cancellations.
        $inUse = ProductRecipe::where('ingredient_id', $ingredient->id)->exists()
            || PoPeriodIngredient::where('ingredient_id', $ingredient->id)->exists()
            || OrderItemIngredient::where('ingredient_id', $ingredient->id)->exists();

        if ($inUse) {
            return $this->backToList()->with('error', "{$ingredient->name} masih dipakai di resep, stok periode, atau pesanan, jadi tidak bisa dihapus.");
        }

        $ingredient->delete();

        return $this->backToList()->with('status', 'Bahan berhasil dihapus.');
    }

    private function backToList(): RedirectResponse
    {
        return redirect()->to(route('admin.master').'#bahan');
    }

    private function validated(Request $request, ?Ingredient $ingredient = null): array
    {
        // An unchecked checkbox is simply absent from the request.
        $request->merge(['is_sugar' => $request->boolean('is_sugar')]);

        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('ingredients', 'name')->ignore($ingredient?->getKey())],
            'unit' => ['required', 'in:g,ml'],
            'is_sugar' => ['boolean'],
        ]);
    }

    // At most one ingredient is the sugar.
    private function clearOtherSugar(bool $isSugar, ?int $exceptId): void
    {
        if (! $isSugar) {
            return;
        }

        $query = Ingredient::where('is_sugar', true);
        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }
        $query->update(['is_sugar' => false]);
    }
}
