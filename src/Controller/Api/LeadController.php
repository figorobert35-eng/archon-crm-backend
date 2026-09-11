<?php

namespace App\Controller\Api;

use App\Entity\Lead;
use App\Entity\LeadEvent;
use App\Repository\CampaignRepository;
use App\Repository\LeadRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/leads', name: 'api_leads_')]
class LeadController extends AbstractController
{
    // Statuts autorisés pour la codification
    private const CODIFICATION_STATUSES = [
        'Souscription',
        'Devis Envoye',
        'Lead Injoignable',
        'HC- Deja Assure',
        'HC- Pas de trotinette',
        'Client Pas Interesse',
        'Client Injoignable',
        'Rappel planifie',
        'Qualifie avec RDV',
        'Assignation Responsable',
    ];

    private const ADMIN_SUPERVISOR_STATUSES = [
        'info manquante_ Rappel client',
    ];

    public function __construct(
        private LeadRepository      $leadRepo,
        private CampaignRepository  $campaignRepo,
        private UserRepository      $userRepo,
        private EntityManagerInterface $em,
    ) {}

    // -----------------------------------------------------------------------
    // GET /api/leads
    // -----------------------------------------------------------------------
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
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

        $leads = $this->leadRepo->findFiltered($user, $filters);

        return $this->json(array_map(fn(Lead $l) => $this->serializeLead($l), $leads));
    }

    // -----------------------------------------------------------------------
    // POST /api/leads  — créer un lead manuellement
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

        if (empty($data['phone'])) {
            return $this->json(['error' => 'Le téléphone est obligatoire.'], 422);
        }

        $lead = new Lead();
        $lead->setPhone($data['phone']);
        $lead->setClientName($data['clientName'] ?? '');
        $lead->setPhone2($data['phone2'] ?? '');
        $lead->setEmail($data['email'] ?? '');
        $lead->setAddress($data['address'] ?? '');
        $lead->setCity($data['city'] ?? '');
        $lead->setSource($data['source'] ?? '');
        $lead->setRequestDetails($data['requestDetails'] ?? '');
        $lead->setProduct($data['product'] ?? 'Trotinette');
        $lead->setStatus('Nouveau');
        $lead->setCreatedBy($user->getUsername());
        $lead->setInjectionDate(new \DateTime());

        if (!empty($data['campaignId'])) {
            $campaign = $this->campaignRepo->find((int) $data['campaignId']);
            if ($campaign) {
                $lead->setCampaign($campaign);
            }
        }

        if (!empty($data['assignedToId'])) {
            $agent = $this->userRepo->find((int) $data['assignedToId']);
            if ($agent) {
                $lead->setAssignedTo($agent);
            }
        }

        $this->em->persist($lead);
        $this->em->flush();

        return $this->json($this->serializeLeadDetail($lead), 201);
    }

    // -----------------------------------------------------------------------
    // GET /api/leads/{id}
    // -----------------------------------------------------------------------
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Lead $lead): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if ($user->getRole() === 'agent' && $lead->getAssignedTo()?->getId() !== $user->getId()) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        return $this->json($this->serializeLeadDetail($lead));
    }

    // -----------------------------------------------------------------------
    // PUT /api/leads/{id}  — modifier un lead
    // -----------------------------------------------------------------------
    #[Route('/{id}', name: 'update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(Lead $lead, Request $request): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        // Un agent ne peut modifier que ses propres leads
        if ($user->getRole() === 'agent' && $lead->getAssignedTo()?->getId() !== $user->getId()) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $data = $request->toArray();

        if (isset($data['clientName']))    $lead->setClientName($data['clientName']);
        if (isset($data['phone']))         $lead->setPhone($data['phone']);
        if (isset($data['phone2']))        $lead->setPhone2($data['phone2']);
        if (isset($data['email']))         $lead->setEmail($data['email']);
        if (isset($data['address']))       $lead->setAddress($data['address']);
        if (isset($data['city']))          $lead->setCity($data['city']);
        if (isset($data['source']))        $lead->setSource($data['source']);
        if (isset($data['requestDetails']))$lead->setRequestDetails($data['requestDetails']);
        if (isset($data['product']))       $lead->setProduct($data['product']);
        if (isset($data['quoteNumber']))   $lead->setQuoteNumber($data['quoteNumber']);
        if (isset($data['lastNote']))      $lead->setLastNote($data['lastNote']);

        if (!empty($data['campaignId'])) {
            $campaign = $this->campaignRepo->find((int) $data['campaignId']);
            if ($campaign) $lead->setCampaign($campaign);
        }

        $lead->setUpdatedAt(new \DateTime());
        $this->em->flush();

        return $this->json($this->serializeLeadDetail($lead));
    }

    // -----------------------------------------------------------------------
    // POST /api/leads/{id}/codify  — codifier un lead
    // -----------------------------------------------------------------------
    #[Route('/{id}/codify', name: 'codify', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function codify(Lead $lead, Request $request): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        // Seul l'agent assigné, le superviseur ou l'admin peut codifier
        if ($user->getRole() === 'agent' && $lead->getAssignedTo()?->getId() !== $user->getId()) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $data = $request->toArray();
        $newStatus = trim($data['status'] ?? '');
        $note      = trim($data['note'] ?? '');
        $callbackAt = null;

        // Valider le statut
        $allowedStatuses = array_merge(['Nouveau'], self::CODIFICATION_STATUSES);
        if (in_array($user->getRole(), ['admin', 'supervisor'])) {
            $allowedStatuses = array_merge($allowedStatuses, self::ADMIN_SUPERVISOR_STATUSES);
        }

        if (!in_array($newStatus, $allowedStatuses)) {
            return $this->json(['error' => "Statut invalide : {$newStatus}"], 422);
        }

        // Callback obligatoire pour certains statuts
        $callbackStatuses = ['Rappel planifie', 'Qualifie avec RDV', 'info manquante_ Rappel client'];
        if (in_array($newStatus, $callbackStatuses)) {
            if (empty($data['callbackAt'])) {
                return $this->json(['error' => 'Une date de rappel est requise pour ce statut.'], 422);
            }
            try {
                $callbackAt = new \DateTime($data['callbackAt']);
            } catch (\Exception) {
                return $this->json(['error' => 'Format de date invalide.'], 422);
            }
        }

        $oldStatus = $lead->getStatus();

        // Mettre à jour le lead
        $lead->setStatus($newStatus);
        $lead->setLastNote($note ?: $lead->getLastNote());
        $lead->setUpdatedAt(new \DateTime());

        if ($callbackAt) {
            $lead->setCallbackAt($callbackAt);
        } elseif (!in_array($newStatus, $callbackStatuses)) {
            // Effacer le callback si le nouveau statut n'en nécessite pas
            $lead->setCallbackAt(null);
        }

        // Enregistrer l'événement dans l'historique
        $event = new LeadEvent();
        $event->setLead($lead);
        $event->setUser($user);
        $event->setEventType('codification');
        $event->setOldStatus($oldStatus);
        $event->setNewStatus($newStatus);
        $event->setNote($note);
        $event->setCallbackAt($callbackAt);

        $this->em->persist($event);
        $this->em->flush();

        return $this->json([
            'success'   => true,
            'lead'      => $this->serializeLead($lead),
            'event'     => $this->serializeEvent($event),
        ]);
    }

    // -----------------------------------------------------------------------
    // GET /api/leads/{id}/history
    // -----------------------------------------------------------------------
    #[Route('/{id}/history', name: 'history', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function history(Lead $lead): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if ($user->getRole() === 'agent' && $lead->getAssignedTo()?->getId() !== $user->getId()) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        // Charger les events triés par date DESC
        $events = $lead->getEvents()->toArray();
        usort($events, fn($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt());

        return $this->json(array_map(fn($e) => $this->serializeEvent($e), $events));
    }

    // -----------------------------------------------------------------------
    // POST /api/leads/{id}/assign  — assigner à un agent
    // -----------------------------------------------------------------------
    #[Route('/{id}/assign', name: 'assign', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function assign(Lead $lead, Request $request): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if (!in_array($user->getRole(), ['admin', 'supervisor'])) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $data = $request->toArray();

        if (empty($data['agentId'])) {
            // Désassigner
            $lead->setAssignedTo(null);
        } else {
            $agent = $this->userRepo->find((int) $data['agentId']);
            if (!$agent || $agent->getRole() !== 'agent') {
                return $this->json(['error' => 'Agent introuvable.'], 404);
            }
            $lead->setAssignedTo($agent);
        }

        $lead->setUpdatedAt(new \DateTime());

        // Enregistrer l'événement
        $event = new LeadEvent();
        $event->setLead($lead);
        $event->setUser($user);
        $event->setEventType('assignation');
        $event->setNote($data['note'] ?? '');

        $this->em->persist($event);
        $this->em->flush();

        return $this->json([
            'success' => true,
            'lead'    => $this->serializeLead($lead),
        ]);
    }

    // -----------------------------------------------------------------------
    // GET /api/leads/statuses  — liste des statuts disponibles
    // -----------------------------------------------------------------------
    #[Route('/statuses', name: 'statuses', methods: ['GET'])]
    public function statuses(): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $statuses = array_merge(['Nouveau'], self::CODIFICATION_STATUSES);
        if (in_array($user->getRole(), ['admin', 'supervisor'])) {
            $statuses = array_merge($statuses, self::ADMIN_SUPERVISOR_STATUSES);
        }

        return $this->json($statuses);
    }

    // -----------------------------------------------------------------------
    // Sérialiseurs privés
    // -----------------------------------------------------------------------

    private function serializeLead(Lead $lead): array
    {
        return [
            'id'            => $lead->getId(),
            'clientName'    => $lead->getClientName(),
            'phone'         => $lead->getPhone(),
            'phone2'        => $lead->getPhone2(),
            'email'         => $lead->getEmail(),
            'city'          => $lead->getCity(),
            'status'        => $lead->getStatus(),
            'product'       => $lead->getProduct(),
            'callbackAt'    => $lead->getCallbackAt()?->format('Y-m-d H:i:s'),
            'lastNote'      => $lead->getLastNote(),
            'injectionDate' => $lead->getInjectionDate()?->format('Y-m-d'),
            'quoteNumber'   => $lead->getQuoteNumber(),
            'assignedTo'    => $lead->getAssignedTo() ? [
                'id'       => $lead->getAssignedTo()->getId(),
                'username' => $lead->getAssignedTo()->getUsername(),
            ] : null,
            'campaign'      => $lead->getCampaign() ? [
                'id'    => $lead->getCampaign()->getId(),
                'name'  => $lead->getCampaign()->getName(),
                'color' => $lead->getCampaign()->getColor(),
            ] : null,
            'updatedAt'     => $lead->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    private function serializeLeadDetail(Lead $lead): array
    {
        $data = $this->serializeLead($lead);
        $data['address']        = $lead->getAddress();
        $data['source']         = $lead->getSource();
        $data['requestDetails'] = $lead->getRequestDetails();
        $data['createdBy']      = $lead->getCreatedBy();
        $data['createdAt']      = $lead->getCreatedAt()?->format('Y-m-d H:i:s');

        $events = $lead->getEvents()->toArray();
        usort($events, fn($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt());
        $data['events'] = array_map(fn($e) => $this->serializeEvent($e), $events);

        $data['attachments'] = array_map(fn($a) => [
            'id'             => $a->getId(),
            'attachmentType' => $a->getAttachmentType(),
            'originalName'   => $a->getOriginalName(),
            'mimeType'       => $a->getMimeType(),
            'size'           => $a->getSize(),
            'createdAt'      => $a->getCreatedAt()?->format('Y-m-d H:i:s'),
        ], $lead->getAttachments()->toArray());

        return $data;
    }

    private function serializeEvent(LeadEvent $e): array
    {
        return [
            'id'        => $e->getId(),
            'eventType' => $e->getEventType(),
            'oldStatus' => $e->getOldStatus(),
            'newStatus' => $e->getNewStatus(),
            'note'      => $e->getNote(),
            'callbackAt'=> $e->getCallbackAt()?->format('Y-m-d H:i:s'),
            'createdAt' => $e->getCreatedAt()?->format('Y-m-d H:i:s'),
            'user'      => $e->getUser() ? [
                'id'       => $e->getUser()->getId(),
                'username' => $e->getUser()->getUsername(),
            ] : null,
        ];
    }
}
