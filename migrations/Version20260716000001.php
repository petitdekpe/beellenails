<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260716000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute expires_at à la table rendezvous pour expirer les réservations non payées';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE rendezvous ADD expires_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE rendezvous DROP COLUMN expires_at');
    }
}
