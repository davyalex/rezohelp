@extends('layouts.public')

@section('titre', 'Notifications')

@section('contenu')
    <div class="mx-auto max-w-2xl">
        <x-menu-espace />
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-bold">Notifications</h1>
            @if ($notifications->contains(fn ($n) => $n->read_at === null))
                <form method="POST" action="{{ route('espace.notifications.tout-lu') }}">
                    @csrf
                    <x-bouton variante="secondaire" class="text-sm">Tout marquer comme lu</x-bouton>
                </form>
            @endif
        </div>

        @if ($notifications->isEmpty())
            <div class="mt-6 rounded-2xl bg-white p-6 text-center shadow-sm ring-1 ring-slate-200/70">
                <p class="font-semibold">Aucune notification pour le moment.</p>
                <p class="mt-1 text-slate-600">Créez une alerte pour être prévenu des nouvelles offres.</p>
                <x-bouton :href="route('alertes.create')" class="mt-4">Créer une alerte</x-bouton>
            </div>
        @else
            <ul class="mt-4 divide-y divide-slate-100 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/70">
                @foreach ($notifications as $notification)
                    <li>
                        <a href="{{ route('espace.notifications.ouvrir', $notification->id) }}" @class([
                            'flex gap-3 px-4 py-3 hover:bg-slate-50',
                            'bg-brand-clair/50' => $notification->read_at === null,
                        ])>
                            <span aria-hidden="true" class="mt-1.5 size-2.5 shrink-0 rounded-full {{ $notification->read_at === null ? 'bg-brand' : 'bg-transparent' }}"></span>
                            <span class="min-w-0">
                                <span class="block font-semibold text-slate-900">{{ $notification->data['titre'] ?? 'Notification' }}</span>
                                @if (! empty($notification->data['message']))
                                    <span class="block truncate text-sm text-slate-700">{{ $notification->data['message'] }}</span>
                                @endif
                                <span class="block text-xs text-slate-600">{{ $notification->created_at?->diffForHumans() }}@if ($notification->read_at === null)<span class="sr-only"> · non lue</span>@endif</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="mt-4">{{ $notifications->links() }}</div>
        @endif
    </div>
@endsection
