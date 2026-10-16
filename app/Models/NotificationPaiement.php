<?php

namespace App\Models;

use App\Models\Concerns\HorodatageCreation;
use Database\Factories\NotificationPaiementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Journal des notifications (webhooks) reçues des passerelles de paiement.
 */
class NotificationPaiement extends Model
{
    /** @use HasFactory<NotificationPaiementFactory> */
    use HasFactory, HorodatageCreation;

    protected $table = 'notifications_paiement';

    /**
     * @var list<string>
     */
    protected $fillable = ['passerelle', 'reference', 'charge_utile', 'signature_valide'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'charge_utile' => 'array',
            'signature_valide' => 'boolean',
            'traitee_le' => 'datetime',
        ];
    }
}
