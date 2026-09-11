<?php

namespace App\Controller\Api;

use App\Entity\BreakLog;
use App\Entity\WorkLog;
use App\Entity\WorkLogCorrection;
use App\Repository\BreakLogRepository;
use App\Repository\UserRepository;
use App\Repository\WorkLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api', name: 'api_worklog_')]
class WorkLogController extends AbstractController
{
    // Plages horaires valides (heure Morocco)
    private const SCHEDULE = [
        ['start' => '09:00', 'end' => '12:00', 'label' => 'Matin'],
        ['start' => '13:30', 'end' => '19:00', 'label' => 'Apres-midi'],
    ];

    private const BREAK_WINDOWS = [
        'morning'   => ['start' => '09:00', 'end' => '12:00'],
        'afternoon' => ['start' => '14:30', 'end' => '18:30'],
    ];

    public function __construct(
        private WorkLogRepository  $workLogRepo,
        private BreakLogRepository $breakLogRepo,
        private UserRepository     $userRepo,
        private EntityManagerInterface $em,
    ) {}

    // -----------------------------------------------------------------------
    // POST /api/work-log/in  — pointer arrivée
    // -----------------------------------------------------------------------
    #[Route('/work-log/in', name: 'log_in', methods: ['POST'])]
    public function logIn(): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        // Vérifier qu'il n'y a pas déjà un log actif
        $active = $this->workLogRepo->findActiveForUser($user->getId());
        if ($active) {
            return $this->json(['error' => 'Vous êtes déjà loggé depuis ' . $active->getLoggedInAt()->format('H:i') . '.'], 409);
        }

        $now = new \DateTime();

        $log = new WorkLog();
        $log->setUser($user);
        $log->setLoggedInAt($now);
        $log->setApprovalStatus('pending');

        $this->em->persist($log);
        $this->em->flush();

