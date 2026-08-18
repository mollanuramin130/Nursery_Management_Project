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
            'mobile' => ['required', 'string', 'max:20', 'regex:/^\+?\d{10,15}$/'],
            'email' => ['nullable', 'email', 'max:190'],
            'otp_verified_token' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()],
            'device' => ['nullable', 'array'],
            'device.platform' => ['nullable', 'string', 'in:web,android,ios'],
            'device.device_id' => ['nullable', 'string', 'max:100'],
            'device.push_token' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'mobile.required' => 'Mobile number is required.',
            'mobile.regex' => 'Enter a valid mobile number.',
            'otp_verified_token.required' => 'Mobile verification is required before registration.',
        ];
    }
}
