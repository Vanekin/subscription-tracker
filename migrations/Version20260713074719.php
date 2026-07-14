<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260713074719 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE subscription ADD COLUMN cancelled_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__subscription AS SELECT id, name, amount, currency, start_date, next_payment_date, billing_period, custom_period_days, preferred_day, reminder_sent_for, user_id FROM subscription');
        $this->addSql('DROP TABLE subscription');
        $this->addSql('CREATE TABLE subscription (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, amount DOUBLE PRECISION DEFAULT NULL, currency VARCHAR(10) NOT NULL, start_date DATETIME NOT NULL, next_payment_date DATETIME NOT NULL, billing_period VARCHAR(255) NOT NULL, custom_period_days INTEGER DEFAULT NULL, preferred_day INTEGER DEFAULT NULL, reminder_sent_for DATETIME DEFAULT NULL, user_id INTEGER DEFAULT NULL, CONSTRAINT FK_A3C664D3A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO subscription (id, name, amount, currency, start_date, next_payment_date, billing_period, custom_period_days, preferred_day, reminder_sent_for, user_id) SELECT id, name, amount, currency, start_date, next_payment_date, billing_period, custom_period_days, preferred_day, reminder_sent_for, user_id FROM __temp__subscription');
        $this->addSql('DROP TABLE __temp__subscription');
        $this->addSql('CREATE INDEX IDX_A3C664D3A76ED395 ON subscription (user_id)');
    }
}
