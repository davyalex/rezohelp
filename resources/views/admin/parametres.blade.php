@extends('admin.layout')

@section('titre', 'Paramètres')

@section('contenu')
    <h1 class="text-2xl font-bold">Paramètres</h1>
    <p class="text-sm text-slate-600">Les limites par utilisateur (alertes, photos, offres…) se règlent dans les plans, pas ici.</p>

    <section aria-labelledby="titre-cache" class="mt-4 flex flex-wrap items-center gap-2 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70">
        <h2 id="titre-cache" class="mr-2 font-semibold">Cache</h2>
        <form method="POST" action="{{ route('admin.cache.vider-fil') }}" data-confirmer="Vider le cache du fil ? Les salves seront recalculées.">
            @csrf
            <x-bouton variante="secondaire" class="text-sm">Vider le cache du fil</x-bouton>
        </form>
        <form method="POST" action="{{ route('admin.cache.vider-referentiels') }}" data-confirmer="Vider le cache des référentiels (pays, communes, catégories, paramètres) ?">
            @csrf
            <x-bouton variante="secondaire" class="text-sm">Vider les référentiels</x-bouton>
        </form>
    </section>

    <form method="POST" action="{{ route('admin.parametres.update') }}" class="mt-4 space-y-3">
        @csrf
        @method('PUT')
        @foreach ($parametres as $parametre)
            @php
                $nom = 'parametres['.$parametre->cle.']';
                $id = 'parametre-'.$parametre->cle;
                $erreur = $errors->first('parametres.'.$parametre->cle);
                $valeur = old('parametres.'.$parametre->cle, $parametre->type === \App\Enums\TypeParametre::Json
                    ? json_encode(json_decode((string) $parametre->valeur, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                    : $parametre->valeur);
            @endphp
            <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70">
                <label for="{{ $id }}" class="block font-medium text-slate-900">{{ $parametre->libelle }}</label>
                <p class="text-xs text-slate-500">
                    <code>{{ $parametre->cle }}</code>
                    @if ($parametre->modifie_le) · modifié le {{ $parametre->modifie_le->format('d/m/Y H:i') }}{{ $parametre->modifiePar ? ' par '.$parametre->modifiePar->nomAffiche() : '' }}@endif
                </p>
                <div class="mt-2">
                    @switch($parametre->type)
                        @case(\App\Enums\TypeParametre::Booleen)
                            <input type="hidden" name="{{ $nom }}" value="0">
                            <label class="inline-flex min-h-11 items-center gap-2 text-sm">
                                <input id="{{ $id }}" type="checkbox" name="{{ $nom }}" value="1" @checked((bool) $valeur) class="size-5 rounded border-slate-300">
                                Activé
                            </label>
                            @break
                        @case(\App\Enums\TypeParametre::Entier)
                            <input id="{{ $id }}" type="number" min="0" name="{{ $nom }}" value="{{ $valeur }}" class="min-h-11 w-48 rounded-lg border border-slate-300 px-3">
                            @break
                        @case(\App\Enums\TypeParametre::Texte)
                            <input id="{{ $id }}" type="text" name="{{ $nom }}" value="{{ $valeur }}" maxlength="2000" class="min-h-11 w-full rounded-lg border border-slate-300 px-3">
                            @break
                        @default
                            <textarea id="{{ $id }}" name="{{ $nom }}" rows="{{ $parametre->type === \App\Enums\TypeParametre::Html ? 6 : 8 }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm">{{ $valeur }}</textarea>
                    @endswitch
                </div>
                @if ($erreur)
                    <p class="mt-1 text-sm text-red-700">{{ $erreur }}</p>
                @endif
            </div>
        @endforeach
        <div class="sticky bottom-0 bg-fond/90 py-3 backdrop-blur">
            <x-bouton>Enregistrer les paramètres</x-bouton>
        </div>
    </form>
@endsection
