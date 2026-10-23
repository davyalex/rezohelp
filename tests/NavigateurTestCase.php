<?php

namespace Tests;

/**
 * Tests de bout en bout dans un vrai navigateur (lot 10) : contrairement aux autres tests,
 * la page charge le vrai build Vite (CSS, Alpine, fil infini). Lancer « npm run build » avant.
 */
abstract class NavigateurTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withVite();
    }
}
