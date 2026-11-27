<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    public const TONES = ['amber', 'sage', 'oat'];

    public const BOTTLE_STYLES = ['klasik', 'maple', 'oat', 'murni', 'strawberry'];

    /** Menu tabs, in the order they show on the menu. */
    public const CATEGORIES = ['matcha' => 'Matcha', 'coffee' => 'Coffee'];

    protected static function booted(): void
    {
        // Anything outside the known tabs (e.g. the old "minuman") is matcha.
        static::saving(function (Product $product) {
            if (! array_key_exists((string) $product->category, self::CATEGORIES)) {
                $product->category = 'matcha';
            }
        });
    }

    /**
     * The menu tab this product shows under.
     */
    public function categoryKey(): string
    {
        return array_key_exists((string) $this->category, self::CATEGORIES) ? $this->category : 'matcha';
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->categoryKey()];
    }

    /**
     * Page theme for what someone is ordering: "matcha", "coffee", or
     * "blend" (both, shown as a green-to-brown gradient). Empty = matcha.
     *
     * @param  iterable<Product|null>  $products
     */
    public static function themeFor(iterable $products): string
    {
        $kinds = collect($products)->filter()->map(fn (Product $product) => $product->categoryKey())->unique();

        return match (true) {
            $kinds->contains('matcha') && $kinds->contains('coffee') => 'blend',
            $kinds->contains('coffee') => 'coffee',
            default => 'matcha',
        };
    }

    protected $fillable = [
        'category',
        'name',
        'description',
        'photo_path',
        'base_price',
        'size_options',
        'size_prices',
        'sweetness_options',
        'tag',
        'tone',
        'bottle_style',
        'sort_order',
        'is_active',
        'allow_extra_matcha',
    ];

    protected $attributes = [
        'is_active' => true,
        'allow_extra_matcha' => true,
    ];

    protected $casts = [
        'base_price' => 'integer',
        'size_options' => 'array',
        'size_prices' => 'array',
        'sweetness_options' => 'array',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'allow_extra_matcha' => 'boolean',
    ];

    /**
     * Extra matcha price per size of this product (size => price), only sizes
     * that offer it; empty when the product doesn't allow Extra matcha.
     *
     * @return array<string,int>
     */
    public function extraMatchaPrices(): array
    {
        if (! $this->allow_extra_matcha) {
            return [];
        }

        $prices = Size::extraMatchaPrices(array_map('strval', array_values($this->size_options ?? [])));
        if ($prices === []) {
            return [];
        }

        // The extra is a percent of the recipe's matcha, so a size whose recipe
        // has no ingredient marked "Ini matcha" can't offer it.
        $withMatcha = ProductRecipe::where('product_id', $this->id)
            ->whereIn('size', array_keys($prices))
            ->where('amount', '>', 0)
            ->whereHas('ingredient', fn ($query) => $query->where('is_matcha', true))
            ->distinct()->pluck('size')->all();

        return array_intersect_key($prices, array_flip($withMatcha));
    }

    /**
     * Price added per bottle for Extra matcha in this size; 0 = not offered.
     */
    public function extraMatchaPrice(?string $size): int
    {
        return $size === null ? 0 : ($this->extraMatchaPrices()[$size] ?? 0);
    }

    public function poPeriodProducts(): HasMany
    {
        return $this->hasMany(PoPeriodProduct::class);
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(ProductRecipe::class);
    }

    /**
     * The product's sizes, smallest first by the size master's volume (sizes
     * missing from the master fall back to a volume guessed from the name).
     *
     * @return array<int, string>
     */
    public function sizesSmallestFirst(): array
    {
        $sizes = array_values($this->size_options ?? []);

        if (count($sizes) < 2) {
            return $sizes;
        }

        $volumes = Size::whereIn('name', $sizes)->pluck('volume_ml', 'name');
        $volumeOf = fn (string $size) => $volumes[$size] ?? Size::volumeFromName($size);

        usort($sizes, fn ($a, $b) => Size::sortVolume($volumeOf($a)) <=> Size::sortVolume($volumeOf($b)));

        return $sizes;
    }

    /**
     * The product's sweetness levels with their sugar percent, least sweet
     * first (names missing from the master get a percent guessed from the
     * wording).
     *
     * @return array<string, int> name => percent
     */
    public function sweetnessLeastFirst(): array
    {
        $levels = array_values($this->sweetness_options ?? []);
        $map = SweetnessLevel::whereIn('name', $levels)->pluck('percent', 'name');

        $percents = [];
        foreach ($levels as $name) {
            $percents[$name] = (int) ($map[$name] ?? SweetnessLevel::percentFromName($name));
        }

        uksort($percents, fn ($a, $b) => [$percents[$a], array_search($a, $levels, true)] <=> [$percents[$b], array_search($b, $levels, true)]);

        return $percents;
    }

    /**
     * Unit price for the given size label. Sizes without an explicit price
     * (or products without per-size pricing) use base_price.
     */
    public function priceFor(?string $size): int
    {
        $prices = $this->size_prices ?? [];

        if ($size !== null && isset($prices[$size])) {
            return (int) $prices[$size];
        }

        return $this->base_price;
    }
}
