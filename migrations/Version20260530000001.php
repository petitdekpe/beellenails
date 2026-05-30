<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260530000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le système de QCM (quiz) par module de formation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE quiz (
            id INT AUTO_INCREMENT NOT NULL,
            module_id INT NOT NULL,
            passing_score INT NOT NULL DEFAULT 70,
            max_attempts INT DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME DEFAULT NULL,
            UNIQUE INDEX UNIQ_A412FA92AE073A02 (module_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE quiz_question (
            id INT AUTO_INCREMENT NOT NULL,
            quiz_id INT NOT NULL,
            question_text LONGTEXT NOT NULL,
            type VARCHAR(10) NOT NULL DEFAULT \'single\',
            explanation LONGTEXT DEFAULT NULL,
            position INT NOT NULL DEFAULT 1,
            INDEX IDX_QUIZ_QUESTION_QUIZ (quiz_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE quiz_answer (
            id INT AUTO_INCREMENT NOT NULL,
            question_id INT NOT NULL,
            answer_text VARCHAR(500) NOT NULL,
            is_correct TINYINT(1) NOT NULL DEFAULT 0,
            position INT NOT NULL DEFAULT 1,
            INDEX IDX_QUIZ_ANSWER_QUESTION (question_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE quiz_attempt (
            id INT AUTO_INCREMENT NOT NULL,
            enrollment_id INT NOT NULL,
            quiz_id INT NOT NULL,
            attempt_number INT NOT NULL DEFAULT 1,
            score DECIMAL(5,2) DEFAULT NULL,
            passed TINYINT(1) NOT NULL DEFAULT 0,
            started_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            completed_at DATETIME DEFAULT NULL,
            INDEX IDX_QUIZ_ATTEMPT_ENROLLMENT (enrollment_id),
            INDEX IDX_QUIZ_ATTEMPT_QUIZ (quiz_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE quiz_attempt_answer (
            id INT AUTO_INCREMENT NOT NULL,
            attempt_id INT NOT NULL,
            question_id INT NOT NULL,
            selected_answer_ids JSON NOT NULL,
            is_correct TINYINT(1) NOT NULL DEFAULT 0,
            INDEX IDX_QUIZ_ATTEMPT_ANSWER_ATTEMPT (attempt_id),
            INDEX IDX_QUIZ_ATTEMPT_ANSWER_QUESTION (question_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE quiz ADD CONSTRAINT FK_QUIZ_MODULE FOREIGN KEY (module_id) REFERENCES formation_module (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quiz_question ADD CONSTRAINT FK_QUESTION_QUIZ FOREIGN KEY (quiz_id) REFERENCES quiz (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quiz_answer ADD CONSTRAINT FK_ANSWER_QUESTION FOREIGN KEY (question_id) REFERENCES quiz_question (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quiz_attempt ADD CONSTRAINT FK_ATTEMPT_ENROLLMENT FOREIGN KEY (enrollment_id) REFERENCES formation_enrollment (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quiz_attempt ADD CONSTRAINT FK_ATTEMPT_QUIZ FOREIGN KEY (quiz_id) REFERENCES quiz (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quiz_attempt_answer ADD CONSTRAINT FK_ATTEMPT_ANSWER_ATTEMPT FOREIGN KEY (attempt_id) REFERENCES quiz_attempt (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quiz_attempt_answer ADD CONSTRAINT FK_ATTEMPT_ANSWER_QUESTION FOREIGN KEY (question_id) REFERENCES quiz_question (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE module_progress ADD quiz_passed TINYINT(1) DEFAULT NULL, ADD quiz_score DECIMAL(5,2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE module_progress DROP COLUMN quiz_passed, DROP COLUMN quiz_score');
        $this->addSql('ALTER TABLE quiz_attempt_answer DROP FOREIGN KEY FK_ATTEMPT_ANSWER_ATTEMPT');
        $this->addSql('ALTER TABLE quiz_attempt_answer DROP FOREIGN KEY FK_ATTEMPT_ANSWER_QUESTION');
        $this->addSql('ALTER TABLE quiz_attempt DROP FOREIGN KEY FK_ATTEMPT_ENROLLMENT');
        $this->addSql('ALTER TABLE quiz_attempt DROP FOREIGN KEY FK_ATTEMPT_QUIZ');
        $this->addSql('ALTER TABLE quiz_answer DROP FOREIGN KEY FK_ANSWER_QUESTION');
        $this->addSql('ALTER TABLE quiz_question DROP FOREIGN KEY FK_QUESTION_QUIZ');
        $this->addSql('ALTER TABLE quiz DROP FOREIGN KEY FK_QUIZ_MODULE');
        $this->addSql('DROP TABLE quiz_attempt_answer');
        $this->addSql('DROP TABLE quiz_attempt');
        $this->addSql('DROP TABLE quiz_answer');
        $this->addSql('DROP TABLE quiz_question');
        $this->addSql('DROP TABLE quiz');
    }
}
