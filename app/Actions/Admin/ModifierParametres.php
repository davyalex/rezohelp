<?php

namespace App\Actions\Admin;

use App\Actions\Admin\Concerns\JournaliseActivite;
use App\Enums\TypeParametre;
use App\Models\Administrateur;
use App\Models\Parametre;
use Illuminate\Support\Facades\DB;

/**
 * G — Paramètres (CONTEXTE §13.3.G) : seules les valeurs modifiées sont enregistrées, avec leur
 * auteur ; l'observer invalide ref:parametres. Une ligne de journal par paramètre modifié.
 */
class ModifierParametres
{
    use JournaliseActivite;

    /**
     * @param  array<string, mixed>  $valeurs  clé => valeur saisie (déjà validée)
     * @return int nombre de paramètres modifiés
     */
    public function executer(array $valeurs, Administrateur $admin): int
    {
        return DB::transaction(function () use ($valeurs, $admin): int {
            $modifies = 0;

            foreach (Parametre::query()->whereIn('cle', array_keys($valeurs))->get() as $parametre) {
                $nouvelle = self::serialiser($parametre->type, $valeurs[$parametre->cle]);

                if ($nouvelle === (string) $parametre->valeur) {
                    continue;
                }

                $avant = (string) $parametre->valeur;
                $parametre->valeur = $nouvelle;
                $parametre->modifie_par = $admin->id;
                $parametre->save();

                // Clé primaire textuelle : le paramètre est identifié dans les données, pas en sujet (sujet_id entier).
                $this->journaliser($admin, 'parametre.modifie', null, ['cle' => $parametre->cle, 'avant' => $avant, 'apres' => $nouvelle]);
                $modifies++;
            }

            return $modifies;
        });
    }

    public static function serialiser(TypeParametre $type, mixed $valeur): string
    {
        return match ($type) {
            TypeParametre::Entier => (string) (int) $valeur,
            TypeParametre::Booleen => filter_var($valeur, FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
            TypeParametre::Json => (string) json_encode(is_string($valeur) ? json_decode($valeur, true) : $valeur, JSON_UNESCAPED_UNICODE),
            TypeParametre::Texte, TypeParametre::Html => trim((string) $valeur),
        };
    }
}
