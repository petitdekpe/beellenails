<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <jy.ahouanvoedo@gmail.com>


declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration pour ajouter les champs de tracking des reports de rendez-vous
 */
final class Version20250825_AddRescheduledFlag extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute des champs pour tracker les reports de rendez-vous';
    }

    public function up(Schema $schema): void
    {
        // Ajouter les colonnes pour tracker les reports
        $this->addSql('ALTER TABLE rendezvous ADD COLUMN is_rescheduled BOOLEAN DEFAULT FALSE');
        $this->addSql('ALTER TABLE rendezvous ADD COLUMN rescheduled_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE rendezvous ADD COLUMN original_day DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE rendezvous ADD COLUMN original_creneau_id INTEGER DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE rendezvous DROP COLUMN is_rescheduled');
        $this->addSql('ALTER TABLE rendezvous DROP COLUMN rescheduled_at');
        $this->addSql('ALTER TABLE rendezvous DROP COLUMN original_day');
        $this->addSql('ALTER TABLE rendezvous DROP COLUMN original_creneau_id');
    }
}