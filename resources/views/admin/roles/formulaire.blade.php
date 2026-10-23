@extends('admin.layout')

@php($P = \App\Support\PermissionsAdmin::class)
@php($super = $role->exists && $role->name === $P::ROLE_SUPER)
@php($libelle = $role->exists ? $P::libelleRole($role->name) : 'Nouveau rôle')

@section('titre', $libelle)

@section('contenu')
    <a href="{{ route('admin.roles.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand hover:underline">← Rôles et permissions</a>
    <h1 class="text-2xl font-bold">{{ $role->exists ? 'Rôle « '.$libelle.' »' : $libelle }}</h1>
    @if ($super)
        <p class="mt-1 text-sm text-slate-600">Le super administrateur a toujours toutes les permissions : ce rôle ne se modifie pas.</p>
    @endif

    <form method="POST" action="{{ $role->exists ? route('admin.roles.update', $role) : route('admin.roles.store') }}" class="mt-4 space-y-4"
          x-data="{ cochees: @js(old('permissions', $cochees)) }">
        @csrf
        @if ($role->exists) @method('PUT') @endif

        @unless ($role->exists)
            <div class="max-w-md rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/70">
                <x-champ name="libelle" label="Nom du rôle" maxlength="50" required aide="Par exemple « Support » ou « Comptabilité »." />
            </div>
        @endunless

        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($P::parGroupe() as $groupe => $cles)
                <fieldset class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/70" @disabled($super)>
                    <div class="flex items-center justify-between gap-3">
                        <legend class="font-semibold">{{ $groupe }}</legend>
                        @unless ($super)
                            <button type="button" class="text-xs font-semibold text-brand hover:underline"
                                    @click="const cles = @js($cles); const toutes = cles.every(c => cochees.includes(c)); cochees = toutes ? cochees.filter(c => !cles.includes(c)) : [...new Set([...cochees, ...cles])]">
                                Tout cocher / décocher
                            </button>
                        @endunless
                    </div>
                    <ul class="mt-3 space-y-1">
                        @foreach ($cles as $cle)
                            <li>
                                <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg px-2 hover:bg-slate-50">
                                    <input type="checkbox" name="permissions[]" value="{{ $cle }}" x-model="cochees" class="size-5 rounded text-brand"
                                           @checked(in_array($cle, old('permissions', $cochees), true))>
                                    <span class="flex-1 text-sm">{{ $P::libelle($cle) }}</span>
                                    <code class="hidden text-xs text-slate-400 sm:inline">{{ $cle }}</code>
                                </label>
                            </li>
                        @endforeach
                    </ul>
                </fieldset>
            @endforeach
        </div>

        @unless ($super)
            <div class="sticky bottom-0 -mx-4 flex flex-wrap items-center gap-3 border-t border-slate-200 bg-white/95 px-4 py-3 backdrop-blur lg:-mx-6 lg:px-6">
                <x-bouton>{{ $role->exists ? 'Enregistrer les permissions' : 'Créer le rôle' }}</x-bouton>
                <span class="text-sm text-slate-600"><span x-text="cochees.length">{{ count($cochees) }}</span> permission(s) cochée(s)</span>
            </div>
        @endunless
    </form>
@endsection
