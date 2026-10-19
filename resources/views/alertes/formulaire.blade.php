@extends('layouts.public')

@php
    use App\Enums\FrequenceAlerte;
    use App\Enums\TypeContrat;
    use App\Enums\TypePublication;

    $referentiels = app(\App\Services\Referentiels::class);
    $communes = collect($referentiels->communes())->mapWithKeys(fn ($c) => [$c['id'] => $c['nom']])->all();
    $groupes = [];
    foreach (TypePublication::cases() as $type) {
        $groupes[$type->libelle()] = collect($referentiels->categories())->where('type', $type->value)
            ->mapWithKeys(fn ($c) => [$c['id'] => ($c['parent_id'] ? '— ' : '').$c['nom']])->all();
    }
    $v = fn (string $cle) => $valeurs[$cle] ?? null;
@endphp

@section('titre', $alerte ? 'Modifier l’alerte' : 'Créer une alerte')

@section('contenu')
    <x-carte-formulaire :titre="$alerte ? 'Modifier l’alerte' : 'Créer une alerte'" sous-titre="Choisissez au moins un critère. Nous vous prévenons dès qu’une publication correspond.">
        <form method="POST" action="{{ $alerte ? route('alertes.update', $alerte->id) : route('alertes.store') }}" class="space-y-5" novalidate>
            @csrf
            @if ($alerte)
                @method('PUT')
            @endif
            <x-liste name="type" label="Type" :options="TypePublication::options()" :valeur="$v('type')" vide="Tous les types" />
            <x-liste name="categorie_id" label="Catégorie ou métier" :groupes="$groupes" :valeur="$v('categorie_id')" vide="Toutes les catégories" />
            <x-liste name="commune_id" label="Commune" :options="$communes" :valeur="$v('commune_id')" vide="Toutes les communes" />
            <x-liste name="type_contrat" label="Type de contrat (offres d’emploi)" :options="TypeContrat::options()" :valeur="$v('type_contrat')" vide="Tous les contrats" />
            <x-champ name="mots_cles" label="Mots-clés (facultatif)" :valeur="$v('mots_cles')" maxlength="150" placeholder="Ex. comptable, chauffeur" aide="Tous les mots doivent apparaître dans l’annonce." />
            <fieldset>
                <legend class="text-sm font-medium text-slate-800">Fréquence</legend>
                @foreach (FrequenceAlerte::cases() as $frequence)
                    <label class="flex min-h-11 items-center gap-3 text-sm">
                        <input type="radio" name="frequence" value="{{ $frequence->value }}" class="size-5 text-brand" @checked(old('frequence', $v('frequence')) === $frequence->value)>
                        {{ $frequence->libelle() }}
                    </label>
                @endforeach
                @error('frequence')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
            </fieldset>
            <x-champ name="nom" label="Nom de l’alerte (facultatif)" :valeur="$v('nom')" maxlength="100" aide="Sinon, nous la nommons d’après vos critères." />
            <x-bouton class="w-full">{{ $alerte ? 'Enregistrer' : 'Créer l’alerte' }}</x-bouton>
        </form>
        <p class="mt-4 text-center"><a href="{{ route('alertes.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand hover:underline">Mes alertes</a></p>
    </x-carte-formulaire>
@endsection
