@extends('layouts.public')

@section('titre', 'Mes alertes')

@section('contenu')
    <div class="mx-auto max-w-2xl">
        <x-menu-espace />
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-bold">Mes alertes</h1>
            @if ($autorisation->allowed())
                <x-bouton :href="route('alertes.create')">+ Nouvelle alerte</x-bouton>
            @endif
        </div>
        <p class="mt-1 text-slate-600">Nous vous prévenons dans « Notifications » dès qu’une publication correspond.</p>

        @if ($autorisation->denied())
            <x-message type="info" class="mt-4">{{ $autorisation->message() }}</x-message>
        @endif
        @error('alerte')<x-message type="erreur" class="mt-4">{{ $message }}</x-message>@enderror

        @if ($alertes->isEmpty())
            <div class="mt-6 rounded-2xl bg-white p-6 text-center shadow-sm ring-1 ring-slate-200/70">
                <p class="font-semibold">Vous n’avez pas encore d’alerte.</p>
                <p class="mt-1 text-slate-600">Exemple : « Électricien · Cocody » ou « Stage · Plateau ».</p>
            </div>
        @else
            <ul class="mt-4 space-y-3">
                @foreach ($alertes as $alerte)
                    <li class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p class="font-bold text-slate-900">{{ $alerte->nom }}</p>
                                <p class="text-sm text-slate-600">
                                    {{ $alerte->frequence->libelle() }}
                                    @if ($alerte->derniere_notification_le) · dernière correspondance {{ $alerte->derniere_notification_le->diffForHumans() }}@endif
                                </p>
                            </div>
                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $alerte->active ? 'bg-green-100 text-green-900' : 'bg-slate-100 text-slate-700' }}">
                                {{ $alerte->active ? 'Active' : 'En pause' }}
                            </span>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2 border-t border-slate-100 pt-3">
                            <x-bouton :href="$correspondance->urlFil($alerte)" variante="secondaire" class="text-sm">Voir les résultats</x-bouton>
                            <x-bouton :href="route('alertes.edit', $alerte->id)" variante="secondaire" class="text-sm">Modifier</x-bouton>
                            <form method="POST" action="{{ route('alertes.basculer', $alerte->id) }}">
                                @csrf
                                <x-bouton variante="secondaire" class="text-sm">{{ $alerte->active ? 'Mettre en pause' : 'Reprendre' }}</x-bouton>
                            </form>
                            <form method="POST" action="{{ route('alertes.destroy', $alerte->id) }}" x-data @submit="confirm('Supprimer cette alerte ?') || $event.preventDefault()">
                                @csrf
                                @method('DELETE')
                                <x-bouton variante="lien" class="text-sm text-red-700">Supprimer</x-bouton>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
