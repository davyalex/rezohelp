<?php

namespace App\Models;

use App\Casts\TelephoneE164;
use App\Enums\MotifSms;
use App\Enums\StatutSms;
use App\Models\Concerns\HorodatageCreation;
use Database\Factories\SmsEnvoyeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Journal des SMS : statut, coût et référence du fournisseur (§14.8).
 */
class SmsEnvoye extends Model
{
    /** @use HasFactory<SmsEnvoyeFactory> */
    use HasFactory, HorodatageCreation;

    protected $table = 'sms_envoyes';

    /**
     * @var list<string>
     */
    protected $fillable = ['utilisateur_id', 'telephone', 'message', 'motif', 'fournisseur'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'telephone' => TelephoneE164::class,
            'motif' => MotifSms::class,
            'statut' => StatutSms::class,
            'cout_fcfa' => 'integer',
            'envoye_le' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Utilisateur, $this>
     */
    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class);
    }
}
