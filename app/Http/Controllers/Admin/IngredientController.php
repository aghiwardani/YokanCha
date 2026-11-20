<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\OrderItemIngredient;
use App\Models\PoPeriodIngredient;
use App\Models\ProductRecipe;
use App\Support\Number;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IngredientController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        Ingredient::create($data);

        return $this->backToList()->with('status', 'Bahan berhasil ditambahkan.');
    }

    public function update(Request $request, Ingredient $ingredient): RedirectResponse
    {
        $data = $this->validated($request, $ingredient);

        $ingredient->update($data);

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
        // Amounts may arrive typed the Indonesian way ("1.000", "38.000").
        $request->merge([
            'pack_size' => Number::normalize($request->input('pack_size')),
            'pack_price' => Number::normalize($request->input('pack_price')),
        ]);

        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('ingredients', 'name')->ignore($ingredient?->getKey())],
            'unit' => ['required', 'in:g,ml'],
            // Any number of ingredients can be sweeteners (gula cair, maple, simple syrup).
            'is_sugar' => ['boolean'],
            // Usual purchase: pack_size units for pack_price Rupiah.
            'pack_size' => ['nullable', 'required_with:pack_price', 'numeric', 'gt:0', 'max:99999999'],
            'pack_price' => ['nullable', 'required_with:pack_size', 'integer', 'min:0', 'max:999999999999'],
        ], [
            'pack_size.required_with' => 'Isi juga isi kemasannya, misalnya 1.000 untuk 1 liter.',
            'pack_price.required_with' => 'Isi juga harga per kemasannya.',
        ]);
    }
}
