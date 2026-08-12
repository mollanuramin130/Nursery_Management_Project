<?php

namespace App\Shared\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Accept only http(s) image URLs — rejects javascript:/data:/file: and path tricks.
 */
class SecureImageUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || strlen($value) > 500) {
            $fail('The :attribute must be a valid http(s) URL.');

            return;
        }

        if (! filter_var($value, FILTER_VALIDATE_URL)) {
            $fail('The :attribute must be a valid http(s) URL.');

            return;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            $fail('The :attribute must use http or https.');

            return;
        }

        $host = parse_url($value, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            $fail('The :attribute must include a host.');
        }
    }
}
