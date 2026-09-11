<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajustement des defaults + index supplémentaires sur leads (compatible MariaDB 10.4)
 * Note : on ne renomme pas les index FK existants (contrainte MariaDB).
 */
final class Version20260909152032 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Defaults colonnes + index performance sur leads/lead_events/break_logs/work_logs';
    }

    public function up(Schema $schema): void
    {
        // --- announcement_reads ---
        $this->addSql('ALTER TABLE announcement_reads
            CHANGE acknowledged_at acknowledged_at DATETIME DEFAULT NULL,
            CHANGE remind_after    remind_after    DATETIME DEFAULT NULL,
            CHANGE last_seen_at    last_seen_at    DATETIME DEFAULT NULL');

        // --- announcements ---
        $this->addSql("ALTER TABLE announcements
            CHANGE priority    priority    VARCHAR(20) NOT NULL DEFAULT 'info',
            CHANGE target_role target_role VARCHAR(20) NOT NULL DEFAULT 'all',
            CHANGE archived_at archived_at DATETIME DEFAULT NULL");

        // --- break_logs ---
        $this->addSql('ALTER TABLE break_logs CHANGE ended_at ended_at DATETIME DEFAULT NULL');

        // --- campaigns ---
        $this->addSql("ALTER TABLE campaigns
            CHANGE color color VARCHAR(7) NOT NULL DEFAULT '#8f1d14'");

        // --- injection_batches ---
        $this->addSql("ALTER TABLE injection_batches
            CHANGE source     source     VARCHAR(100) NOT NULL DEFAULT '',
            CHANGE created_by created_by VARCHAR(100) NOT NULL DEFAULT ''");

        // --- lead_attachments ---
        $this->addSql("ALTER TABLE lead_attachments
            CHANGE attachment_type attachment_type VARCHAR(50)  NOT NULL DEFAULT 'document',
            CHANGE mime_type       mime_type       VARCHAR(100) NOT NULL DEFAULT 'application/octet-stream'");

        // --- lead_events ---
        $this->addSql('ALTER TABLE lead_events
            CHANGE old_status  old_status  VARCHAR(50) DEFAULT NULL,
            CHANGE new_status  new_status  VARCHAR(50) DEFAULT NULL,
            CHANGE callback_at callback_at DATETIME    DEFAULT NULL');

        // --- leads : defaults ---
        $this->addSql("ALTER TABLE leads
            CHANGE client_name  client_name  VARCHAR(255) NOT NULL DEFAULT '',
            CHANGE phone_2      phone_2      VARCHAR(50)  NOT NULL DEFAULT '',
            CHANGE email        email        VARCHAR(255) NOT NULL DEFAULT '',
            CHANGE city         city         VARCHAR(100) NOT NULL DEFAULT '',
            CHANGE source       source       VARCHAR(100) NOT NULL DEFAULT '',
            CHANGE product      product      VARCHAR(50)  NOT NULL DEFAULT 'Trotinette',
            CHANGE status       status       VARCHAR(50)  NOT NULL DEFAULT 'Nouveau',
            CHANGE quote_number quote_number VARCHAR(50)  NOT NULL DEFAULT '',
            CHANGE created_by   created_by   VARCHAR(100) NOT NULL DEFAULT '',
            CHANGE callback_at  callback_at  DATETIME DEFAULT NULL");

        // Nouveaux index de performance sur leads (non FK)
        $this->addSql('CREATE INDEX idx_lead_status    ON leads (status)');
        $this->addSql('CREATE INDEX idx_lead_callback  ON leads (callback_at)');
        $this->addSql('CREATE INDEX idx_lead_injection ON leads (injection_date)');

        // --- users ---
        $this->addSql("ALTER TABLE users
            CHANGE salt           salt           VARCHAR(64)    DEFAULT NULL,
            CHANGE role           role           VARCHAR(20)    NOT NULL DEFAULT 'agent',
            CHANGE monthly_salary monthly_salary NUMERIC(10,2) NOT NULL DEFAULT 4523.52,
            CHANGE account_status account_status VARCHAR(20)    NOT NULL DEFAULT 'active',
            CHANGE inactive_from  inactive_from  DATE DEFAULT NULL,
            CHANGE employee_code  employee_code  VARCHAR(20)    NOT NULL DEFAULT ''");

        // --- work_log_corrections ---
        $this->addSql('ALTER TABLE work_log_corrections
            CHANGE original_logged_out_at original_logged_out_at DATETIME DEFAULT NULL');

        // --- work_logs ---
        $this->addSql("ALTER TABLE work_logs
            CHANGE logged_out_at   logged_out_at   DATETIME DEFAULT NULL,
            CHANGE approval_status approval_status VARCHAR(20) NOT NULL DEFAULT 'pending',
            CHANGE validated_at    validated_at    DATETIME DEFAULT NULL");

        // --- messenger_messages ---
        $this->addSql('ALTER TABLE messenger_messages
            CHANGE delivered_at delivered_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_lead_status    ON leads');
        $this->addSql('DROP INDEX idx_lead_callback  ON leads');
        $this->addSql('DROP INDEX idx_lead_injection ON leads');
    }
}
