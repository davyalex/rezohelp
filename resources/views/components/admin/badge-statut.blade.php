@props(['statut'])
{{-- Statut d'une publication, d'une commande ou d'un abonnement (enum avec libelle()). --}}
@php
    $valeur = $statut instanceof \BackedEnum ? $statut->value : (string) $statut;
    $couleur = match ($valeur) {
        'publiee', 'payee', 'actif', 'termine', 'haut' => 'bg-green-100 text-green-900',
        'en_attente', 'essai', 'grace', 'en_cours', 'moyen' => 'bg-amber-100 text-amber-900',
        'rejetee', 'echouee', 'erreur', 'resilie', 'bas', 'suspendu' => 'bg-red-100 text-red-900',
        default => 'bg-slate-100 text-slate-700',
    };
@endphp
<span {{ $attributes->class(['inline-block whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-semibold', $couleur]) }}>{{ method_exists($statut, 'libelle') ? $statut->libelle() : $valeur }}</span>
