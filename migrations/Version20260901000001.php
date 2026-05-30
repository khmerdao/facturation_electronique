<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\Database\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Migration : Ajout des champs Stripe sur la table tenant (TASK-022).
 */
final class Version20260901000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout des champs Stripe/Billing sur la table tenant';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE tenant ' .
            'ADD COLUMN stripe_customer_id VARCHAR(255) DEFAULT NULL, ' .
            'ADD COLUMN stripe_subscription_id VARCHAR(255) DEFAULT NULL, ' .
            'ADD COLUMN stripe_subscription_status VARCHAR(50) DEFAULT NULL, ' .
            'ADD COLUMN current_period_end DATETIME DEFAULT NULL, ' .
            'ADD COLUMN cancel_at_period_end TINYINT(1) NOT NULL DEFAULT 0, ' .
            'ADD COLUMN stripe_price_id VARCHAR(255) DEFAULT NULL'
        );

        $this->addSql('CREATE INDEX idx_tenant_stripe_customer ON tenant (stripe_customer_id)');
        $this->addSql('CREATE INDEX idx_tenant_stripe_subscription ON tenant (stripe_subscription_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_tenant_stripe_customer ON tenant');
        $this->addSql('DROP INDEX idx_tenant_stripe_subscription ON tenant');
        $this->addSql(
            'ALTER TABLE tenant ' .
            'DROP COLUMN stripe_customer_id, ' .
            'DROP COLUMN stripe_subscription_id, ' .
            'DROP COLUMN stripe_subscription_status, ' .
            'DROP COLUMN current_period_end, ' .
            'DROP COLUMN cancel_at_period_end, ' .
            'DROP COLUMN stripe_price_id'
        );
    }
}
