<?php

declare(strict_types=1);

namespace App\Bibliothek;

use App\Bibliothek\Repository\FassungRepository;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

final readonly class UploadPruefung
{
    public function __construct(private FassungRepository $fassungen)
    {
    }

    /** Über post_max_size verwirft PHP den ganzen Body – auch das CSRF-Token. */
    public function anfrageZuGross(Request $request): bool
    {
        return 0 === $request->files->count()
            && 0 === $request->request->count()
            && (int) $request->server->get('CONTENT_LENGTH', 0) > 0;
    }

    /** @return string|null null = in Ordnung, sonst Meldung für Mina */
    public function pruefen(UploadedFile $upload, bool $trotzdem): ?string
    {
        if (!$upload->isValid()) {
            return sprintf('„%s“: %s', $upload->getClientOriginalName(), $upload->getErrorMessage());
        }
        if ($trotzdem) {
            return null;
        }
        $sha256 = hash_file('sha256', $upload->getPathname());
        $vorhanden = false === $sha256 ? null : $this->fassungen->findOneBySha256($sha256);
        if (null === $vorhanden) {
            return null;
        }

        return sprintf(
            '„%s“ gibt es schon als „%s“ (Fassung %d) – nicht angelegt. Zum Anlegen „trotzdem anlegen“ ankreuzen und erneut hochladen.',
            $upload->getClientOriginalName(),
            $vorhanden->getDatei()->getName(),
            $vorhanden->getNummer(),
        );
    }
}
