<?php

namespace App\Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()],
            'device' => ['nullable', 'array'],
            'device.platform' => ['nullable', 'string', 'in:web,android,ios'],
            'device.device_id' => ['nullable', 'string', 'max:100'],
            'device.push_token' => ['nullable', 'string', 'max:255'],
        ];
    }
}
