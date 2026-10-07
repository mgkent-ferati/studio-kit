<?php

declare(strict_types=1);

namespace App\Mappe\Controller;

use App\Bibliothek\DateiManager;
use App\Bibliothek\Entity\Datei;
use App\Bibliothek\Entity\Schlagwort;
use App\Bibliothek\Enum\DateiTyp;
use App\Bibliothek\Repository\DateiRepository;
use App\Bibliothek\Repository\SchlagwortRepository;
use App\Bibliothek\UploadPruefung;
use App\Mappe\BestandteilZip;
use App\Mappe\Entity\Mappe;
use App\Mappe\Repository\MappeRepository;
use App\Musik\MusikUnterlegenFehlgeschlagen;
use App\Musik\MusikUnterleger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

final class MappeController extends AbstractController
{
    public function __construct(
        private readonly MappeRepository $mappen,
        private readonly DateiRepository $dateien,
        private readonly SchlagwortRepository $schlagwoerter,
        private readonly DateiManager $dateiManager,
        private readonly UploadPruefung $uploadPruefung,
        private readonly BestandteilZip $bestandteilZip,
        private readonly MusikUnterleger $musikUnterleger,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/mappen', name: 'mappe_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $vorlageFilter = $request->query->getString('vorlage');
        $schlagwort = $request->query->getString('schlagwort');
        $text = $request->query->getString('q');

        return $this->render('mappe/index.html.twig', [
            'mappen' => $this->mappen->suche(
                '' === $schlagwort ? null : $schlagwort,
                match ($vorlageFilter) {
                    '1' => true, '0' => false, default => null,
                },
                $text,
            ),
            'alleSchlagwoerter' => $this->schlagwoerter->alle(),
            'filter' => ['schlagwort' => $schlagwort, 'vorlage' => $vorlageFilter, 'q' => $text],
        ]);
    }

    #[Route('/mappen', name: 'mappe_anlegen', methods: ['POST'])]
    public function anlegen(Request $request): Response
    {
        $this->tokenPruefen($request, 'mappe-anlegen');
        $name = trim($request->getPayload()->getString('name'));
        if ('' === $name) {
            $this->addFlash('fehler', 'Bitte einen Namen angeben.');

            return $this->redirectToRoute('mappe_index');
        }
        $mappe = new Mappe(mb_substr($name, 0, 255));
        $this->em->persist($mappe);
        $this->em->flush();

        return $this->redirectToRoute('mappe_detail', ['id' => $mappe->getId()]);
    }

    #[Route('/mappen/{id}', name: 'mappe_detail', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    public function detail(Mappe $mappe): Response
    {
        $bestandteilDateien = array_map(static fn ($b): Datei => $b->getDatei(), $mappe->getBestandteile());

        return $this->render('mappe/detail.html.twig', [
            'mappe' => $mappe,
            'alleDateien' => array_values(array_filter($this->dateien->suche(), static fn (Datei $d): bool => !in_array($d, $bestandteilDateien, true))),
            'audioBestandteile' => array_values(array_filter($bestandteilDateien, static fn (Datei $d): bool => DateiTyp::Audio === $d->getTyp())),
            'ergebnisFassungen' => null === $mappe->getErgebnis() ? [] : array_reverse($mappe->getErgebnis()->getFassungen()),
        ]);
    }

    #[Route('/mappen/{id}/bearbeiten', name: 'mappe_bearbeiten', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function bearbeiten(Mappe $mappe, Request $request): Response
    {
        $this->tokenPruefen($request, 'mappe-bearbeiten');
        $payload = $request->getPayload();
        $name = trim($payload->getString('name'));
        if ('' !== $name) {
            $mappe->setName(mb_substr($name, 0, 255));
        }
        $mappe->setBeschreibung(trim($payload->getString('beschreibung')));
        $mappe->setVorlage($payload->getBoolean('vorlage'));
        $mappe->setSchlagwoerter($this->schlagwoerter->holeOderErzeuge(Schlagwort::ausText($payload->getString('schlagwoerter'))));
        $this->em->flush();
        $this->addFlash('erfolg', 'Gespeichert.');

        return $this->zurDetailseite($mappe);
    }

    #[Route('/mappen/{id}/bestandteile', name: 'mappe_bestandteil_hinzufuegen', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function bestandteilHinzufuegen(Mappe $mappe, Request $request): Response
    {
        if ($this->uploadPruefung->anfrageZuGross($request)) {
            $this->addFlash('fehler', 'Die Anfrage war zu groß (über 2 GB) und ist nicht angekommen.');

            return $this->zurDetailseite($mappe);
        }
        $this->tokenPruefen($request, 'bestandteil');
        $upload = $request->files->get('datei');
        if ($upload instanceof UploadedFile) {
            $problem = $this->uploadPruefung->pruefen($upload, $request->getPayload()->getBoolean('trotzdem'));
            if (null !== $problem) {
                $this->addFlash('fehler', $problem);

                return $this->zurDetailseite($mappe);
            }
            $datei = $this->dateiManager->hochladen($upload, $upload->getClientOriginalName());
        } else {
            $id = $request->getPayload()->getString('datei_id');
            $datei = Uuid::isValid($id) ? $this->dateien->find($id) : null;
        }
        if (null === $datei) {
            $this->addFlash('fehler', 'Bitte eine Datei wählen oder hochladen.');

            return $this->zurDetailseite($mappe);
        }
        $mappe->bestandteilHinzufuegen($datei);
        $this->em->flush();
        $this->addFlash('erfolg', sprintf('„%s“ hinzugefügt.', $datei->getName()));

        return $this->zurDetailseite($mappe);
    }

