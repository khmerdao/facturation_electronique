<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration initiale — crée toutes les tables de l'application
 * de facturation électronique conforme à la réforme française 2026-2027.
 *
 * Ordre de création respectant les contraintes FK :
 *   1. tenants                        (aucune dépendance)
 *   2. users                          (aucune dépendance)
 *   3. tenant_memberships             (→ tenants, users)
 *   4. tenant_invitations             (→ tenants, users)
 *   5. email_verification_tokens      (→ users)
 *   6. contacts                       (→ tenants)
 *   7. contact_persons                (→ contacts)
 *   8. contact_documents              (→ contacts, tenants, users)
 *   9. products                       (→ tenants)
 *  10. product_price_history          (→ products, tenants, users)
 *  11. invoice_sequences              (→ tenants)
 *  12. invoice_templates              (→ tenants)
 *  13. invoices                       (→ tenants, contacts, sequences, templates, invoices)
 *  14. invoice_lines                  (→ invoices, products)
 *  15. invoice_status_history         (→ invoices, users)
 *  16. received_invoices              (→ tenants, contacts)
 *  17. received_invoice_lines         (→ received_invoices)
 *  18. pdp_transmissions              (→ invoices, tenants)
 *  19. pdp_webhook_logs               (→ tenants)
 *  20. ereporting_batches             (→ tenants)
 *  21. ereporting_transactions        (→ ereporting_batches, invoices)
 *  22. ereporting_payment_lines       (→ ereporting_batches)  [payments créé après]
 *  23. ereporting_corrections         (→ ereporting_batches, users)
 *  24. payments                       (→ tenants, invoices, received_invoices, ereporting_batches, users)
 *  25. relance_emails                 (→ tenants, invoices, users)
 *  26. tax_adjustments                (→ tenants, users)
 *  27. export_jobs                    (→ tenants, users)
 *  28. notifications                  (→ tenants, users)
 *  29. notification_preferences       (→ tenants, users)
 *  30. api_keys                       (→ tenants, users)
 *  31. webhook_endpoints              (→ tenants)
 *  32. webhook_deliveries             (→ webhook_endpoints)
 *  33. audit_logs                     (→ tenants, users)
 *  34. super_admin_logs               (→ users, tenants)
 *
 * FK manquante volontairement : ereporting_payment_lines.payment_id
 * est ajoutée en ALTER après la table payments (dépendance circulaire).
 */
