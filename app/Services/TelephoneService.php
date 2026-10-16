<?php

namespace App\Services;

use libphonenumber\PhoneNumberType;
use Propaganistas\LaravelPhone\PhoneNumber;
use Throwable;

/**
 * Normalisation et affichage des numéros de téléphone (CONTEXTE §9).
 *
 * Toute saisie est normalisée en E.164 avant d'être stockée ou recherchée :
 * « 0708091011 », « 07 08 09 10 11 » et « +225 07 08 09 10 11 » donnent
 * « +2250708091011 ».
 */
class TelephoneService
{
    public const string PAYS_PAR_DEFAUT = 'CI';

    /**
     * Numéro E.164, ou null si la saisie n'est pas un numéro valide pour le pays.
     */
    public function normaliser(?string $saisie, ?string $paysCode = null): ?string
    {
        $numero = $this->analyser($saisie, $paysCode);

        return $numero?->formatE164();
    }

    /**
     * Numéro E.164 d'un mobile, ou null (fixe, invalide…).
     */
    public function normaliserMobile(?string $saisie, ?string $paysCode = null): ?string
    {
        $numero = $this->analyser($saisie, $paysCode);

        if ($numero === null || ! $numero->isOfType([PhoneNumberType::MOBILE, PhoneNumberType::FIXED_LINE_OR_MOBILE])) {
            return null;
        }

        return $numero->formatE164();
    }

    public function paysDe(string $e164): ?string
    {
        return $this->analyser($e164, null)?->getCountry();
    }

    /**
     * Format national. En Côte d'Ivoire, les 10 chiffres sont groupés par deux
     * (« 07 08 09 10 11 »), comme on les écrit localement.
     */
    public function formatNational(string $e164): string
    {
        $numero = $this->analyser($e164, null);

        if ($numero === null) {
            return $e164;
        }

        return $numero->getCountry() === 'CI'
            ? $this->parPaires($numero->formatNational())
            : $numero->formatNational();
    }

    public function formatInternational(string $e164): string
    {
        $numero = $this->analyser($e164, null);

        if ($numero === null) {
            return $e164;
        }

        return $numero->getCountry() === 'CI'
            ? '+225 '.$this->parPaires($numero->formatNational())
            : $numero->formatInternational();
    }

    /**
     * Format national pour un lecteur du même pays, international sinon.
     */
    public function afficher(string $e164, ?string $paysDuLecteur = null): string
    {
        $pays = $this->paysDe($e164);

        return $pays !== null && $pays === strtoupper($paysDuLecteur ?? self::PAYS_PAR_DEFAUT)
            ? $this->formatNational($e164)
            : $this->formatInternational($e164);
    }

    /**
     * Empreinte SHA-256 d'un numéro E.164 (essais gratuits : le numéro n'est pas conservé en clair).
     */
    public function empreinte(string $e164): string
    {
        return hash('sha256', $e164);
    }

    private function parPaires(string $numero): string
    {
        return implode(' ', str_split((string) preg_replace('/\D/', '', $numero), 2));
    }

    private function analyser(?string $saisie, ?string $paysCode): ?PhoneNumber
    {
        $saisie = trim((string) $saisie);

        if ($saisie === '') {
            return null;
        }

        try {
            $numero = new PhoneNumber($saisie, strtoupper($paysCode ?? self::PAYS_PAR_DEFAUT));

            return $numero->isValid() ? $numero : null;
        } catch (Throwable) {
            return null;
        }
    }
}
