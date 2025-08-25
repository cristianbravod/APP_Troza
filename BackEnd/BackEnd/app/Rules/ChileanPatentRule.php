<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ChileanPatentRule implements Rule
{
    public function passes($attribute, $value)
    {
        // Formato antiguo: AA1234 o AAAA12
        // Formato nuevo: BBBB12
        $patterns = [
            '/^[A-Z]{2}[0-9]{4}$/',     // AA1234
            '/^[A-Z]{4}[0-9]{2}$/',     // AAAA12 o BBBB12
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, strtoupper($value))) {
                return true;
            }
        }
        
        return false;
    }
}