final class Version20260901000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migration initiale — toutes les tables du domaine facturation électronique';
    }

    public function up(Schema $schema): void
    {
        // ─────────────────────────────────────────────────────────────────
        // 1. TENANTS
        // ─────────────────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE tenants (
                id                      UUID        NOT NULL,
                slug                    VARCHAR(100) NOT NULL,
                name                    VARCHAR(255) NOT NULL,
                siret                   VARCHAR(14)  DEFAULT NULL,
                legal_form              VARCHAR(60)  DEFAULT NULL,
                tva_intra               VARCHAR(20)  DEFAULT NULL,
                vat_exempt              BOOLEAN      NOT NULL DEFAULT FALSE,
                vat_regime              VARCHAR(20)  NOT NULL DEFAULT 'REEL_NORMAL',
                ape_code                VARCHAR(10)  DEFAULT NULL,
                rcs_number              VARCHAR(30)  DEFAULT NULL,
                share_capital           INT          DEFAULT NULL,
                -- Adresse (embeddable, préfixe addr_)
                addr_line1              VARCHAR(255) DEFAULT NULL,
                addr_line2              VARCHAR(255) DEFAULT NULL,
                addr_postal_code        VARCHAR(20)  DEFAULT NULL,
                addr_city               VARCHAR(120) DEFAULT NULL,
                addr_country            VARCHAR(2)   NOT NULL DEFAULT 'FR',
                -- Contact
                billing_email           VARCHAR(255) DEFAULT NULL,
                phone                   VARCHAR(30)  DEFAULT NULL,
                website                 VARCHAR(255) DEFAULT NULL,
                iban                    VARCHAR(34)  DEFAULT NULL,
                bic                     VARCHAR(11)  DEFAULT NULL,
                logo_s3_key             VARCHAR(255) DEFAULT NULL,
                brand_color             VARCHAR(7)   DEFAULT NULL,
                -- PDP config (embeddable, préfixe pdp_)
                pdp_mode                VARCHAR(10)  DEFAULT NULL,
                pdp_pdp_name            VARCHAR(120) DEFAULT NULL,
                pdp_endpoint_url        VARCHAR(500) DEFAULT NULL,
                pdp_api_key_encrypted   TEXT         DEFAULT NULL,
                pdp_emitter_id          VARCHAR(120) DEFAULT NULL,
                pdp_last_test_status    VARCHAR(30)  DEFAULT NULL,
                pdp_connected_at        TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                -- Plan & statut
                plan                    VARCHAR(20)  NOT NULL DEFAULT 'FREE',
                status                  VARCHAR(20)  NOT NULL DEFAULT 'ONBOARDING',
                onboarding_step         VARCHAR(20)  NOT NULL DEFAULT 'ORGANISATION',
                onboarding_completed    BOOLEAN      NOT NULL DEFAULT FALSE,
                -- Préférences facturation
                default_currency        VARCHAR(3)   NOT NULL DEFAULT 'EUR',
                document_locale         VARCHAR(5)   NOT NULL DEFAULT 'fr',
                default_invoice_format  VARCHAR(20)  NOT NULL DEFAULT 'FACTURX',
                default_payment_terms   INT          NOT NULL DEFAULT 30,
                late_payment_rate       NUMERIC(5,2) DEFAULT NULL,
                recovery_fee            NUMERIC(8,2) NOT NULL DEFAULT 40.00,
                legal_mentions          TEXT         DEFAULT NULL,
                cgv_s3_key              VARCHAR(255) DEFAULT NULL,
                -- Dates
                assujettissement_date   TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                deleted_at              TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                created_at              TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at              TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_tenant_slug ON tenants (slug)');
        $this->addSql('CREATE INDEX idx_tenant_siret ON tenants (siret)');
        $this->addSql('CREATE INDEX idx_tenant_status ON tenants (status)');
        $this->addSql('COMMENT ON COLUMN tenants.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN tenants.pdp_connected_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN tenants.assujettissement_date IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN tenants.deleted_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN tenants.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN tenants.updated_at IS \'(DC2Type:datetime_immutable)\'');

        // ─────────────────────────────────────────────────────────────────
        // 2. USERS
        // ─────────────────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE users (
                id              UUID         NOT NULL,
                email           VARCHAR(255) NOT NULL,
                password        VARCHAR(255) NOT NULL,
                first_name      VARCHAR(100) DEFAULT NULL,
                last_name       VARCHAR(100) DEFAULT NULL,
                locale          VARCHAR(5)   NOT NULL DEFAULT 'fr',
                totp_secret     VARCHAR(255) DEFAULT NULL,
                email_verified  BOOLEAN      NOT NULL DEFAULT FALSE,
                super_admin     BOOLEAN      NOT NULL DEFAULT FALSE,
                created_at      TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                last_login_at   TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_user_email ON users (email)');
        $this->addSql('CREATE INDEX idx_user_email ON users (email)');
        $this->addSql('COMMENT ON COLUMN users.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN users.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN users.last_login_at IS \'(DC2Type:datetime_immutable)\'');

        // ─────────────────────────────────────────────────────────────────
        // 3. TENANT_MEMBERSHIPS  (liaison User ↔ Tenant, pas de ManyToMany)
        // ─────────────────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE tenant_memberships (
                id          UUID        NOT NULL,
                user_id     UUID        NOT NULL,
                tenant_id   UUID        NOT NULL,
                role        VARCHAR(20) NOT NULL,
                invited_at  TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                joined_at   TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_user_tenant ON tenant_memberships (user_id, tenant_id)');
        $this->addSql('CREATE INDEX idx_membership_tenant ON tenant_memberships (tenant_id)');
        $this->addSql('CREATE INDEX idx_membership_user ON tenant_memberships (user_id)');
        $this->addSql('COMMENT ON COLUMN tenant_memberships.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN tenant_memberships.user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN tenant_memberships.tenant_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN tenant_memberships.invited_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN tenant_memberships.joined_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE tenant_memberships ADD CONSTRAINT fk_membership_user   FOREIGN KEY (user_id)   REFERENCES users   (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE tenant_memberships ADD CONSTRAINT fk_membership_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        // ─────────────────────────────────────────────────────────────────
        // 4. TENANT_INVITATIONS
        // ─────────────────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE tenant_invitations (
                id              UUID         NOT NULL,
                tenant_id       UUID         NOT NULL,
                invited_by_id   UUID         DEFAULT NULL,
                email           VARCHAR(255) NOT NULL,
                role            VARCHAR(20)  NOT NULL,
                token           VARCHAR(128) NOT NULL,
                message         TEXT         DEFAULT NULL,
                created_at      TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                expires_at      TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                accepted_at     TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_invitation_token ON tenant_invitations (token)');
        $this->addSql('CREATE INDEX idx_invitation_token ON tenant_invitations (token)');
        $this->addSql('CREATE INDEX idx_invitation_email ON tenant_invitations (email)');
        $this->addSql('COMMENT ON COLUMN tenant_invitations.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN tenant_invitations.tenant_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN tenant_invitations.invited_by_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN tenant_invitations.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN tenant_invitations.expires_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN tenant_invitations.accepted_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE tenant_invitations ADD CONSTRAINT fk_invitation_tenant     FOREIGN KEY (tenant_id)     REFERENCES tenants (id) ON DELETE CASCADE  NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE tenant_invitations ADD CONSTRAINT fk_invitation_invited_by FOREIGN KEY (invited_by_id) REFERENCES users   (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        // ─────────────────────────────────────────────────────────────────
        // 5. EMAIL_VERIFICATION_TOKENS
        // ─────────────────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE email_verification_tokens (
                id          UUID         NOT NULL,
                user_id     UUID         NOT NULL,
                token       VARCHAR(128) NOT NULL,
                created_at  TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                expires_at  TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                used_at     TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_email_verif_token ON email_verification_tokens (token)');
        $this->addSql('CREATE INDEX idx_email_verif_token ON email_verification_tokens (token)');
        $this->addSql('COMMENT ON COLUMN email_verification_tokens.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN email_verification_tokens.user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN email_verification_tokens.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN email_verification_tokens.expires_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN email_verification_tokens.used_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE email_verification_tokens ADD CONSTRAINT fk_verif_token_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        // ─────────────────────────────────────────────────────────────────
        // 6. CONTACTS
        // ─────────────────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE contacts (
                id                              UUID         NOT NULL,
                tenant_id                       UUID         NOT NULL,
                type                            VARCHAR(20)  NOT NULL,
                name                            VARCHAR(255) NOT NULL,
                siret                           VARCHAR(14)  DEFAULT NULL,
                tva_intra                       VARCHAR(20)  DEFAULT NULL,
                legal_form                      VARCHAR(60)  DEFAULT NULL,
                ape_code                        VARCHAR(10)  DEFAULT NULL,
                pdp_identifier                  VARCHAR(120) DEFAULT NULL,
                -- Adresse siège
                addr_line1                      VARCHAR(255) DEFAULT NULL,
                addr_line2                      VARCHAR(255) DEFAULT NULL,
                addr_postal_code                VARCHAR(20)  DEFAULT NULL,
                addr_city                       VARCHAR(120) DEFAULT NULL,
                addr_country                    VARCHAR(2)   NOT NULL DEFAULT 'FR',
                -- Adresse livraison
                ship_line1                      VARCHAR(255) DEFAULT NULL,
                ship_line2                      VARCHAR(255) DEFAULT NULL,
                ship_postal_code                VARCHAR(20)  DEFAULT NULL,
                ship_city                       VARCHAR(120) DEFAULT NULL,
                ship_country                    VARCHAR(2)   NOT NULL DEFAULT 'FR',
                has_distinct_shipping_address   BOOLEAN      NOT NULL DEFAULT FALSE,
                -- Contact
                email                           VARCHAR(255) DEFAULT NULL,
                billing_email                   VARCHAR(255) DEFAULT NULL,
                phone                           VARCHAR(30)  DEFAULT NULL,
                website                         VARCHAR(255) DEFAULT NULL,
                -- Params facturation client
                payment_terms                   INT          DEFAULT NULL,
                default_discount                NUMERIC(5,2) DEFAULT NULL,
                preferred_currency              VARCHAR(3)   DEFAULT NULL,
                document_locale                 VARCHAR(5)   DEFAULT NULL,
                -- Params fournisseur
                supplier_iban                   VARCHAR(34)  DEFAULT NULL,
                notes                           TEXT         DEFAULT NULL,
                -- Sirene
                sirene_status                   VARCHAR(20)  DEFAULT NULL,
                sirene_checked_at               TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                -- Archivage
                active                          BOOLEAN      NOT NULL DEFAULT TRUE,
                archived_at                     TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                created_at                      TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at                      TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_contact_tenant ON contacts (tenant_id)');
        $this->addSql('CREATE INDEX idx_contact_siret ON contacts (siret)');
        $this->addSql('CREATE INDEX idx_contact_type ON contacts (type)');
        $this->addSql('COMMENT ON COLUMN contacts.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN contacts.tenant_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN contacts.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN contacts.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE contacts ADD CONSTRAINT fk_contact_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        // ─────────────────────────────────────────────────────────────────
        // 7. CONTACT_PERSONS
        // ─────────────────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE contact_persons (
                id          UUID         NOT NULL,
                contact_id  UUID         NOT NULL,
                first_name  VARCHAR(100) DEFAULT NULL,
                last_name   VARCHAR(100) DEFAULT NULL,
                role        VARCHAR(100) DEFAULT NULL,
                email       VARCHAR(255) DEFAULT NULL,
                phone       VARCHAR(30)  DEFAULT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_contact_person_contact ON contact_persons (contact_id)');
        $this->addSql('COMMENT ON COLUMN contact_persons.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN contact_persons.contact_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE contact_persons ADD CONSTRAINT fk_person_contact FOREIGN KEY (contact_id) REFERENCES contacts (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        // ─────────────────────────────────────────────────────────────────
        // 8. CONTACT_DOCUMENTS
        // ─────────────────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE contact_documents (
                id              UUID         NOT NULL,
                tenant_id       UUID         NOT NULL,
                contact_id      UUID         NOT NULL,
                uploaded_by_id  UUID         DEFAULT NULL,
                s3_key          VARCHAR(255) NOT NULL,
                filename        VARCHAR(255) NOT NULL,
                mime_type       VARCHAR(100) NOT NULL,
                size            INT          NOT NULL,
                uploaded_at     TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_contact_doc_contact ON contact_documents (contact_id)');
        $this->addSql('COMMENT ON COLUMN contact_documents.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN contact_documents.uploaded_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE contact_documents ADD CONSTRAINT fk_doc_tenant      FOREIGN KEY (tenant_id)      REFERENCES tenants  (id) ON DELETE CASCADE  NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE contact_documents ADD CONSTRAINT fk_doc_contact     FOREIGN KEY (contact_id)     REFERENCES contacts (id) ON DELETE CASCADE  NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE contact_documents ADD CONSTRAINT fk_doc_uploaded_by FOREIGN KEY (uploaded_by_id) REFERENCES users    (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        // ─────────────────────────────────────────────────────────────────
        // 9. PRODUCTS
        // ─────────────────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE products (
                id                    UUID          NOT NULL,
                tenant_id             UUID          NOT NULL,
                type                  VARCHAR(20)   NOT NULL DEFAULT 'SERVICE',
                reference             VARCHAR(80)   NOT NULL,
                label                 VARCHAR(150)  NOT NULL,
                description           TEXT          DEFAULT NULL,
                unit_price            NUMERIC(14,4) NOT NULL DEFAULT 0,
                unit                  VARCHAR(20)   NOT NULL DEFAULT 'U',
                tva_rate              NUMERIC(5,2)  NOT NULL DEFAULT 20.00,
                tva_exemption_reason  VARCHAR(30)   DEFAULT NULL,
                accounting_code       VARCHAR(20)   DEFAULT NULL,
                supplier_reference    VARCHAR(80)   DEFAULT NULL,
                min_price             NUMERIC(14,4) DEFAULT NULL,
                notes                 TEXT          DEFAULT NULL,
                active                BOOLEAN       NOT NULL DEFAULT TRUE,
                archived_at           TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                created_at            TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at            TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_product_reference_tenant ON products (tenant_id, reference)');
        $this->addSql('CREATE INDEX idx_product_tenant ON products (tenant_id)');
        $this->addSql('CREATE INDEX idx_product_tva ON products (tva_rate)');
        $this->addSql('COMMENT ON COLUMN products.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN products.tenant_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN products.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN products.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE products ADD CONSTRAINT fk_product_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        // ─────────────────────────────────────────────────────────────────
        // 10. PRODUCT_PRICE_HISTORY
        // ─────────────────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE product_price_history (
                id              UUID          NOT NULL,
                tenant_id       UUID          NOT NULL,
                product_id      UUID          NOT NULL,
                changed_by_id   UUID          DEFAULT NULL,
                old_price       NUMERIC(14,4) NOT NULL,
                new_price       NUMERIC(14,4) NOT NULL,
                changed_at      TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_price_history_product ON product_price_history (product_id)');
        $this->addSql('COMMENT ON COLUMN product_price_history.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN product_price_history.changed_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE product_price_history ADD CONSTRAINT fk_pph_tenant     FOREIGN KEY (tenant_id)     REFERENCES tenants  (id) ON DELETE CASCADE  NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_price_history ADD CONSTRAINT fk_pph_product    FOREIGN KEY (product_id)    REFERENCES products (id) ON DELETE CASCADE  NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_price_history ADD CONSTRAINT fk_pph_changed_by FOREIGN KEY (changed_by_id) REFERENCES users    (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        // ─────────────────────────────────────────────────────────────────
        // 11. INVOICE_SEQUENCES
        // ─────────────────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE invoice_sequences (
                id                      UUID        NOT NULL,
                tenant_id               UUID        NOT NULL,
                name                    VARCHAR(100) NOT NULL DEFAULT 'Séquence principale',
                prefix                  VARCHAR(20)  DEFAULT 'FAC',
                year_format             VARCHAR(4)   DEFAULT 'AAAA',
                include_month           BOOLEAN      NOT NULL DEFAULT FALSE,
                separator               VARCHAR(1)   DEFAULT '-',
                padding                 INT          NOT NULL DEFAULT 4,
                start_number            INT          NOT NULL DEFAULT 1,
                next_number             INT          NOT NULL DEFAULT 1,
                reset_yearly            BOOLEAN      NOT NULL DEFAULT FALSE,
                last_year               INT          DEFAULT NULL,
                locked                  BOOLEAN      NOT NULL DEFAULT FALSE,
                is_credit_note_sequence BOOLEAN      NOT NULL DEFAULT FALSE,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_sequence_tenant ON invoice_sequences (tenant_id)');
        $this->addSql('COMMENT ON COLUMN invoice_sequences.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN invoice_sequences.tenant_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE invoice_sequences ADD CONSTRAINT fk_sequence_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        // ─────────────────────────────────────────────────────────────────
        // 12. INVOICE_TEMPLATES
        // ─────────────────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE invoice_templates (
                id              UUID         NOT NULL,
                tenant_id       UUID         NOT NULL,
                name            VARCHAR(100) NOT NULL,
                base_key        VARCHAR(50)  NOT NULL DEFAULT 'classique',
                is_default      BOOLEAN      NOT NULL DEFAULT FALSE,
                is_customized   BOOLEAN      NOT NULL DEFAULT FALSE,
                config          JSON         NOT NULL,
                preview_s3_key  VARCHAR(255) DEFAULT NULL,
                created_at      TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at      TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_template_tenant ON invoice_templates (tenant_id)');
        $this->addSql('COMMENT ON COLUMN invoice_templates.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN invoice_templates.tenant_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN invoice_templates.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN invoice_templates.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE invoice_templates ADD CONSTRAINT fk_template_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        // ─────────────────────────────────────────────────────────────────
        // 13. INVOICES
        // ─────────────────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE invoices (
                id                      UUID          NOT NULL,
                tenant_id               UUID          NOT NULL,
                contact_id              UUID          DEFAULT NULL,
                sequence_id             UUID          DEFAULT NULL,
                template_id             UUID          DEFAULT NULL,
                credit_note_for_id      UUID          DEFAULT NULL,
                number                  VARCHAR(50)   DEFAULT NULL,
                status                  VARCHAR(20)   NOT NULL DEFAULT 'DRAFT',
                type                    VARCHAR(20)   NOT NULL DEFAULT 'INVOICE',
                format                  VARCHAR(20)   NOT NULL DEFAULT 'FACTURX',
                -- Snapshot client (immuabilité de la facture)
                client_name_snapshot    VARCHAR(255)  DEFAULT NULL,
                client_siret_snapshot   VARCHAR(14)   DEFAULT NULL,
                client_pdp_identifier   VARCHAR(120)  DEFAULT NULL,
                -- Montants (precision 2 pour les totaux)
                currency                VARCHAR(3)    NOT NULL DEFAULT 'EUR',
                total_ht                NUMERIC(14,2) NOT NULL DEFAULT 0,
                total_tva               NUMERIC(14,2) NOT NULL DEFAULT 0,
                total_ttc               NUMERIC(14,2) NOT NULL DEFAULT 0,
                amount_paid             NUMERIC(14,2) NOT NULL DEFAULT 0,
                -- Dates
                issue_date              DATE          NOT NULL,
                due_date                DATE          DEFAULT NULL,
                -- Métadonnées
                client_reference        VARCHAR(100)  DEFAULT NULL,
                subject                 TEXT          DEFAULT NULL,
                client_notes            TEXT          DEFAULT NULL,
                internal_notes          TEXT          DEFAULT NULL,
                -- Archivage S3 (piste d'audit fiable)
                pdf_s3_key              VARCHAR(255)  DEFAULT NULL,
                xml_s3_key              VARCHAR(255)  DEFAULT NULL,
                file_hash               VARCHAR(64)   DEFAULT NULL,
                -- Timestamps cycle de vie
                validated_at            TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                paid_at                 TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                deleted_at              TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                created_at              TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at              TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                -- Optimistic lock
                version                 INT           NOT NULL DEFAULT 1,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_invoice_tenant_status ON invoices (tenant_id, status)');
        $this->addSql('CREATE INDEX idx_invoice_tenant_issue  ON invoices (tenant_id, issue_date)');
        $this->addSql('CREATE INDEX idx_invoice_number        ON invoices (number)');
        $this->addSql('CREATE INDEX idx_invoice_contact       ON invoices (contact_id)');
        $this->addSql('COMMENT ON COLUMN invoices.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN invoices.tenant_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN invoices.issue_date IS \'(DC2Type:date_immutable)\'');
        $this->addSql('COMMENT ON COLUMN invoices.due_date IS \'(DC2Type:date_immutable)\'');
        $this->addSql('COMMENT ON COLUMN invoices.validated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN invoices.paid_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN invoices.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN invoices.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE invoices ADD CONSTRAINT fk_invoice_tenant         FOREIGN KEY (tenant_id)          REFERENCES tenants          (id) ON DELETE CASCADE  NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE invoices ADD CONSTRAINT fk_invoice_contact        FOREIGN KEY (contact_id)         REFERENCES contacts         (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE invoices ADD CONSTRAINT fk_invoice_sequence       FOREIGN KEY (sequence_id)        REFERENCES invoice_sequences (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE invoices ADD CONSTRAINT fk_invoice_template       FOREIGN KEY (template_id)        REFERENCES invoice_templates (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE invoices ADD CONSTRAINT fk_invoice_credit_note_for FOREIGN KEY (credit_note_for_id) REFERENCES invoices          (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        // ─────────────────────────────────────────────────────────────────
        // 14. INVOICE_LINES
        // ─────────────────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE invoice_lines (
                id                    UUID          NOT NULL,
                invoice_id            UUID          NOT NULL,
                product_id            UUID          DEFAULT NULL,
                position              INT           NOT NULL DEFAULT 0,
                is_comment            BOOLEAN       NOT NULL DEFAULT FALSE,
                reference             VARCHAR(80)   DEFAULT NULL,
                description           TEXT          NOT NULL DEFAULT '',
                quantity              NUMERIC(14,4) NOT NULL DEFAULT 1,
                unit                  VARCHAR(20)   NOT NULL DEFAULT 'U',
                unit_price            NUMERIC(14,4) NOT NULL DEFAULT 0,
                discount              NUMERIC(5,2)  NOT NULL DEFAULT 0,
                tva_rate              NUMERIC(5,2)  NOT NULL DEFAULT 20.00,
                tva_exemption_reason  VARCHAR(30)   DEFAULT NULL,
                amount_ht             NUMERIC(14,2) NOT NULL DEFAULT 0,
                amount_tva            NUMERIC(14,2) NOT NULL DEFAULT 0,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_invoice_line_invoice ON invoice_lines (invoice_id)');
        $this->addSql('CREATE INDEX idx_invoice_line_product ON invoice_lines (product_id)');
        $this->addSql('COMMENT ON COLUMN invoice_lines.id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE invoice_lines ADD CONSTRAINT fk_line_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE  NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE invoice_lines ADD CONSTRAINT fk_line_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        // ─────────────────────────────────────────────────────────────────
        // 15. INVOICE_STATUS_HISTORY
        // ─────────────────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE invoice_status_history (
                id           UUID        NOT NULL,
                invoice_id   UUID        NOT NULL,
                actor_id     UUID        DEFAULT NULL,
                from_status  VARCHAR(20) DEFAULT NULL,
                to_status    VARCHAR(20) NOT NULL,
                comment      TEXT        DEFAULT NULL,
                created_at   TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_status_history_invoice ON invoice_status_history (invoice_id)');
        $this->addSql('COMMENT ON COLUMN invoice_status_history.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN invoice_status_history.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE invoice_status_history ADD CONSTRAINT fk_ish_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE  NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE invoice_status_history ADD CONSTRAINT fk_ish_actor   FOREIGN KEY (actor_id)   REFERENCES users    (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        // ─────────────────────────────────────────────────────────────────
        // 16. RECEIVED_INVOICES
        // ─────────────────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE received_invoices (
                id                      UUID          NOT NULL,
                tenant_id               UUID          NOT NULL,
                supplier_contact_id     UUID          DEFAULT NULL,
                status                  VARCHAR(30)   NOT NULL DEFAULT 'PENDING_VALIDATION',
                external_pdp_id         VARCHAR(191)  DEFAULT NULL,
                invoice_number          VARCHAR(80)   DEFAULT NULL,
                supplier_name           VARCHAR(255)  DEFAULT NULL,
                supplier_siret          VARCHAR(14)   DEFAULT NULL,
                supplier_tva_intra      VARCHAR(20)   DEFAULT NULL,
                supplier_iban           VARCHAR(34)   DEFAULT NULL,
                format                  VARCHAR(20)   DEFAULT NULL,
                invoice_date            DATE          DEFAULT NULL,
                due_date                DATE          DEFAULT NULL,
                currency                VARCHAR(3)    NOT NULL DEFAULT 'EUR',
                amount_ht               NUMERIC(14,2) DEFAULT NULL,
                amount_tva              NUMERIC(14,2) DEFAULT NULL,
                amount_ttc              NUMERIC(14,2) DEFAULT NULL,
                amount_paid             NUMERIC(14,2) NOT NULL DEFAULT 0,
                parsed_data             JSON          DEFAULT NULL,
                parse_errors            JSON          DEFAULT NULL,
                raw_file_s3_key         VARCHAR(255)  DEFAULT NULL,
                file_hash               VARCHAR(64)   DEFAULT NULL,
                received_at             TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                technical_ack_sent_at   TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                contest_reason          VARCHAR(50)   DEFAULT NULL,
                contest_description     TEXT          DEFAULT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_received_external_pdp ON received_invoices (tenant_id, external_pdp_id)');
        $this->addSql('CREATE INDEX idx_received_tenant_status ON received_invoices (tenant_id, status)');
        $this->addSql('CREATE INDEX idx_received_supplier      ON received_invoices (supplier_contact_id)');
        $this->addSql('COMMENT ON COLUMN received_invoices.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN received_invoices.received_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN received_invoices.invoice_date IS \'(DC2Type:date_immutable)\'');
        $this->addSql('COMMENT ON COLUMN received_invoices.due_date IS \'(DC2Type:date_immutable)\'');
        $this->addSql('ALTER TABLE received_invoices ADD CONSTRAINT fk_ri_tenant           FOREIGN KEY (tenant_id)           REFERENCES tenants  (id) ON DELETE CASCADE  NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE received_invoices ADD CONSTRAINT fk_ri_supplier_contact FOREIGN KEY (supplier_contact_id) REFERENCES contacts (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        // ─────────────────────────────────────────────────────────────────
        // 17. RECEIVED_INVOICE_LINES
        // ─────────────────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE received_invoice_lines (
                id                    UUID          NOT NULL,
                received_invoice_id   UUID          NOT NULL,
                description           TEXT          NOT NULL DEFAULT '',
                quantity              NUMERIC(14,4) NOT NULL DEFAULT 1,
                unit_price            NUMERIC(14,4) NOT NULL DEFAULT 0,
                tva_rate              NUMERIC(5,2)  NOT NULL DEFAULT 20.00,
                amount_ht             NUMERIC(14,2) NOT NULL DEFAULT 0,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_received_line_invoice ON received_invoice_lines (received_invoice_id)');
        $this->addSql('COMMENT ON COLUMN received_invoice_lines.id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE received_invoice_lines ADD CONSTRAINT fk_ril_received_invoice FOREIGN KEY (received_invoice_id) REFERENCES received_invoices (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        // ─────────────────────────────────────────────────────────────────
        // 18. PDP_TRANSMISSIONS
        // ─────────────────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE pdp_transmissions (
                id              UUID         NOT NULL,
                tenant_id       UUID         NOT NULL,
                invoice_id      UUID         NOT NULL,
                status          VARCHAR(20)  NOT NULL DEFAULT 'PENDING',
                external_id     VARCHAR(191) DEFAULT NULL,
                pdp_name        VARCHAR(120) DEFAULT NULL,
                attempt         INT          NOT NULL DEFAULT 0,
                sent_at         TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                acknowledged_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                reject_code     VARCHAR(100) DEFAULT NULL,
                reject_reason   TEXT         DEFAULT NULL,
                raw_response    JSON         DEFAULT NULL,
                created_at      TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_transmission_invoice       ON pdp_transmissions (invoice_id)');
        $this->addSql('CREATE INDEX idx_transmission_tenant_status ON pdp_transmissions (tenant_id, status)');
        $this->addSql('CREATE INDEX idx_transmission_external      ON pdp_transmissions (external_id)');
        $this->addSql('COMMENT ON COLUMN pdp_transmissions.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN pdp_transmissions.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE pdp_transmissions ADD CONSTRAINT fk_pdpt_tenant  FOREIGN KEY (tenant_id)  REFERENCES tenants  (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE pdp_transmissions ADD CONSTRAINT fk_pdpt_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        // ─────────────────────────────────────────────────────────────────
        // 19. PDP_WEBHOOK_LOGS
        // ─────────────────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE pdp_webhook_logs (
                id                UUID         NOT NULL,
                tenant_id         UUID         NOT NULL,
                event_id          VARCHAR(191) NOT NULL,
                event_type        VARCHAR(80)  NOT NULL,
                payload           JSON         NOT NULL,
                processed         BOOLEAN      NOT NULL DEFAULT FALSE,
                processing_error  TEXT         DEFAULT NULL,
                signature_hash    VARCHAR(64)  DEFAULT NULL,
                received_at       TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                processed_at      TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_webhook_event ON pdp_webhook_logs (tenant_id, event_id)');
        $this->addSql('CREATE INDEX idx_webhook_log_tenant ON pdp_webhook_logs (tenant_id)');
        $this->addSql('CREATE INDEX idx_webhook_log_type   ON pdp_webhook_logs (event_type)');
        $this->addSql('COMMENT ON COLUMN pdp_webhook_logs.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN pdp_webhook_logs.received_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE pdp_webhook_logs ADD CONSTRAINT fk_webhook_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        // ─────────────────────────────────────────────────────────────────
        // 20. EREPORTING_BATCHES
        // ─────────────────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE ereporting_batches (
                id              UUID        NOT NULL,
                tenant_id       UUID        NOT NULL,
                period          VARCHAR(7)  NOT NULL,
                periodicity     VARCHAR(20) NOT NULL DEFAULT 'MONTHLY',
                status          VARCHAR(20) NOT NULL DEFAULT 'NOT_STARTED',
                deadline        DATE        NOT NULL,
                late            BOOLEAN     NOT NULL DEFAULT FALSE,
                is_nil          BOOLEAN     NOT NULL DEFAULT FALSE,
                dgfip_reference VARCHAR(191) DEFAULT NULL,
                xml_s3_key      VARCHAR(255) DEFAULT NULL,
                file_hash       VARCHAR(64)  DEFAULT NULL,
                submitted_at    TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                accepted_at     TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                reject_reason   TEXT         DEFAULT NULL,
                created_at      TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_batch_tenant_period ON ereporting_batches (tenant_id, period)');
        $this->addSql('CREATE INDEX idx_batch_tenant_status ON ereporting_batches (tenant_id, status)');
        $this->addSql('COMMENT ON COLUMN ereporting_batches.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN ereporting_batches.deadline IS \'(DC2Type:date_immutable)\'');
        $this->addSql('COMMENT ON COLUMN ereporting_batches.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE ereporting_batches ADD CONSTRAINT fk_erb_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        // ─────────────────────────────────────────────────────────────────
        // 21. EREPORTING_TRANSACTIONS
        // ─────────────────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE ereporting_transactions (
                id                  UUID          NOT NULL,
                batch_id            UUID          NOT NULL,
                invoice_id          UUID          DEFAULT NULL,
                type                VARCHAR(30)   NOT NULL,
                transaction_date    DATE          NOT NULL,
                amount_ht_by_rate   JSON          NOT NULL,
                amount_tva_by_rate  JSON          NOT NULL,
                total_ht            NUMERIC(14,2) NOT NULL DEFAULT 0,
                total_tva           NUMERIC(14,2) NOT NULL DEFAULT 0,
                currency            VARCHAR(3)    NOT NULL DEFAULT 'EUR',
                transaction_count   INT           NOT NULL DEFAULT 1,
                country_code        VARCHAR(2)    DEFAULT NULL,
                source              VARCHAR(10)   NOT NULL DEFAULT 'AUTO',
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_er_transaction_batch ON ereporting_transactions (batch_id)');
        $this->addSql('COMMENT ON COLUMN ereporting_transactions.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN ereporting_transactions.transaction_date IS \'(DC2Type:date_immutable)\'');
        $this->addSql('ALTER TABLE ereporting_transactions ADD CONSTRAINT fk_ert_batch   FOREIGN KEY (batch_id)   REFERENCES ereporting_batches (id) ON DELETE CASCADE  NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE ereporting_transactions ADD CONSTRAINT fk_ert_invoice FOREIGN KEY (invoice_id) REFERENCES invoices           (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        // ─────────────────────────────────────────────────────────────────
        // 22. EREPORTING_PAYMENT_LINES  (payment_id FK ajoutée après payments)
        // ─────────────────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE ereporting_payment_lines (
                id              UUID          NOT NULL,
                batch_id        UUID          NOT NULL,
                payment_id      UUID          DEFAULT NULL,
                payment_date    DATE          NOT NULL,
                amount_ttc      NUMERIC(14,2) NOT NULL,
                amount_tva      NUMERIC(14,2) NOT NULL DEFAULT 0,
                tva_rate        NUMERIC(5,2)  NOT NULL DEFAULT 20.00,
                source          VARCHAR(10)   NOT NULL DEFAULT 'AUTO',
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_er_payment_batch   ON ereporting_payment_lines (batch_id)');
        $this->addSql('CREATE INDEX idx_er_payment_payment ON ereporting_payment_lines (payment_id)');
        $this->addSql('COMMENT ON COLUMN ereporting_payment_lines.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN ereporting_payment_lines.payment_date IS \'(DC2Type:date_immutable)\'');
        $this->addSql('ALTER TABLE ereporting_payment_lines ADD CONSTRAINT fk_erpl_batch FOREIGN KEY (batch_id) REFERENCES ereporting_batches (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        // ─────────────────────────────────────────────────────────────────
        // 23. EREPORTING_CORRECTIONS
        // ─────────────────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE ereporting_corrections (
                id              UUID        NOT NULL,
                batch_id        UUID        NOT NULL,
                corrected_by_id UUID        DEFAULT NULL,
                field           VARCHAR(50) NOT NULL,
                old_value       TEXT        DEFAULT NULL,
                new_value       TEXT        DEFAULT NULL,
                reason          TEXT        NOT NULL,
                created_at      TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_er_correction_batch ON ereporting_corrections (batch_id)');
        $this->addSql('COMMENT ON COLUMN ereporting_corrections.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN ereporting_corrections.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE ereporting_corrections ADD CONSTRAINT fk_erc_batch        FOREIGN KEY (batch_id)        REFERENCES ereporting_batches (id) ON DELETE CASCADE  NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE ereporting_corrections ADD CONSTRAINT fk_erc_corrected_by FOREIGN KEY (corrected_by_id) REFERENCES users               (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        // ─────────────────────────────────────────────────────────────────
        // 24. PAYMENTS
        // ─────────────────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE payments (
                id                      UUID          NOT NULL,
                tenant_id               UUID          NOT NULL,
                invoice_id              UUID          DEFAULT NULL,
                received_invoice_id     UUID          DEFAULT NULL,
                ereporting_batch_id     UUID          DEFAULT NULL,
                recorded_by_id          UUID          DEFAULT NULL,
                direction               VARCHAR(20)   NOT NULL,
                date                    DATE          NOT NULL,
                amount                  NUMERIC(14,2) NOT NULL,
                currency                VARCHAR(3)    NOT NULL DEFAULT 'EUR',
                amount_eur              NUMERIC(14,2) DEFAULT NULL,
                exchange_rate           NUMERIC(14,6) DEFAULT NULL,
                mode                    VARCHAR(20)   NOT NULL,
                mode_dgfip_code         VARCHAR(2)    NOT NULL,
                reference               VARCHAR(191)  DEFAULT NULL,
                notes                   TEXT          DEFAULT NULL,
                idempotency_key         VARCHAR(191)  DEFAULT NULL,
                ereporting_required     BOOLEAN       NOT NULL DEFAULT FALSE,
                ereporting_reported     BOOLEAN       NOT NULL DEFAULT FALSE,
                created_at              TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_payment_idempotency ON payments (idempotency_key)');
        $this->addSql('CREATE INDEX idx_payment_invoice    ON payments (invoice_id)');
        $this->addSql('CREATE INDEX idx_payment_received   ON payments (received_invoice_id)');
        $this->addSql('CREATE INDEX idx_payment_tenant_date ON payments (tenant_id, date)');
        $this->addSql('CREATE INDEX idx_payment_direction  ON payments (direction)');
        $this->addSql('COMMENT ON COLUMN payments.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN payments.date IS \'(DC2Type:date_immutable)\'');
        $this->addSql('COMMENT ON COLUMN payments.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE payments ADD CONSTRAINT fk_pay_tenant           FOREIGN KEY (tenant_id)           REFERENCES tenants           (id) ON DELETE CASCADE  NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE payments ADD CONSTRAINT fk_pay_invoice          FOREIGN KEY (invoice_id)          REFERENCES invoices          (id) ON DELETE CASCADE  NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE payments ADD CONSTRAINT fk_pay_received_invoice FOREIGN KEY (received_invoice_id) REFERENCES received_invoices (id) ON DELETE CASCADE  NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE payments ADD CONSTRAINT fk_pay_er_batch         FOREIGN KEY (ereporting_batch_id) REFERENCES ereporting_batches(id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE payments ADD CONSTRAINT fk_pay_recorded_by      FOREIGN KEY (recorded_by_id)      REFERENCES users             (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        // FK manquante sur ereporting_payment_lines (circular dependency résolue)
        $this->addSql('ALTER TABLE ereporting_payment_lines ADD CONSTRAINT fk_erpl_payment FOREIGN KEY (payment_id) REFERENCES payments (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        // ─────────────────────────────────────────────────────────────────
        // 25. RELANCE_EMAILS
        // ─────────────────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE relance_emails (
                id                  UUID         NOT NULL,
                tenant_id           UUID         NOT NULL,
                invoice_id          UUID         NOT NULL,
                sent_by_id          UUID         DEFAULT NULL,
                level               SMALLINT     NOT NULL DEFAULT 1,
                recipient_email     VARCHAR(255) NOT NULL,
                subject             VARCHAR(255) NOT NULL,
                body                TEXT         NOT NULL,
                includes_late_fees  BOOLEAN      NOT NULL DEFAULT FALSE,
                sent_at             TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                opened_at           TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_relance_invoice ON relance_emails (invoice_id)');
        $this->addSql('CREATE INDEX idx_relance_tenant  ON relance_emails (tenant_id)');
        $this->addSql('COMMENT ON COLUMN relance_emails.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN relance_emails.sent_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE relance_emails ADD CONSTRAINT fk_rel_tenant    FOREIGN KEY (tenant_id)  REFERENCES tenants  (id) ON DELETE CASCADE  NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE relance_emails ADD CONSTRAINT fk_rel_invoice   FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE  NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE relance_emails ADD CONSTRAINT fk_rel_sent_by   FOREIGN KEY (sent_by_id) REFERENCES users    (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        // ─────────────────────────────────────────────────────────────────
        // 26. TAX_ADJUSTMENTS
        // ─────────────────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE tax_adjustments (
                id              UUID          NOT NULL,
                tenant_id       UUID          NOT NULL,
                created_by_id   UUID          DEFAULT NULL,
                period          VARCHAR(7)    NOT NULL,
                type            VARCHAR(12)   NOT NULL,
                tva_rate        NUMERIC(5,2)  DEFAULT NULL,
                amount          NUMERIC(14,2) NOT NULL,
                ca3_box         VARCHAR(10)   DEFAULT NULL,
                reason          TEXT          NOT NULL,
                created_at      TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_tax_adj_tenant_period ON tax_adjustments (tenant_id, period)');
        $this->addSql('COMMENT ON COLUMN tax_adjustments.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN tax_adjustments.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE tax_adjustments ADD CONSTRAINT fk_ta_tenant     FOREIGN KEY (tenant_id)     REFERENCES tenants (id) ON DELETE CASCADE  NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE tax_adjustments ADD CONSTRAINT fk_ta_created_by FOREIGN KEY (created_by_id) REFERENCES users   (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        // ─────────────────────────────────────────────────────────────────
        // 27. EXPORT_JOBS
        // ─────────────────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE export_jobs (
                id              UUID        NOT NULL,
                tenant_id       UUID        NOT NULL,
                generated_by_id UUID        DEFAULT NULL,
                type            VARCHAR(10) NOT NULL,
                status          VARCHAR(15) NOT NULL DEFAULT 'PENDING',
                params          JSON        NOT NULL,
                s3_key          VARCHAR(255) DEFAULT NULL,
                file_hash       VARCHAR(64)  DEFAULT NULL,
                file_size       INT          DEFAULT NULL,
                row_count       INT          DEFAULT NULL,
                error_message   TEXT         DEFAULT NULL,
                created_at      TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                completed_at    TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                expires_at      TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_export_tenant_status ON export_jobs (tenant_id, status)');
        $this->addSql('COMMENT ON COLUMN export_jobs.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN export_jobs.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE export_jobs ADD CONSTRAINT fk_ej_tenant       FOREIGN KEY (tenant_id)       REFERENCES tenants (id) ON DELETE CASCADE  NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE export_jobs ADD CONSTRAINT fk_ej_generated_by FOREIGN KEY (generated_by_id) REFERENCES users   (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        // ─────────────────────────────────────────────────────────────────
        // 28. NOTIFICATIONS
        // ─────────────────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE notifications (
                id            UUID         NOT NULL,
                tenant_id     UUID         NOT NULL,
                user_id       UUID         DEFAULT NULL,
                type          VARCHAR(60)  NOT NULL,
                severity      VARCHAR(10)  NOT NULL DEFAULT 'INFO',
                title         VARCHAR(255) NOT NULL,
                description   TEXT         DEFAULT NULL,
                action_url    VARCHAR(500) DEFAULT NULL,
                action_label  VARCHAR(100) DEFAULT NULL,
                payload       JSON         DEFAULT NULL,
                read_at       TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                dismissed_at  TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                created_at    TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_notification_tenant_user ON notifications (tenant_id, user_id)');
        $this->addSql('CREATE INDEX idx_notification_read        ON notifications (read_at)');
        $this->addSql('CREATE INDEX idx_notification_type        ON notifications (type)');
        $this->addSql('COMMENT ON COLUMN notifications.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN notifications.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE notifications ADD CONSTRAINT fk_notif_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE  NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE notifications ADD CONSTRAINT fk_notif_user   FOREIGN KEY (user_id)   REFERENCES users   (id) ON DELETE CASCADE  NOT DEFERRABLE INITIALLY IMMEDIATE');

        // ─────────────────────────────────────────────────────────────────
        // 29. NOTIFICATION_PREFERENCES
        // ─────────────────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE notification_preferences (
                id                  UUID        NOT NULL,
                tenant_id           UUID        NOT NULL,
                user_id             UUID        NOT NULL,
                notification_type   VARCHAR(60) NOT NULL,
                in_app_enabled      BOOLEAN     NOT NULL DEFAULT TRUE,
                email_enabled       BOOLEAN     NOT NULL DEFAULT TRUE,
                email_digest        VARCHAR(15) NOT NULL DEFAULT 'IMMEDIATE',
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_pref_user_type ON notification_preferences (user_id, notification_type)');
        $this->addSql('CREATE INDEX idx_pref_user ON notification_preferences (user_id)');
        $this->addSql('COMMENT ON COLUMN notification_preferences.id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE notification_preferences ADD CONSTRAINT fk_np_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE notification_preferences ADD CONSTRAINT fk_np_user   FOREIGN KEY (user_id)   REFERENCES users   (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        // ─────────────────────────────────────────────────────────────────
        // 30. API_KEYS
        // ─────────────────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE api_keys (
                id              UUID         NOT NULL,
                tenant_id       UUID         NOT NULL,
                created_by_id   UUID         DEFAULT NULL,
                name            VARCHAR(100) NOT NULL,
                key_hash        VARCHAR(64)  NOT NULL,
                key_prefix      VARCHAR(20)  NOT NULL,
                environment     VARCHAR(15)  NOT NULL DEFAULT 'TEST',
                permissions     JSON         NOT NULL,
                created_at      TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                last_used_at    TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                expires_at      TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                revoked_at      TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_apikey_hash ON api_keys (key_hash)');
        $this->addSql('CREATE INDEX idx_apikey_tenant ON api_keys (tenant_id)');
        $this->addSql('CREATE INDEX idx_apikey_hash   ON api_keys (key_hash)');
        $this->addSql('COMMENT ON COLUMN api_keys.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN api_keys.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE api_keys ADD CONSTRAINT fk_ak_tenant     FOREIGN KEY (tenant_id)     REFERENCES tenants (id) ON DELETE CASCADE  NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE api_keys ADD CONSTRAINT fk_ak_created_by FOREIGN KEY (created_by_id) REFERENCES users   (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        // ─────────────────────────────────────────────────────────────────
        // 31. WEBHOOK_ENDPOINTS
        // ─────────────────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE webhook_endpoints (
                id              UUID         NOT NULL,
                tenant_id       UUID         NOT NULL,
                url             VARCHAR(500) NOT NULL,
                events          JSON         NOT NULL,
                secret_hash     VARCHAR(64)  NOT NULL,
                active          BOOLEAN      NOT NULL DEFAULT TRUE,
                failure_count   INT          NOT NULL DEFAULT 0,
                created_at      TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                last_success_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_webhook_endpoint_tenant ON webhook_endpoints (tenant_id)');
        $this->addSql('COMMENT ON COLUMN webhook_endpoints.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN webhook_endpoints.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE webhook_endpoints ADD CONSTRAINT fk_we_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        // ─────────────────────────────────────────────────────────────────
        // 32. WEBHOOK_DELIVERIES
        // ─────────────────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE webhook_deliveries (
                id              UUID        NOT NULL,
                endpoint_id     UUID        NOT NULL,
                event_type      VARCHAR(80) NOT NULL,
                payload         JSON        NOT NULL,
                status          VARCHAR(15) NOT NULL DEFAULT 'PENDING',
                http_status     INT         DEFAULT NULL,
                attempts        INT         NOT NULL DEFAULT 0,
                response_body   TEXT        DEFAULT NULL,
                created_at      TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                delivered_at    TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                next_retry_at   TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_delivery_endpoint ON webhook_deliveries (endpoint_id)');
        $this->addSql('CREATE INDEX idx_delivery_status   ON webhook_deliveries (status)');
        $this->addSql('COMMENT ON COLUMN webhook_deliveries.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN webhook_deliveries.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE webhook_deliveries ADD CONSTRAINT fk_wd_endpoint FOREIGN KEY (endpoint_id) REFERENCES webhook_endpoints (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        // ─────────────────────────────────────────────────────────────────
        // 33. AUDIT_LOGS  (INSERT only — immuable, rétention 10 ans)
        // ─────────────────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE audit_logs (
                id                UUID        NOT NULL,
                tenant_id         UUID        NOT NULL,
                user_id           UUID        DEFAULT NULL,
                action            VARCHAR(80) NOT NULL,
                entity_type       VARCHAR(80) DEFAULT NULL,
                entity_id         VARCHAR(36) DEFAULT NULL,
                payload_before    JSON        DEFAULT NULL,
                payload_after     JSON        DEFAULT NULL,
                ip_address        VARCHAR(45) DEFAULT NULL,
                user_agent        VARCHAR(255) DEFAULT NULL,
                is_impersonated   BOOLEAN     NOT NULL DEFAULT FALSE,
                impersonated_by   VARCHAR(255) DEFAULT NULL,
                created_at        TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_audit_tenant_created ON audit_logs (tenant_id, created_at)');
        $this->addSql('CREATE INDEX idx_audit_entity         ON audit_logs (entity_type, entity_id)');
        $this->addSql('CREATE INDEX idx_audit_action         ON audit_logs (action)');
        $this->addSql('COMMENT ON COLUMN audit_logs.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN audit_logs.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE audit_logs ADD CONSTRAINT fk_al_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE  NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE audit_logs ADD CONSTRAINT fk_al_user   FOREIGN KEY (user_id)   REFERENCES users   (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        // ─────────────────────────────────────────────────────────────────
        // 34. SUPER_ADMIN_LOGS  (cross-tenant, pas de TenantFilter)
        // ─────────────────────────────────────────────────────────────────
        $this->addSql(<<<'SQL'
            CREATE TABLE super_admin_logs (
                id                  UUID         NOT NULL,
                super_admin_id      UUID         NOT NULL,
                target_tenant_id    UUID         DEFAULT NULL,
                action              VARCHAR(80)  NOT NULL,
                target_tenant_name  VARCHAR(255) DEFAULT NULL,
                details             JSON         DEFAULT NULL,
                ip_address          VARCHAR(45)  DEFAULT NULL,
                created_at          TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_sa_log_admin  ON super_admin_logs (super_admin_id)');
        $this->addSql('CREATE INDEX idx_sa_log_target ON super_admin_logs (target_tenant_id)');
        $this->addSql('CREATE INDEX idx_sa_log_action ON super_admin_logs (action)');
        $this->addSql('COMMENT ON COLUMN super_admin_logs.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN super_admin_logs.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE super_admin_logs ADD CONSTRAINT fk_sal_super_admin    FOREIGN KEY (super_admin_id)   REFERENCES users   (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE super_admin_logs ADD CONSTRAINT fk_sal_target_tenant  FOREIGN KEY (target_tenant_id) REFERENCES tenants (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // Drop dans l'ordre inverse pour respecter les FK
        $this->addSql('DROP TABLE super_admin_logs');
        $this->addSql('DROP TABLE audit_logs');
        $this->addSql('DROP TABLE webhook_deliveries');
        $this->addSql('DROP TABLE webhook_endpoints');
        $this->addSql('DROP TABLE api_keys');
        $this->addSql('DROP TABLE notification_preferences');
        $this->addSql('DROP TABLE notifications');
        $this->addSql('DROP TABLE export_jobs');
        $this->addSql('DROP TABLE tax_adjustments');
        $this->addSql('DROP TABLE relance_emails');
        $this->addSql('ALTER TABLE ereporting_payment_lines DROP CONSTRAINT IF EXISTS fk_erpl_payment');
        $this->addSql('DROP TABLE payments');
        $this->addSql('DROP TABLE ereporting_corrections');
        $this->addSql('DROP TABLE ereporting_payment_lines');
        $this->addSql('DROP TABLE ereporting_transactions');
        $this->addSql('DROP TABLE ereporting_batches');
        $this->addSql('DROP TABLE pdp_webhook_logs');
        $this->addSql('DROP TABLE pdp_transmissions');
        $this->addSql('DROP TABLE received_invoice_lines');
        $this->addSql('DROP TABLE received_invoices');
        $this->addSql('DROP TABLE invoice_status_history');
        $this->addSql('DROP TABLE invoice_lines');
        $this->addSql('DROP TABLE invoices');
        $this->addSql('DROP TABLE invoice_templates');
        $this->addSql('DROP TABLE invoice_sequences');
        $this->addSql('DROP TABLE product_price_history');
        $this->addSql('DROP TABLE products');
        $this->addSql('DROP TABLE contact_documents');
        $this->addSql('DROP TABLE contact_persons');
        $this->addSql('DROP TABLE contacts');
        $this->addSql('DROP TABLE email_verification_tokens');
        $this->addSql('DROP TABLE tenant_invitations');
        $this->addSql('DROP TABLE tenant_memberships');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE tenants');
    }
}
