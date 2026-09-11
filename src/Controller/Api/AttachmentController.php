<?php

namespace App\Controller\Api;

use App\Entity\Lead;
use App\Entity\LeadAttachment;
use App\Repository\LeadAttachmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/leads', name: 'api_attachment_')]
class AttachmentController extends AbstractController
{
    private const ALLOWED_MIME = [
        'application/pdf',
        'image/png',
        'image/jpeg',
        'image/webp',
    ];

    private const MAX_SIZE = 10 * 1024 * 1024; // 10 Mo

    public function __construct(
        private EntityManagerInterface    $em,
        private LeadAttachmentRepository  $attachRepo,
        private string                    $uploadDir,
    ) {}

    // -----------------------------------------------------------------------
    // POST /api/leads/{id}/attach
    // -----------------------------------------------------------------------
    #[Route('/{id}/attach', name: 'upload', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function upload(Lead $lead, Request $request): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        // Seul l'agent assigné, le supervisor ou l'admin peut uploader
        if ($user->getRole() === 'agent' && $lead->getAssignedTo()?->getId() !== $user->getId()) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $file = $request->files->get('file');
        if (!$file) {
            return $this->json(['error' => 'Aucun fichier fourni.'], 422);
        }

        if ($file->getSize() > self::MAX_SIZE) {
            return $this->json(['error' => 'Fichier trop volumineux (max 10 Mo).'], 422);
        }

        $mime = $file->getMimeType();
        if (!in_array($mime, self::ALLOWED_MIME)) {
            return $this->json(['error' => 'Format non autorisé. Utilisez PDF, PNG, JPEG ou WEBP.'], 422);
        }

        // Nom de stockage sécurisé
        $ext        = $file->getClientOriginalExtension() ?: 'bin';
        $storedName = sprintf('%d_%d_%s.%s', $lead->getId(), time(), bin2hex(random_bytes(6)), strtolower($ext));

        // Créer le dossier si nécessaire
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0775, true);
        }

        $file->move($this->uploadDir, $storedName);

        $attachment = new LeadAttachment();
        $attachment->setLead($lead);
        $attachment->setUser($user);
        $attachment->setAttachmentType($request->request->get('type', 'quote'));
        $attachment->setOriginalName($file->getClientOriginalName() ?: $storedName);
        $attachment->setStoredName($storedName);
        $attachment->setMimeType($mime);
        $attachment->setSize($file->getSize() ?: (int) filesize($this->uploadDir . '/' . $storedName));

        $this->em->persist($attachment);
        $this->em->flush();

        return $this->json([
            'id'           => $attachment->getId(),
            'originalName' => $attachment->getOriginalName(),
            'mimeType'     => $attachment->getMimeType(),
            'size'         => $attachment->getSize(),
            'createdAt'    => $attachment->getCreatedAt()?->format('Y-m-d H:i:s'),
        ], 201);
    }

    // -----------------------------------------------------------------------
    // GET /api/leads/{id}/attach/{attachId}  — télécharger
    // -----------------------------------------------------------------------
    #[Route('/{id}/attach/{attachId}', name: 'download', methods: ['GET'], requirements: ['id' => '\d+', 'attachId' => '\d+'])]
    public function download(Lead $lead, int $attachId): BinaryFileResponse|JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if ($user->getRole() === 'agent' && $lead->getAssignedTo()?->getId() !== $user->getId()) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $attachment = $this->attachRepo->find($attachId);

        if (!$attachment || $attachment->getLead()->getId() !== $lead->getId()) {
            return $this->json(['error' => 'Pièce jointe introuvable.'], 404);
        }

        $path = $this->uploadDir . '/' . $attachment->getStoredName();

        if (!file_exists($path)) {
            return $this->json(['error' => 'Fichier manquant sur le serveur.'], 404);
        }

        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $attachment->getOriginalName()
        );
        $response->headers->set('Content-Type', $attachment->getMimeType());

        return $response;
    }

    // -----------------------------------------------------------------------
    // DELETE /api/leads/{id}/attach/{attachId}
    // -----------------------------------------------------------------------
    #[Route('/{id}/attach/{attachId}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+', 'attachId' => '\d+'])]
    public function delete(Lead $lead, int $attachId): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if (!in_array($user->getRole(), ['admin', 'supervisor'])) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $attachment = $this->attachRepo->find($attachId);

        if (!$attachment || $attachment->getLead()->getId() !== $lead->getId()) {
            return $this->json(['error' => 'Pièce jointe introuvable.'], 404);
        }

        $path = $this->uploadDir . '/' . $attachment->getStoredName();
        if (file_exists($path)) {
            unlink($path);
        }

        $this->em->remove($attachment);
        $this->em->flush();

        return $this->json(['success' => true]);
    }
}
