<?php

namespace App\Controller\Api;

use App\Repository\LeadRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/reminders', name: 'api_reminders_')]
class ReminderController extends AbstractController
{
    public function __construct(
        private LeadRepository $leadRepo,
    ) {}

    /**
     * GET /api/reminders
     * Retourne tous les rappels de l'utilisateur connecté,
     * avec leur état : late (en retard), today (aujourd'hui), future
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $quick = $request->query->get('quick', 'all'); // all | late | today | future

        $leads = $this->leadRepo->findReminders($user, $quick);

        $now      = new \DateTime();
        $today    = new \DateTime('today');
        $tomorrow = new \DateTime('tomorrow');

        $result = array_map(function ($lead) use ($now, $today, $tomorrow) {
            $cb = $lead->getCallbackAt();
            $state = 'future';
            if ($cb < $now) {
                $state = 'late';
            } elseif ($cb >= $today && $cb < $tomorrow) {
                $state = 'today';
            }

            return [
                'id'          => $lead->getId(),
                'clientName'  => $lead->getClientName(),
                'phone'       => $lead->getPhone(),
                'status'      => $lead->getStatus(),
                'callbackAt'  => $cb?->format('Y-m-d H:i:s'),
                'callbackState' => $state,
                'lastNote'    => $lead->getLastNote(),
                'campaign'    => $lead->getCampaign() ? [
                    'id'    => $lead->getCampaign()->getId(),
                    'name'  => $lead->getCampaign()->getName(),
                    'color' => $lead->getCampaign()->getColor(),
                ] : null,
                'assignedTo'  => $lead->getAssignedTo() ? [
                    'id'       => $lead->getAssignedTo()->getId(),
                    'username' => $lead->getAssignedTo()->getUsername(),
                ] : null,
            ];
        }, $leads);

        return $this->json([
            'total'     => count($result),
            'reminders' => $result,
        ]);
    }
}
