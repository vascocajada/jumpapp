<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250721071256 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE category (id SERIAL NOT NULL, owner_id INT NOT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_64C19C17E3C61F9 ON category (owner_id)');
        $this->addSql('CREATE TABLE config (id SERIAL NOT NULL, user_id INT NOT NULL, use_gmail_list_unsubscribe BOOLEAN DEFAULT true NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D48A2F7CA76ED395 ON config (user_id)');
        $this->addSql('CREATE TABLE email (id SERIAL NOT NULL, owner_id INT NOT NULL, category_id INT NOT NULL, gmail_account_id INT DEFAULT NULL, subject VARCHAR(255) NOT NULL, sender VARCHAR(255) NOT NULL, body TEXT NOT NULL, summary TEXT DEFAULT NULL, received_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, gmail_id VARCHAR(255) NOT NULL, list_unsubscribe TEXT DEFAULT NULL, unsubscribe_status VARCHAR(32) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_E7927C747E3C61F9 ON email (owner_id)');
        $this->addSql('CREATE INDEX IDX_E7927C7412469DE2 ON email (category_id)');
        $this->addSql('CREATE INDEX IDX_E7927C74B0788806 ON email (gmail_account_id)');
        $this->addSql('COMMENT ON COLUMN email.received_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE gmail_account (id SERIAL NOT NULL, owner_id INT NOT NULL, email VARCHAR(255) NOT NULL, access_token TEXT NOT NULL, name VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_A0AA8B207E3C61F9 ON gmail_account (owner_id)');
        $this->addSql('COMMENT ON COLUMN gmail_account.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE notification (id SERIAL NOT NULL, user_id INT NOT NULL, related_email_id INT DEFAULT NULL, message VARCHAR(255) NOT NULL, type VARCHAR(32) NOT NULL, is_read BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_BF5476CAA76ED395 ON notification (user_id)');
        $this->addSql('CREATE INDEX IDX_BF5476CAEB18F64 ON notification (related_email_id)');
        $this->addSql('COMMENT ON COLUMN notification.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE "user" (id SERIAL NOT NULL, email VARCHAR(180) NOT NULL, name VARCHAR(255) DEFAULT NULL, roles JSON NOT NULL, password VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL ON "user" (email)');
        $this->addSql('CREATE TABLE messenger_messages (id BIGSERIAL NOT NULL, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0 ON messenger_messages (queue_name)');
        $this->addSql('CREATE INDEX IDX_75EA56E0E3BD61CE ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX IDX_75EA56E016BA31DB ON messenger_messages (delivered_at)');
        $this->addSql('COMMENT ON COLUMN messenger_messages.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.available_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.delivered_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE OR REPLACE FUNCTION notify_messenger_messages() RETURNS TRIGGER AS $$
            BEGIN
                PERFORM pg_notify(\'messenger_messages\', NEW.queue_name::text);
                RETURN NEW;
            END;
        $$ LANGUAGE plpgsql;');
        $this->addSql('DROP TRIGGER IF EXISTS notify_trigger ON messenger_messages;');
        $this->addSql('CREATE TRIGGER notify_trigger AFTER INSERT OR UPDATE ON messenger_messages FOR EACH ROW EXECUTE PROCEDURE notify_messenger_messages();');
        $this->addSql('ALTER TABLE category ADD CONSTRAINT FK_64C19C17E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE config ADD CONSTRAINT FK_D48A2F7CA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE email ADD CONSTRAINT FK_E7927C747E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE email ADD CONSTRAINT FK_E7927C7412469DE2 FOREIGN KEY (category_id) REFERENCES category (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE email ADD CONSTRAINT FK_E7927C74B0788806 FOREIGN KEY (gmail_account_id) REFERENCES gmail_account (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE gmail_account ADD CONSTRAINT FK_A0AA8B207E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAEB18F64 FOREIGN KEY (related_email_id) REFERENCES email (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE category DROP CONSTRAINT FK_64C19C17E3C61F9');
        $this->addSql('ALTER TABLE config DROP CONSTRAINT FK_D48A2F7CA76ED395');
        $this->addSql('ALTER TABLE email DROP CONSTRAINT FK_E7927C747E3C61F9');
        $this->addSql('ALTER TABLE email DROP CONSTRAINT FK_E7927C7412469DE2');
        $this->addSql('ALTER TABLE email DROP CONSTRAINT FK_E7927C74B0788806');
        $this->addSql('ALTER TABLE gmail_account DROP CONSTRAINT FK_A0AA8B207E3C61F9');
        $this->addSql('ALTER TABLE notification DROP CONSTRAINT FK_BF5476CAA76ED395');
        $this->addSql('ALTER TABLE notification DROP CONSTRAINT FK_BF5476CAEB18F64');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE config');
        $this->addSql('DROP TABLE email');
        $this->addSql('DROP TABLE gmail_account');
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE "user"');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
