@props([
    'plan',
    'entreprise' => null,
    'variante' => 'primaire',
])
{{-- Démarrage d'un essai gratuit en un clic, sans paiement (CONTEXTE §14.6). --}}
<form method="POST" action="{{ route('essais.store') }}" {{ $attributes->class('contents') }}>
    @csrf
    <input type="hidden" name="plan" value="{{ $plan }}">
    @if ($entreprise)
        <input type="hidden" name="entreprise_id" value="{{ $entreprise }}">
    @endif
    <x-bouton :variante="$variante">{{ $slot }}</x-bouton>
</form>
