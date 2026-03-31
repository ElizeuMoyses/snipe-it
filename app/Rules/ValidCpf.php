<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class ValidCpf implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        // Remove any non-numeric characters
        $cpf = preg_replace('/[^0-9]/', '', $value);
        
        \Illuminate\Support\Facades\Log::debug('ValidCpf passes check', ['original' => $value, 'numeric_only' => $cpf]);

        // Check if CPF has 11 digits
        if (strlen($cpf) !== 11) {
            \Illuminate\Support\Facades\Log::warning('ValidCpf failed: length mismatch', ['cpf' => $cpf, 'length' => strlen($cpf)]);
            return false;
        }
        
        // Check for known invalid CPFs (all same digits)
        if (preg_match('/(\d)\1{10}/', $cpf)) {
            \Illuminate\Support\Facades\Log::warning('ValidCpf failed: all same digits', ['cpf' => $cpf]);
            return false;
        }
        
        // Validate first check digit
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += intval($cpf[$i]) * (10 - $i);
        }
        $remainder = $sum % 11;
        $firstDigit = ($remainder < 2) ? 0 : 11 - $remainder;
        
        if (intval($cpf[9]) !== $firstDigit) {
            \Illuminate\Support\Facades\Log::warning('ValidCpf failed: first check digit mismatch', ['cpf' => $cpf, 'expected' => $firstDigit, 'actual' => intval($cpf[9])]);
            return false;
        }
        
        // Validate second check digit
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += intval($cpf[$i]) * (11 - $i);
        }
        $remainder = $sum % 11;
        $secondDigit = ($remainder < 2) ? 0 : 11 - $remainder;
        
        if (intval($cpf[10]) !== $secondDigit) {
            \Illuminate\Support\Facades\Log::warning('ValidCpf failed: second check digit mismatch', ['cpf' => $cpf, 'expected' => $secondDigit, 'actual' => intval($cpf[10])]);
            return false;
        }
        
        \Illuminate\Support\Facades\Log::info('ValidCpf passed', ['cpf' => $cpf]);
        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'CPF inválido.';
    }
}