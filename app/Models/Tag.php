<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Normalizer;

class Tag extends Model
{
    protected $fillable = [
        'name',
        'normalized_name',
    ];

    protected $hidden = [
        'normalized_name',
        'normalized_name_hash',
        'created_at',
        'updated_at',
        'pivot',
    ];

    public static function normalizeName(string $name): string
    {
        $normalizedName = Normalizer::normalize(trim($name), Normalizer::FORM_KC);

        if ($normalizedName === false) {
            throw new InvalidArgumentException('標籤名稱必須是有效的 Unicode 字串。');
        }

        return mb_convert_case($normalizedName, MB_CASE_FOLD, 'UTF-8');
    }

    public static function createFromName(string $name): self
    {
        return self::query()->create([
            'name' => trim($name),
            'normalized_name' => self::normalizeName($name),
        ]);
    }
}
