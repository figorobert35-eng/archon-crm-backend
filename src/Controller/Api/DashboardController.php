<?php

namespace App\Controller\Api;

use App\Repository\LeadRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/dashboard', name: 'api_dashboard_', methods: ['GET'])]
class DashboardController extends AbstractController
{
    public function __construct(
        private LeadRepository $leadRepo,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        // Comptages par statut
        $byStatus = $this->leadRepo->countByStatus($user);

        $total = array_sum($byStatus);
        $nouveaux = $byStatus['Nouveau'] ?? 0;
        $rappels  = $byStatus['Rappel planifie'] ?? 0;

        // Rappels d'aujourd'hui
        $callbacksToday = $this->leadRepo->findCallbacksToday($user);
        $callbacksTodayCount = count($callbacksToday);

        // Rappels en retard (callbackAt < maintenant)
        $lateCount = 0;
        $now = new \DateTime();
        foreach ($callbacksToday as $lead) {
            if ($lead->getCallbackAt() < $now) {
                $lateCount++;
            }
        }

        return $this->json([
            'total'          => $total,
            'nouveaux'       => $nouveaux,
            'rappels'        => $rappels,
            'callbacksToday' => $callbacksTodayCount,
            'late'           => $lateCount,
            'byStatus'       => $byStatus,
            'role'           => $user->getRole(),
            'username'       => $user->getUsername(),
        ]);
    }
}
