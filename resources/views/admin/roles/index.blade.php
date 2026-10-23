@extends('admin.layout')

@section('titre', 'Rôles et permissions')

@section('contenu')
    @php($P = \App\Support\PermissionsAdmin::class)
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">Rôles et permissions</h1>
            <p class="text-sm text-slate-600">Chaque administrateur a un rôle ; le rôle donne les permissions. Le super administrateur a toujours tout.</p>
        </div>
        <x-bouton :href="route('admin.roles.create')">+ Nouveau rôle</x-bouton>
    </div>

    <ul class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($roles as $role)
            @php($super = $role->name === $P::ROLE_SUPER)
            @php($nbPermissions = $super ? $total : $role->permissions_count)
            <li class="flex flex-col rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/70">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        @include('admin.administrateurs._role', ['role' => $role->name])
                        <p class="mt-2 text-sm text-slate-600">{{ $comptes[$role->id] ?? 0 }} compte(s)</p>
                    </div>
                    @if (in_array($role->name, $P::ROLES_SYSTEME, true))
                        <span class="text-xs text-slate-500">Livré</span>
                    @endif
                </div>
                <div class="mt-4">
                    <div class="flex justify-between text-xs text-slate-600"><span>Permissions</span><span>{{ $nbPermissions }} / {{ $total }}</span></div>
                    <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100" aria-hidden="true">
                        <div class="h-full rounded-full {{ $super ? 'bg-slate-900' : 'bg-brand' }}" style="width: {{ (int) round($nbPermissions / max(1, $total) * 100) }}%"></div>
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap gap-2 pt-1">
                    <x-bouton :href="route('admin.roles.edit', $role)" variante="secondaire" class="min-h-10 px-3 text-sm">
                        {{ $super ? 'Voir les permissions' : 'Régler les permissions' }}
                    </x-bouton>
                    @unless (in_array($role->name, $P::ROLES_SYSTEME, true))
                        <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" data-confirmer="Supprimer le rôle « {{ $P::libelleRole($role->name) }} » ?" data-danger="1">
                            @csrf
                            @method('DELETE')
                            <x-bouton variante="lien" class="min-h-10 px-2 text-sm text-red-700">Supprimer</x-bouton>
                        </form>
                    @endunless
                </div>
            </li>
        @endforeach
    </ul>
@endsection
