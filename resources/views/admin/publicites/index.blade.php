@extends('admin.layout')

@section('titre', 'Publicités')

@section('contenu')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">Publicités</h1>
            <p class="text-sm text-slate-600">
                Bannières des emplacements du site. Une annonce est tirée à chaque page, au prorata de son poids ; la période démarre et arrête la campagne toute seule.
            </p>
        </div>
        <x-bouton :href="route('admin.publicites.create')">+ Nouvelle publicité</x-bouton>
    </div>

    {{-- Réglages généraux (paramètres publicites_actives et publicite_contact) --}}
    <form method="POST" action="{{ route('admin.publicites.reglages') }}"
          @class(['mb-6 flex flex-wrap items-end gap-x-6 gap-y-3 rounded-2xl p-4 shadow-sm ring-1', 'bg-white ring-slate-200/70' => $actives, 'bg-red-50 ring-red-200' => ! $actives])>
        @csrf
        @method('PUT')
        <div class="min-w-56">
            <p class="text-sm font-semibold {{ $actives ? 'text-green-800' : 'text-red-800' }}">
                {{ $actives ? 'Publicités affichées sur le site' : 'Publicités coupées sur le site' }}
            </p>
            <x-case name="publicites_actives" label="Afficher les publicités et « Annoncez ici »" :coche="$actives" />
        </div>
        <div class="min-w-72 flex-1">
            <x-champ name="publicite_contact" label="Lien du bouton « Annoncez ici »" :valeur="$contact" maxlength="500"
                     placeholder="mailto:pub@rezohelp.ci" aide="mailto:…, tel:…, https://… ou /chemin du site. Vide : page Tarifs." />
        </div>
        <x-bouton variante="secondaire" class="mb-6">Enregistrer</x-bouton>
    </form>

    {{-- Plan des emplacements --}}
    <section aria-labelledby="titre-emplacements" class="mb-6">
        <h2 id="titre-emplacements" class="sr-only">Emplacements</h2>
        <ul class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            @foreach ($emplacements as $emplacement)
                @php($stats = $statistiques->get($emplacement->value))
                @php($enCours = (int) ($stats->en_cours ?? 0))
                @php($format = $emplacement->formatPrincipal())
                <li class="flex flex-col rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70">
                    <div class="flex items-start gap-3">
                        {{-- Silhouette du format, à l'échelle --}}
                        <span aria-hidden="true" class="flex size-12 shrink-0 items-center justify-center rounded-lg bg-slate-50 ring-1 ring-slate-200">
                            <span class="block rounded-sm {{ $enCours > 0 ? 'bg-brand' : 'bg-slate-300' }}"
                                  style="width: {{ max(4, (int) round(36 * min(1, $format['largeur'] / max($format['largeur'], $format['hauteur'])))) }}px; height: {{ max(4, (int) round(36 * min(1, $format['hauteur'] / max($format['largeur'], $format['hauteur'])))) }}px"></span>
                        </span>
                        <div class="min-w-0">
                            <p class="font-semibold leading-tight">{{ $emplacement->libelle() }}</p>
                            <p class="text-xs text-slate-500">{{ $format['largeur'] }} × {{ $format['hauteur'] }} · {{ $emplacement->estVerticale() ? 'vertical' : 'horizontal' }}</p>
                        </div>
                    </div>
                    <p class="mt-2 flex-1 text-xs text-slate-600">{{ $emplacement->description() }}</p>
                    <p class="mt-3 text-sm">
                        @if ($enCours > 0)
                            <span class="font-semibold text-green-800">{{ $enCours }} en cours</span>
                        @else
                            <span class="font-semibold text-amber-800">Libre</span>
                            <span class="text-xs text-slate-500">{{ $emplacement->afficheAnnoncezIci() ? '(« Annoncez ici »)' : '(rien d’affiché)' }}</span>
                        @endif
                    </p>
                    <p class="text-xs text-slate-500">{{ number_format((int) ($stats->impressions ?? 0), 0, ',', ' ') }} impressions · {{ number_format((int) ($stats->clics ?? 0), 0, ',', ' ') }} clics</p>
                    <a href="{{ route('admin.publicites.create', ['emplacement' => $emplacement->value]) }}" class="mt-2 inline-flex min-h-9 items-center text-sm font-semibold text-brand hover:underline">+ Ajouter ici</a>
                </li>
            @endforeach
        </ul>
    </section>

    <x-admin.filtres id="filtres-publicites">
        <x-liste name="emplacement" label="Emplacement" vide="Tous" :options="\App\Enums\EmplacementPublicite::options()" />
        <x-liste name="etat" label="État" vide="Tous" :options="['en_cours' => 'En cours', 'programmee' => 'Programmée', 'terminee' => 'Terminée', 'inactive' => 'Inactive']" />
    </x-admin.filtres>

    <x-admin.table libelle="Publicités" :table="$table" :url="route('admin.publicites.index')" filtres="#filtres-publicites" />
    <p class="mt-2 text-xs text-slate-500">Une impression est comptée quand la moitié de la bannière reste visible une seconde. Les chiffres sont mis à jour toutes les 5 minutes.</p>
@endsection
