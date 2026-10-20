<?php

namespace App\Actions\Publication;

use App\Enums\CleDroit;
use App\Enums\ModeCandidature;
use App\Enums\ModeInscription;
use App\Enums\SousTypeService;
use App\Enums\StatutReponse;
use App\Enums\TypePublication;
use App\Enums\TypeReponse;
use App\Models\Publication;
use App\Models\Reponse;
use App\Models\Utilisateur;
use App\Notifications\NouvelleReponse;
use App\Services\Droits;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Réponse à une publication sur Rezohelp (CONTEXTE §11.8) : candidature, inscription,
 * proposition (« Je suis disponible ») ou demande de contact. Une seule par personne.
 */
class RepondrePublication
{
    /**
     * Type de réponse accepté par la publication, ou null si elle se contacte autrement
     * (lien, e-mail, téléphone affiché).
     */
    public static function typeAccepte(Publication $publication): ?TypeReponse
    {
        return match (true) {
            $publication->type === TypePublication::Emploi => $publication->detailEmploi?->mode_candidature === ModeCandidature::Rezohelp
                ? TypeReponse::Candidature : null,
            $publication->type === TypePublication::Formation => $publication->detailFormation?->mode_inscription === ModeInscription::Rezohelp
                ? TypeReponse::Inscription : null,
            $publication->sous_type === SousTypeService::Demande => TypeReponse::Proposition,
            default => $publication->detailService?->afficher_telephone ? null : TypeReponse::Contact,
        };
    }

    /**
     * Pour un contact ou une proposition, le numéro est toujours transmis à l'auteur :
     * c'est le seul moyen pour lui de répondre (pas de messagerie avant la V2).
     */
    public static function telephoneObligatoire(TypeReponse $type): bool
    {
        return in_array($type, [TypeReponse::Proposition, TypeReponse::Contact], true);
    }

    public function executer(Utilisateur $repondant, Publication $publication, ?string $message, bool $partagerTelephone): Reponse
    {
        $publication->loadMissing(['detailEmploi', 'detailService', 'detailFormation']);
        $type = self::typeAccepte($publication);

        if ($type === null) {
            throw ValidationException::withMessages(['message' => 'Cette publication se contacte directement, sans passer par Rezohelp.']);
        }

        if ($type === TypeReponse::Proposition) {
            Droits::pour($repondant)->verifierOuEchouer(CleDroit::ReponsesDemandesMois);
        }

        $reponse = DB::transaction(function () use ($repondant, $publication, $message, $partagerTelephone, $type): Reponse {
            if (Reponse::query()->where('publication_id', $publication->id)->where('utilisateur_id', $repondant->id)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['message' => 'Vous avez déjà répondu à cette publication.']);
            }

            $reponse = new Reponse([
                'publication_id' => $publication->id,
                'utilisateur_id' => $repondant->id,
                'type' => $type,
                'message' => $message !== null && trim($message) !== '' ? trim($message) : null,
                'telephone_partage' => self::telephoneObligatoire($type) || $partagerTelephone,
            ]);
            $reponse->statut = StatutReponse::Envoyee;
            $reponse->save();

            Publication::query()->whereKey($publication->id)->toBase()->increment('nb_reponses');

            return $reponse;
        });

        $reponse->setRelation('publication', $publication);
        $publication->auteur?->notify(new NouvelleReponse($reponse));

        return $reponse;
    }
}
