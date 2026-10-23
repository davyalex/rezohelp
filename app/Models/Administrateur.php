<?php

namespace App\Models;

use App\Models\Concerns\HorodatageFrancais;
use App\Support\PermissionsAdmin;
use Database\Factories\AdministrateurFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Traits\HasRoles;

/**
 * Compte de l'équipe (back-office), distinct des membres du site (D-066).
 * Identifiant : nom d'utilisateur + mot de passe, garde « admin », rôles Spatie sur cette garde.
 *
 * @property int $id
 * @property string $nom
 * @property ?string $prenoms
 * @property string $nom_utilisateur
 * @property ?string $email
 * @property bool $doit_changer_mot_de_passe
 * @property ?Carbon $suspendu_le
 * @property ?Carbon $derniere_connexion_le
 */
class Administrateur extends Authenticatable
{
    /** @use HasFactory<AdministrateurFactory> */
    use HasFactory, HasRoles, HorodatageFrancais, Notifiable, SoftDeletes;

    public const string GARDE = 'admin';

    protected $table = 'administrateurs';

    protected string $guard_name = self::GARDE;

    /**
     * @var list<string>
     */
    protected $fillable = ['nom', 'prenoms', 'nom_utilisateur', 'email', 'mot_de_passe'];

    /**
     * @var list<string>
     */
    protected $hidden = ['mot_de_passe', 'remember_token'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mot_de_passe' => 'hashed',
            'doit_changer_mot_de_passe' => 'boolean',
            'suspendu_le' => 'datetime',
            'derniere_connexion_le' => 'datetime',
        ];
    }

    public function getAuthPasswordName(): string
    {
        return 'mot_de_passe';
    }

    public function nomComplet(): string
    {
        return trim(($this->prenoms ?? '').' '.$this->nom);
    }

    /**
     * Même signature que Utilisateur::nomAffiche(), pour les vues d'audit.
     */
    public function nomAffiche(): string
    {
        return $this->nomComplet();
    }

    public function initiales(): string
    {
        $prenoms = trim((string) $this->prenoms);

        return mb_strtoupper(mb_substr($prenoms !== '' ? $prenoms : $this->nom, 0, 1).mb_substr($this->nom, 0, 1));
    }

    public function estSuperAdministrateur(): bool
    {
        return $this->hasRole(PermissionsAdmin::ROLE_SUPER);
    }

    public function estSuspendu(): bool
    {
        return $this->suspendu_le !== null;
    }
}
