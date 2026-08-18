<?php

namespace App\Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mobile' => ['required', 'string', 'max:20', 'regex:/^\+?\d{10,15}$/'],
            'purpose' => ['required', 'string', 'in:register,login,reset'],
        ];
    }

    public function messages(): array
    {
        return [
            'mobile.regex' => 'Enter a valid mobile number.',
            'mobile.required' => 'Mobile number is required.',
        ];
    }
}
