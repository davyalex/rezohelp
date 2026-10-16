@props(['type' => 'succes'])
@if (trim($slot) !== '')
    <div role="{{ $type === 'erreur' ? 'alert' : 'status' }}"
         {{ $attributes->class([
             'rounded-lg border px-4 py-3 text-sm',
             'border-green-200 bg-green-50 text-green-900' => $type === 'succes',
             'border-red-200 bg-red-50 text-red-900' => $type === 'erreur',
             'border-amber-200 bg-amber-50 text-amber-900' => $type === 'info',
         ]) }}>
        {{ $slot }}
    </div>
@endif
