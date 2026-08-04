CREATE TABLE IF NOT EXISTS bdc_users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    role ENUM('super_admin','admin','organiser','competitor') NOT NULL DEFAULT 'competitor',
    status ENUM('active','pending','suspended') NOT NULL DEFAULT 'active',
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bdc_competitors (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    exact_name VARCHAR(190) NOT NULL,
    normalised_name VARCHAR(190) NOT NULL,
    email VARCHAR(190) NULL,
    country VARCHAR(100) NULL,
    dance_role ENUM('leader','follower','both','unknown') NOT NULL DEFAULT 'unknown',
    status ENUM('active','pending','archived') NOT NULL DEFAULT 'active',
    is_historical TINYINT(1) NOT NULL DEFAULT 0,
    import_batch_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_competitor_name (normalised_name),
    CONSTRAINT fk_competitor_user FOREIGN KEY (user_id) REFERENCES bdc_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bdc_claims (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    competitor_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    evidence_text TEXT NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_claim_competitor FOREIGN KEY (competitor_id) REFERENCES bdc_competitors(id) ON DELETE CASCADE,
    CONSTRAINT fk_claim_user FOREIGN KEY (user_id) REFERENCES bdc_users(id) ON DELETE CASCADE,
    CONSTRAINT fk_claim_reviewer FOREIGN KEY (reviewed_by) REFERENCES bdc_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bdc_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(190) NOT NULL,
    normalised_name VARCHAR(190) NULL,
    slug VARCHAR(190) NOT NULL UNIQUE,
    event_date DATE NULL,
    location VARCHAR(190) NULL,
    organiser_name VARCHAR(190) NULL,
    organiser_email VARCHAR(190) NULL,
    website_url VARCHAR(500) NULL,
    status ENUM('draft','published','completed','cancelled') NOT NULL DEFAULT 'draft',
    points_tier ENUM('1','2','3') NULL,
    import_batch_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_events_normalised_date (normalised_name, event_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bdc_point_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    competitor_id BIGINT UNSIGNED NOT NULL,
    event_id BIGINT UNSIGNED NULL,
    division ENUM('novice','intermediate','advanced','all_star','unknown') NOT NULL DEFAULT 'unknown',
    dance_role ENUM('leader','follower','both','unknown') NOT NULL DEFAULT 'unknown',
    points DECIMAL(8,2) NOT NULL DEFAULT 0,
    placement VARCHAR(50) NULL,
    notes TEXT NULL,
    source_type ENUM('manual','csv_import','correction') NOT NULL DEFAULT 'manual',
    import_batch_id BIGINT UNSIGNED NULL,
    source_row_hash CHAR(64) NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_points_competitor FOREIGN KEY (competitor_id) REFERENCES bdc_competitors(id) ON DELETE CASCADE,
    CONSTRAINT fk_points_event FOREIGN KEY (event_id) REFERENCES bdc_events(id) ON DELETE SET NULL,
    CONSTRAINT fk_points_user FOREIGN KEY (created_by) REFERENCES bdc_users(id) ON DELETE SET NULL,
    UNIQUE INDEX uq_points_source_hash (source_row_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bdc_import_batches (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_name VARCHAR(255) NOT NULL,
    import_type ENUM('points','events','results','competitors','other') NOT NULL,
    status ENUM('pending','processing','completed','failed','completed_with_errors') NOT NULL DEFAULT 'pending',
    total_rows INT UNSIGNED NOT NULL DEFAULT 0,
    imported_rows INT UNSIGNED NOT NULL DEFAULT 0,
    skipped_rows INT UNSIGNED NOT NULL DEFAULT 0,
    error_rows INT UNSIGNED NOT NULL DEFAULT 0,
    summary_json LONGTEXT NULL,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    rolled_back_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_import_user FOREIGN KEY (created_by) REFERENCES bdc_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bdc_import_errors (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id BIGINT UNSIGNED NOT NULL,
    row_number INT UNSIGNED NULL,
    raw_data_json LONGTEXT NULL,
    error_message TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_import_error_batch FOREIGN KEY (batch_id) REFERENCES bdc_import_batches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bdc_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT UNSIGNED NULL,
    title VARCHAR(190) NOT NULL,
    document_type ENUM('heats','finals','points','rules','other') NOT NULL DEFAULT 'other',
    url VARCHAR(500) NOT NULL,
    import_batch_id BIGINT UNSIGNED NULL,
    source_row_hash CHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_document_event FOREIGN KEY (event_id) REFERENCES bdc_events(id) ON DELETE SET NULL,
    UNIQUE INDEX uq_documents_source_hash (source_row_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS bdc_result_documents (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,event_id BIGINT UNSIGNED NULL,title VARCHAR(190) NOT NULL,document_category ENUM('heats','finals','points','full_results','other') NOT NULL DEFAULT 'other',file_type ENUM('pdf','csv','world_result','external') NOT NULL DEFAULT 'external',url VARCHAR(1000) NOT NULL,storage_path VARCHAR(500) NULL,version_number INT UNSIGNED NOT NULL DEFAULT 1,status ENUM('draft','published','archived') NOT NULL DEFAULT 'published',source ENUM('historical_import','manual_upload','scoring_engine') NOT NULL DEFAULT 'manual_upload',import_batch_id BIGINT UNSIGNED NULL,source_row_hash CHAR(64) NULL,created_by BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,CONSTRAINT fk_result_document_event FOREIGN KEY(event_id) REFERENCES bdc_events(id) ON DELETE SET NULL,CONSTRAINT fk_result_document_user FOREIGN KEY(created_by) REFERENCES bdc_users(id) ON DELETE SET NULL,UNIQUE INDEX uq_result_documents_source_hash(source_row_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bdc_participant_results (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,event_id BIGINT UNSIGNED NOT NULL,competitor_id BIGINT UNSIGNED NOT NULL,division ENUM('novice','intermediate','advanced','all_star','unknown') NOT NULL DEFAULT 'unknown',dance_role ENUM('leader','follower','both','unknown') NOT NULL DEFAULT 'unknown',placement VARCHAR(50) NULL,finalist_status ENUM('participant','finalist','placed','winner') NOT NULL DEFAULT 'participant',partner_name VARCHAR(190) NULL,points_awarded DECIMAL(8,2) NOT NULL DEFAULT 0,source ENUM('historical_import','manual','scoring_engine') NOT NULL DEFAULT 'manual',point_transaction_id BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,CONSTRAINT fk_participant_result_event FOREIGN KEY(event_id) REFERENCES bdc_events(id) ON DELETE CASCADE,CONSTRAINT fk_participant_result_competitor FOREIGN KEY(competitor_id) REFERENCES bdc_competitors(id) ON DELETE CASCADE,CONSTRAINT fk_participant_result_transaction FOREIGN KEY(point_transaction_id) REFERENCES bdc_point_transactions(id) ON DELETE SET NULL,UNIQUE INDEX uq_participant_result_transaction(point_transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS bdc_settings (
    setting_key VARCHAR(190) PRIMARY KEY,
    setting_value LONGTEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bdc_audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) NULL,
    entity_id BIGINT UNSIGNED NULL,
    details_json LONGTEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES bdc_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO bdc_settings (setting_key, setting_value) VALUES
('app_version', '2.2.0'),
('novice_max_points', '25'),
('intermediate_min_novice_points', '20'),
('intermediate_max_points', '30'),
('advanced_min_intermediate_points', '25'),
('advanced_max_points', '40')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- v0.8.0 Event Registration Module
ALTER TABLE bdc_events
  ADD COLUMN IF NOT EXISTS description LONGTEXT NULL,
  ADD COLUMN IF NOT EXISTS venue VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS starts_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS ends_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS registration_opens_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS registration_closes_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS payment_instructions LONGTEXT NULL,
  ADD COLUMN IF NOT EXISTS terms_conditions LONGTEXT NULL,
  ADD COLUMN IF NOT EXISTS banner_url VARCHAR(1000) NULL,
  ADD COLUMN IF NOT EXISTS event_mode ENUM('bdc','independent') NOT NULL DEFAULT 'bdc';

CREATE TABLE IF NOT EXISTS bdc_event_ticket_types (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,event_id BIGINT UNSIGNED NOT NULL,name VARCHAR(190) NOT NULL,description TEXT NULL,price DECIMAL(10,2) NOT NULL DEFAULT 0,currency CHAR(3) NOT NULL DEFAULT 'SGD',capacity INT UNSIGNED NULL,sold_count INT UNSIGNED NOT NULL DEFAULT 0,includes_workshop TINYINT(1) NOT NULL DEFAULT 0,includes_social TINYINT(1) NOT NULL DEFAULT 0,includes_jack_jill TINYINT(1) NOT NULL DEFAULT 0,sales_start_at DATETIME NULL,sales_end_at DATETIME NULL,status ENUM('active','inactive') NOT NULL DEFAULT 'active',sort_order INT NOT NULL DEFAULT 0,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,CONSTRAINT fk_ticket_event FOREIGN KEY(event_id) REFERENCES bdc_events(id) ON DELETE CASCADE,INDEX idx_ticket_event(event_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bdc_event_registrations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,event_id BIGINT UNSIGNED NOT NULL,ticket_type_id BIGINT UNSIGNED NOT NULL,competitor_id BIGINT UNSIGNED NULL,registration_number VARCHAR(50) NOT NULL UNIQUE,full_name VARCHAR(190) NOT NULL,email VARCHAR(190) NOT NULL,phone VARCHAR(80) NOT NULL,instagram VARCHAR(190) NULL,country VARCHAR(120) NULL,dance_role ENUM('leader','follower','both','unknown') NOT NULL DEFAULT 'unknown',dance_category VARCHAR(100) NULL,includes_jack_jill TINYINT(1) NOT NULL DEFAULT 0,registration_status ENUM('pending_verification','approved','rejected','more_information','cancelled','checked_in','no_show') NOT NULL DEFAULT 'pending_verification',payment_status ENUM('pending','verified','rejected','cash_at_venue','refunded') NOT NULL DEFAULT 'pending',terms_accepted_at DATETIME NOT NULL,admin_notes TEXT NULL,submitted_ip VARCHAR(45) NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,CONSTRAINT fk_registration_event FOREIGN KEY(event_id) REFERENCES bdc_events(id) ON DELETE CASCADE,CONSTRAINT fk_registration_ticket FOREIGN KEY(ticket_type_id) REFERENCES bdc_event_ticket_types(id) ON DELETE RESTRICT,CONSTRAINT fk_registration_competitor FOREIGN KEY(competitor_id) REFERENCES bdc_competitors(id) ON DELETE SET NULL,INDEX idx_registration_event_status(event_id,registration_status),INDEX idx_registration_email(email),INDEX idx_registration_payment(payment_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bdc_registration_payments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,registration_id BIGINT UNSIGNED NOT NULL,payment_method ENUM('paynow','bank_transfer','cash','other') NOT NULL,expected_amount DECIMAL(10,2) NOT NULL DEFAULT 0,paid_amount DECIMAL(10,2) NULL,currency CHAR(3) NOT NULL DEFAULT 'SGD',payment_reference VARCHAR(190) NULL,verification_status ENUM('pending','verified','rejected','cash_at_venue','refunded') NOT NULL DEFAULT 'pending',verified_by BIGINT UNSIGNED NULL,verified_at DATETIME NULL,admin_notes TEXT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,CONSTRAINT fk_payment_registration FOREIGN KEY(registration_id) REFERENCES bdc_event_registrations(id) ON DELETE CASCADE,CONSTRAINT fk_payment_verifier FOREIGN KEY(verified_by) REFERENCES bdc_users(id) ON DELETE SET NULL,UNIQUE INDEX uq_payment_registration(registration_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bdc_registration_uploads (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,registration_id BIGINT UNSIGNED NOT NULL,file_type ENUM('payment_receipt','other') NOT NULL DEFAULT 'payment_receipt',original_filename VARCHAR(255) NOT NULL,stored_filename VARCHAR(255) NOT NULL,storage_path VARCHAR(500) NOT NULL,mime_type VARCHAR(120) NOT NULL,file_size BIGINT UNSIGNED NOT NULL,uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,CONSTRAINT fk_upload_registration FOREIGN KEY(registration_id) REFERENCES bdc_event_registrations(id) ON DELETE CASCADE,INDEX idx_upload_registration(registration_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bdc_registration_history (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,registration_id BIGINT UNSIGNED NOT NULL,old_registration_status VARCHAR(50) NULL,new_registration_status VARCHAR(50) NULL,old_payment_status VARCHAR(50) NULL,new_payment_status VARCHAR(50) NULL,notes TEXT NULL,changed_by BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,CONSTRAINT fk_history_registration FOREIGN KEY(registration_id) REFERENCES bdc_event_registrations(id) ON DELETE CASCADE,CONSTRAINT fk_history_user FOREIGN KEY(changed_by) REFERENCES bdc_users(id) ON DELETE SET NULL,INDEX idx_history_registration(registration_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
