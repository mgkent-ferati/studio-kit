<?php

declare(strict_types=1);

namespace App\Bibliothek\Controller;

use App\Bibliothek\DateiInVerwendung;
use App\Bibliothek\DateiManager;
use App\Bibliothek\Entity\Datei;
use App\Bibliothek\Entity\Fassung;
use App\Bibliothek\Entity\Schlagwort;
use App\Bibliothek\Enum\DateiTyp;
use App\Bibliothek\FassungStorage;
use App\Bibliothek\Repository\DateiRepository;
use App\Bibliothek\Repository\SchlagwortRepository;
use App\Bibliothek\UploadPruefung;
use App\Mappe\Repository\MappeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final class BibliothekController extends AbstractController
{
    public function __construct(
        private readonly DateiRepository $dateien,
        private readonly SchlagwortRepository $schlagwoerter,
        private readonly MappeRepository $mappen,
        private readonly DateiManager $dateiManager,
        private readonly UploadPruefung $uploadPruefung,
        private readonly FassungStorage $storage,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/', name: 'bibliothek_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $typ = DateiTyp::tryFrom($request->query->getString('typ'));
        $vorlageFilter = $request->query->getString('vorlage');
        $vorlage = match ($vorlageFilter) {
            '1' => true,
            '0' => false,
            default => null,
        };
        $schlagwort = $request->query->getString('schlagwort');
        $text = $request->query->getString('q');

        return $this->render('bibliothek/index.html.twig', [
            'dateien' => $this->dateien->suche(
                $typ,
                '' === $schlagwort ? null : $schlagwort,
                $vorlage,
                $text,
            ),
            'typen' => DateiTyp::cases(),
            'alleSchlagwoerter' => $this->schlagwoerter->alle(),
            'filter' => ['typ' => $typ?->value, 'schlagwort' => $schlagwort, 'vorlage' => $vorlageFilter, 'q' => $text],
        ]);
    }

    #[Route('/dateien', name: 'datei_hochladen', methods: ['POST'])]
    public function hochladen(Request $request): Response
    {
        if ($this->uploadPruefung->anfrageZuGross($request)) {
            $this->addFlash('fehler', 'Die Anfrage war zu groß (über 2 GB) und ist nicht angekommen.');

            return $this->redirectToRoute('bibliothek_index');
        }
        $this->tokenPruefen($request, 'hochladen');
        $uploads = array_filter($request->files->all('dateien'), static fn ($u): bool => $u instanceof UploadedFile);
        if ([] === $uploads) {
            $this->addFlash('fehler', 'Keine Datei ausgewählt.');

            return $this->redirectToRoute('bibliothek_index');
        }
        $trotzdem = $request->getPayload()->getBoolean('trotzdem');
        foreach ($uploads as $upload) {
            $problem = $this->uploadPruefung->pruefen($upload, $trotzdem);
            if (null !== $problem) {
                $this->addFlash('fehler', $problem);
                continue;
            }
            $datei = $this->dateiManager->hochladen($upload, $upload->getClientOriginalName());
            $this->addFlash('erfolg', sprintf('„%s“ hochgeladen.', $datei->getName()));
        }

        return $this->redirectToRoute('bibliothek_index');
    }

    #[Route('/dateien/{id}', name: 'datei_detail', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function detail(Datei $datei): Response
    {
        $fassungen = array_reverse($datei->getFassungen());

        return $this->render('bibliothek/detail.html.twig', [
            'datei' => $datei,
            'fassungen' => $fassungen,
            'fehlend' => array_map(fn (Fassung $f): bool => !$this->storage->existiert($f), $fassungen),
            'mappen' => $this->mappen->findByDatei($datei),
        ]);
    }

    #[Route('/dateien/{id}/bearbeiten', name: 'datei_bearbeiten', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function bearbeiten(Datei $datei, Request $request): Response
    {
        $this->tokenPruefen($request, 'datei-bearbeiten');
        $name = trim($request->getPayload()->getString('name'));
        if ('' !== $name) {
            $datei->setName(mb_substr($name, 0, 255));
        }
        $datei->setVorlage($request->getPayload()->getBoolean('vorlage'));
        $datei->setSchlagwoerter($this->schlagwoerter->holeOderErzeuge(Schlagwort::ausText($request->getPayload()->getString('schlagwoerter'))));
        $this->em->flush();
        $this->addFlash('erfolg', 'Gespeichert.');

        return $this->redirectToRoute('datei_detail', ['id' => $datei->getId()]);
    }

    #[Route('/dateien/{id}/fassungen', name: 'datei_neue_fassung', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function neueFassung(Datei $datei, Request $request): Response
    {
        if ($this->uploadPruefung->anfrageZuGross($request)) {
            $this->addFlash('fehler', 'Die Anfrage war zu groß (über 2 GB) und ist nicht angekommen.');

            return $this->redirectToRoute('datei_detail', ['id' => $datei->getId()]);
        }
        $this->tokenPruefen($request, 'neue-fassung');
        $upload = $request->files->get('datei');
        if (!$upload instanceof UploadedFile) {
            $this->addFlash('fehler', 'Keine Datei ausgewählt.');

            return $this->redirectToRoute('datei_detail', ['id' => $datei->getId()]);
        }
        $problem = $this->uploadPruefung->pruefen($upload, $request->getPayload()->getBoolean('trotzdem'));
        if (null !== $problem) {
            $this->addFlash('fehler', $problem);
        } else {
            $fassung = $this->dateiManager->neueFassung($datei, $upload, $upload->getClientOriginalName());
            $this->addFlash('erfolg', sprintf('Fassung %d hochgeladen.', $fassung->getNummer()));
        }

        return $this->redirectToRoute('datei_detail', ['id' => $datei->getId()]);
    }

    #[Route('/dateien/{id}/loeschen', name: 'datei_loeschen', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function loeschen(Datei $datei, Request $request): Response
    {
        $this->tokenPruefen($request, 'datei-loeschen');
        $name = $datei->getName();
        try {
            $this->dateiManager->loeschen($datei);
        } catch (DateiInVerwendung $e) {
            $this->addFlash('fehler', $e->getMessage());

            return $this->redirectToRoute('datei_detail', ['id' => $datei->getId()]);
        }
        $this->addFlash('erfolg', sprintf('„%s“ gelöscht.', $name));

        return $this->redirectToRoute('bibliothek_index');
    }

    #[Route('/fassungen/{id}/inhalt', name: 'fassung_inhalt', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    public function inhalt(Fassung $fassung): BinaryFileResponse
    {
        return $this->ausliefern($fassung, ResponseHeaderBag::DISPOSITION_INLINE);
    }

    #[Route('/fassungen/{id}/download', name: 'fassung_download', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    public function download(Fassung $fassung): BinaryFileResponse
    {
        return $this->ausliefern($fassung, ResponseHeaderBag::DISPOSITION_ATTACHMENT);
    }

    private function ausliefern(Fassung $fassung, string $disposition): BinaryFileResponse
    {
        $pfad = $this->storage->absolutePath($fassung);
        if (!is_file($pfad)) {
            throw $this->createNotFoundException('Datei fehlt im Speicher.');
        }
        $response = new BinaryFileResponse($pfad);
        $response->headers->set('Content-Type', $fassung->getMimeTyp());
        $response->setContentDisposition(
            $disposition,
            $fassung->getOriginalname(),
            (string) preg_replace('/[^\x20-\x7E]|[%\/\\\\]/', '_', $fassung->getOriginalname()),
        );

        return $response;
    }

    private function tokenPruefen(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Ungültiges Formular-Token.');
        }
    }
}
