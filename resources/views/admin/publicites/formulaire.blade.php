@extends('admin.layout')

@php
    $formats = collect($emplacements)->mapWithKeys(fn ($e) => [$e->value => [
        'principal' => $e->formatPrincipal()['largeur'].' × '.$e->formatPrincipal()['hauteur'],
        'secondaire' => $e->formatSecondaire() ? $e->formatSecondaire()['largeur'].' × '.$e->formatSecondaire()['hauteur'].' — '.$e->formatSecondaire()['libelle'] : null,
    ]])->all();
    $urlPrincipale = $publicite->exists ? $publicite->urlImage(\App\Models\Publicite::IMAGE_PRINCIPALE) : null;
    $urlSecondaire = $publicite->exists ? $publicite->urlImage(\App\Models\Publicite::IMAGE_SECONDAIRE) : null;
@endphp

@section('titre', $publicite->exists ? 'Publicité '.$publicite->titre : 'Nouvelle publicité')

@section('contenu')
    <a href="{{ route('admin.publicites.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand hover:underline">← Publicités</a>
    <div class="flex flex-wrap items-center gap-3">
        <h1 class="text-2xl font-bold">{{ $publicite->exists ? 'Publicité « '.$publicite->titre.' »' : 'Nouvelle publicité' }}</h1>
        @if ($publicite->exists)
            @include('admin.publicites._etat', ['etat' => $publicite->etat()])
        @endif
    </div>

    @if ($publicite->exists)
        <dl class="mt-3 grid max-w-3xl grid-cols-3 gap-3">
            <div class="rounded-xl bg-white p-3 ring-1 ring-slate-200/70"><dt class="text-xs text-slate-500">Impressions</dt><dd class="text-xl font-bold">{{ number_format($publicite->nb_impressions, 0, ',', ' ') }}</dd></div>
            <div class="rounded-xl bg-white p-3 ring-1 ring-slate-200/70"><dt class="text-xs text-slate-500">Clics</dt><dd class="text-xl font-bold">{{ number_format($publicite->nb_clics, 0, ',', ' ') }}</dd></div>
            <div class="rounded-xl bg-white p-3 ring-1 ring-slate-200/70"><dt class="text-xs text-slate-500">Taux de clic</dt><dd class="text-xl font-bold">{{ $publicite->tauxDeClic() === null ? '—' : number_format((float) $publicite->tauxDeClic(), 2, ',', ' ').' %' }}</dd></div>
        </dl>
    @endif

    <form method="POST" enctype="multipart/form-data"
          action="{{ $publicite->exists ? route('admin.publicites.update', $publicite) : route('admin.publicites.store') }}"
          class="mt-4 grid gap-6 xl:grid-cols-2"
          x-data="{ emplacement: @js(old('emplacement', $publicite->emplacement?->value)), formats: @js($formats), apercu: null, apercuSecondaire: null }">
        @csrf
        @if ($publicite->exists) @method('PUT') @endif

        <div class="space-y-6">
            <section aria-labelledby="titre-emplacement" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/70">
                <h2 id="titre-emplacement" class="font-semibold">Emplacement</h2>
                @error('emplacement')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                    @foreach ($emplacements as $emplacement)
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 transition-colors hover:bg-slate-50 has-[:checked]:border-brand has-[:checked]:bg-brand-clair">
                            <input type="radio" name="emplacement" value="{{ $emplacement->value }}" x-model="emplacement" class="mt-1 size-4 text-brand"
                                   @checked(old('emplacement', $publicite->emplacement?->value) === $emplacement->value)>
                            <span>
                                <span class="block text-sm font-semibold">{{ $emplacement->libelle() }}</span>
                                <span class="block text-xs text-slate-600">{{ $emplacement->formatPrincipal()['largeur'] }} × {{ $emplacement->formatPrincipal()['hauteur'] }} · {{ $emplacement->description() }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </section>

            <section aria-labelledby="titre-annonce" class="space-y-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/70">
                <h2 id="titre-annonce" class="font-semibold">Annonce</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-champ name="titre" label="Nom interne" :valeur="$publicite->titre" maxlength="120" required aide="Pour l’équipe, jamais affiché." />
                    <x-champ name="annonceur" label="Annonceur" :valeur="$publicite->annonceur" maxlength="120" required aide="Affiché sous la bannière." />
                </div>
                <x-champ name="lien" type="url" label="Lien de destination (https)" :valeur="$publicite->lien" maxlength="500" required placeholder="https://" />
                <x-champ name="texte_alternatif" label="Description de l’image" :valeur="$publicite->texte_alternatif" maxlength="200" required
                         aide="Lue par les lecteurs d’écran, et affichée si l’image ne charge pas. Exemple : « Banque X : ouvrez un compte en 10 minutes »." />
            </section>
        </div>

        <div class="space-y-6">
            <section aria-labelledby="titre-images" class="space-y-5 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/70">
                <h2 id="titre-images" class="font-semibold">Images</h2>

                <div>
                    <label for="image_principale" class="block text-sm font-medium text-slate-800">
                        Image principale <span class="font-normal text-slate-500" x-text="emplacement ? '(' + formats[emplacement].principal + ' px)' : ''"></span>
                    </label>
                    <input id="image_principale" name="image_principale" type="file" accept="image/jpeg,image/png,image/webp,image/gif" @if (! $publicite->exists) required @endif
                           @change="apercu = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null"
                           class="mt-1.5 block w-full text-sm file:mr-3 file:min-h-10 file:rounded-lg file:border-0 file:bg-brand-clair file:px-3 file:font-semibold file:text-brand-fonce">
                    @error('image_principale')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                    <p class="mt-1 text-xs text-slate-500">JPG, PNG, WebP ou GIF, 2 Mo au plus. Au moins la taille indiquée, dans ces proportions (une image deux fois plus grande reste nette sur les écrans haute définition).</p>
                    <div class="mt-2 flex max-h-72 justify-center overflow-hidden rounded-xl bg-slate-50 p-2 ring-1 ring-slate-200" x-show="apercu || @js((bool) $urlPrincipale)">
                        <img :src="apercu ?? @js($urlPrincipale)" alt="Aperçu de l’image principale" class="max-h-64 w-auto object-contain">
                    </div>
                </div>

                <div x-show="emplacement && formats[emplacement].secondaire">
                    <label for="image_secondaire" class="block text-sm font-medium text-slate-800">
                        Image secondaire, facultative <span class="font-normal text-slate-500" x-text="emplacement && formats[emplacement].secondaire ? '(' + formats[emplacement].secondaire + ')' : ''"></span>
                    </label>
                    <input id="image_secondaire" name="image_secondaire" type="file" accept="image/jpeg,image/png,image/webp,image/gif"
                           @change="apercuSecondaire = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null"
                           class="mt-1.5 block w-full text-sm file:mr-3 file:min-h-10 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:font-semibold file:text-slate-700">
                    @error('image_secondaire')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                    <p class="mt-1 text-xs text-slate-500">Sans elle, l’image principale est affichée partout, mise à l’échelle.</p>
                    <div class="mt-2 flex max-h-72 justify-center overflow-hidden rounded-xl bg-slate-50 p-2 ring-1 ring-slate-200" x-show="apercuSecondaire || @js((bool) $urlSecondaire)">
                        <img :src="apercuSecondaire ?? @js($urlSecondaire)" alt="Aperçu de l’image secondaire" class="max-h-64 w-auto object-contain">
                    </div>
                    @if ($urlSecondaire)
                        <x-case name="retirer_image_secondaire" label="Retirer l’image secondaire" />
                    @endif
                </div>
            </section>

            <section aria-labelledby="titre-diffusion" class="space-y-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/70">
                <h2 id="titre-diffusion" class="font-semibold">Diffusion</h2>
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-champ name="debut_le" type="date" label="Du (facultatif)" :valeur="$publicite->debut_le?->toDateString()" />
                    <x-champ name="fin_le" type="date" label="Au (facultatif)" :valeur="$publicite->fin_le?->toDateString()" />
                    <x-champ name="poids" type="number" min="1" max="10" label="Poids (1 à 10)" :valeur="$publicite->poids" required
                             aide="Poids 2 : deux fois plus d’affichages qu’une annonce de poids 1 au même emplacement." />
                </div>
                <x-case name="active" label="Active (diffusée pendant sa période)" :coche="$publicite->active" />
            </section>

            <div class="flex flex-wrap gap-3">
                <x-bouton>{{ $publicite->exists ? 'Enregistrer' : 'Créer la publicité' }}</x-bouton>
                <x-bouton :href="route('admin.publicites.index')" variante="secondaire">Annuler</x-bouton>
            </div>
        </div>
    </form>
@endsection
