<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231210090300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE category_prestation (id INT AUTO_INCREMENT NOT NULL, nom_category VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE prestation ADD category_prestation_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE prestation ADD CONSTRAINT FK_51C88FAD809EE01F FOREIGN KEY (category_prestation_id) REFERENCES category_prestation (id)');
        $this->addSql('CREATE INDEX IDX_51C88FAD809EE01F ON prestation (category_prestation_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE prestation DROP FOREIGN KEY FK_51C88FAD809EE01F');
        $this->addSql('DROP TABLE category_prestation');
        $this->addSql('DROP INDEX IDX_51C88FAD809EE01F ON prestation');
        $this->addSql('ALTER TABLE prestation DROP category_prestation_id');
    }
}
