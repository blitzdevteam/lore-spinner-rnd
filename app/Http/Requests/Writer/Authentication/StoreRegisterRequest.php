<?php

declare(strict_types=1);

namespace App\Http\Requests\Writer\Authentication;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', Rule::unique('writers', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
