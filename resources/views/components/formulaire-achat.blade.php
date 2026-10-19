@props([
    'type' => 'plan',
    'code',
    'entreprise' => null,
    'publication' => null,
    'variante' => 'primaire',
])
{{-- Achat d'un plan ou d'un produit : seul le code est transmis, le prix vient de la base. --}}
<form method="POST" action="{{ route('commandes.store') }}" {{ $attributes->class('contents') }}>
    @csrf
    <input type="hidden" name="article_type" value="{{ $type }}">
    <input type="hidden" name="code" value="{{ $code }}">
    @if ($entreprise)
        <input type="hidden" name="entreprise_id" value="{{ $entreprise }}">
    @endif
    @if ($publication)
        <input type="hidden" name="publication_id" value="{{ $publication }}">
    @endif
    <x-bouton :variante="$variante">{{ $slot }}</x-bouton>
</form>
