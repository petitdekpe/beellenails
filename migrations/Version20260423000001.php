<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260423000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute certificate_template à la table formation (default par défaut)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE formation ADD certificate_template VARCHAR(50) NOT NULL DEFAULT 'default'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE formation DROP COLUMN certificate_template');
    }
}
