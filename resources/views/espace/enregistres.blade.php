@extends('layouts.public')

@section('titre', 'Publications enregistrées')

@section('contenu')
    <div class="mx-auto max-w-2xl">
        <x-menu-espace />
        <h1 class="text-2xl font-bold">Publications enregistrées</h1>

        @if ($publications->isEmpty())
            <div class="mt-6 rounded-2xl bg-white p-6 text-center shadow-sm ring-1 ring-slate-200/70">
                <p class="font-semibold">Vous n’avez rien enregistré pour le moment.</p>
                <p class="mt-1 text-slate-600">Touchez « … » puis « Enregistrer » sur une carte du fil pour la retrouver ici.</p>
            </div>
        @else
            <ol class="mt-4 space-y-4">
                @foreach ($publications as $publication)
                    <li>
                        @if ($publication->statut === $statutPublie)
                            {!! $rendu->rendre($publication) !!}
                        @else
                            <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70">
                                <p class="font-semibold">{{ $publication->titre }}</p>
                                <p class="text-sm text-slate-600">Cette publication n’est plus en ligne ({{ $publication->statut->libelle() }}).</p>
                                <form method="POST" action="{{ route('publications.enregistrer', $publication->id) }}" class="mt-2">
                                    @csrf
                                    <x-bouton variante="lien" class="text-sm">Retirer de mes enregistrements</x-bouton>
                                </form>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ol>
            <div class="mt-4">{{ $publications->links() }}</div>
        @endif
    </div>
@endsection
