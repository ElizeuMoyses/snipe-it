<?php

declare(strict_types=1);

namespace App\Rules;

use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\Rule;

class BrDocument implements Rule, DataAwareRule
{
    protected array $data = [];

    public function setData(array $data): static
    {
        $this->data = $data;
        return $this;
    }

    public function passes($attribute, $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        $type = $this->data['supplier_type'] ?? null;

        return match ($type) {
            'pj'            => $this->validateCnpj((string) $value),
            'pf'            => (new ValidCpf())->passes($attribute, $value),
            'international' => mb_strlen((string) $value) <= 20,
            default         => true,
        };
    }

    public function message(): string
    {
        $type = $this->data['supplier_type'] ?? null;

        return match ($type) {
            'pj'    => trans('admin/suppliers/validation.document_cnpj_invalid'),
            'pf'    => trans('admin/suppliers/validation.document_cpf_invalid'),
            default => trans('admin/suppliers/validation.document_invalid'),
        };
    }

    private function validateCnpj(string $value): bool
    {
        $cnpj = preg_replace('/[^A-Z0-9]/', '', strtoupper($value));

        if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }
        // Check digits (positions 12-13) must be numeric
        if (!ctype_digit(substr($cnpj, 12, 2))) {
            return false;
        }

        // Positions 0-11 must be alphanumeric [A-Z0-9]
        if (!preg_match('/^[A-Z0-9]{12}$/', substr($cnpj, 0, 12))) {
            return false;
        }

        // Receita Federal: ASCII value minus 48 (digits 0..9, letters A=17..Z=42).
        $values = [];
        for ($i = 0; $i < 14; $i++) {
            $char     = $cnpj[$i];
            $values[] = ord($char) - 48;
        }

        // First check digit — weights [5,4,3,2,9,8,7,6,5,4,3,2]
        $weights1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $sum      = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += $values[$i] * $weights1[$i];
        }
        $remainder = $sum % 11;
        $digit1    = $remainder < 2 ? 0 : 11 - $remainder;
        if ($values[12] !== $digit1) {
            return false;
        }

        // Second check digit — weights [6,5,4,3,2,9,8,7,6,5,4,3,2]
        $weights2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $sum      = 0;
        for ($i = 0; $i < 13; $i++) {
            $sum += $values[$i] * $weights2[$i];
        }
        $remainder = $sum % 11;
        $digit2    = $remainder < 2 ? 0 : 11 - $remainder;

        return $values[13] === $digit2;
    }
}
