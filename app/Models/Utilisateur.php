<?php

namespace App\Models;

use App\Enums\ConfianceUtilisateur;
use App\Models\Concerns\HorodatageFrancais;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Compte membre. Identifiant : téléphone (E.164) + mot de passe.
 */
class Utilisateur extends Authenticatable
{
    use HorodatageFrancais, Notifiable, SoftDeletes;

    protected $table = 'utilisateurs';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nom',
        'prenoms',
        'telephone',
        'pays_code',
        'email',
        'mot_de_passe',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'mot_de_passe',
        'remember_token',
        'telephone',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mot_de_passe' => 'hashed',
            'confiance' => ConfianceUtilisateur::class,
            'telephone_verifie_le' => 'datetime',
            'derniere_connexion_le' => 'datetime',
            'suspendu_le' => 'datetime',
            'suspendu_jusqu_au' => 'datetime',
            'doit_changer_mot_de_passe' => 'boolean',
        ];
    }

    public function getAuthPasswordName(): string
    {
        return 'mot_de_passe';
    }
}
