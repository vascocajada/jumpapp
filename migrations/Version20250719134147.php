<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250719134147 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE email DROP CONSTRAINT FK_E7927C74B0788806');
        $this->addSql('ALTER TABLE email ADD CONSTRAINT FK_E7927C74B0788806 FOREIGN KEY (gmail_account_id) REFERENCES gmail_account (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE email DROP CONSTRAINT fk_e7927c74b0788806');
        $this->addSql('ALTER TABLE email ADD CONSTRAINT fk_e7927c74b0788806 FOREIGN KEY (gmail_account_id) REFERENCES gmail_account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
