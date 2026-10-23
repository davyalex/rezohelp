{{-- Cloche des notifications de l'équipe (publications masquées après signalements…). --}}
@php
    $admin = auth('admin')->user();
    $nonLues = $admin->unreadNotifications()->latest()->limit(8)->get();
    $total = $admin->unreadNotifications()->count();
@endphp
<div x-data="menuDeroulant" class="relative" @keydown.escape.window="fermer()" @click.outside="fermer(false)"
     @focusout="$event.relatedTarget && !$el.contains($event.relatedTarget) && fermer(false)">
    <button type="button" x-ref="bouton" @click="basculer()" :aria-expanded="ouvert.toString()" aria-expanded="false" aria-haspopup="true" aria-controls="notifications-admin"
            class="relative inline-flex size-10 items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100 hover:text-slate-900 aria-expanded:bg-slate-100">
        <x-admin.icone nom="cloche" />
        <span class="sr-only">Notifications{{ $total > 0 ? ' : '.$total.' non lue(s)' : '' }}</span>
        @if ($total > 0)
            <span aria-hidden="true" class="absolute right-1 top-1 min-w-4 rounded-full bg-red-600 px-1 text-center text-[10px] font-bold leading-4 text-white">{{ $total > 9 ? '9+' : $total }}</span>
        @endif
    </button>
    <div id="notifications-admin" x-ref="panneau" x-cloak x-show="ouvert" x-transition.opacity.duration.100ms
         @keydown.arrow-down.prevent="deplacer(1)" @keydown.arrow-up.prevent="deplacer(-1)"
         class="absolute right-0 top-full z-50 mt-2 w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-slate-200">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
            <p class="font-semibold">Notifications</p>
            @if ($total > 0)
                <form method="POST" action="{{ route('admin.notifications.tout-lire') }}" data-sans-spinner>
                    @csrf
                    <button class="text-xs font-semibold text-brand hover:underline">Tout marquer comme lu</button>
                </form>
            @endif
        </div>
        @forelse ($nonLues as $notification)
            <a href="{{ route('admin.notifications.ouvrir', $notification->id) }}" class="block border-b border-slate-50 px-4 py-3 hover:bg-slate-50 focus-visible:bg-slate-50">
                <span class="block text-sm font-semibold text-slate-900">{{ $notification->data['titre'] ?? 'Notification' }}</span>
                <span class="mt-0.5 line-clamp-2 block text-xs text-slate-600">{{ $notification->data['message'] ?? '' }}</span>
                <span class="mt-1 block text-[11px] text-slate-400">{{ $notification->created_at?->diffForHumans() }}</span>
            </a>
        @empty
            <p class="px-4 py-6 text-center text-sm text-slate-500">Aucune nouvelle notification.</p>
        @endforelse
    </div>
</div>
