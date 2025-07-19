<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250719104036 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE gmail_account (id SERIAL NOT NULL, owner_id INT NOT NULL, email VARCHAR(255) NOT NULL, access_token TEXT NOT NULL, name VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_A0AA8B207E3C61F9 ON gmail_account (owner_id)');
        $this->addSql('COMMENT ON COLUMN gmail_account.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE gmail_account ADD CONSTRAINT FK_A0AA8B207E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE email ADD gmail_account_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE email ADD CONSTRAINT FK_E7927C74B0788806 FOREIGN KEY (gmail_account_id) REFERENCES gmail_account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_E7927C74B0788806 ON email (gmail_account_id)');
        $this->addSql('ALTER TABLE "user" DROP gmail_access_token');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE email DROP CONSTRAINT FK_E7927C74B0788806');
        $this->addSql('ALTER TABLE gmail_account DROP CONSTRAINT FK_A0AA8B207E3C61F9');
        $this->addSql('DROP TABLE gmail_account');
        $this->addSql('ALTER TABLE "user" ADD gmail_access_token TEXT DEFAULT NULL');
        $this->addSql('DROP INDEX IDX_E7927C74B0788806');
        $this->addSql('ALTER TABLE email DROP gmail_account_id');
    }
}
