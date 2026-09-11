<?php

namespace App\Controller\Api;

use App\Entity\Campaign;
use App\Repository\CampaignRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/campaigns', name: 'api_campaigns_')]
class CampaignController extends AbstractController
{
    public function __construct(
        private CampaignRepository     $campaignRepo,
        private EntityManagerInterface $em,
    ) {}

    /**
     * GET /api/campaigns
     * Retourne les campagnes actives (ou toutes si ?all=1 pour admin)
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $showAll = $request->query->getBoolean('all') && in_array($user->getRole(), ['admin', 'supervisor']);

        $campaigns = $showAll
            ? $this->campaignRepo->findAll()
            : $this->campaignRepo->findActive();

        return $this->json(array_map(fn(Campaign $c) => $this->serialize($c), $campaigns));
    }

    /**
     * GET /api/campaigns/{id}
     */
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Campaign $campaign): JsonResponse
    {
        return $this->json($this->serializeDetail($campaign));
    }

    /**
     * POST /api/campaigns  — créer une campagne (admin seulement)
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        if ($user->getRole() !== 'admin') {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $data = $request->toArray();

        if (empty($data['name'])) {
            return $this->json(['error' => 'Le nom est obligatoire.'], 422);
        }

        $campaign = new Campaign();
        $campaign->setName($data['name']);
        $campaign->setDescription($data['description'] ?? '');
        $campaign->setScript($data['script'] ?? '');
        $campaign->setColor($data['color'] ?? '#8f1d14');
        $campaign->setCodificationStatuses($data['codificationStatuses'] ?? '');
        $campaign->setActive(true);
        $campaign->setArchived(false);

        $this->em->persist($campaign);
        $this->em->flush();

        return $this->json($this->serialize($campaign), 201);
    }

    /**
     * PUT /api/campaigns/{id}  — modifier une campagne (admin seulement)
     */
    #[Route('/{id}', name: 'update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(Campaign $campaign, Request $request): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        if ($user->getRole() !== 'admin') {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $data = $request->toArray();

        if (isset($data['name']))                  $campaign->setName($data['name']);
        if (isset($data['description']))           $campaign->setDescription($data['description']);
        if (isset($data['script']))                $campaign->setScript($data['script']);
        if (isset($data['color']))                 $campaign->setColor($data['color']);
        if (isset($data['codificationStatuses']))  $campaign->setCodificationStatuses($data['codificationStatuses']);
        if (isset($data['active']))                $campaign->setActive((bool) $data['active']);
        if (isset($data['archived']))              $campaign->setArchived((bool) $data['archived']);

        $campaign->setUpdatedAt(new \DateTime());
        $this->em->flush();

        return $this->json($this->serialize($campaign));
    }

    // -----------------------------------------------------------------------
    private function serialize(Campaign $c): array
    {
        return [
            'id'          => $c->getId(),
            'name'        => $c->getName(),
            'color'       => $c->getColor(),
            'active'      => $c->isActive(),
            'archived'    => $c->isArchived(),
            'description' => $c->getDescription(),
        ];
    }

    private function serializeDetail(Campaign $c): array
    {
        $data = $this->serialize($c);
        $data['script']               = $c->getScript();
        $data['codificationStatuses'] = $c->getCodificationStatuses();
        $data['createdAt']            = $c->getCreatedAt()?->format('Y-m-d H:i:s');
        $data['updatedAt']            = $c->getUpdatedAt()?->format('Y-m-d H:i:s');
        $data['agentsCount']          = $c->getAgents()->count();
        return $data;
    }
}
