<?php

namespace App\Providers;

use App\Contracts\ClientIA;
use App\Contracts\FournisseurSms;
use App\Contracts\PasserellePaiement;
use App\Contracts\SourceVeille;
use App\Services\CacheFil;
use App\Services\IA\FakeClientIA;
use App\Services\Paiement\CinetPayPasserelle;
use App\Services\Paiement\FakePasserelle;
use App\Services\Sms\LogFournisseurSms;
use App\Services\Sms\OrangeFournisseurSms;
use App\Services\Sms\TwilioFournisseurSms;
use App\Services\Veille\FakeSourceVeille;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory;
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
            'fake' => new FakePasserelle(Storage::disk('local'), (string) config('rezohelp.paiement.fake.secret')),
            'cinetpay' => new CinetPayPasserelle($app->make(Factory::class), (array) config('services.cinetpay')),
            default => $this->driverInconnu('paiement'),
        });

        $this->app->singleton(FournisseurSms::class, fn (Application $app): FournisseurSms => match ($this->driver('sms')) {
            'log' => new LogFournisseurSms(Log::channel((string) config('rezohelp.sms.canal_log'))),
            'orange' => new OrangeFournisseurSms($app->make(Factory::class), $app->make(CacheFil::class), (array) config('services.orange_sms')),
            'twilio' => new TwilioFournisseurSms($app->make(Factory::class), (array) config('services.twilio')),
            default => $this->driverInconnu('sms'),
        });

        $this->app->singleton(SourceVeille::class, fn (Application $app): SourceVeille => match ($this->driver('veille')) {
            'fake' => new FakeSourceVeille((string) config('rezohelp.veille.fake.dossier')),
            default => $this->driverInconnu('veille'),
        });

        $this->app->singleton(ClientIA::class, fn (Application $app): ClientIA => match ($this->driver('ia')) {
            'fake' => new FakeClientIA,
            default => $this->driverInconnu('ia'),
        });
    }

    private function driver(string $service): string
    {
        return (string) config("rezohelp.{$service}.driver");
    }

    private function driverInconnu(string $service): never
    {
        throw new InvalidArgumentException("Driver « {$this->driver($service)} » inconnu pour le service {$service}.");
    }
}
