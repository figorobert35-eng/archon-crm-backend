<?php

namespace App\Command;

use App\Entity\Announcement;
use App\Entity\BreakLog;
use App\Entity\Campaign;
use App\Entity\InjectionBatch;
use App\Entity\Lead;
use App\Entity\LeadEvent;
use App\Entity\User;
use App\Entity\WorkLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:migrate-sqlite',
    description: 'Migre les données de l\'ancienne base SQLite vers MySQL'
)]
class MigrateSqliteCommand extends Command
{
    public function __construct(
        private EntityManagerInterface      $em,
        private UserPasswordHasherInterface $hasher,
        private string                      $defaultSqlitePath,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('sqlite', null, InputOption::VALUE_OPTIONAL, 'Chemin vers le fichier SQLite', null);
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Confirmer la migration sans prompt');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $sqlitePath = $input->getOption('sqlite') ?? $this->defaultSqlitePath;

        if (!file_exists($sqlitePath)) {
            $io->error("Fichier SQLite introuvable : {$sqlitePath}");
            $io->note("Utilisez --sqlite=/chemin/vers/crm_leads.sqlite");
            return Command::FAILURE;
        }

        $io->title('Migration SQLite → MySQL');
        $io->text("Source : {$sqlitePath}");

        if (!$input->getOption('force')) {
            if (!$io->confirm('Cette opération va importer les données. Continuer ?', false)) {
                $io->warning('Migration annulée.');
                return Command::SUCCESS;
            }
        }

        try {
            $pdo = new \PDO("sqlite:{$sqlitePath}", null, null, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (\PDOException $e) {
            $io->error("Impossible d'ouvrir la base SQLite : " . $e->getMessage());
            return Command::FAILURE;
        }

        $io->section('Migration des utilisateurs');
        $userMap = $this->migrateUsers($pdo, $io);

        $io->section('Migration des campagnes');
        $campaignMap = $this->migrateCampaigns($pdo, $io);

        $io->section('Migration des lots d\'injection');
        $batchMap = $this->migrateBatches($pdo, $io, $campaignMap, $userMap);

        $io->section('Migration des leads');
        $leadMap = $this->migrateLeads($pdo, $io, $userMap, $campaignMap, $batchMap);

        $io->section('Migration des événements leads');
        $this->migrateLeadEvents($pdo, $io, $leadMap, $userMap);

        $io->section('Migration des pointages');
        $this->migrateWorkLogs($pdo, $io, $userMap);

        $io->section('Migration des pauses');
        $this->migrateBreakLogs($pdo, $io, $userMap);

        $io->success(sprintf(
            'Migration terminée ! %d utilisateurs, %d campagnes, %d leads migrés.',
            count($userMap), count($campaignMap), count($leadMap)
        ));

        return Command::SUCCESS;
    }

    // -----------------------------------------------------------------------

    private function migrateUsers(\PDO $pdo, SymfonyStyle $io): array
    {
        $map = [];
        $rows = $pdo->query("SELECT * FROM users ORDER BY id")->fetchAll(\PDO::FETCH_ASSOC);
        $repo = $this->em->getRepository(User::class);
        $count = 0;

        foreach ($rows as $row) {
            // Ne pas dupliquer si l'username existe déjà
            if ($repo->findOneBy(['username' => $row['username']])) {
                $existing = $repo->findOneBy(['username' => $row['username']]);
                $map[$row['id']] = $existing->getId();
                continue;
            }

            $user = new User();
            $user->setUsername($row['username']);
            // On recrée le hash avec le password visible si disponible, sinon un placeholder
            $plainPassword = $row['password_visible'] ?? null;
            if ($plainPassword) {
                $user->setPassword($this->hasher->hashPassword($user, $plainPassword));
            } else {
                // Hash arbitraire — l'admin devra reset le mot de passe
                $user->setPassword($row['password_hash']);
            }
            $user->setRole($row['role'] ?? 'agent');
            $user->setEmployeeCode($row['employee_code'] ?? '');
            $user->setMonthlySalary($row['monthly_salary'] ?? '4523.52');
            $user->setAccountStatus($row['account_status'] ?? 'active');

            $this->em->persist($user);
            $this->em->flush();

            $map[$row['id']] = $user->getId();
            $count++;
        }

        $io->text("{$count} utilisateurs importés.");
        return $map;
    }

    private function migrateCampaigns(\PDO $pdo, SymfonyStyle $io): array
    {
        $map = [];

        // La table campaigns peut ne pas exister dans l'ancienne DB
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(\PDO::FETCH_COLUMN);
        if (!in_array('campaigns', $tables)) {
            $io->text('Pas de table campaigns dans SQLite — ignoré.');
            return $map;
        }

        $rows = $pdo->query("SELECT * FROM campaigns ORDER BY id")->fetchAll(\PDO::FETCH_ASSOC);
        $repo = $this->em->getRepository(Campaign::class);
        $count = 0;

        foreach ($rows as $row) {
            if ($repo->findOneBy(['name' => $row['name']])) {
                $existing = $repo->findOneBy(['name' => $row['name']]);
                $map[$row['id']] = $existing->getId();
                continue;
            }

            $campaign = new Campaign();
            $campaign->setName($row['name']);
            $campaign->setDescription($row['description'] ?? '');
            $campaign->setScript($row['script'] ?? '');
            $campaign->setColor($row['color'] ?? '#8f1d14');
            $campaign->setCodificationStatuses($row['codification_statuses'] ?? '');
            $campaign->setActive((bool) ($row['active'] ?? 1));
            $campaign->setArchived((bool) ($row['archived'] ?? 0));

            $this->em->persist($campaign);
            $this->em->flush();

            $map[$row['id']] = $campaign->getId();
            $count++;
        }

        $io->text("{$count} campagnes importées.");
        return $map;
    }

    private function migrateBatches(\PDO $pdo, SymfonyStyle $io, array $campaignMap, array $userMap): array
    {
        $map = [];
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(\PDO::FETCH_COLUMN);

        if (!in_array('injection_batches', $tables)) {
            return $map;
        }

        $rows = $pdo->query("SELECT * FROM injection_batches ORDER BY id")->fetchAll(\PDO::FETCH_ASSOC);
        $count = 0;

        foreach ($rows as $row) {
            $batch = new InjectionBatch();
            $batch->setInjectionDate(new \DateTime($row['injection_date'] ?? 'today'));
            $batch->setSource($row['source'] ?? '');
            $batch->setCreatedBy($row['created_by'] ?? '');

            if (!empty($row['campaign_id']) && isset($campaignMap[$row['campaign_id']])) {
                $campaign = $this->em->find(Campaign::class, $campaignMap[$row['campaign_id']]);
                if ($campaign) $batch->setCampaign($campaign);
            }

            $this->em->persist($batch);
            $this->em->flush();

            $map[$row['id']] = $batch->getId();
            $count++;
        }

        $io->text("{$count} batches importés.");
        return $map;
    }

    private function migrateLeads(\PDO $pdo, SymfonyStyle $io, array $userMap, array $campaignMap, array $batchMap): array
    {
        $map = [];
        $rows = $pdo->query("SELECT * FROM leads ORDER BY id")->fetchAll(\PDO::FETCH_ASSOC);
        $count = 0;
        $batchSize = 100;

        foreach ($rows as $row) {
            $lead = new Lead();
            $lead->setClientName($row['client_name'] ?? '');
            $lead->setPhone($row['phone'] ?? '0000000000');
            $lead->setPhone2($row['phone_2'] ?? '');
            $lead->setEmail($row['email'] ?? '');
            $lead->setAddress($row['address'] ?? '');
            $lead->setCity($row['city'] ?? '');
            $lead->setSource($row['source'] ?? '');
            $lead->setRequestDetails($row['request_details'] ?? '');
            $lead->setProduct($row['product'] ?? 'Trotinette');
            $lead->setStatus($row['status'] ?? 'Nouveau');
            $lead->setQuoteNumber($row['quote_number'] ?? '');
            $lead->setLastNote($row['last_note'] ?? '');
            $lead->setCreatedBy($row['created_by'] ?? '');

            try {
                $lead->setInjectionDate(new \DateTime($row['injection_date'] ?? 'today'));
            } catch (\Exception) {
                $lead->setInjectionDate(new \DateTime());
            }

            if (!empty($row['callback_at'])) {
                try {
                    $lead->setCallbackAt(new \DateTime($row['callback_at']));
                } catch (\Exception) {}
            }

            if (!empty($row['assigned_to']) && isset($userMap[$row['assigned_to']])) {
                $user = $this->em->find(User::class, $userMap[$row['assigned_to']]);
                if ($user) $lead->setAssignedTo($user);
            }

            if (!empty($row['campaign_id']) && isset($campaignMap[$row['campaign_id']])) {
                $campaign = $this->em->find(Campaign::class, $campaignMap[$row['campaign_id']]);
                if ($campaign) $lead->setCampaign($campaign);
            }

            if (!empty($row['batch_id']) && isset($batchMap[$row['batch_id']])) {
                $batch = $this->em->find(InjectionBatch::class, $batchMap[$row['batch_id']]);
                if ($batch) $lead->setBatch($batch);
            }

            if (!empty($row['created_at'])) {
                try { $lead->setCreatedAt(new \DateTime($row['created_at'])); } catch (\Exception) {}
            }
            if (!empty($row['updated_at'])) {
                try { $lead->setUpdatedAt(new \DateTime($row['updated_at'])); } catch (\Exception) {}
            }

            $this->em->persist($lead);
            $map[$row['id']] = $lead;
            $count++;

            if ($count % $batchSize === 0) {
                $this->em->flush();
                $this->em->clear(Lead::class);
                $io->text("  {$count} leads traités...");
            }
        }

        $this->em->flush();

        // Reconstruire la map avec les IDs MySQL
        $finalMap = [];
        foreach ($rows as $i => $row) {
            if (isset($map[$row['id']])) {
                // Après flush les entités ont leurs IDs
            }
        }

        $io->text("{$count} leads importés.");
        return $map; // map[sqliteId] => Lead entity (ou ID après flush)
    }

    private function migrateLeadEvents(\PDO $pdo, SymfonyStyle $io, array $leadMap, array $userMap): void
    {
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(\PDO::FETCH_COLUMN);
        if (!in_array('lead_events', $tables)) return;

        $rows = $pdo->query("SELECT * FROM lead_events ORDER BY id")->fetchAll(\PDO::FETCH_ASSOC);
        $count = 0;

        foreach ($rows as $row) {
            if (empty($leadMap[$row['lead_id']])) continue;

            $lead = $leadMap[$row['lead_id']];
            if (!($lead instanceof Lead)) continue;

            $event = new LeadEvent();
            $event->setLead($lead);
            $event->setEventType($row['event_type'] ?? 'note');
            $event->setOldStatus($row['old_status'] ?? null);
            $event->setNewStatus($row['new_status'] ?? null);
            $event->setNote($row['note'] ?? '');

            if (!empty($row['user_id']) && isset($userMap[$row['user_id']])) {
                $user = $this->em->find(User::class, $userMap[$row['user_id']]);
                if ($user) $event->setUser($user);
            }

            if (!empty($row['created_at'])) {
                try { $event->setCreatedAt(new \DateTime($row['created_at'])); } catch (\Exception) {}
            }

            $this->em->persist($event);
            $count++;

            if ($count % 200 === 0) {
                $this->em->flush();
            }
        }

        $this->em->flush();
        $io->text("{$count} événements leads importés.");
    }

    private function migrateWorkLogs(\PDO $pdo, SymfonyStyle $io, array $userMap): void
    {
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(\PDO::FETCH_COLUMN);
        if (!in_array('work_logs', $tables)) return;

        $rows = $pdo->query("SELECT * FROM work_logs ORDER BY id")->fetchAll(\PDO::FETCH_ASSOC);
        $count = 0;

        foreach ($rows as $row) {
            if (empty($userMap[$row['user_id']])) continue;

            $user = $this->em->find(User::class, $userMap[$row['user_id']]);
            if (!$user) continue;

            $log = new WorkLog();
            $log->setUser($user);
            $log->setApprovalStatus($row['approval_status'] ?? 'pending');
            $log->setSupervisorNote($row['supervisor_note'] ?? '');

            try {
                $log->setLoggedInAt(new \DateTime($row['logged_in_at']));
            } catch (\Exception) {
                continue;
            }

            if (!empty($row['logged_out_at'])) {
                try { $log->setLoggedOutAt(new \DateTime($row['logged_out_at'])); } catch (\Exception) {}
            }

            $this->em->persist($log);
            $count++;

            if ($count % 200 === 0) $this->em->flush();
        }

        $this->em->flush();
        $io->text("{$count} pointages importés.");
    }

    private function migrateBreakLogs(\PDO $pdo, SymfonyStyle $io, array $userMap): void
    {
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(\PDO::FETCH_COLUMN);
        if (!in_array('break_logs', $tables)) return;

        $rows = $pdo->query("SELECT * FROM break_logs ORDER BY id")->fetchAll(\PDO::FETCH_ASSOC);
        $count = 0;

        foreach ($rows as $row) {
            if (empty($userMap[$row['user_id']])) continue;

            $user = $this->em->find(User::class, $userMap[$row['user_id']]);
            if (!$user) continue;

            $break = new BreakLog();
            $break->setUser($user);
            $break->setBreakType($row['break_type'] ?? 'morning');

            try {
                $break->setStartedAt(new \DateTime($row['started_at']));
            } catch (\Exception) {
                continue;
            }

            if (!empty($row['ended_at'])) {
                try { $break->setEndedAt(new \DateTime($row['ended_at'])); } catch (\Exception) {}
            }

            $this->em->persist($break);
            $count++;

            if ($count % 200 === 0) $this->em->flush();
        }

        $this->em->flush();
        $io->text("{$count} pauses importées.");
    }
}
