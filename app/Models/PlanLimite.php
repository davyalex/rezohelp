<?php

namespace App\Models;

use App\Enums\CleDroit;
use Database\Factories\PlanLimiteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Valeur d'une clé de droit dans un plan : entier (-1 = illimité) ou 0/1.
 */
class PlanLimite extends Model
{
    /** @use HasFactory<PlanLimiteFactory> */
    use HasFactory;

    protected $table = 'plan_limites';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = ['plan_id', 'cle', 'valeur'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cle' => CleDroit::class,
        ];
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
