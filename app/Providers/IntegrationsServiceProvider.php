<?php

namespace App\Providers;

use App\Contracts\ClientIA;
use App\Contracts\FournisseurSms;
use App\Contracts\PasserellePaiement;
use App\Contracts\SourceVeille;
use App\Services\IA\FakeClientIA;
use App\Services\Paiement\FakePasserelle;
use App\Services\Sms\LogFournisseurSms;
use App\Services\Veille\FakeSourceVeille;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

/**
 * Lie chaque interface de service externe au driver choisi dans .env
 * (PAIEMENT_DRIVER, SMS_DRIVER, VEILLE_DRIVE_DRIVER, IA_DRIVER).
 */
class IntegrationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PasserellePaiement::class, fn (Application $app): PasserellePaiement => match ($this->driver('paiement')) {
            'fake' => new FakePasserelle(Storage::disk('local'), (string) config('resohelph.paiement.fake.secret')),
            default => $this->driverInconnu('paiement'),
        });

        $this->app->singleton(FournisseurSms::class, fn (Application $app): FournisseurSms => match ($this->driver('sms')) {
            'log' => new LogFournisseurSms(Log::channel((string) config('resohelph.sms.canal_log'))),
            default => $this->driverInconnu('sms'),
        });

        $this->app->singleton(SourceVeille::class, fn (Application $app): SourceVeille => match ($this->driver('veille')) {
            'fake' => new FakeSourceVeille((string) config('resohelph.veille.fake.dossier')),
            default => $this->driverInconnu('veille'),
        });

        $this->app->singleton(ClientIA::class, fn (Application $app): ClientIA => match ($this->driver('ia')) {
            'fake' => new FakeClientIA,
            default => $this->driverInconnu('ia'),
        });
    }

    private function driver(string $service): string
    {
        return (string) config("resohelph.{$service}.driver");
    }

    private function driverInconnu(string $service): never
    {
        throw new InvalidArgumentException("Driver « {$this->driver($service)} » inconnu pour le service {$service}.");
    }
}
