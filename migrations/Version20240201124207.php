<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240201124207 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE rendezvous_supplement (rendezvous_id INT NOT NULL, supplement_id INT NOT NULL, INDEX IDX_35F924353345E0A3 (rendezvous_id), INDEX IDX_35F924357793FA21 (supplement_id), PRIMARY KEY(rendezvous_id, supplement_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE rendezvous_supplement ADD CONSTRAINT FK_35F924353345E0A3 FOREIGN KEY (rendezvous_id) REFERENCES rendezvous (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE rendezvous_supplement ADD CONSTRAINT FK_35F924357793FA21 FOREIGN KEY (supplement_id) REFERENCES supplement (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE rendezvous_supplement DROP FOREIGN KEY FK_35F924353345E0A3');
        $this->addSql('ALTER TABLE rendezvous_supplement DROP FOREIGN KEY FK_35F924357793FA21');
        $this->addSql('DROP TABLE rendezvous_supplement');
    }
}
