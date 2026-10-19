@extends('layouts.public')

@php
    $communes = collect(app(\App\Services\Referentiels::class)->communes())->mapWithKeys(fn ($c) => [$c['id'] => $c['nom']])->all();
    $photo = $membre->getFirstMediaUrl('photo', 'avatar');
@endphp

@section('titre', 'Mon profil')

@section('contenu')
    <div class="mx-auto max-w-2xl">
        <x-menu-espace />
        <h1 class="text-2xl font-bold">Mon profil</h1>

        <form method="POST" action="{{ route('espace.profil.update') }}" enctype="multipart/form-data" class="mt-4 space-y-5 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70 sm:p-6" novalidate>
            @csrf
            @method('PUT')
            <div class="flex items-center gap-4">
                @if ($photo)
                    <img src="{{ $photo }}" alt="Votre photo" width="64" height="64" class="size-16 rounded-full object-cover">
                @endif
                <div class="flex-1 space-y-1.5">
                    <label for="photo" class="block text-sm font-medium text-slate-800">Photo (facultatif)</label>
                    <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm">
                    @error('photo')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-champ name="prenoms" label="Prénoms" :valeur="$membre->prenoms" autocomplete="given-name" required />
                <x-champ name="nom" label="Nom" :valeur="$membre->nom" autocomplete="family-name" required />
            </div>
            <x-liste name="commune_id" label="Commune (facultatif)" :options="$communes" :valeur="$membre->commune_id" vide="—" />
            <x-champ name="email" type="email" label="E-mail (facultatif)" :valeur="$membre->email" autocomplete="email" />
            <x-bouton>Enregistrer</x-bouton>
        </form>

        <section class="mt-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70 sm:p-6" aria-labelledby="titre-numero">
            <h2 id="titre-numero" class="text-lg font-bold">Numéro de téléphone</h2>
            <p class="mt-1 text-slate-700">{{ $membre->telephone ? app(\App\Services\TelephoneService::class)->afficher($membre->telephone, $membre->pays_code) : '—' }}
                @if ($membre->telephone_verifie_le) <span class="text-sm text-green-800">· vérifié</span>@endif</p>
            <x-bouton :href="route('espace.numero')" variante="secondaire" class="mt-3">Changer de numéro</x-bouton>
            <x-bouton :href="route('mot-de-passe.changement')" variante="secondaire" class="mt-3">Changer de mot de passe</x-bouton>
        </section>

        <section class="mt-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-red-200 sm:p-6" aria-labelledby="titre-suppression">
            <details @if ($errors->has('mot_de_passe_actuel')) open @endif>
                <summary id="titre-suppression" class="inline-flex min-h-11 cursor-pointer items-center font-bold text-red-800">Supprimer mon compte</summary>
                <p class="mt-2 text-sm text-slate-700">Vos données personnelles (nom, numéro, e-mail, photo), vos alertes et vos notifications seront effacées. Vos publications resteront visibles sous le nom « Utilisateur supprimé ». Cette action est définitive.</p>
                <form method="POST" action="{{ route('espace.profil.supprimer') }}" class="mt-3 space-y-3">
                    @csrf
                    @method('DELETE')
                    <x-champ name="mot_de_passe_actuel" type="password" label="Confirmez avec votre mot de passe" autocomplete="current-password" required />
                    <x-bouton variante="danger">Supprimer définitivement mon compte</x-bouton>
                </form>
            </details>
        </section>
    </div>
@endsection
