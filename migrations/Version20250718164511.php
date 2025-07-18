<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250718164511 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE email (id SERIAL NOT NULL, category_id INT NOT NULL, owner_id INT NOT NULL, subject VARCHAR(255) NOT NULL, sender VARCHAR(255) NOT NULL, body TEXT NOT NULL, summary TEXT DEFAULT NULL, received_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, gmail_id VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_E7927C7412469DE2 ON email (category_id)');
        $this->addSql('CREATE INDEX IDX_E7927C747E3C61F9 ON email (owner_id)');
        $this->addSql('COMMENT ON COLUMN email.received_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE email ADD CONSTRAINT FK_E7927C7412469DE2 FOREIGN KEY (category_id) REFERENCES category (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE email ADD CONSTRAINT FK_E7927C747E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE email DROP CONSTRAINT FK_E7927C7412469DE2');
        $this->addSql('ALTER TABLE email DROP CONSTRAINT FK_E7927C747E3C61F9');
        $this->addSql('DROP TABLE email');
    }
}
