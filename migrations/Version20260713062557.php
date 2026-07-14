<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260713062557 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE subscription_payment (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, paid_at DATETIME NOT NULL, amount DOUBLE PRECISION NOT NULL, currency VARCHAR(10) NOT NULL, amount_in_rub DOUBLE PRECISION NOT NULL, subscription_id INTEGER NOT NULL, CONSTRAINT FK_1E3D64969A1887DC FOREIGN KEY (subscription_id) REFERENCES subscription (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_1E3D64969A1887DC ON subscription_payment (subscription_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__subscription AS SELECT id, name, amount, currency, start_date, next_payment_date, billing_period FROM subscription');
        $this->addSql('DROP TABLE subscription');
        $this->addSql('CREATE TABLE subscription (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, amount DOUBLE PRECISION DEFAULT NULL, currency VARCHAR(10) NOT NULL, start_date DATETIME NOT NULL, next_payment_date DATETIME NOT NULL, billing_period VARCHAR(255) NOT NULL, custom_period_days INTEGER DEFAULT NULL, preferred_day INTEGER DEFAULT NULL, user_id INTEGER DEFAULT NULL, CONSTRAINT FK_A3C664D3A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO subscription (id, name, amount, currency, start_date, next_payment_date, billing_period) SELECT id, name, amount, currency, start_date, next_payment_date, billing_period FROM __temp__subscription');
        $this->addSql('DROP TABLE __temp__subscription');
        $this->addSql('CREATE INDEX IDX_A3C664D3A76ED395 ON subscription (user_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__user AS SELECT id, email, password, name, surname, patronymic, roles FROM user');
        $this->addSql('DROP TABLE user');
        $this->addSql('CREATE TABLE user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, surname VARCHAR(255) NOT NULL, patronymic VARCHAR(255) DEFAULT NULL, roles CLOB NOT NULL)');
        $this->addSql('INSERT INTO user (id, email, password, name, surname, patronymic, roles) SELECT id, email, password, name, surname, patronymic, roles FROM __temp__user');
        $this->addSql('DROP TABLE __temp__user');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE subscription_payment');
        $this->addSql('CREATE TEMPORARY TABLE __temp__subscription AS SELECT id, name, amount, currency, start_date, next_payment_date, billing_period FROM subscription');
        $this->addSql('DROP TABLE subscription');
        $this->addSql('CREATE TABLE subscription (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, amount DOUBLE PRECISION DEFAULT NULL, currency VARCHAR(10) NOT NULL, start_date DATETIME NOT NULL, next_payment_date DATETIME NOT NULL, billing_period VARCHAR(255) NOT NULL)');
        $this->addSql('INSERT INTO subscription (id, name, amount, currency, start_date, next_payment_date, billing_period) SELECT id, name, amount, currency, start_date, next_payment_date, billing_period FROM __temp__subscription');
        $this->addSql('DROP TABLE __temp__subscription');
        $this->addSql('CREATE TEMPORARY TABLE __temp__user AS SELECT id, email, password, name, surname, patronymic, roles FROM user');
        $this->addSql('DROP TABLE user');
        $this->addSql('CREATE TABLE user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, surname VARCHAR(255) NOT NULL, patronymic VARCHAR(255) NOT NULL, roles CLOB NOT NULL)');
        $this->addSql('INSERT INTO user (id, email, password, name, surname, patronymic, roles) SELECT id, email, password, name, surname, patronymic, roles FROM __temp__user');
        $this->addSql('DROP TABLE __temp__user');
    }
}
