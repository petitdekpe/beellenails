<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250909141329 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE formation_module (id INT AUTO_INCREMENT NOT NULL, formation_id INT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, duration INT NOT NULL, position INT NOT NULL, youtube_url VARCHAR(500) DEFAULT NULL, is_active TINYINT(1) NOT NULL, INDEX IDX_2C3D28055200282E (formation_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE formation_resource (id INT AUTO_INCREMENT NOT NULL, formation_id INT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, type VARCHAR(50) NOT NULL, file_name VARCHAR(255) NOT NULL, original_name VARCHAR(255) DEFAULT NULL, file_size INT DEFAULT NULL, is_downloadable TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_7B03A3E75200282E (formation_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE formation_review (id INT AUTO_INCREMENT NOT NULL, formation_id INT NOT NULL, user_id INT NOT NULL, rating INT NOT NULL, comment LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, is_visible TINYINT(1) NOT NULL, INDEX IDX_595A8FEB5200282E (formation_id), INDEX IDX_595A8FEBA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE formation_module ADD CONSTRAINT FK_2C3D28055200282E FOREIGN KEY (formation_id) REFERENCES formation (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE formation_resource ADD CONSTRAINT FK_7B03A3E75200282E FOREIGN KEY (formation_id) REFERENCES formation (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE formation_review ADD CONSTRAINT FK_595A8FEB5200282E FOREIGN KEY (formation_id) REFERENCES formation (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE formation_review ADD CONSTRAINT FK_595A8FEBA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE formation ADD theme VARCHAR(100) DEFAULT NULL, ADD level VARCHAR(50) DEFAULT NULL, ADD duration INT DEFAULT NULL, ADD is_free TINYINT(1) NOT NULL, ADD access_type VARCHAR(20) NOT NULL, ADD access_duration INT DEFAULT NULL, ADD start_date DATE DEFAULT NULL, ADD end_date DATE DEFAULT NULL, ADD instructor_name VARCHAR(255) DEFAULT NULL, ADD instructor_bio LONGTEXT DEFAULT NULL, ADD instructor_image_name VARCHAR(255) DEFAULT NULL, ADD youtube_url VARCHAR(500) DEFAULT NULL, ADD target_audience LONGTEXT DEFAULT NULL, ADD is_active TINYINT(1) NOT NULL, ADD created_at DATETIME NOT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE formation_module DROP FOREIGN KEY FK_2C3D28055200282E
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE formation_resource DROP FOREIGN KEY FK_7B03A3E75200282E
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE formation_review DROP FOREIGN KEY FK_595A8FEB5200282E
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE formation_review DROP FOREIGN KEY FK_595A8FEBA76ED395
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE formation_module
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE formation_resource
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE formation_review
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE formation DROP theme, DROP level, DROP duration, DROP is_free, DROP access_type, DROP access_duration, DROP start_date, DROP end_date, DROP instructor_name, DROP instructor_bio, DROP instructor_image_name, DROP youtube_url, DROP target_audience, DROP is_active, DROP created_at
        SQL);
    }
}
