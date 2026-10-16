<?php

namespace App\Listeners;

use App\Models\Publication;
use Spatie\MediaLibrary\Conversions\Events\ConversionHasBeenCompletedEvent;

/**
 * Quand la miniature WebP (640 px) de la première photo est prête, l'image de la carte
 * est dénormalisée sur la publication (URL et dimensions, pour éviter tout saut de mise en page).
 */
class MettreAJourImagePublication
{
    public function handle(ConversionHasBeenCompletedEvent $evenement): void
    {
        $media = $evenement->media;

        if ($evenement->conversion->getName() !== 'fil' || $media->collection_name !== 'photos' || $media->model_type !== 'publication') {
            return;
        }

        $publication = Publication::query()->find($media->model_id);

        if ($publication === null || $publication->getFirstMedia('photos')?->id !== $media->id) {
            return;
        }

        $dimensions = @getimagesize($media->getPath('fil')) ?: [null, null];

        $publication->forceFill([
            'image_url' => $media->getUrl('fil'),
            'image_largeur' => $dimensions[0],
            'image_hauteur' => $dimensions[1],
        ])->save();
    }
}
