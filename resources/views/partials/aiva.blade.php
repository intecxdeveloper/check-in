{{-- Aiva, the mascot: real rendered frames from the Cursor Island app (idle, wave, work, done). Decorative. --}}
@php($w = $size ?? 72)
<img src="{{ asset('images/aiva/aiva-'.($pose ?? 'idle').'.webp') }}" alt="" aria-hidden="true" class="aiva {{ $class ?? '' }}" width="{{ $w }}" height="{{ (int) round($w * 285 / 240) }}" decoding="async" loading="{{ $loading ?? 'lazy' }}" @isset($attrs){!! $attrs !!}@endisset>
