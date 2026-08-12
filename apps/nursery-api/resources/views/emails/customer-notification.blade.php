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
{{ config('app.name', 'GreenLeaf Nursery') }}

---
This is a transactional message from GreenLeaf Nursery. Marketing preferences do not disable order and account emails.
@endcomponent
