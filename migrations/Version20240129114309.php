<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240129114309 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE payment (id INT AUTO_INCREMENT NOT NULL, customer_id INT DEFAULT NULL, rendezvou_id INT DEFAULT NULL, description VARCHAR(255) DEFAULT NULL, amount INT NOT NULL, currency VARCHAR(5) NOT NULL, phone_number VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, transaction_id VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL, reference VARCHAR(255) NOT NULL, token VARCHAR(255) DEFAULT NULL, mode VARCHAR(255) DEFAULT NULL, fees INT DEFAULT NULL, INDEX IDX_6D28840D9395C3F3 (customer_id), UNIQUE INDEX UNIQ_6D28840D8F6D6463 (rendezvou_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D9395C3F3 FOREIGN KEY (customer_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D8F6D6463 FOREIGN KEY (rendezvou_id) REFERENCES rendezvous (id)');
        $this->addSql('ALTER TABLE rendezvous ADD paid TINYINT(1) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840D9395C3F3');
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840D8F6D6463');
        $this->addSql('DROP TABLE payment');
        $this->addSql('ALTER TABLE rendezvous DROP paid');
    }
}
