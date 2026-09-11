<?php

namespace App\Controller\Api;

use App\Entity\Announcement;
use App\Entity\AnnouncementRead;
use App\Repository\AnnouncementRepository;
use App\Repository\AnnouncementReadRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/announcements', name: 'api_announcements_')]
class AnnouncementController extends AbstractController
{
    public function __construct(
        private AnnouncementRepository     $announcementRepo,
        private AnnouncementReadRepository $readRepo,
        private EntityManagerInterface     $em,
    ) {}

    // -----------------------------------------------------------------------
    // GET /api/announcements/pending
    // Retourne la première annonce non lue/non reportée pour l'utilisateur
    // -----------------------------------------------------------------------
    #[Route('/pending', name: 'pending', methods: ['GET'])]
    public function pending(): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $announcement = $this->announcementRepo->findPendingForUser($user->getId(), $user->getRole());

        if (!$announcement) {
            return $this->json(null);
        }

        // Marquer comme vu (last_seen_at)
        $this->upsertRead($announcement->getId(), $user->getId(), ['lastSeenAt' => new \DateTime()]);

        return $this->json($this->serialize($announcement));
    }

    // -----------------------------------------------------------------------
    // GET /api/announcements
    // Liste toutes les annonces actives (admin/supervisor)
    // -----------------------------------------------------------------------
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $showAll = $request->query->getBoolean('all');

        if ($showAll && $user->getRole() !== 'admin') {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $announcements = $showAll
            ? $this->announcementRepo->findBy([], ['createdAt' => 'DESC'])
            : $this->announcementRepo->findBy(['active' => true], ['createdAt' => 'DESC']);

        return $this->json(array_map(fn($a) => $this->serialize($a), $announcements));
    }

    // -----------------------------------------------------------------------
    // POST /api/announcements  — créer une annonce (admin)
    // -----------------------------------------------------------------------
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if (!in_array($user->getRole(), ['admin', 'supervisor'])) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $data = $request->toArray();

        if (empty($data['title']) || empty($data['message'])) {
            return $this->json(['error' => 'Titre et message sont obligatoires.'], 422);
        }

        $announcement = new Announcement();
        $announcement->setTitle($data['title']);
        $announcement->setMessage($data['message']);
        $announcement->setPriority($data['priority'] ?? 'info');          // info | important | urgent
        $announcement->setTargetRole($data['targetRole'] ?? 'all');       // all | agent | supervisor
        $announcement->setActive(true);
        $announcement->setCreatedBy($user);

        $this->em->persist($announcement);
        $this->em->flush();

        return $this->json($this->serialize($announcement), 201);
    }

    // -----------------------------------------------------------------------
    // PUT /api/announcements/{id}  — modifier une annonce (admin)
    // -----------------------------------------------------------------------
    #[Route('/{id}', name: 'update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(Announcement $announcement, Request $request): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if ($user->getRole() !== 'admin') {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $data = $request->toArray();

        if (isset($data['title']))      $announcement->setTitle($data['title']);
        if (isset($data['message']))    $announcement->setMessage($data['message']);
        if (isset($data['priority']))   $announcement->setPriority($data['priority']);
        if (isset($data['targetRole'])) $announcement->setTargetRole($data['targetRole']);
        if (isset($data['active']))     $announcement->setActive((bool) $data['active']);

        if (isset($data['active']) && !$data['active']) {
            $announcement->setArchivedAt(new \DateTime());
        }

        $this->em->flush();

        return $this->json($this->serialize($announcement));
    }

    // -----------------------------------------------------------------------
    // POST /api/announcements/{id}/ack  — confirmer lecture
    // -----------------------------------------------------------------------
    #[Route('/{id}/ack', name: 'ack', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function ack(Announcement $announcement, Request $request): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $data = $request->toArray();
        $action = $data['action'] ?? 'acknowledge'; // acknowledge | remind_later

        if ($action === 'remind_later') {
            // Reporter de 2 heures
            $remindAfter = (new \DateTime())->modify('+2 hours');
            $this->upsertRead($announcement->getId(), $user->getId(), [
                'remindAfter'   => $remindAfter,
                'reminderCount' => true, // incrémenter
            ]);

            return $this->json([
                'success'    => true,
                'action'     => 'remind_later',
                'remindAfter'=> $remindAfter->format('Y-m-d H:i:s'),
            ]);
        }

        // Confirmer la lecture
        $this->upsertRead($announcement->getId(), $user->getId(), [
            'acknowledgedAt' => new \DateTime(),
        ]);

        return $this->json(['success' => true, 'action' => 'acknowledged']);
    }

    // -----------------------------------------------------------------------
    // POST /api/announcements/{id}/archive  — archiver (admin)
    // -----------------------------------------------------------------------
    #[Route('/{id}/archive', name: 'archive', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function archive(Announcement $announcement): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if ($user->getRole() !== 'admin') {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $announcement->setActive(false);
        $announcement->setArchivedAt(new \DateTime());
        $this->em->flush();

        return $this->json(['success' => true]);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function upsertRead(int $announcementId, int $userId, array $fields): void
    {
        $read = $this->readRepo->findOneBy([
            'announcement' => $announcementId,
            'user'         => $userId,
        ]);

        if (!$read) {
            $announcement = $this->em->getReference(Announcement::class, $announcementId);
            $user = $this->em->getReference(\App\Entity\User::class, $userId);

            $read = new AnnouncementRead();
            $read->setAnnouncement($announcement);
            $read->setUser($user);
            $this->em->persist($read);
        }

        if (isset($fields['acknowledgedAt'])) {
            $read->setAcknowledgedAt($fields['acknowledgedAt']);
        }
        if (isset($fields['remindAfter'])) {
            $read->setRemindAfter($fields['remindAfter']);
        }
        if (isset($fields['lastSeenAt'])) {
            $read->setLastSeenAt($fields['lastSeenAt']);
        }
        if (!empty($fields['reminderCount'])) {
            $read->setReminderCount($read->getReminderCount() + 1);
        }

        $read->setUpdatedAt(new \DateTime());
        $this->em->flush();
    }

    private function serialize(Announcement $a): array
    {
        return [
            'id'         => $a->getId(),
            'title'      => $a->getTitle(),
            'message'    => $a->getMessage(),
            'priority'   => $a->getPriority(),
            'targetRole' => $a->getTargetRole(),
            'active'     => $a->isActive(),
            'createdBy'  => $a->getCreatedBy()?->getUsername(),
            'createdAt'  => $a->getCreatedAt()?->format('Y-m-d H:i:s'),
            'archivedAt' => $a->getArchivedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
