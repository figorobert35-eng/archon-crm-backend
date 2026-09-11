<?php

namespace App\Controller\Api;

use App\Repository\LeadRepository;
use App\Repository\UserRepository;
use App\Repository\WorkLogRepository;
use App\Repository\BreakLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/export', name: 'api_export_')]
class ExportController extends AbstractController
{
    public function __construct(
        private LeadRepository    $leadRepo,
        private UserRepository    $userRepo,
        private WorkLogRepository $workLogRepo,
        private BreakLogRepository $breakLogRepo,
    ) {}

    // -----------------------------------------------------------------------
    // GET /api/export/leads.csv
    // -----------------------------------------------------------------------
    #[Route('/leads.csv', name: 'leads', methods: ['GET'])]
    public function leads(Request $request): StreamedResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $filters = [
            'status'    => $request->query->get('status', ''),
            'assigned'  => $request->query->get('assigned', ''),
            'campaign'  => $request->query->get('campaign', ''),
            'injection' => $request->query->get('injection', ''),
            'q'         => $request->query->get('q', ''),
            'quick'     => $request->query->get('quick', ''),
        ];

        // Lever la limite de 300 pour l'export
        $leads = $this->leadRepo->findFilteredUnlimited($user, $filters);

        $filename = 'leads_' . date('Y-m-d') . '.csv';

        $response = new StreamedResponse(function () use ($leads) {
            $handle = fopen('php://output', 'w');

            // BOM UTF-8 pour Excel
            fwrite($handle, "\xEF\xBB\xBF");

            // En-têtes
            fputcsv($handle, [
                'ID', 'Nom client', 'Téléphone', 'Téléphone 2', 'Email',
                'Ville', 'Adresse', 'Source', 'Détails demande',
                'Statut', 'Produit', 'Numéro devis',
                'Date rappel', 'Dernière note',
                'Campagne', 'Agent assigné',
                'Date injection', 'Créé par', 'Créé le', 'Modifié le',
            ], ';');

            foreach ($leads as $lead) {
                fputcsv($handle, [
                    $lead->getId(),
                    $lead->getClientName(),
                    $lead->getPhone(),
                    $lead->getPhone2(),
                    $lead->getEmail(),
                    $lead->getCity(),
                    $lead->getAddress(),
                    $lead->getSource(),
                    $lead->getRequestDetails(),
                    $lead->getStatus(),
                    $lead->getProduct(),
                    $lead->getQuoteNumber(),
                    $lead->getCallbackAt()?->format('d/m/Y H:i') ?? '',
                    $lead->getLastNote(),
                    $lead->getCampaign()?->getName() ?? '',
                    $lead->getAssignedTo()?->getUsername() ?? '',
                    $lead->getInjectionDate()?->format('Y-m-d') ?? '',
                    $lead->getCreatedBy(),
                    $lead->getCreatedAt()?->format('d/m/Y H:i') ?? '',
                    $lead->getUpdatedAt()?->format('d/m/Y H:i') ?? '',
                ], ';');
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', "attachment; filename=\"{$filename}\"");
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    // -----------------------------------------------------------------------
    // GET /api/export/work-logs.csv
    // -----------------------------------------------------------------------
    #[Route('/work-logs.csv', name: 'work_logs', methods: ['GET'])]
    public function workLogs(Request $request): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if (!in_array($user->getRole(), ['admin', 'supervisor'])) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $from = $request->query->get('from', (new \DateTime('first day of this month'))->format('Y-m-d'));
        $to   = $request->query->get('to',   (new \DateTime())->format('Y-m-d'));

        $filters = ['from' => $from, 'to' => $to];
        if ($user->getRole() === 'supervisor') {
            $filters['supervisorId'] = $user->getId();
        }

        $logs = $this->workLogRepo->findFiltered($filters);

        // Regrouper les logs par agent et par jour
        $byAgentDay = [];
        foreach ($logs as $log) {
            $agentId  = $log->getUser()?->getId();
            $day      = $log->getLoggedInAt()?->format('Y-m-d');
            $key      = "{$agentId}_{$day}";

            if (!isset($byAgentDay[$key])) {
                $byAgentDay[$key] = [
                    'userId'       => $agentId,
                    'username'     => $log->getUser()?->getUsername() ?? '',
                    'employeeCode' => $log->getUser()?->getEmployeeCode() ?? '',
                    'day'          => $day,
                    'totalMin'     => 0,
                    'sessions'     => 0,
                    'status'       => 'pending',
                ];
            }

            $inAt  = $log->getLoggedInAt();
            $outAt = $log->getLoggedOutAt();
            if ($inAt && $outAt) {
                $byAgentDay[$key]['totalMin'] += (int) round(($outAt->getTimestamp() - $inAt->getTimestamp()) / 60);
            }
            $byAgentDay[$key]['sessions']++;
            if ($log->getApprovalStatus() === 'validated') {
                $byAgentDay[$key]['status'] = 'validated';
            }
        }

        $filename = 'pointage_' . $from . '_' . $to . '.csv';

        $response = new StreamedResponse(function () use ($byAgentDay) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Code employé', 'Identifiant', 'Date',
                'Durée (min)', 'Durée (h)', 'Sessions', 'Statut validation',
            ], ';');

            foreach ($byAgentDay as $row) {
                fputcsv($handle, [
                    $row['employeeCode'],
                    $row['username'],
                    $row['day'],
                    $row['totalMin'],
                    number_format($row['totalMin'] / 60, 2, ',', ''),
                    $row['sessions'],
                    $row['status'],
                ], ';');
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', "attachment; filename=\"{$filename}\"");
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
