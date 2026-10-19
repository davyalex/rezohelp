@extends('layouts.public')

@section('titre', 'Mon espace')

@section('contenu')
    <div class="mx-auto max-w-3xl">
        <x-menu-espace />
        <h1 class="text-2xl font-bold">Bonjour {{ $membre->prenoms ?: $membre->nom }}</h1>

        @if ($membre->telephone_verifie_le === null)
            <x-message type="info" class="mt-4">
                Votre numéro n’est pas encore vérifié.
                <a href="{{ route('telephone.verification') }}" class="font-semibold underline">Le vérifier maintenant</a>
            </x-message>
        @endif

        <ul class="mt-6 grid gap-4 sm:grid-cols-2">
            @foreach ([
                ['route' => 'espace.publications.index', 'titre' => 'Mes publications', 'detail' => $nbPublications.' publication(s)', 'icone' => '📝'],
                ['route' => 'alertes.index', 'titre' => 'Mes alertes', 'detail' => $nbAlertes.' alerte(s) active(s)', 'icone' => '🔔'],
                ['route' => 'espace.notifications', 'titre' => 'Notifications', 'detail' => $nbNonLues.' non lue(s)', 'icone' => '📬'],
                ['route' => 'espace.enregistres', 'titre' => 'Enregistrés', 'detail' => $nbEnregistres.' publication(s)', 'icone' => '🔖'],
                ['route' => 'espace.abonnement', 'titre' => 'Mon abonnement', 'detail' => 'Formules, compteurs et reçus', 'icone' => '⭐'],
            ] as $carte)
                <li>
                    <a href="{{ route($carte['route']) }}" class="flex min-h-24 items-center gap-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/70 hover:ring-brand">
                        <span aria-hidden="true" class="text-3xl">{{ $carte['icone'] }}</span>
                        <span>
                            <span class="block font-bold text-slate-900">{{ $carte['titre'] }}</span>
                            <span class="block text-sm text-slate-600">{{ $carte['detail'] }}</span>
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="mt-6 flex flex-col gap-2 sm:flex-row">
            <x-bouton :href="route('publier.choix')">+ Publier</x-bouton>
            <x-bouton :href="route('espace.profil')" variante="secondaire">Modifier mon profil</x-bouton>
        </div>
    </div>
@endsection
