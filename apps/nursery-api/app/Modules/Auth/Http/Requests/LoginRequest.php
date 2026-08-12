<?php

namespace App\Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device' => ['nullable', 'array'],
            'device.platform' => ['nullable', 'string', 'in:web,android,ios'],
            'device.device_id' => ['nullable', 'string', 'max:100'],
            'device.push_token' => ['nullable', 'string', 'max:255'],
        ];
    }
}
