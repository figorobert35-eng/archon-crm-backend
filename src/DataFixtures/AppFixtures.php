<?php

namespace App\DataFixtures;

use App\Entity\Campaign;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
    ) {}

    public function load(ObjectManager $manager): void
    {
        // --- Utilisateurs ---

        $admin = new User();
        $admin->setUsername('admin');
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'ChangeMe_Admin_2026!'));
        $admin->setRole('admin');
        $admin->setEmployeeCode('');
        $admin->setAccountStatus('active');
        $manager->persist($admin);

        $supervisor = new User();
        $supervisor->setUsername('superviseur');
        $supervisor->setPassword($this->passwordHasher->hashPassword($supervisor, 'ChangeMe_Supervisor_2026!'));
        $supervisor->setRole('supervisor');
        $supervisor->setEmployeeCode('CDC140');
        $supervisor->setAccountStatus('active');
        $manager->persist($supervisor);

        $agent = new User();
        $agent->setUsername('agent');
        $agent->setPassword($this->passwordHasher->hashPassword($agent, 'ChangeMe_Agent_2026!'));
        $agent->setRole('agent');
        $agent->setEmployeeCode('CDC141');
        $agent->setAccountStatus('active');
        $agent->setSupervisor($supervisor);
        $manager->persist($agent);

        // --- Campagne par défaut ---

        $campaign = new Campaign();
        $campaign->setName('Projet par defaut');
        $campaign->setDescription('Campagne initiale pour les fiches existantes.');
        $campaign->setScript('');
        $campaign->setColor('#8f1d14');
        $campaign->setActive(true);
        $campaign->setArchived(false);
        $campaign->addAgent($agent);
        $manager->persist($campaign);

        // --- Campagne exemple ---

        $campaign2 = new Campaign();
        $campaign2->setName('Trotinette Elite');
        $campaign2->setDescription('Campagne assurance trotinette haut de gamme.');
        $campaign2->setScript('Bonjour, je vous contacte au sujet de votre demande d\'assurance trotinette...');
        $campaign2->setColor('#2563eb');
        $campaign2->setActive(true);
        $campaign2->setArchived(false);
        $campaign2->addAgent($agent);
        $manager->persist($campaign2);

        $manager->flush();
    }
}
