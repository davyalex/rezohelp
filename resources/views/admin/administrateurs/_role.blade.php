@php($P = \App\Support\PermissionsAdmin::class)
@if ($role === null)
    <span class="text-slate-500">—</span>
@else
    <span @class([
        'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold',
        'bg-slate-900 text-white' => $role === $P::ROLE_SUPER,
        'bg-brand-clair text-brand-fonce' => $role === $P::ROLE_ADMIN,
        'bg-amber-100 text-amber-900' => $role === $P::ROLE_MODERATEUR,
        'bg-slate-100 text-slate-700' => ! in_array($role, $P::ROLES_SYSTEME, true),
    ])>{{ $P::libelleRole($role) }}</span>
@endif