        return $this->json([
            'success'    => true,
            'workLogId'  => $log->getId(),
            'loggedInAt' => $log->getLoggedInAt()->format('Y-m-d H:i:s'),
        ], 201);
    }

    // -----------------------------------------------------------------------
    // POST /api/work-log/out  — pointer départ
    // -----------------------------------------------------------------------
    #[Route('/work-log/out', name: 'log_out', methods: ['POST'])]
    public function logOut(): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $active = $this->workLogRepo->findActiveForUser($user->getId());
        if (!$active) {
            return $this->json(['error' => 'Aucun pointage actif trouvé.'], 404);
        }

        // Fermer la pause en cours si elle existe
        $activeBreak = $this->breakLogRepo->findActiveForUser($user->getId());
        if ($activeBreak) {
            $activeBreak->setEndedAt(new \DateTime());
            $activeBreak->setUpdatedAt(new \DateTime());
        }

        $now = new \DateTime();
        $active->setLoggedOutAt($now);
        $active->setUpdatedAt($now);

        $this->em->flush();

        return $this->json([
            'success'     => true,
            'loggedOutAt' => $active->getLoggedOutAt()->format('Y-m-d H:i:s'),
            'durationMin' => (int) round(($active->getLoggedOutAt()->getTimestamp() - $active->getLoggedInAt()->getTimestamp()) / 60),
        ]);
    }

    // -----------------------------------------------------------------------
    // POST /api/break-log/start  — début pause
    // -----------------------------------------------------------------------
    #[Route('/break-log/start', name: 'break_start', methods: ['POST'])]
    public function breakStart(Request $request): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $data      = $request->toArray();
        $breakType = $data['breakType'] ?? 'morning'; // morning | afternoon

        if (!isset(self::BREAK_WINDOWS[$breakType])) {
            return $this->json(['error' => 'Type de pause invalide. Utilisez "morning" ou "afternoon".'], 422);
        }

        // Vérifier qu'il y a un log actif
        $activeLog = $this->workLogRepo->findActiveForUser($user->getId());
        if (!$activeLog) {
            return $this->json(['error' => 'Vous devez être loggé pour prendre une pause.'], 409);
        }

        // Vérifier qu'il n'y a pas déjà une pause active
        $activeBreak = $this->breakLogRepo->findActiveForUser($user->getId());
        if ($activeBreak) {
            return $this->json(['error' => 'Une pause est déjà en cours.'], 409);
        }

        // Vérifier que cette pause n'a pas déjà été prise aujourd'hui
        $today = (new \DateTime())->format('Y-m-d');
        $alreadyUsed = $this->breakLogRepo->hasUsedBreakToday($user->getId(), $breakType, $today);
        if ($alreadyUsed) {
            $label = $breakType === 'afternoon' ? 'après-midi' : 'matin';
            return $this->json(['error' => "La pause {$label} a déjà été prise aujourd'hui."], 409);
        }

        $now = new \DateTime();

        $break = new BreakLog();
        $break->setUser($user);
        $break->setBreakType($breakType);
        $break->setStartedAt($now);

        $this->em->persist($break);
        $this->em->flush();

        return $this->json([
            'success'   => true,
            'breakId'   => $break->getId(),
            'breakType' => $break->getBreakType(),
            'startedAt' => $break->getStartedAt()->format('Y-m-d H:i:s'),
        ], 201);
    }

    // -----------------------------------------------------------------------
    // POST /api/break-log/end  — fin pause
    // -----------------------------------------------------------------------
    #[Route('/break-log/end', name: 'break_end', methods: ['POST'])]
    public function breakEnd(): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $activeBreak = $this->breakLogRepo->findActiveForUser($user->getId());
        if (!$activeBreak) {
            return $this->json(['error' => 'Aucune pause en cours.'], 404);
        }

        $now = new \DateTime();
        $activeBreak->setEndedAt($now);
        $activeBreak->setUpdatedAt($now);

        $this->em->flush();

        $durationMin = (int) round(($now->getTimestamp() - $activeBreak->getStartedAt()->getTimestamp()) / 60);

        return $this->json([
            'success'     => true,
            'endedAt'     => $now->format('Y-m-d H:i:s'),
            'durationMin' => $durationMin,
        ]);
    }

    // -----------------------------------------------------------------------
    // GET /api/work-logs/me  — mes pointages (agent)
    // -----------------------------------------------------------------------
    #[Route('/work-logs/me', name: 'my_logs', methods: ['GET'])]
    public function myLogs(Request $request): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $from = $request->query->get('from', (new \DateTime('first day of this month'))->format('Y-m-d'));
        $to   = $request->query->get('to',   (new \DateTime('last day of this month'))->format('Y-m-d'));

        $logs = $this->workLogRepo->findForUser($user->getId(), $from, $to);

        // Récupérer aussi le log actif
        $active = $this->workLogRepo->findActiveForUser($user->getId());
        $activeBreak = $this->breakLogRepo->findActiveForUser($user->getId());

        return $this->json([
            'active'      => $active ? $this->serializeLog($active, true) : null,
            'activeBreak' => $activeBreak ? $this->serializeBreak($activeBreak) : null,
            'logs'        => array_map(fn($l) => $this->serializeLog($l), $logs),
        ]);
    }

    // -----------------------------------------------------------------------
    // GET /api/work-logs  — liste globale (admin/supervisor)
    // -----------------------------------------------------------------------
    #[Route('/work-logs', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if (!in_array($user->getRole(), ['admin', 'supervisor'])) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $filters = [
            'from'            => $request->query->get('from', ''),
            'to'              => $request->query->get('to', ''),
            'userId'          => $request->query->get('userId', ''),
            'approvalStatus'  => $request->query->get('approvalStatus', ''),
        ];

        // Supervisor ne voit que son équipe
        if ($user->getRole() === 'supervisor') {
            $filters['supervisorId'] = $user->getId();
        }

        $logs = $this->workLogRepo->findFiltered($filters);

        return $this->json(array_map(fn($l) => $this->serializeLog($l), $logs));
    }

    // -----------------------------------------------------------------------
    // POST /api/work-logs/{id}/validate  — valider un pointage
    // -----------------------------------------------------------------------
    #[Route('/work-logs/{id}/validate', name: 'validate', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function validate(WorkLog $log, Request $request): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if (!in_array($user->getRole(), ['admin', 'supervisor'])) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $data = $request->toArray();

        $log->setApprovalStatus($data['approved'] ?? true ? 'validated' : 'rejected');
        $log->setValidatedBy($user);
        $log->setValidatedAt(new \DateTime());
        $log->setSupervisorNote($data['note'] ?? '');
        $log->setUpdatedAt(new \DateTime());

        $this->em->flush();

        return $this->json([
            'success'        => true,
            'approvalStatus' => $log->getApprovalStatus(),
            'validatedAt'    => $log->getValidatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    // -----------------------------------------------------------------------
    // POST /api/work-logs/{id}/correct  — corriger un pointage
    // -----------------------------------------------------------------------
    #[Route('/work-logs/{id}/correct', name: 'correct', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function correct(WorkLog $log, Request $request): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if (!in_array($user->getRole(), ['admin', 'supervisor'])) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $data = $request->toArray();

        if (empty($data['loggedInAt']) || empty($data['loggedOutAt'])) {
            return $this->json(['error' => 'loggedInAt et loggedOutAt sont obligatoires.'], 422);
        }

        try {
            $newIn  = new \DateTime($data['loggedInAt']);
            $newOut = new \DateTime($data['loggedOutAt']);
        } catch (\Exception) {
            return $this->json(['error' => 'Format de date invalide. Utilisez Y-m-d H:i:s.'], 422);
        }

        if ($newOut <= $newIn) {
            return $this->json(['error' => 'Le delog doit être après le log.'], 422);
        }

        // Sauvegarder la correction
        $correction = new WorkLogCorrection();
        $correction->setWorkLog($log);
        $correction->setUser($log->getUser());
        $correction->setOriginalLoggedInAt($log->getLoggedInAt());
        $correction->setOriginalLoggedOutAt($log->getLoggedOutAt());
        $correction->setCorrectedLoggedInAt($newIn);
        $correction->setCorrectedLoggedOutAt($newOut);
        $correction->setCorrectedBy($user);
        $correction->setCorrectionNote($data['note'] ?? '');
        $correction->setCorrectedAt(new \DateTime());

        // Appliquer la correction
        $log->setLoggedInAt($newIn);
        $log->setLoggedOutAt($newOut);
        $log->setUpdatedAt(new \DateTime());
        // Remettre en pending pour revalidation
        $log->setApprovalStatus('pending');
        $log->setValidatedBy(null);
        $log->setValidatedAt(null);

        $this->em->persist($correction);
        $this->em->flush();

        return $this->json([
            'success'  => true,
            'workLog'  => $this->serializeLog($log),
        ]);
    }

    // -----------------------------------------------------------------------
    // Sérialiseurs
    // -----------------------------------------------------------------------

    private function serializeLog(WorkLog $log, bool $withActive = false): array
    {
        $inAt  = $log->getLoggedInAt();
        $outAt = $log->getLoggedOutAt();

        $durationMin = null;
        if ($inAt && $outAt) {
            $durationMin = (int) round(($outAt->getTimestamp() - $inAt->getTimestamp()) / 60);
        } elseif ($withActive && $inAt) {
            $durationMin = (int) round((time() - $inAt->getTimestamp()) / 60);
        }

        return [
            'id'             => $log->getId(),
            'userId'         => $log->getUser()?->getId(),
            'username'       => $log->getUser()?->getUsername(),
            'loggedInAt'     => $inAt?->format('Y-m-d H:i:s'),
            'loggedOutAt'    => $outAt?->format('Y-m-d H:i:s'),
            'durationMin'    => $durationMin,
            'approvalStatus' => $log->getApprovalStatus(),
            'note'           => $log->getNote(),
            'supervisorNote' => $log->getSupervisorNote(),
            'validatedBy'    => $log->getValidatedBy()?->getUsername(),
            'validatedAt'    => $log->getValidatedAt()?->format('Y-m-d H:i:s'),
            'createdAt'      => $log->getCreatedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    private function serializeBreak(BreakLog $b): array
    {
        return [
            'id'        => $b->getId(),
            'breakType' => $b->getBreakType(),
            'startedAt' => $b->getStartedAt()?->format('Y-m-d H:i:s'),
            'endedAt'   => $b->getEndedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
