<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    /**
     * Mots de passe que l'API « Have I Been Pwned » simulée déclare compromis.
     *
     * @var list<string>
     */
    protected array $motsDePasseCompromis = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Les vues ne dépendent pas d'un build Vite pendant les tests.
        $this->withoutVite();

        // Aucun appel réseau réel : tout appel HTTP non simulé échoue.
        Http::preventStrayRequests();
        Http::fake(['api.pwnedpasswords.com/range/*' => fn (Request $requete) => Http::response($this->reponsePwned($requete))]);
    }

    public function declarerCompromis(string $motDePasse): void
    {
        $this->motsDePasseCompromis[] = $motDePasse;
    }

    private function reponsePwned(Request $requete): string
    {
        $prefixe = strtoupper(substr($requete->url(), -5));
        $lignes = [];

        foreach ($this->motsDePasseCompromis as $motDePasse) {
            $empreinte = strtoupper(sha1($motDePasse));

            if (str_starts_with($empreinte, $prefixe)) {
                $lignes[] = substr($empreinte, 5).':1520';
            }
        }

        return implode("\r\n", $lignes);
    }
}
