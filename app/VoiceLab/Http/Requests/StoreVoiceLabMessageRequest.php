<?php

declare(strict_types=1);

namespace App\VoiceLab\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class StoreVoiceLabMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:1', 'max:2000'],
        ];
    }
}
