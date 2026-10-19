@props(['id'])
{{-- Filtres d'une liste : leurs valeurs accompagnent chaque requête DataTables (admin.js). --}}
<form id="{{ $id }}" {{ $attributes->class('mb-4 grid gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70 sm:grid-cols-2 lg:grid-cols-4') }} role="search" aria-label="Filtres">
    {{ $slot }}
</form>
