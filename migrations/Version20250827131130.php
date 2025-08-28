<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250827131130 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE rendezvous ADD previous_creneau_id INT DEFAULT NULL, ADD previous_day DATE DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rendezvous ADD CONSTRAINT FK_C09A9BA8A076B10 FOREIGN KEY (previous_creneau_id) REFERENCES creneau (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_C09A9BA8A076B10 ON rendezvous (previous_creneau_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE rendezvous DROP FOREIGN KEY FK_C09A9BA8A076B10
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_C09A9BA8A076B10 ON rendezvous
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rendezvous DROP previous_creneau_id, DROP previous_day
        SQL);
    }
}
