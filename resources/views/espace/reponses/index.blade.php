@extends('layouts.public')

@php
    use App\Enums\StatutReponse;
    $telephones = app(\App\Services\TelephoneService::class);
@endphp

@section('titre', 'Réponses reçues')

@section('contenu')
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('espace.publications.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand hover:underline">← Mes publications</a>
        <h1 class="text-2xl font-bold">Réponses reçues</h1>
        <p class="mt-1 text-slate-600">Pour « {{ $publication->titre }} ».</p>

        @if ($reponses->isEmpty())
            <div class="mt-6 rounded-2xl bg-white p-6 text-center shadow-sm ring-1 ring-slate-200/70">
                <p class="font-semibold">Aucune réponse pour le moment.</p>
                <p class="mt-1 text-slate-600">Partagez votre publication pour qu’elle soit vue plus vite.</p>
            </div>
        @else
            <ul class="mt-6 space-y-4">
                @foreach ($reponses as $reponse)
                    <li class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <p class="font-semibold">{{ $reponse->utilisateur->nomAffiche() }} <span class="text-sm font-normal text-slate-600">· {{ $reponse->type->libelle() }}</span></p>
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">{{ $reponse->statut->libelle() }}</span>
                        </div>
                        <p class="text-sm text-slate-600">{{ $reponse->cree_le?->diffForHumans() }}</p>
                        @if ($reponse->message)
                            <p class="mt-2 whitespace-pre-line text-slate-800">{{ $reponse->message }}</p>
                        @endif
                        @if ($reponse->telephone_partage && $reponse->utilisateur->telephone)
                            <p class="mt-2">
                                <a href="tel:{{ $reponse->utilisateur->telephone }}" class="inline-flex min-h-11 items-center font-semibold text-brand hover:underline">
                                    📞 {{ $telephones->afficher($reponse->utilisateur->telephone, auth()->user()->pays_code) }}
                                </a>
                            </p>
                        @else
                            <p class="mt-2 text-sm text-slate-600">Numéro non partagé.</p>
                        @endif
                        <div class="mt-3 flex flex-wrap gap-2 border-t border-slate-100 pt-3">
                            @foreach ([StatutReponse::Vue, StatutReponse::Retenue, StatutReponse::Refusee] as $statut)
                                @if ($reponse->statut !== $statut)
                                    <form method="POST" action="{{ route('espace.reponses.update', $reponse->id) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="statut" value="{{ $statut->value }}">
                                        <x-bouton variante="secondaire" class="min-h-11 text-sm">Marquer « {{ $statut->libelle() }} »</x-bouton>
                                    </form>
                                @endif
                            @endforeach
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
