<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <jy.ahouanvoedo@gmail.com>


declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration pour ajouter la colonne total_cost à la table rendezvous
 */
final class Version20250824204500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout de la colonne total_cost à la table rendezvous pour stocker le coût total calculé';
    }

    public function up(Schema $schema): void
    {
        // Vérifier si la colonne n'existe pas déjà avant de l'ajouter
        $this->addSql('ALTER TABLE rendezvous ADD COLUMN IF NOT EXISTS total_cost NUMERIC(10, 2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // Supprimer la colonne total_cost
        $this->addSql('ALTER TABLE rendezvous DROP COLUMN IF EXISTS total_cost');
    }
}