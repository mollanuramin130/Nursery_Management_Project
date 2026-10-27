@component('mail::message')
# {{ $headline }}

Hello {{ $customerName }},

{{ $bodyText }}

@if($ctaUrl && $ctaLabel)
@component('mail::button', ['url' => $ctaUrl])
{{ $ctaLabel }}
@endcomponent
@endif

Thanks,<br>
{{ config('brand.name', 'NUVIKOZ') }}

---
This is a transactional message from {{ config('brand.name', 'NUVIKOZ') }}. Marketing preferences do not disable order and account emails.
@endcomponent
