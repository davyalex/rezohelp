<?php

namespace App\Http\Controllers\Espace;

use App\Http\Controllers\Controller;
use App\Models\Utilisateur;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * « Mon espace → Notifications » : liste, ouverture (marquée lue), tout marquer comme lu.
 */
class NotificationController extends Controller
{
    public const int PAR_PAGE = 20;

    public function index(Request $request): View
    {
        return view('espace.notifications', [
            'notifications' => $this->membre($request)->notifications()->paginate(self::PAR_PAGE),
        ]);
    }

    public function ouvrir(Request $request, string $notification): RedirectResponse
    {
        $notification = $this->membre($request)->notifications()->whereKey($notification)->firstOrFail();
        $notification->markAsRead();

        $lien = $notification->data['lien'] ?? null;

        // Seuls les liens internes sont suivis (pas de redirection ouverte).
        return is_string($lien) && str_starts_with($lien, rtrim((string) config('app.url'), '/').'/')
            ? redirect()->to($lien)
            : redirect()->route('espace.notifications');
    }

    public function toutLu(Request $request): RedirectResponse
    {
        $this->membre($request)->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('statut', 'Toutes vos notifications sont marquées comme lues.');
    }

    private function membre(Request $request): Utilisateur
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof Utilisateur, 401);

        return $utilisateur;
    }
}
