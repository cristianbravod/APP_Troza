<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ChileanRutRule implements Rule
{
    public function passes($attribute, $value)
    {
        return $this->validateRut($value);
    }
    
    private function validateRut($rut)
    {
        $rut = preg_replace('/[^0-9kK]/', '', $rut);
        
        if (strlen($rut) < 8 || strlen($rut) > 9) {
            return false;
        }
        
        $dv = strtoupper(substr($rut, -1));
        $number = substr($rut, 0, -1);
        
        return $dv === $this->calculateDV($number);
    }
}