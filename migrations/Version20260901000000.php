<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration initiale — MySQL 8.0+ / MariaDB 10.6+
 *
 * Différences avec la version PostgreSQL d'origine :
 *  - UUID : CHAR(36) + COMMENT '(DC2Type:uuid)'  (MySQL n'a pas de type UUID natif)
 *  - DATETIME au lieu de TIMESTAMP WITHOUT TIME ZONE
 *  - TINYINT(1) au lieu de BOOLEAN
 *  - JSON natif (MySQL 5.7.8+, équivalent fonctionnel du JSONB PostgreSQL)
 *  - DECIMAL au lieu de NUMERIC (alias identiques en MySQL)
 *  - Suppression des COMMENT ON COLUMN (syntaxe PostgreSQL uniquement)
 *  - ENGINE=InnoDB + CHARSET=utf8mb4 sur chaque table
 *  - DROP TABLE IF EXISTS dans down()
 *  - ALTER TABLE DROP FOREIGN KEY (syntaxe MySQL)
 */
final class Version20260901000000 extends AbstractMigration
{
    /** Options ENGINE/CHARSET communes à toutes les tables. */
    private const O = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC';

    public function getDescription(): string
    {
        return 'Migration initiale MySQL — 34 tables facturation électronique';
    }

    public function up(Schema $schema): void
    {
        $o = self::O;

        // ── 1. TENANTS ─────────────────────────────────────────────────────
        $this->addSql("CREATE TABLE tenants (
            id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            slug VARCHAR(100) NOT NULL, name VARCHAR(255) NOT NULL,
            siret VARCHAR(14) DEFAULT NULL, legal_form VARCHAR(60) DEFAULT NULL,
            tva_intra VARCHAR(20) DEFAULT NULL, vat_exempt TINYINT(1) NOT NULL DEFAULT 0,
            vat_regime VARCHAR(20) NOT NULL DEFAULT 'REEL_NORMAL',
            ape_code VARCHAR(10) DEFAULT NULL, rcs_number VARCHAR(30) DEFAULT NULL,
            share_capital INT DEFAULT NULL,
            addr_line1 VARCHAR(255) DEFAULT NULL, addr_line2 VARCHAR(255) DEFAULT NULL,
            addr_postal_code VARCHAR(20) DEFAULT NULL, addr_city VARCHAR(120) DEFAULT NULL,
            addr_country VARCHAR(2) NOT NULL DEFAULT 'FR',
            billing_email VARCHAR(255) DEFAULT NULL, phone VARCHAR(30) DEFAULT NULL,
            website VARCHAR(255) DEFAULT NULL, iban VARCHAR(34) DEFAULT NULL,
            bic VARCHAR(11) DEFAULT NULL, logo_s3_key VARCHAR(255) DEFAULT NULL,
            brand_color VARCHAR(7) DEFAULT NULL,
            pdp_mode VARCHAR(10) DEFAULT NULL, pdp_pdp_name VARCHAR(120) DEFAULT NULL,
            pdp_endpoint_url VARCHAR(500) DEFAULT NULL,
            pdp_api_key_encrypted TEXT DEFAULT NULL,
            pdp_emitter_id VARCHAR(120) DEFAULT NULL,
            pdp_last_test_status VARCHAR(30) DEFAULT NULL,
            pdp_connected_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            plan VARCHAR(20) NOT NULL DEFAULT 'FREE',
            status VARCHAR(20) NOT NULL DEFAULT 'ONBOARDING',
            onboarding_step VARCHAR(20) NOT NULL DEFAULT 'ORGANISATION',
            onboarding_completed TINYINT(1) NOT NULL DEFAULT 0,
            default_currency VARCHAR(3) NOT NULL DEFAULT 'EUR',
            document_locale VARCHAR(5) NOT NULL DEFAULT 'fr',
            default_invoice_format VARCHAR(20) NOT NULL DEFAULT 'FACTURX',
            default_payment_terms INT NOT NULL DEFAULT 30,
            late_payment_rate DECIMAL(5,2) DEFAULT NULL,
            recovery_fee DECIMAL(8,2) NOT NULL DEFAULT 40.00,
            legal_mentions TEXT DEFAULT NULL, cgv_s3_key VARCHAR(255) DEFAULT NULL,
            assujettissement_date DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            deleted_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            PRIMARY KEY (id)
        ) $o");
        $this->addSql('CREATE UNIQUE INDEX uniq_tenant_slug ON tenants (slug)');
        $this->addSql('CREATE INDEX idx_tenant_siret ON tenants (siret)');
        $this->addSql('CREATE INDEX idx_tenant_status ON tenants (status)');

        // ── 2. USERS ───────────────────────────────────────────────────────
        $this->addSql("CREATE TABLE users (
            id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            email VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL,
            first_name VARCHAR(100) DEFAULT NULL, last_name VARCHAR(100) DEFAULT NULL,
            locale VARCHAR(5) NOT NULL DEFAULT 'fr',
            totp_secret VARCHAR(255) DEFAULT NULL,
            email_verified TINYINT(1) NOT NULL DEFAULT 0,
            super_admin TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            last_login_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            PRIMARY KEY (id)
        ) $o");
        $this->addSql('CREATE UNIQUE INDEX uniq_user_email ON users (email)');

        // ── 3. TENANT_MEMBERSHIPS ──────────────────────────────────────────
        $this->addSql("CREATE TABLE tenant_memberships (
            id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            user_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            tenant_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            role VARCHAR(20) NOT NULL,
            invited_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            joined_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            PRIMARY KEY (id)
        ) $o");
        $this->addSql('CREATE UNIQUE INDEX uniq_user_tenant ON tenant_memberships (user_id, tenant_id)');
        $this->addSql('CREATE INDEX idx_membership_tenant ON tenant_memberships (tenant_id)');
        $this->addSql('CREATE INDEX idx_membership_user ON tenant_memberships (user_id)');
        $this->addSql('ALTER TABLE tenant_memberships ADD CONSTRAINT fk_membership_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tenant_memberships ADD CONSTRAINT fk_membership_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE');

        // ── 4. TENANT_INVITATIONS ──────────────────────────────────────────
        $this->addSql("CREATE TABLE tenant_invitations (
            id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            tenant_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            invited_by_id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)',
            email VARCHAR(255) NOT NULL, role VARCHAR(20) NOT NULL,
            token VARCHAR(128) NOT NULL, message TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            expires_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            accepted_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            PRIMARY KEY (id)
        ) $o");
        $this->addSql('CREATE UNIQUE INDEX uniq_invitation_token ON tenant_invitations (token)');
        $this->addSql('CREATE INDEX idx_invitation_email ON tenant_invitations (email)');
        $this->addSql('ALTER TABLE tenant_invitations ADD CONSTRAINT fk_invitation_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tenant_invitations ADD CONSTRAINT fk_invitation_invited_by FOREIGN KEY (invited_by_id) REFERENCES users (id) ON DELETE SET NULL');

        // ── 5. EMAIL_VERIFICATION_TOKENS ───────────────────────────────────
        $this->addSql("CREATE TABLE email_verification_tokens (
            id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            user_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            token VARCHAR(128) NOT NULL,
            created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            expires_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            used_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            PRIMARY KEY (id)
        ) $o");
        $this->addSql('CREATE UNIQUE INDEX uniq_email_verif_token ON email_verification_tokens (token)');
        $this->addSql('ALTER TABLE email_verification_tokens ADD CONSTRAINT fk_verif_token_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');

        // ── 6. CONTACTS ────────────────────────────────────────────────────
        $this->addSql("CREATE TABLE contacts (
            id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            tenant_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            type VARCHAR(20) NOT NULL, name VARCHAR(255) NOT NULL,
            siret VARCHAR(14) DEFAULT NULL, tva_intra VARCHAR(20) DEFAULT NULL,
            legal_form VARCHAR(60) DEFAULT NULL, ape_code VARCHAR(10) DEFAULT NULL,
            pdp_identifier VARCHAR(120) DEFAULT NULL,
            addr_line1 VARCHAR(255) DEFAULT NULL, addr_line2 VARCHAR(255) DEFAULT NULL,
            addr_postal_code VARCHAR(20) DEFAULT NULL, addr_city VARCHAR(120) DEFAULT NULL,
            addr_country VARCHAR(2) NOT NULL DEFAULT 'FR',
            ship_line1 VARCHAR(255) DEFAULT NULL, ship_line2 VARCHAR(255) DEFAULT NULL,
            ship_postal_code VARCHAR(20) DEFAULT NULL, ship_city VARCHAR(120) DEFAULT NULL,
            ship_country VARCHAR(2) NOT NULL DEFAULT 'FR',
            has_distinct_shipping_address TINYINT(1) NOT NULL DEFAULT 0,
            email VARCHAR(255) DEFAULT NULL, billing_email VARCHAR(255) DEFAULT NULL,
            phone VARCHAR(30) DEFAULT NULL, website VARCHAR(255) DEFAULT NULL,
            payment_terms INT DEFAULT NULL, default_discount DECIMAL(5,2) DEFAULT NULL,
            preferred_currency VARCHAR(3) DEFAULT NULL, document_locale VARCHAR(5) DEFAULT NULL,
            supplier_iban VARCHAR(34) DEFAULT NULL, notes TEXT DEFAULT NULL,
            sirene_status VARCHAR(20) DEFAULT NULL,
            sirene_checked_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            active TINYINT(1) NOT NULL DEFAULT 1,
            archived_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            PRIMARY KEY (id)
        ) $o");
        $this->addSql('CREATE INDEX idx_contact_tenant ON contacts (tenant_id)');
        $this->addSql('CREATE INDEX idx_contact_siret ON contacts (siret)');
        $this->addSql('CREATE INDEX idx_contact_type ON contacts (type)');
        $this->addSql('ALTER TABLE contacts ADD CONSTRAINT fk_contact_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE');

        // ── 7. CONTACT_PERSONS ─────────────────────────────────────────────
        $this->addSql("CREATE TABLE contact_persons (
            id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            contact_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            first_name VARCHAR(100) DEFAULT NULL, last_name VARCHAR(100) DEFAULT NULL,
            role VARCHAR(100) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL,
            phone VARCHAR(30) DEFAULT NULL,
            PRIMARY KEY (id)
        ) $o");
        $this->addSql('CREATE INDEX idx_contact_person_contact ON contact_persons (contact_id)');
        $this->addSql('ALTER TABLE contact_persons ADD CONSTRAINT fk_person_contact FOREIGN KEY (contact_id) REFERENCES contacts (id) ON DELETE CASCADE');

        // ── 8. CONTACT_DOCUMENTS ───────────────────────────────────────────
        $this->addSql("CREATE TABLE contact_documents (
            id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            tenant_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            contact_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            uploaded_by_id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)',
            s3_key VARCHAR(255) NOT NULL, filename VARCHAR(255) NOT NULL,
            mime_type VARCHAR(100) NOT NULL, size INT NOT NULL,
            uploaded_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            PRIMARY KEY (id)
        ) $o");
        $this->addSql('CREATE INDEX idx_contact_doc_contact ON contact_documents (contact_id)');
        $this->addSql('ALTER TABLE contact_documents ADD CONSTRAINT fk_doc_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE contact_documents ADD CONSTRAINT fk_doc_contact FOREIGN KEY (contact_id) REFERENCES contacts (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE contact_documents ADD CONSTRAINT fk_doc_uploaded_by FOREIGN KEY (uploaded_by_id) REFERENCES users (id) ON DELETE SET NULL');

        // ── 9. PRODUCTS ────────────────────────────────────────────────────
        $this->addSql("CREATE TABLE products (
            id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            tenant_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            type VARCHAR(20) NOT NULL DEFAULT 'SERVICE',
            reference VARCHAR(80) NOT NULL, label VARCHAR(150) NOT NULL,
            description TEXT DEFAULT NULL,
            unit_price DECIMAL(14,4) NOT NULL DEFAULT 0,
            unit VARCHAR(20) NOT NULL DEFAULT 'U',
            tva_rate DECIMAL(5,2) NOT NULL DEFAULT 20.00,
            tva_exemption_reason VARCHAR(30) DEFAULT NULL,
            accounting_code VARCHAR(20) DEFAULT NULL,
            supplier_reference VARCHAR(80) DEFAULT NULL,
            min_price DECIMAL(14,4) DEFAULT NULL, notes TEXT DEFAULT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            archived_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            PRIMARY KEY (id)
        ) $o");
        $this->addSql('CREATE UNIQUE INDEX uniq_product_reference_tenant ON products (tenant_id, reference)');
        $this->addSql('CREATE INDEX idx_product_tenant ON products (tenant_id)');
        $this->addSql('CREATE INDEX idx_product_tva ON products (tva_rate)');
        $this->addSql('ALTER TABLE products ADD CONSTRAINT fk_product_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE');

        // ── 10. PRODUCT_PRICE_HISTORY ──────────────────────────────────────
        $this->addSql("CREATE TABLE product_price_history (
            id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            tenant_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            product_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            changed_by_id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)',
            old_price DECIMAL(14,4) NOT NULL, new_price DECIMAL(14,4) NOT NULL,
            changed_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            PRIMARY KEY (id)
        ) $o");
        $this->addSql('CREATE INDEX idx_price_history_product ON product_price_history (product_id)');
        $this->addSql('ALTER TABLE product_price_history ADD CONSTRAINT fk_pph_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_price_history ADD CONSTRAINT fk_pph_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_price_history ADD CONSTRAINT fk_pph_changed_by FOREIGN KEY (changed_by_id) REFERENCES users (id) ON DELETE SET NULL');

        // ── 11. INVOICE_SEQUENCES ──────────────────────────────────────────
        $this->addSql("CREATE TABLE invoice_sequences (
            id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            tenant_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            name VARCHAR(100) NOT NULL DEFAULT 'Séquence principale',
            prefix VARCHAR(20) DEFAULT 'FAC', year_format VARCHAR(4) DEFAULT 'AAAA',
            include_month TINYINT(1) NOT NULL DEFAULT 0,
            separator VARCHAR(1) DEFAULT '-',
            padding INT NOT NULL DEFAULT 4,
            start_number INT NOT NULL DEFAULT 1, next_number INT NOT NULL DEFAULT 1,
            reset_yearly TINYINT(1) NOT NULL DEFAULT 0,
            last_year INT DEFAULT NULL,
            locked TINYINT(1) NOT NULL DEFAULT 0,
            is_credit_note_sequence TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id)
        ) $o");
        $this->addSql('CREATE INDEX idx_sequence_tenant ON invoice_sequences (tenant_id)');
        $this->addSql('ALTER TABLE invoice_sequences ADD CONSTRAINT fk_sequence_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE');

        // ── 12. INVOICE_TEMPLATES ──────────────────────────────────────────
        $this->addSql("CREATE TABLE invoice_templates (
            id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            tenant_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            name VARCHAR(100) NOT NULL, base_key VARCHAR(50) NOT NULL DEFAULT 'classique',
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            is_customized TINYINT(1) NOT NULL DEFAULT 0,
            config JSON NOT NULL, preview_s3_key VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            PRIMARY KEY (id)
        ) $o");
        $this->addSql('CREATE INDEX idx_template_tenant ON invoice_templates (tenant_id)');
        $this->addSql('ALTER TABLE invoice_templates ADD CONSTRAINT fk_template_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE');

        // ── 13. INVOICES ───────────────────────────────────────────────────
        $this->addSql("CREATE TABLE invoices (
            id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            tenant_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            contact_id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)',
            sequence_id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)',
            template_id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)',
            credit_note_for_id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)',
            number VARCHAR(50) DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
            type VARCHAR(20) NOT NULL DEFAULT 'INVOICE',
            format VARCHAR(20) NOT NULL DEFAULT 'FACTURX',
            client_name_snapshot VARCHAR(255) DEFAULT NULL,
            client_siret_snapshot VARCHAR(14) DEFAULT NULL,
            client_pdp_identifier VARCHAR(120) DEFAULT NULL,
            currency VARCHAR(3) NOT NULL DEFAULT 'EUR',
            total_ht DECIMAL(14,2) NOT NULL DEFAULT 0,
            total_tva DECIMAL(14,2) NOT NULL DEFAULT 0,
            total_ttc DECIMAL(14,2) NOT NULL DEFAULT 0,
            amount_paid DECIMAL(14,2) NOT NULL DEFAULT 0,
            issue_date DATE NOT NULL COMMENT '(DC2Type:date_immutable)',
            due_date DATE DEFAULT NULL COMMENT '(DC2Type:date_immutable)',
            client_reference VARCHAR(100) DEFAULT NULL,
            subject TEXT DEFAULT NULL, client_notes TEXT DEFAULT NULL,
            internal_notes TEXT DEFAULT NULL,
            pdf_s3_key VARCHAR(255) DEFAULT NULL, xml_s3_key VARCHAR(255) DEFAULT NULL,
            file_hash VARCHAR(64) DEFAULT NULL,
            validated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            paid_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            deleted_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            version INT NOT NULL DEFAULT 1,
            PRIMARY KEY (id)
        ) $o");
        $this->addSql('CREATE INDEX idx_invoice_tenant_status ON invoices (tenant_id, status)');
        $this->addSql('CREATE INDEX idx_invoice_tenant_issue ON invoices (tenant_id, issue_date)');
        $this->addSql('CREATE INDEX idx_invoice_number ON invoices (number)');
        $this->addSql('CREATE INDEX idx_invoice_contact ON invoices (contact_id)');
        $this->addSql('ALTER TABLE invoices ADD CONSTRAINT fk_invoice_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE invoices ADD CONSTRAINT fk_invoice_contact FOREIGN KEY (contact_id) REFERENCES contacts (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE invoices ADD CONSTRAINT fk_invoice_sequence FOREIGN KEY (sequence_id) REFERENCES invoice_sequences (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE invoices ADD CONSTRAINT fk_invoice_template FOREIGN KEY (template_id) REFERENCES invoice_templates (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE invoices ADD CONSTRAINT fk_invoice_credit_note_for FOREIGN KEY (credit_note_for_id) REFERENCES invoices (id) ON DELETE SET NULL');

        // ── 14. INVOICE_LINES ──────────────────────────────────────────────
        $this->addSql("CREATE TABLE invoice_lines (
            id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            invoice_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            product_id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)',
            position INT NOT NULL DEFAULT 0, is_comment TINYINT(1) NOT NULL DEFAULT 0,
            reference VARCHAR(80) DEFAULT NULL, description TEXT NOT NULL,
            quantity DECIMAL(14,4) NOT NULL DEFAULT 1,
            unit VARCHAR(20) NOT NULL DEFAULT 'U',
            unit_price DECIMAL(14,4) NOT NULL DEFAULT 0,
            discount DECIMAL(5,2) NOT NULL DEFAULT 0,
            tva_rate DECIMAL(5,2) NOT NULL DEFAULT 20.00,
            tva_exemption_reason VARCHAR(30) DEFAULT NULL,
            amount_ht DECIMAL(14,2) NOT NULL DEFAULT 0,
            amount_tva DECIMAL(14,2) NOT NULL DEFAULT 0,
            PRIMARY KEY (id)
        ) $o");
        $this->addSql('CREATE INDEX idx_invoice_line_invoice ON invoice_lines (invoice_id)');
        $this->addSql('CREATE INDEX idx_invoice_line_product ON invoice_lines (product_id)');
        $this->addSql('ALTER TABLE invoice_lines ADD CONSTRAINT fk_line_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE invoice_lines ADD CONSTRAINT fk_line_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL');

        // ── 15. INVOICE_STATUS_HISTORY ─────────────────────────────────────
        $this->addSql("CREATE TABLE invoice_status_history (
            id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            invoice_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            actor_id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)',
            from_status VARCHAR(20) DEFAULT NULL, to_status VARCHAR(20) NOT NULL,
            comment TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            PRIMARY KEY (id)
        ) $o");
        $this->addSql('CREATE INDEX idx_status_history_invoice ON invoice_status_history (invoice_id)');
        $this->addSql('ALTER TABLE invoice_status_history ADD CONSTRAINT fk_ish_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE invoice_status_history ADD CONSTRAINT fk_ish_actor FOREIGN KEY (actor_id) REFERENCES users (id) ON DELETE SET NULL');

        // ── 16. RECEIVED_INVOICES ──────────────────────────────────────────
        $this->addSql("CREATE TABLE received_invoices (
            id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            tenant_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            supplier_contact_id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)',
            status VARCHAR(30) NOT NULL DEFAULT 'PENDING_VALIDATION',
            external_pdp_id VARCHAR(191) DEFAULT NULL,
            invoice_number VARCHAR(80) DEFAULT NULL,
            supplier_name VARCHAR(255) DEFAULT NULL,
            supplier_siret VARCHAR(14) DEFAULT NULL,
            supplier_tva_intra VARCHAR(20) DEFAULT NULL,
            supplier_iban VARCHAR(34) DEFAULT NULL,
            format VARCHAR(20) DEFAULT NULL,
            invoice_date DATE DEFAULT NULL COMMENT '(DC2Type:date_immutable)',
            due_date DATE DEFAULT NULL COMMENT '(DC2Type:date_immutable)',
            currency VARCHAR(3) NOT NULL DEFAULT 'EUR',
            amount_ht DECIMAL(14,2) DEFAULT NULL,
            amount_tva DECIMAL(14,2) DEFAULT NULL,
            amount_ttc DECIMAL(14,2) DEFAULT NULL,
            amount_paid DECIMAL(14,2) NOT NULL DEFAULT 0,
            parsed_data JSON DEFAULT NULL, parse_errors JSON DEFAULT NULL,
            raw_file_s3_key VARCHAR(255) DEFAULT NULL, file_hash VARCHAR(64) DEFAULT NULL,
            received_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            technical_ack_sent_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            contest_reason VARCHAR(50) DEFAULT NULL, contest_description TEXT DEFAULT NULL,
            PRIMARY KEY (id)
        ) $o");
        $this->addSql('CREATE UNIQUE INDEX uniq_received_external_pdp ON received_invoices (tenant_id, external_pdp_id)');
        $this->addSql('CREATE INDEX idx_received_tenant_status ON received_invoices (tenant_id, status)');
        $this->addSql('CREATE INDEX idx_received_supplier ON received_invoices (supplier_contact_id)');
        $this->addSql('ALTER TABLE received_invoices ADD CONSTRAINT fk_ri_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE received_invoices ADD CONSTRAINT fk_ri_supplier_contact FOREIGN KEY (supplier_contact_id) REFERENCES contacts (id) ON DELETE SET NULL');

        // ── 17. RECEIVED_INVOICE_LINES ─────────────────────────────────────
        $this->addSql("CREATE TABLE received_invoice_lines (
            id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            received_invoice_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            description TEXT NOT NULL,
            quantity DECIMAL(14,4) NOT NULL DEFAULT 1,
            unit_price DECIMAL(14,4) NOT NULL DEFAULT 0,
            tva_rate DECIMAL(5,2) NOT NULL DEFAULT 20.00,
            amount_ht DECIMAL(14,2) NOT NULL DEFAULT 0,
            PRIMARY KEY (id)
        ) $o");
        $this->addSql('CREATE INDEX idx_received_line_invoice ON received_invoice_lines (received_invoice_id)');
        $this->addSql('ALTER TABLE received_invoice_lines ADD CONSTRAINT fk_ril_ri FOREIGN KEY (received_invoice_id) REFERENCES received_invoices (id) ON DELETE CASCADE');

        // ── 18. PDP_TRANSMISSIONS ──────────────────────────────────────────
        $this->addSql("CREATE TABLE pdp_transmissions (
            id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            tenant_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            invoice_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
            external_id VARCHAR(191) DEFAULT NULL, pdp_name VARCHAR(120) DEFAULT NULL,
            attempt INT NOT NULL DEFAULT 0,
            sent_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            acknowledged_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            reject_code VARCHAR(100) DEFAULT NULL, reject_reason TEXT DEFAULT NULL,
            raw_response JSON DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            PRIMARY KEY (id)
        ) $o");
        $this->addSql('CREATE INDEX idx_transmission_invoice ON pdp_transmissions (invoice_id)');
        $this->addSql('CREATE INDEX idx_transmission_tenant_status ON pdp_transmissions (tenant_id, status)');
        $this->addSql('CREATE INDEX idx_transmission_external ON pdp_transmissions (external_id)');
        $this->addSql('ALTER TABLE pdp_transmissions ADD CONSTRAINT fk_pdpt_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE pdp_transmissions ADD CONSTRAINT fk_pdpt_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE');

        // ── 19. PDP_WEBHOOK_LOGS ───────────────────────────────────────────
        $this->addSql("CREATE TABLE pdp_webhook_logs (
            id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            tenant_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            event_id VARCHAR(191) NOT NULL, event_type VARCHAR(80) NOT NULL,
            payload JSON NOT NULL, processed TINYINT(1) NOT NULL DEFAULT 0,
            processing_error TEXT DEFAULT NULL, signature_hash VARCHAR(64) DEFAULT NULL,
            received_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            processed_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            PRIMARY KEY (id)
        ) $o");
        $this->addSql('CREATE UNIQUE INDEX uniq_webhook_event ON pdp_webhook_logs (tenant_id, event_id)');
        $this->addSql('CREATE INDEX idx_webhook_log_tenant ON pdp_webhook_logs (tenant_id)');
        $this->addSql('CREATE INDEX idx_webhook_log_type ON pdp_webhook_logs (event_type)');
        $this->addSql('ALTER TABLE pdp_webhook_logs ADD CONSTRAINT fk_webhook_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE');

        // ── 20. EREPORTING_BATCHES ─────────────────────────────────────────
        $this->addSql("CREATE TABLE ereporting_batches (
            id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            tenant_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            period VARCHAR(7) NOT NULL, periodicity VARCHAR(20) NOT NULL DEFAULT 'MONTHLY',
            status VARCHAR(20) NOT NULL DEFAULT 'NOT_STARTED',
            deadline DATE NOT NULL COMMENT '(DC2Type:date_immutable)',
            late TINYINT(1) NOT NULL DEFAULT 0, is_nil TINYINT(1) NOT NULL DEFAULT 0,
            dgfip_reference VARCHAR(191) DEFAULT NULL,
            xml_s3_key VARCHAR(255) DEFAULT NULL, file_hash VARCHAR(64) DEFAULT NULL,
            submitted_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            accepted_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            reject_reason TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            PRIMARY KEY (id)
        ) $o");
        $this->addSql('CREATE UNIQUE INDEX uniq_batch_tenant_period ON ereporting_batches (tenant_id, period)');
        $this->addSql('CREATE INDEX idx_batch_tenant_status ON ereporting_batches (tenant_id, status)');
        $this->addSql('ALTER TABLE ereporting_batches ADD CONSTRAINT fk_erb_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE');

        // ── 21. EREPORTING_TRANSACTIONS ────────────────────────────────────
        $this->addSql("CREATE TABLE ereporting_transactions (
            id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            batch_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            invoice_id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)',
            type VARCHAR(30) NOT NULL,
            transaction_date DATE NOT NULL COMMENT '(DC2Type:date_immutable)',
            amount_ht_by_rate JSON NOT NULL, amount_tva_by_rate JSON NOT NULL,
            total_ht DECIMAL(14,2) NOT NULL DEFAULT 0,
            total_tva DECIMAL(14,2) NOT NULL DEFAULT 0,
            currency VARCHAR(3) NOT NULL DEFAULT 'EUR',
            transaction_count INT NOT NULL DEFAULT 1,
            country_code VARCHAR(2) DEFAULT NULL, source VARCHAR(10) NOT NULL DEFAULT 'AUTO',
            PRIMARY KEY (id)
        ) $o");
        $this->addSql('CREATE INDEX idx_er_transaction_batch ON ereporting_transactions (batch_id)');
        $this->addSql('ALTER TABLE ereporting_transactions ADD CONSTRAINT fk_ert_batch FOREIGN KEY (batch_id) REFERENCES ereporting_batches (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ereporting_transactions ADD CONSTRAINT fk_ert_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE SET NULL');

        // ── 22. EREPORTING_PAYMENT_LINES (FK circulaire résolue après payments)
        $this->addSql("CREATE TABLE ereporting_payment_lines (
            id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            batch_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            payment_id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)',
            payment_date DATE NOT NULL COMMENT '(DC2Type:date_immutable)',
            amount_ttc DECIMAL(14,2) NOT NULL,
            amount_tva DECIMAL(14,2) NOT NULL DEFAULT 0,
            tva_rate DECIMAL(5,2) NOT NULL DEFAULT 20.00,
            source VARCHAR(10) NOT NULL DEFAULT 'AUTO',
            PRIMARY KEY (id)
        ) $o");
        $this->addSql('CREATE INDEX idx_er_payment_batch ON ereporting_payment_lines (batch_id)');
        $this->addSql('CREATE INDEX idx_er_payment_payment ON ereporting_payment_lines (payment_id)');
        $this->addSql('ALTER TABLE ereporting_payment_lines ADD CONSTRAINT fk_erpl_batch FOREIGN KEY (batch_id) REFERENCES ereporting_batches (id) ON DELETE CASCADE');

        // ── 23. EREPORTING_CORRECTIONS ─────────────────────────────────────
        $this->addSql("CREATE TABLE ereporting_corrections (
            id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            batch_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            corrected_by_id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)',
            field VARCHAR(50) NOT NULL,
            old_value TEXT DEFAULT NULL, new_value TEXT DEFAULT NULL,
            reason TEXT NOT NULL,
            created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            PRIMARY KEY (id)
        ) $o");
        $this->addSql('CREATE INDEX idx_er_correction_batch ON ereporting_corrections (batch_id)');
        $this->addSql('ALTER TABLE ereporting_corrections ADD CONSTRAINT fk_erc_batch FOREIGN KEY (batch_id) REFERENCES ereporting_batches (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ereporting_corrections ADD CONSTRAINT fk_erc_corrected_by FOREIGN KEY (corrected_by_id) REFERENCES users (id) ON DELETE SET NULL');

        // ── 24. PAYMENTS ───────────────────────────────────────────────────
        $this->addSql("CREATE TABLE payments (
            id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            tenant_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            invoice_id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)',
            received_invoice_id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)',
            ereporting_batch_id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)',
            recorded_by_id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)',
            direction VARCHAR(20) NOT NULL,
            date DATE NOT NULL COMMENT '(DC2Type:date_immutable)',
            amount DECIMAL(14,2) NOT NULL,
            currency VARCHAR(3) NOT NULL DEFAULT 'EUR',
            amount_eur DECIMAL(14,2) DEFAULT NULL,
            exchange_rate DECIMAL(14,6) DEFAULT NULL,
            mode VARCHAR(20) NOT NULL, mode_dgfip_code VARCHAR(2) NOT NULL,
            reference VARCHAR(191) DEFAULT NULL, notes TEXT DEFAULT NULL,
            idempotency_key VARCHAR(191) DEFAULT NULL,
            ereporting_required TINYINT(1) NOT NULL DEFAULT 0,
            ereporting_reported TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            PRIMARY KEY (id)
        ) $o");
        $this->addSql('CREATE UNIQUE INDEX uniq_payment_idempotency ON payments (idempotency_key)');
        $this->addSql('CREATE INDEX idx_payment_invoice ON payments (invoice_id)');
        $this->addSql('CREATE INDEX idx_payment_received ON payments (received_invoice_id)');
        $this->addSql('CREATE INDEX idx_payment_tenant_date ON payments (tenant_id, date)');
        $this->addSql('CREATE INDEX idx_payment_direction ON payments (direction)');
        $this->addSql('ALTER TABLE payments ADD CONSTRAINT fk_pay_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE payments ADD CONSTRAINT fk_pay_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE payments ADD CONSTRAINT fk_pay_received_invoice FOREIGN KEY (received_invoice_id) REFERENCES received_invoices (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE payments ADD CONSTRAINT fk_pay_er_batch FOREIGN KEY (ereporting_batch_id) REFERENCES ereporting_batches (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE payments ADD CONSTRAINT fk_pay_recorded_by FOREIGN KEY (recorded_by_id) REFERENCES users (id) ON DELETE SET NULL');

        // FK circulaire résolue : ereporting_payment_lines → payments
        $this->addSql('ALTER TABLE ereporting_payment_lines ADD CONSTRAINT fk_erpl_payment FOREIGN KEY (payment_id) REFERENCES payments (id) ON DELETE SET NULL');

        // ── 25. RELANCE_EMAILS ─────────────────────────────────────────────
        $this->addSql("CREATE TABLE relance_emails (
            id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            tenant_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            invoice_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            sent_by_id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)',
            level SMALLINT NOT NULL DEFAULT 1,
            recipient_email VARCHAR(255) NOT NULL, subject VARCHAR(255) NOT NULL,
            body TEXT NOT NULL, includes_late_fees TINYINT(1) NOT NULL DEFAULT 0,
            sent_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            opened_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            PRIMARY KEY (id)
        ) $o");
        $this->addSql('CREATE INDEX idx_relance_invoice ON relance_emails (invoice_id)');
        $this->addSql('CREATE INDEX idx_relance_tenant ON relance_emails (tenant_id)');
        $this->addSql('ALTER TABLE relance_emails ADD CONSTRAINT fk_rel_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE relance_emails ADD CONSTRAINT fk_rel_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE relance_emails ADD CONSTRAINT fk_rel_sent_by FOREIGN KEY (sent_by_id) REFERENCES users (id) ON DELETE SET NULL');

        // ── 26. TAX_ADJUSTMENTS ────────────────────────────────────────────
        $this->addSql("CREATE TABLE tax_adjustments (
            id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            tenant_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            created_by_id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)',
            period VARCHAR(7) NOT NULL, type VARCHAR(12) NOT NULL,
            tva_rate DECIMAL(5,2) DEFAULT NULL,
            amount DECIMAL(14,2) NOT NULL,
            ca3_box VARCHAR(10) DEFAULT NULL, reason TEXT NOT NULL,
            created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            PRIMARY KEY (id)
        ) $o");
        $this->addSql('CREATE INDEX idx_tax_adj_tenant_period ON tax_adjustments (tenant_id, period)');
        $this->addSql('ALTER TABLE tax_adjustments ADD CONSTRAINT fk_ta_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tax_adjustments ADD CONSTRAINT fk_ta_created_by FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE SET NULL');

        // ── 27. EXPORT_JOBS ────────────────────────────────────────────────
        $this->addSql("CREATE TABLE export_jobs (
            id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            tenant_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            generated_by_id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)',
            type VARCHAR(10) NOT NULL, status VARCHAR(15) NOT NULL DEFAULT 'PENDING',
            params JSON NOT NULL,
            s3_key VARCHAR(255) DEFAULT NULL, file_hash VARCHAR(64) DEFAULT NULL,
            file_size INT DEFAULT NULL, row_count INT DEFAULT NULL,
            error_message TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            completed_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            expires_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            PRIMARY KEY (id)
        ) $o");
        $this->addSql('CREATE INDEX idx_export_tenant_status ON export_jobs (tenant_id, status)');
        $this->addSql('ALTER TABLE export_jobs ADD CONSTRAINT fk_ej_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE export_jobs ADD CONSTRAINT fk_ej_generated_by FOREIGN KEY (generated_by_id) REFERENCES users (id) ON DELETE SET NULL');

        // ── 28. NOTIFICATIONS ──────────────────────────────────────────────
        $this->addSql("CREATE TABLE notifications (
            id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            tenant_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            user_id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)',
            type VARCHAR(60) NOT NULL, severity VARCHAR(10) NOT NULL DEFAULT 'INFO',
            title VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL,
            action_url VARCHAR(500) DEFAULT NULL, action_label VARCHAR(100) DEFAULT NULL,
            payload JSON DEFAULT NULL,
            read_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            dismissed_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            PRIMARY KEY (id)
        ) $o");
        $this->addSql('CREATE INDEX idx_notification_tenant_user ON notifications (tenant_id, user_id)');
        $this->addSql('CREATE INDEX idx_notification_read ON notifications (read_at)');
        $this->addSql('CREATE INDEX idx_notification_type ON notifications (type)');
        $this->addSql('ALTER TABLE notifications ADD CONSTRAINT fk_notif_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notifications ADD CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');

        // ── 29. NOTIFICATION_PREFERENCES ──────────────────────────────────
        $this->addSql("CREATE TABLE notification_preferences (
            id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            tenant_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            user_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            notification_type VARCHAR(60) NOT NULL,
            in_app_enabled TINYINT(1) NOT NULL DEFAULT 1,
            email_enabled TINYINT(1) NOT NULL DEFAULT 1,
            email_digest VARCHAR(15) NOT NULL DEFAULT 'IMMEDIATE',
            PRIMARY KEY (id)
        ) $o");
        $this->addSql('CREATE UNIQUE INDEX uniq_pref_user_type ON notification_preferences (user_id, notification_type)');
        $this->addSql('CREATE INDEX idx_pref_user ON notification_preferences (user_id)');
        $this->addSql('ALTER TABLE notification_preferences ADD CONSTRAINT fk_np_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notification_preferences ADD CONSTRAINT fk_np_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');

        // ── 30. API_KEYS ───────────────────────────────────────────────────
        $this->addSql("CREATE TABLE api_keys (
            id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            tenant_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            created_by_id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)',
            name VARCHAR(100) NOT NULL, key_hash VARCHAR(64) NOT NULL,
            key_prefix VARCHAR(20) NOT NULL, environment VARCHAR(15) NOT NULL DEFAULT 'TEST',
            permissions JSON NOT NULL,
            created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            last_used_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            expires_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            revoked_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            PRIMARY KEY (id)
        ) $o");
        $this->addSql('CREATE UNIQUE INDEX uniq_apikey_hash ON api_keys (key_hash)');
        $this->addSql('CREATE INDEX idx_apikey_tenant ON api_keys (tenant_id)');
        $this->addSql('ALTER TABLE api_keys ADD CONSTRAINT fk_ak_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE api_keys ADD CONSTRAINT fk_ak_created_by FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE SET NULL');

        // ── 31. WEBHOOK_ENDPOINTS ──────────────────────────────────────────
        $this->addSql("CREATE TABLE webhook_endpoints (
            id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            tenant_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            url VARCHAR(500) NOT NULL, events JSON NOT NULL,
            secret_hash VARCHAR(64) NOT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1, failure_count INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            last_success_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            PRIMARY KEY (id)
        ) $o");
        $this->addSql('CREATE INDEX idx_webhook_endpoint_tenant ON webhook_endpoints (tenant_id)');
        $this->addSql('ALTER TABLE webhook_endpoints ADD CONSTRAINT fk_we_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE');

        // ── 32. WEBHOOK_DELIVERIES ─────────────────────────────────────────
        $this->addSql("CREATE TABLE webhook_deliveries (
            id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            endpoint_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            event_type VARCHAR(80) NOT NULL, payload JSON NOT NULL,
            status VARCHAR(15) NOT NULL DEFAULT 'PENDING',
            http_status INT DEFAULT NULL, attempts INT NOT NULL DEFAULT 0,
            response_body TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            delivered_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            next_retry_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            PRIMARY KEY (id)
        ) $o");
        $this->addSql('CREATE INDEX idx_delivery_endpoint ON webhook_deliveries (endpoint_id)');
        $this->addSql('CREATE INDEX idx_delivery_status ON webhook_deliveries (status)');
        $this->addSql('ALTER TABLE webhook_deliveries ADD CONSTRAINT fk_wd_endpoint FOREIGN KEY (endpoint_id) REFERENCES webhook_endpoints (id) ON DELETE CASCADE');

        // ── 33. AUDIT_LOGS (INSERT only — rétention 10 ans) ───────────────
        $this->addSql("CREATE TABLE audit_logs (
            id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            tenant_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            user_id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)',
            action VARCHAR(80) NOT NULL,
            entity_type VARCHAR(80) DEFAULT NULL, entity_id VARCHAR(36) DEFAULT NULL,
            payload_before JSON DEFAULT NULL, payload_after JSON DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL, user_agent VARCHAR(255) DEFAULT NULL,
            is_impersonated TINYINT(1) NOT NULL DEFAULT 0,
            impersonated_by VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            PRIMARY KEY (id)
        ) $o");
        $this->addSql('CREATE INDEX idx_audit_tenant_created ON audit_logs (tenant_id, created_at)');
        $this->addSql('CREATE INDEX idx_audit_entity ON audit_logs (entity_type, entity_id)');
        $this->addSql('CREATE INDEX idx_audit_action ON audit_logs (action)');
        $this->addSql('ALTER TABLE audit_logs ADD CONSTRAINT fk_al_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE audit_logs ADD CONSTRAINT fk_al_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL');

        // ── 34. SUPER_ADMIN_LOGS (cross-tenant, hors TenantFilter) ────────
        $this->addSql("CREATE TABLE super_admin_logs (
            id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            super_admin_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            target_tenant_id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)',
            action VARCHAR(80) NOT NULL,
            target_tenant_name VARCHAR(255) DEFAULT NULL,
            details JSON DEFAULT NULL, ip_address VARCHAR(45) DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            PRIMARY KEY (id)
        ) $o");
        $this->addSql('CREATE INDEX idx_sa_log_admin ON super_admin_logs (super_admin_id)');
        $this->addSql('CREATE INDEX idx_sa_log_target ON super_admin_logs (target_tenant_id)');
        $this->addSql('CREATE INDEX idx_sa_log_action ON super_admin_logs (action)');
        $this->addSql('ALTER TABLE super_admin_logs ADD CONSTRAINT fk_sal_super_admin FOREIGN KEY (super_admin_id) REFERENCES users (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE super_admin_logs ADD CONSTRAINT fk_sal_target_tenant FOREIGN KEY (target_tenant_id) REFERENCES tenants (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS super_admin_logs');
        $this->addSql('DROP TABLE IF EXISTS audit_logs');
        $this->addSql('DROP TABLE IF EXISTS webhook_deliveries');
        $this->addSql('DROP TABLE IF EXISTS webhook_endpoints');
        $this->addSql('DROP TABLE IF EXISTS api_keys');
        $this->addSql('DROP TABLE IF EXISTS notification_preferences');
        $this->addSql('DROP TABLE IF EXISTS notifications');
        $this->addSql('DROP TABLE IF EXISTS export_jobs');
        $this->addSql('DROP TABLE IF EXISTS tax_adjustments');
        $this->addSql('DROP TABLE IF EXISTS relance_emails');
        $this->addSql('ALTER TABLE ereporting_payment_lines DROP FOREIGN KEY fk_erpl_payment');
        $this->addSql('DROP TABLE IF EXISTS payments');
        $this->addSql('DROP TABLE IF EXISTS ereporting_corrections');
        $this->addSql('DROP TABLE IF EXISTS ereporting_payment_lines');
        $this->addSql('DROP TABLE IF EXISTS ereporting_transactions');
        $this->addSql('DROP TABLE IF EXISTS ereporting_batches');
        $this->addSql('DROP TABLE IF EXISTS pdp_webhook_logs');
        $this->addSql('DROP TABLE IF EXISTS pdp_transmissions');
        $this->addSql('DROP TABLE IF EXISTS received_invoice_lines');
        $this->addSql('DROP TABLE IF EXISTS received_invoices');
        $this->addSql('DROP TABLE IF EXISTS invoice_status_history');
        $this->addSql('DROP TABLE IF EXISTS invoice_lines');
        $this->addSql('DROP TABLE IF EXISTS invoices');
        $this->addSql('DROP TABLE IF EXISTS invoice_templates');
        $this->addSql('DROP TABLE IF EXISTS invoice_sequences');
        $this->addSql('DROP TABLE IF EXISTS product_price_history');
        $this->addSql('DROP TABLE IF EXISTS products');
        $this->addSql('DROP TABLE IF EXISTS contact_documents');
        $this->addSql('DROP TABLE IF EXISTS contact_persons');
        $this->addSql('DROP TABLE IF EXISTS contacts');
        $this->addSql('DROP TABLE IF EXISTS email_verification_tokens');
        $this->addSql('DROP TABLE IF EXISTS tenant_invitations');
        $this->addSql('DROP TABLE IF EXISTS tenant_memberships');
        $this->addSql('DROP TABLE IF EXISTS users');
        $this->addSql('DROP TABLE IF EXISTS tenants');
    }
}
