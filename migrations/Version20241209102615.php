<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20241209102615 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Corrige la colonne customer_id dans la table payment et ajuste les index associés.';
    }

    public function up(Schema $schema): void
    {
        // Supprimer la contrainte de clé étrangère liée à customer_id
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840D9395C3F3');

        // Modifier la colonne customer_id
        $this->addSql('ALTER TABLE payment CHANGE customer_id customer_id INT NOT NULL');

        // Réappliquer la contrainte de clé étrangère
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D9395C3F3 FOREIGN KEY (customer_id) REFERENCES user (id) ON DELETE CASCADE');

        // Modifier les index associés à la table payment
        $this->addSql('ALTER TABLE payment DROP INDEX UNIQ_6D28840D8F6D6463, ADD INDEX IDX_6D28840D8F6D6463 (rendezvou_id)');
    }

    public function down(Schema $schema): void
    {
        // Supprimer la contrainte de clé étrangère liée à customer_id
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840D9395C3F3');

        // Revenir au type précédent de la colonne customer_id
        $this->addSql('ALTER TABLE payment CHANGE customer_id customer_id INT DEFAULT NULL');

        // Réappliquer la contrainte de clé étrangère initiale
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D9395C3F3 FOREIGN KEY (customer_id) REFERENCES user (id) ON DELETE CASCADE');

        // Revenir aux index d'origine
        $this->addSql('ALTER TABLE payment DROP INDEX IDX_6D28840D8F6D6463, ADD UNIQUE INDEX UNIQ_6D28840D8F6D6463 (rendezvou_id)');
    }
}
