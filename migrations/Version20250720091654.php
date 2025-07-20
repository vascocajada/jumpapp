<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250720091654 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE notification DROP CONSTRAINT FK_BF5476CAEB18F64');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAEB18F64 FOREIGN KEY (related_email_id) REFERENCES email (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE notification DROP CONSTRAINT fk_bf5476caeb18f64');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT fk_bf5476caeb18f64 FOREIGN KEY (related_email_id) REFERENCES email (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
