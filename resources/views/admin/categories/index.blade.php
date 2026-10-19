@extends('admin.layout')

@section('titre', 'Catégories')

@section('contenu')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold">Catégories</h1>
        <x-bouton :href="route('admin.categories.create')">+ Ajouter une catégorie</x-bouton>
    </div>

    <div class="grid gap-4 xl:grid-cols-3">
        @foreach ($arbre as ['type' => $type, 'principales' => $principales])
            <section aria-labelledby="type-{{ $type->value }}" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70">
                <div class="flex items-center justify-between">
                    <h2 id="type-{{ $type->value }}" class="font-semibold">{{ $type->libelle() }}</h2>
                    <a href="{{ route('admin.categories.create', ['type' => $type->value]) }}" class="text-sm font-semibold text-brand hover:underline">+ Ajouter</a>
                </div>
                <ul class="mt-3 space-y-2">
                    @foreach ($principales as $categorie)
                        <li class="rounded-lg border border-slate-200 p-2">
                            <div class="flex items-center justify-between gap-2">
                                <a href="{{ route('admin.categories.edit', $categorie) }}" class="font-semibold text-slate-900 hover:underline {{ $categorie->actif ? '' : 'line-through opacity-60' }}">
                                    <span aria-hidden="true">{{ $categorie->icone }}</span> {{ $categorie->nom }}
                                </a>
                                @include('admin._deplacer', ['element' => $categorie, 'route' => 'admin.categories.deplacer'])
                            </div>
                            <ul class="mt-1 space-y-1 pl-4">
                                @foreach ($enfants->get($categorie->id, collect()) as $enfant)
                                    <li class="flex items-center justify-between gap-2 text-sm">
                                        <a href="{{ route('admin.categories.edit', $enfant) }}" class="hover:underline {{ $enfant->actif ? '' : 'line-through opacity-60' }}">— {{ $enfant->nom }}</a>
                                        @include('admin._deplacer', ['element' => $enfant, 'route' => 'admin.categories.deplacer'])
                                    </li>
                                @endforeach
                                <li><a href="{{ route('admin.categories.create', ['type' => $type->value, 'parent_id' => $categorie->id]) }}" class="text-xs font-semibold text-brand hover:underline">+ Sous-catégorie</a></li>
                            </ul>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    </div>
@endsection
