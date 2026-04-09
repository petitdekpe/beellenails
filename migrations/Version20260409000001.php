<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260409000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le champ is_active à la table creneau (true par défaut)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE creneau ADD is_active BOOLEAN NOT NULL DEFAULT TRUE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE creneau DROP COLUMN is_active
        SQL);
    }
}
