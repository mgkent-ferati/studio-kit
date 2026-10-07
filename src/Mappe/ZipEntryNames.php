<?php

declare(strict_types=1);

namespace App\Mappe;

final class ZipEntryNames
{
    /**
     * Doppelte Namen (ohne Groß-/Kleinschreibung, wie auf macOS) bekommen „ (2)“, „ (3)“ … vor der Endung.
     *
     * @param list<string> $namen
     *
     * @return list<string>
     */
    public static function eindeutig(array $namen): array
    {
        $vergeben = [];
        $ergebnis = [];
        foreach ($namen as $name) {
            $basis = pathinfo($name, \PATHINFO_FILENAME);
            $endung = pathinfo($name, \PATHINFO_EXTENSION);
            $endung = '' === $endung ? '' : '.'.$endung;
            $kandidat = $name;
            $zaehler = 2;
            while (isset($vergeben[mb_strtolower($kandidat)])) {
                $kandidat = sprintf('%s (%d)%s', $basis, $zaehler++, $endung);
            }
            $vergeben[mb_strtolower($kandidat)] = true;
            $ergebnis[] = $kandidat;
        }

        return $ergebnis;
    }
}
