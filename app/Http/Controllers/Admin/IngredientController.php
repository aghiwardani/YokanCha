<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IngredientController extends Controller
{
    public function index(): View
    {
        return view('admin.ingredients.index', [
            'ingredients' => Ingredient::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data) {
            $this->clearOtherSugar($data['is_sugar'], null);
            Ingredient::create($data);
        });

        return redirect()->route('admin.ingredients')->with('status', 'Bahan berhasil ditambahkan.');
    }

    public function update(Request $request, Ingredient $ingredient): RedirectResponse
    {
        $data = $this->validated($request, $ingredient);

        DB::transaction(function () use ($data, $ingredient) {
            $this->clearOtherSugar($data['is_sugar'], $ingredient->getKey());
            $ingredient->update($data);
        });

        return redirect()->route('admin.ingredients')->with('status', 'Bahan berhasil diubah.');
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
