<?php

namespace App\Controller\Api;

use App\Service\CsvImportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/import', name: 'api_import_')]
class ImportController extends AbstractController
{
    public function __construct(
        private CsvImportService $csvImport,
    ) {}

    /**
     * POST /api/import/leads
     *
     * Accepte :
     *  - multipart/form-data avec champ "file" (fichier CSV)
     *  - application/json avec champ "csv" (contenu CSV en string)
     *
     * Paramètres optionnels : source, campaignId, injectionDate (YYYY-MM-DD)
     */
    #[Route('/leads', name: 'leads', methods: ['POST'])]
    public function leads(Request $request): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if (!in_array($user->getRole(), ['admin', 'supervisor'])) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $csvContent    = '';
        $source        = '';
        $campaignId    = null;
        $injectionDate = null;

        $contentType = $request->headers->get('Content-Type', '');

        if (str_contains($contentType, 'multipart/form-data')) {
            // Upload fichier
            $file = $request->files->get('file');
            if (!$file) {
                return $this->json(['error' => 'Aucun fichier fourni.'], 422);
            }

            $allowedMime = ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel', 'application/octet-stream'];
            if (!in_array($file->getMimeType(), $allowedMime) && !str_ends_with(strtolower($file->getClientOriginalName()), '.csv')) {
                return $this->json(['error' => 'Seuls les fichiers CSV sont acceptés.'], 422);
            }

            $csvContent    = file_get_contents($file->getPathname());
            $source        = $request->request->get('source', '');
            $campaignId    = $request->request->get('campaignId') ? (int) $request->request->get('campaignId') : null;
            $injectionDate = $request->request->get('injectionDate');
        } else {
            // JSON avec contenu CSV en string
            $data          = $request->toArray();
            $csvContent    = $data['csv'] ?? '';
            $source        = $data['source'] ?? '';
            $campaignId    = isset($data['campaignId']) ? (int) $data['campaignId'] : null;
            $injectionDate = $data['injectionDate'] ?? null;
        }

        if (empty(trim($csvContent))) {
            return $this->json(['error' => 'Contenu CSV vide.'], 422);
        }

        // Détecter et corriger l'encodage si besoin
        if (!mb_check_encoding($csvContent, 'UTF-8')) {
            $csvContent = mb_convert_encoding($csvContent, 'UTF-8', 'ISO-8859-1');
        }

        $result = $this->csvImport->import(
            $csvContent,
            $user,
            $source,
            $campaignId,
            $injectionDate
        );

        return $this->json($result, $result['imported'] > 0 ? 200 : 422);
    }
}
