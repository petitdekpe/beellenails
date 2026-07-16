<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260716000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée la table booking_settings (durée du hold de réservation configurable)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE booking_settings (id INT AUTO_INCREMENT NOT NULL, hold_duration_minutes INT NOT NULL, updated_at DATETIME DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql("INSERT INTO booking_settings (hold_duration_minutes, updated_at) VALUES (15, NOW())");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE booking_settings');
    }
}
