<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    /**
     * Setting key => the config/yokancha.php entry it overrides.
     */
    public const CONFIG = [
        'whatsapp' => 'yokancha.whatsapp',
        'bank_name' => 'yokancha.payment.bank',
        'bank_account' => 'yokancha.payment.account_number',
        'bank_holder' => 'yokancha.payment.account_name',
        'max_per_variant' => 'yokancha.max_per_variant',
    ];

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    /**
     * Copy saved settings over the config values for this request.
     */
    public static function apply(): void
    {
        foreach (static::query()->whereIn('key', array_keys(self::CONFIG))->pluck('value', 'key') as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            config([self::CONFIG[$key] => $key === 'max_per_variant' ? (int) $value : $value]);
        }
    }

    /**
     * @param array<string,mixed> $values
     */
    public static function store(array $values): void
    {
        foreach ($values as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => $value === null ? null : (string) $value]);
        }
        static::apply();
    }
}
