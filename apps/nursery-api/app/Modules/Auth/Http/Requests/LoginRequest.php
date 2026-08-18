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
            'email' => ['required_without:mobile', 'nullable', 'email'],
            'mobile' => ['required_without:email', 'nullable', 'string', 'max:20', 'regex:/^\+?\d{10,15}$/'],
            'password' => ['required', 'string'],
            'device' => ['nullable', 'array'],
            'device.platform' => ['nullable', 'string', 'in:web,android,ios'],
            'device.device_id' => ['nullable', 'string', 'max:100'],
            'device.push_token' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required_without' => 'Email or mobile number is required.',
            'mobile.required_without' => 'Mobile number or email is required.',
            'mobile.regex' => 'Enter a valid mobile number.',
        ];
    }
}
