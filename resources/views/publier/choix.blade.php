@extends('layouts.public')

@section('titre', 'Publier')

@section('contenu')
    <div class="mx-auto max-w-2xl">
        <h1 class="text-2xl font-bold">Que souhaitez-vous publier ?</h1>
        <p class="mt-1 text-slate-600">Publier est gratuit. Votre première publication est vérifiée par notre équipe avant d’apparaître dans le fil.</p>

        @if ($autorisation->denied())
            <x-message type="erreur" class="mt-4">{{ $autorisation->message() }}</x-message>
        @endif

        <ul class="mt-6 grid gap-4 sm:grid-cols-2">
            @foreach ($parcours as $choix)
                <li>
                    <a href="{{ $autorisation->allowed() ? route('publier.formulaire', $choix->value) : '#' }}"
                       @if ($autorisation->denied()) aria-disabled="true" tabindex="-1" @endif
                       @class([
                           'flex h-full min-h-32 flex-col gap-2 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/70',
                           'hover:ring-brand focus-visible:ring-brand' => $autorisation->allowed(),
                           'pointer-events-none opacity-60' => $autorisation->denied(),
                       ])>
                        <span aria-hidden="true" class="text-3xl">{{ $choix->icone() }}</span>
                        <span class="text-lg font-bold text-slate-900">{{ $choix->libelle() }}</span>
                        <span class="text-sm text-slate-600">{{ $choix->description() }}</span>
                    </a>
                </li>
            @endforeach
        </ul>

        <p class="mt-6 text-center">
            <a href="{{ route('espace.publications.index') }}" class="inline-flex min-h-11 items-center font-semibold text-brand hover:underline">Voir mes publications</a>
        </p>
    </div>
@endsection
