<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250909192639 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE formation_enrollment (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, formation_id INT NOT NULL, enrolled_at DATETIME NOT NULL, expires_at DATETIME DEFAULT NULL, status VARCHAR(20) NOT NULL, progress_percentage NUMERIC(5, 2) DEFAULT NULL, completed_at DATETIME DEFAULT NULL, last_accessed_at DATETIME DEFAULT NULL, certificate_generated TINYINT(1) DEFAULT NULL, certificate_generated_at DATETIME DEFAULT NULL, expiration_notified_at DATETIME DEFAULT NULL, INDEX IDX_237404D8A76ED395 (user_id), INDEX IDX_237404D85200282E (formation_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE module_progress (id INT AUTO_INCREMENT NOT NULL, enrollment_id INT NOT NULL, module_id INT NOT NULL, started TINYINT(1) NOT NULL, completed TINYINT(1) NOT NULL, started_at DATETIME DEFAULT NULL, completed_at DATETIME DEFAULT NULL, last_accessed_at DATETIME DEFAULT NULL, video_position INT DEFAULT NULL, time_spent INT DEFAULT NULL, completion_percentage NUMERIC(5, 2) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, INDEX IDX_46C121B88F7DB25B (enrollment_id), INDEX IDX_46C121B8AFC2B591 (module_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE formation_enrollment ADD CONSTRAINT FK_237404D8A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE formation_enrollment ADD CONSTRAINT FK_237404D85200282E FOREIGN KEY (formation_id) REFERENCES formation (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE module_progress ADD CONSTRAINT FK_46C121B88F7DB25B FOREIGN KEY (enrollment_id) REFERENCES formation_enrollment (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE module_progress ADD CONSTRAINT FK_46C121B8AFC2B591 FOREIGN KEY (module_id) REFERENCES formation_module (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE formation_enrollment DROP FOREIGN KEY FK_237404D8A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE formation_enrollment DROP FOREIGN KEY FK_237404D85200282E
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE module_progress DROP FOREIGN KEY FK_46C121B88F7DB25B
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE module_progress DROP FOREIGN KEY FK_46C121B8AFC2B591
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE formation_enrollment
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE module_progress
        SQL);
    }
}
