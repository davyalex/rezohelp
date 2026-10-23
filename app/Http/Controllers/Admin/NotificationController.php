<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Notifications de l'équipe (cloche de l'en-tête admin) : publications masquées après signalements…
 */
class NotificationController extends ControleurAdmin
{
    public function ouvrir(Request $requete, string $notification): RedirectResponse
    {
        $notification = $this->admin($requete)->notifications()->findOrFail($notification);
        $notification->markAsRead();

        /** @var array<string, mixed> $donnees */
        $donnees = $notification->data;
        $publication = $donnees['publication_id'] ?? null;

        return is_numeric($publication)
            ? redirect()->route('admin.moderation.show', (int) $publication)
            : redirect()->route('admin.tableau-de-bord');
    }

    public function toutLire(Request $requete): RedirectResponse
    {
        $this->admin($requete)->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }
}
