<?php

namespace App\Controller\Api;

use App\Repository\LeadRepository;
use App\Repository\UserRepository;
use App\Repository\WorkLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/stats', name: 'api_stats_')]
class StatsController extends AbstractController
{
    public function __construct(
        private LeadRepository         $leadRepo,
        private UserRepository         $userRepo,
        private WorkLogRepository      $workLogRepo,
        private EntityManagerInterface $em,
    ) {}

    /**
     * GET /api/stats
     *
     * Query params :
     *   - from   : date début (défaut : 1er du mois)
     *   - to     : date fin   (défaut : aujourd'hui)
     *   - userId : filtrer sur un agent (admin seulement)
     */
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        /** @var \App\Entity\User $currentUser */
        $currentUser = $this->getUser();

        $from = $request->query->get('from', (new \DateTime('first day of this month'))->format('Y-m-d'));
        $to   = $request->query->get('to',   (new \DateTime())->format('Y-m-d'));
        $userId = $request->query->get('userId');

        // Un agent ne peut voir que ses propres stats
        if ($currentUser->getRole() === 'agent') {
            $userId = $currentUser->getId();
        }

        // Liste des agents à inclure
        if ($userId) {
            $agents = [$this->userRepo->find((int) $userId)];
            $agents = array_filter($agents);
        } elseif ($currentUser->getRole() === 'supervisor') {
            $agents = $this->userRepo->findAgentsBySupervisor($currentUser->getId());
        } else {
            // admin : tous les agents actifs
            $agents = $this->userRepo->findBy(['role' => 'agent', 'accountStatus' => 'active'], ['username' => 'ASC']);
        }

        $stats = [];
        foreach ($agents as $agent) {
            $stats[] = $this->buildAgentStats($agent, $from, $to);
        }

        // Stats globales
        $globalByStatus = $this->leadRepo->countByStatusGlobal($from, $to, $userId ? (int)$userId : null);

        return $this->json([
            'from'        => $from,
            'to'          => $to,
            'agents'      => $stats,
            'globalByStatus' => $globalByStatus,
        ]);
    }

    /**
     * GET /api/stats/global
     * Vue d'ensemble rapide pour le dashboard admin
     */
    #[Route('/global', name: 'global', methods: ['GET'])]
    public function global(Request $request): JsonResponse
    {
        /** @var \App\Entity\User $currentUser */
        $currentUser = $this->getUser();

        if ($currentUser->getRole() === 'agent') {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $today = (new \DateTime())->format('Y-m-d');

        // Leads du jour
        $leadsToday = $this->leadRepo->countCreatedToday();

        // Total leads actifs
        $totalLeads = $this->leadRepo->count([]);

        // Agents loggés en ce moment
        $activeAgents = $this->workLogRepo->countActiveNow();

        // Rappels en retard
        $lateCallbacks = $this->leadRepo->countLateCallbacks();

        return $this->json([
            'leadsToday'    => $leadsToday,
            'totalLeads'    => $totalLeads,
            'activeAgents'  => $activeAgents,
            'lateCallbacks' => $lateCallbacks,
        ]);
    }

    // -----------------------------------------------------------------------
    private function buildAgentStats(\App\Entity\User $agent, string $from, string $to): array
    {
        // Leads traités (avec event de codification) sur la période
        $treated = $this->leadRepo->countTreatedByAgent($agent->getId(), $from, $to);

        // Leads par statut assignés à l'agent
        $byStatus = $this->leadRepo->countByStatusForAgent($agent->getId());

        // Durée de présence totale sur la période (minutes)
        $presenceMin = $this->workLogRepo->totalMinutesForUser($agent->getId(), $from, $to);

        // Nombre de sessions de travail
        $sessions = $this->workLogRepo->countSessionsForUser($agent->getId(), $from, $to);

        return [
            'userId'      => $agent->getId(),
            'username'    => $agent->getUsername(),
            'employeeCode'=> $agent->getEmployeeCode(),
            'treated'     => $treated,
            'byStatus'    => $byStatus,
            'presenceMin' => $presenceMin,
            'sessions'    => $sessions,
        ];
    }
}
