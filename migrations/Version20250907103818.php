<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250907103818 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE promo_code (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(50) NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, discount_type VARCHAR(20) NOT NULL, discount_value NUMERIC(10, 2) NOT NULL, minimum_amount NUMERIC(10, 2) DEFAULT NULL, maximum_discount NUMERIC(10, 2) DEFAULT NULL, valid_from DATETIME NOT NULL, valid_until DATETIME NOT NULL, max_usage_global INT DEFAULT NULL, max_usage_per_user INT DEFAULT NULL, current_usage INT NOT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_3D8C939E77153098 (code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE promo_code_prestations (promo_code_id INT NOT NULL, prestation_id INT NOT NULL, INDEX IDX_BD962722FAE4625 (promo_code_id), INDEX IDX_BD962729E45C554 (prestation_id), PRIMARY KEY(promo_code_id, prestation_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE promo_code_usage (id INT AUTO_INCREMENT NOT NULL, promo_code_id INT NOT NULL, user_id INT NOT NULL, rendezvous_id INT DEFAULT NULL, status VARCHAR(20) NOT NULL, original_amount NUMERIC(10, 2) DEFAULT NULL, discount_amount NUMERIC(10, 2) DEFAULT NULL, final_amount NUMERIC(10, 2) DEFAULT NULL, attempted_at DATETIME NOT NULL, validated_at DATETIME DEFAULT NULL, revoked_at DATETIME DEFAULT NULL, ip_address VARCHAR(45) DEFAULT NULL, user_agent LONGTEXT DEFAULT NULL, notes LONGTEXT DEFAULT NULL, INDEX IDX_6025E75F2FAE4625 (promo_code_id), INDEX IDX_6025E75FA76ED395 (user_id), INDEX IDX_6025E75F3345E0A3 (rendezvous_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE promo_code_prestations ADD CONSTRAINT FK_BD962722FAE4625 FOREIGN KEY (promo_code_id) REFERENCES promo_code (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE promo_code_prestations ADD CONSTRAINT FK_BD962729E45C554 FOREIGN KEY (prestation_id) REFERENCES prestation (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE promo_code_usage ADD CONSTRAINT FK_6025E75F2FAE4625 FOREIGN KEY (promo_code_id) REFERENCES promo_code (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE promo_code_usage ADD CONSTRAINT FK_6025E75FA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE promo_code_usage ADD CONSTRAINT FK_6025E75F3345E0A3 FOREIGN KEY (rendezvous_id) REFERENCES rendezvous (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rendezvous ADD promo_code_id INT DEFAULT NULL, ADD original_amount NUMERIC(10, 2) DEFAULT NULL, ADD discount_amount NUMERIC(10, 2) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rendezvous ADD CONSTRAINT FK_C09A9BA82FAE4625 FOREIGN KEY (promo_code_id) REFERENCES promo_code (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_C09A9BA82FAE4625 ON rendezvous (promo_code_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE rendezvous DROP FOREIGN KEY FK_C09A9BA82FAE4625
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE promo_code_prestations DROP FOREIGN KEY FK_BD962722FAE4625
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE promo_code_prestations DROP FOREIGN KEY FK_BD962729E45C554
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE promo_code_usage DROP FOREIGN KEY FK_6025E75F2FAE4625
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE promo_code_usage DROP FOREIGN KEY FK_6025E75FA76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE promo_code_usage DROP FOREIGN KEY FK_6025E75F3345E0A3
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE promo_code
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE promo_code_prestations
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE promo_code_usage
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_C09A9BA82FAE4625 ON rendezvous
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rendezvous DROP promo_code_id, DROP original_amount, DROP discount_amount
        SQL);
    }
}
