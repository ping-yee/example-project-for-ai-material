<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class TagName implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && preg_match_all('/\X/u', $value) > 50) {
            $fail('標籤名稱不可超過 50 個字元。');
        }
    }
}
