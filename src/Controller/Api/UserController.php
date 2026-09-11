<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/users', name: 'api_users_')]
class UserController extends AbstractController
{
    public function __construct(
        private UserRepository              $userRepo,
        private EntityManagerInterface      $em,
        private UserPasswordHasherInterface $hasher,
    ) {}

    /**
     * GET /api/users/agents
     * Liste des agents actifs (pour assignation / dropdowns)
     */
    #[Route('/agents', name: 'agents', methods: ['GET'])]
    public function agents(): JsonResponse
    {
        $agents = $this->userRepo->findBy(['role' => 'agent', 'accountStatus' => 'active'], ['username' => 'ASC']);

        return $this->json(array_map(fn(User $u) => $this->serializeBasic($u), $agents));
    }

    /**
     * GET /api/users
     * Liste complète (admin seulement)
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        if ($user->getRole() !== 'admin') {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $users = $this->userRepo->findBy([], ['username' => 'ASC']);

        return $this->json(array_map(fn(User $u) => $this->serializeFull($u), $users));
    }

    /**
     * GET /api/users/{id}
     */
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(User $target): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        if ($user->getRole() !== 'admin' && $user->getId() !== $target->getId()) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        return $this->json($this->serializeFull($target));
    }

    /**
     * POST /api/users  — créer un utilisateur (admin seulement)
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

        if (empty($data['username']) || empty($data['password'])) {
            return $this->json(['error' => 'Identifiant et mot de passe obligatoires.'], 422);
        }

        // Vérifier l'unicité du username
        if ($this->userRepo->findOneBy(['username' => $data['username']])) {
            return $this->json(['error' => "L'identifiant \"{$data['username']}\" est déjà utilisé."], 409);
        }

        $newUser = new User();
        $newUser->setUsername($data['username']);
        $newUser->setPassword($this->hasher->hashPassword($newUser, $data['password']));
        $newUser->setRole($data['role'] ?? 'agent');
        $newUser->setEmployeeCode($data['employeeCode'] ?? '');
        $newUser->setMonthlySalary($data['monthlySalary'] ?? '4523.52');
        $newUser->setAccountStatus('active');

        if (!empty($data['supervisorId'])) {
            $supervisor = $this->userRepo->find((int) $data['supervisorId']);
            if ($supervisor) $newUser->setSupervisor($supervisor);
        }

        $this->em->persist($newUser);
        $this->em->flush();

        return $this->json($this->serializeFull($newUser), 201);
    }

    /**
     * PUT /api/users/{id}  — modifier un utilisateur (admin seulement)
     */
    #[Route('/{id}', name: 'update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(User $target, Request $request): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        if ($user->getRole() !== 'admin') {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $data = $request->toArray();

        if (isset($data['username']))     $target->setUsername($data['username']);
        if (isset($data['role']))         $target->setRole($data['role']);
        if (isset($data['employeeCode'])) $target->setEmployeeCode($data['employeeCode']);
        if (isset($data['monthlySalary'])) $target->setMonthlySalary($data['monthlySalary']);
        if (isset($data['accountStatus'])) $target->setAccountStatus($data['accountStatus']);

        if (!empty($data['password'])) {
            $target->setPassword($this->hasher->hashPassword($target, $data['password']));
        }

        if (array_key_exists('supervisorId', $data)) {
            $target->setSupervisor($data['supervisorId']
                ? $this->userRepo->find((int) $data['supervisorId'])
                : null
            );
        }

        $this->em->flush();

        return $this->json($this->serializeFull($target));
    }

    // -----------------------------------------------------------------------
    private function serializeBasic(User $u): array
    {
        return [
            'id'           => $u->getId(),
            'username'     => $u->getUsername(),
            'role'         => $u->getRole(),
            'employeeCode' => $u->getEmployeeCode(),
        ];
    }

    private function serializeFull(User $u): array
    {
        return array_merge($this->serializeBasic($u), [
            'monthlySalary' => $u->getMonthlySalary(),
            'accountStatus' => $u->getAccountStatus(),
            'supervisorId'  => $u->getSupervisor()?->getId(),
            'createdAt'     => $u->getCreatedAt()?->format('Y-m-d H:i:s'),
        ]);
    }
}
