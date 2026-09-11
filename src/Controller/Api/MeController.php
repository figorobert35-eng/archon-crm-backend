<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class MeController extends AbstractController
{
    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        return $this->json([
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'role' => $user->getRole(),
            'employeeCode' => $user->getEmployeeCode(),
            'monthlySalary' => $user->getMonthlySalary(),
            'accountStatus' => $user->getAccountStatus(),
            'supervisorId' => $user->getSupervisor()?->getId(),
        ]);
    }
}