    #[Route('/mappen/{id}/bestandteile/{dateiId}/entfernen', name: 'mappe_bestandteil_entfernen', requirements: ['id' => Requirement::DIGITS, 'dateiId' => Requirement::UUID], methods: ['POST'])]
    public function bestandteilEntfernen(Mappe $mappe, #[MapEntity(id: 'dateiId')] Datei $datei, Request $request): Response
    {
        $this->tokenPruefen($request, 'bestandteil-'.$datei->getId());
        $mappe->bestandteilEntfernen($datei);
        $this->em->flush();

        return $this->zurDetailseite($mappe);
    }

    #[Route('/mappen/{id}/bestandteile/{dateiId}/verschieben', name: 'mappe_bestandteil_verschieben', requirements: ['id' => Requirement::DIGITS, 'dateiId' => Requirement::UUID], methods: ['POST'])]
    public function bestandteilVerschieben(Mappe $mappe, #[MapEntity(id: 'dateiId')] Datei $datei, Request $request): Response
    {
        $this->tokenPruefen($request, 'bestandteil-'.$datei->getId());
        $mappe->bestandteilVerschieben($datei, 'hoch' === $request->getPayload()->getString('richtung') ? -1 : 1);
        $this->em->flush();

        return $this->zurDetailseite($mappe);
    }

    #[Route('/mappen/{id}/zip', name: 'mappe_zip', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    public function zip(Mappe $mappe): Response
    {
        try {
            $pfad = $this->bestandteilZip->erstellen($mappe);
        } catch (\RuntimeException $e) {
            $this->addFlash('fehler', $e->getMessage());

            return $this->zurDetailseite($mappe);
        }
        $response = new BinaryFileResponse($pfad);
        $response->headers->set('Content-Type', 'application/zip');
        $dateiname = $mappe->getName().' – Bestandteile.zip';
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $dateiname, (string) preg_replace('/[^\x20-\x7E]|[%\/\\\\]/', '_', $dateiname));
        $response->deleteFileAfterSend();

        return $response;
    }

    #[Route('/mappen/{id}/ergebnis', name: 'mappe_ergebnis_hochladen', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function ergebnisHochladen(Mappe $mappe, Request $request): Response
    {
        if ($this->uploadPruefung->anfrageZuGross($request)) {
            $this->addFlash('fehler', 'Die Anfrage war zu groß (über 2 GB) und ist nicht angekommen.');

            return $this->zurDetailseite($mappe);
        }
        $this->tokenPruefen($request, 'ergebnis');
        $upload = $request->files->get('datei');
        if (!$upload instanceof UploadedFile) {
            $this->addFlash('fehler', 'Keine Datei ausgewählt.');

            return $this->zurDetailseite($mappe);
        }
        $problem = $this->uploadPruefung->pruefen($upload, $request->getPayload()->getBoolean('trotzdem'));
        if (null !== $problem) {
            $this->addFlash('fehler', $problem);

            return $this->zurDetailseite($mappe);
        }
        $ergebnis = $mappe->getErgebnis();
        if (null === $ergebnis) {
            $ergebnis = $this->dateiManager->hochladen($upload, $upload->getClientOriginalName(), $mappe->getName());
            $mappe->setErgebnis($ergebnis);
            $this->em->flush();
            $nummer = 1;
        } else {
            $nummer = $this->dateiManager->neueFassung($ergebnis, $upload, $upload->getClientOriginalName())->getNummer();
        }
        $this->addFlash('erfolg', sprintf('Ergebnis Fassung %d hochgeladen.', $nummer));

        return $this->zurDetailseite($mappe);
    }

    #[Route('/mappen/{id}/loeschen', name: 'mappe_loeschen', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function loeschen(Mappe $mappe, Request $request): Response
    {
        $this->tokenPruefen($request, 'mappe-loeschen');
        $name = $mappe->getName();
        $this->em->remove($mappe);
        $this->em->flush();
        $this->addFlash('erfolg', sprintf('Mappe „%s“ gelöscht.', $name));

        return $this->redirectToRoute('mappe_index');
    }

    #[Route('/mappen/{id}/musik', name: 'mappe_musik_unterlegen', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function musikUnterlegen(Mappe $mappe, Request $request): Response
    {
        $this->tokenPruefen($request, 'musik');
        $payload = $request->getPayload();
        $id = $payload->getString('audio_id');
        $audio = Uuid::isValid($id) ? $this->dateien->find($id) : null;
        if (null === $audio) {
            $this->addFlash('fehler', 'Bitte einen Audio-Bestandteil wählen.');

            return $this->zurDetailseite($mappe);
        }
        try {
            $fassung = $this->musikUnterleger->unterlegen(
                $mappe,
                $audio,
                MusikUnterleger::zahl($payload->getString('lautstaerke', '1')),
                MusikUnterleger::zahl($payload->getString('start', '0')),
                $payload->getBoolean('original'),
            );
            $this->addFlash('erfolg', sprintf('Musik unterlegt – Ergebnis Fassung %d.', $fassung->getNummer()));
        } catch (MusikUnterlegenFehlgeschlagen $e) {
            $this->addFlash('fehler', $e->getMessage());
        }

        return $this->zurDetailseite($mappe);
    }

    private function zurDetailseite(Mappe $mappe): Response
    {
        return $this->redirectToRoute('mappe_detail', ['id' => $mappe->getId()]);
    }

    private function tokenPruefen(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Ungültiges Formular-Token.');
        }
    }
}
