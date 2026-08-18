<?php

namespace App\Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OtpLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mobile' => ['required', 'string', 'max:20', 'regex:/^\+?\d{10,15}$/'],
            'code' => ['required', 'string', 'size:6'],
            'device' => ['nullable', 'array'],
            'device.platform' => ['nullable', 'string', 'in:web,android,ios'],
            'device.device_id' => ['nullable', 'string', 'max:100'],
            'device.push_token' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'mobile.regex' => 'Enter a valid mobile number.',
            'code.size' => 'OTP must be 6 digits.',
        ];
    }
}
