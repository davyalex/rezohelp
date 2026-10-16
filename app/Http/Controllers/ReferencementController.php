<?php

namespace App\Http\Controllers;

use App\Models\Publication;
use App\Services\CacheFil;
use Illuminate\Http\Response;

/**
 * sitemap.xml et robots.txt.
 */
class ReferencementController extends Controller
{
    public const int LIMITE_SITEMAP = 5000;

    public function sitemap(CacheFil $cache): Response
    {
        /** @var list<array{slug: string, modifie_le: ?string}> $publications */
        $publications = $cache->compteurs('sitemap', fn (): array => Publication::query()->publiees()
            ->orderByDesc('tri_le')->orderByDesc('id')
            ->limit(self::LIMITE_SITEMAP)
            ->get(['slug', 'modifie_le'])
            ->map(fn (Publication $publication): array => [
                'slug' => $publication->slug,
                'modifie_le' => $publication->modifie_le?->toAtomString(),
            ])
            ->all());

        return response()
            ->view('referencement.sitemap', ['publications' => $publications])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(): Response
    {
        $lignes = [
            'User-agent: *',
            'Disallow: /mon-espace',
            'Disallow: /admin',
            'Disallow: /fil/suite',
            'Disallow: /fil/nouvelles',
            'Allow: /',
            '',
            'Sitemap: '.route('referencement.sitemap'),
        ];

        return response(implode("\n", $lignes)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
