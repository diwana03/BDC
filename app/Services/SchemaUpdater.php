<?php
declare(strict_types=1);
namespace App\Services;
use PDO;

final class SchemaUpdater
{
 public static function run(PDO $pdo): void
 {
  // Base scoring tables must exist before incremental scoring upgrades run.
  $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_scoring_rounds(
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,event_id BIGINT UNSIGNED NOT NULL,
   parent_round_id BIGINT UNSIGNED NULL,source_round_id BIGINT UNSIGNED NULL,
   round_type ENUM('heats','semifinal','final') NOT NULL,scheduled_at DATETIME NULL,division ENUM('novice','intermediate','advanced','all_star') NOT NULL,
   yes_count INT UNSIGNED NOT NULL DEFAULT 10,callback_count INT UNSIGNED NOT NULL DEFAULT 10,
   tier_manual_override TINYINT(1) NOT NULL DEFAULT 0,yes_weight DECIMAL(5,2) NOT NULL DEFAULT 10.00,
   alt1_weight DECIMAL(5,2) NOT NULL DEFAULT 4.50,alt2_weight DECIMAL(5,2) NOT NULL DEFAULT 4.30,alt3_weight DECIMAL(5,2) NOT NULL DEFAULT 4.20,
   chief_judge_id BIGINT UNSIGNED NULL,status VARCHAR(40) NOT NULL DEFAULT 'draft',generated_version INT UNSIGNED NOT NULL DEFAULT 0,
   last_calculation_ms INT UNSIGNED NULL,last_calculation_memory_bytes BIGINT UNSIGNED NULL,published_document_id BIGINT UNSIGNED NULL,
   publication_id BIGINT UNSIGNED NULL,locked_at DATETIME NULL,locked_by BIGINT UNSIGNED NULL,
   witness_1 VARCHAR(190) NULL,witness_2 VARCHAR(190) NULL,witness_3 VARCHAR(190) NULL,scoring_administrator VARCHAR(190) NULL,
   created_by BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
   INDEX idx_scoring_event(event_id,division,round_type),INDEX idx_scoring_parent(parent_round_id),INDEX idx_scoring_publication(publication_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_scoring_entries(
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,round_id BIGINT UNSIGNED NOT NULL,competitor_id BIGINT UNSIGNED NOT NULL,
   dance_role ENUM('leader','follower') NOT NULL,bib_number INT UNSIGNED NULL,display_name VARCHAR(190) NOT NULL,
   entry_status ENUM('active','withdrawn') NOT NULL DEFAULT 'active',desk_checked_in TINYINT(1) NOT NULL DEFAULT 0,
   desk_ready TINYINT(1) NOT NULL DEFAULT 0,desk_updated_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
   UNIQUE INDEX uq_scoring_entry(round_id,competitor_id,dance_role),INDEX idx_scoring_entry_bib(round_id,dance_role,bib_number),INDEX idx_scoring_entry_status(round_id,entry_status)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_scoring_judges(
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,round_id BIGINT UNSIGNED NOT NULL,judge_name VARCHAR(190) NOT NULL,
   judge_order INT UNSIGNED NOT NULL,is_chief TINYINT(1) NOT NULL DEFAULT 0,scoring_scope ENUM('all','leader','follower') NOT NULL DEFAULT 'all',
   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
   UNIQUE INDEX uq_scoring_judge_order(round_id,judge_order),INDEX idx_scoring_judges_round(round_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_scoring_marks(
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,round_id BIGINT UNSIGNED NOT NULL,entry_id BIGINT UNSIGNED NOT NULL,judge_id BIGINT UNSIGNED NOT NULL,
   mark_type ENUM('yes','alt','blank') NOT NULL DEFAULT 'blank',alt_rank TINYINT UNSIGNED NULL,weighted_score DECIMAL(6,2) NOT NULL DEFAULT 0,
   updated_by BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
   UNIQUE INDEX uq_scoring_mark(round_id,entry_id,judge_id),INDEX idx_scoring_marks_round(round_id),INDEX idx_scoring_marks_judge(judge_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_scoring_results(
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,round_id BIGINT UNSIGNED NOT NULL,entry_id BIGINT UNSIGNED NOT NULL,
   total_score DECIMAL(10,2) NOT NULL DEFAULT 0,chief_score DECIMAL(10,2) NOT NULL DEFAULT 0,rank_number INT UNSIGNED NULL,
   result_status VARCHAR(40) NOT NULL DEFAULT 'pending',alternate_rank INT UNSIGNED NULL,generated_version INT UNSIGNED NOT NULL DEFAULT 0,
   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
   UNIQUE INDEX uq_scoring_result(round_id,entry_id),INDEX idx_scoring_result_rank(round_id,rank_number)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_scoring_audit(
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,round_id BIGINT UNSIGNED NOT NULL,user_id BIGINT UNSIGNED NULL,action VARCHAR(100) NOT NULL,
   details_json LONGTEXT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_scoring_audit(round_id,created_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  self::addColumn($pdo,'bdc_events','normalised_name','VARCHAR(190) NULL AFTER name');
  self::addColumn($pdo,'bdc_events','organiser_email','VARCHAR(190) NULL AFTER organiser_name');
  self::addColumn($pdo,'bdc_events','website_url','VARCHAR(500) NULL AFTER organiser_email');
  self::addColumn($pdo,'bdc_events','import_batch_id','BIGINT UNSIGNED NULL AFTER status');
  self::addColumn($pdo,'bdc_events','points_tier',"ENUM('1','2','3') NULL AFTER event_mode");
  self::addColumn($pdo,'bdc_competitors','import_batch_id','BIGINT UNSIGNED NULL AFTER is_historical');
  self::addColumn($pdo,'bdc_competitors','photo_url','VARCHAR(1000) NULL AFTER country');
  self::addColumn($pdo,'bdc_competitors','countries_json','TEXT NULL AFTER country');
  self::addColumn($pdo,'bdc_test_competitors','countries_json','TEXT NULL AFTER country');
  self::addColumn($pdo,'bdc_competitors','bdc_id','VARCHAR(20) NULL AFTER id');
  self::addColumn($pdo,'bdc_competitors','current_division',"ENUM('novice','intermediate','advanced','all_star','professional','unknown') NOT NULL DEFAULT 'unknown' AFTER dance_role");
  self::addColumn($pdo,'bdc_competitors','instagram','VARCHAR(190) NULL AFTER email');
  self::addColumn($pdo,'bdc_competitors','phone','VARCHAR(80) NULL AFTER instagram');
  self::addColumn($pdo,'bdc_competitors','admin_notes','TEXT NULL AFTER status');
  self::addColumn($pdo,'bdc_competitors','show_on_leaderboard','TINYINT(1) NOT NULL DEFAULT 1 AFTER admin_notes');
  self::addColumn($pdo,'bdc_competitors','novice_manual_out','TINYINT(1) NOT NULL DEFAULT 0 AFTER show_on_leaderboard');
  self::addColumn($pdo,'bdc_competitors','intermediate_manual_out','TINYINT(1) NOT NULL DEFAULT 0 AFTER novice_manual_out');
  self::addColumn($pdo,'bdc_competitors','division_override_reason','VARCHAR(500) NULL AFTER intermediate_manual_out');
  self::addColumn($pdo,'bdc_competitors','career_group_id','BIGINT UNSIGNED NULL AFTER division_override_reason');
  self::addColumn($pdo,'bdc_scoring_judges','scoring_scope',"ENUM('all','leader','follower') NOT NULL DEFAULT 'all' AFTER is_chief");
  self::addColumn($pdo,'bdc_scoring_rounds','tier_manual_override',"TINYINT(1) NOT NULL DEFAULT 0 AFTER callback_count");
  self::addColumn($pdo,'bdc_scoring_rounds','last_calculation_ms',"INT UNSIGNED NULL AFTER generated_version");
  self::addColumn($pdo,'bdc_scoring_rounds','last_calculation_memory_bytes',"BIGINT UNSIGNED NULL AFTER last_calculation_ms");
  self::addColumn($pdo,'bdc_test_scoring_rounds','tier_manual_override',"TINYINT(1) NOT NULL DEFAULT 0 AFTER callback_count");
  self::addColumn($pdo,'bdc_test_scoring_rounds','last_calculation_ms',"INT UNSIGNED NULL AFTER generated_version");
  self::addColumn($pdo,'bdc_test_scoring_rounds','last_calculation_memory_bytes',"BIGINT UNSIGNED NULL AFTER last_calculation_ms");
  self::addColumn($pdo,'bdc_point_transactions','import_batch_id','BIGINT UNSIGNED NULL AFTER source_type');
  self::addColumn($pdo,'bdc_point_transactions','source_row_hash','CHAR(64) NULL AFTER import_batch_id');
  self::addColumn($pdo,'bdc_import_batches','summary_json','LONGTEXT NULL AFTER error_rows');
  self::addColumn($pdo,'bdc_import_batches','rolled_back_at','DATETIME NULL AFTER completed_at');
  self::addIndex($pdo,'bdc_competitors','uq_competitors_bdc_id','(bdc_id)',true);
  self::addIndex($pdo,'bdc_events','idx_events_normalised_date','(normalised_name,event_date)');
  self::addIndex($pdo,'bdc_point_transactions','uq_points_source_hash','(source_row_hash)',true);
  self::addIndex($pdo,'bdc_point_transactions','idx_points_live_leaderboard','(division,dance_role,competitor_id,points)');
  self::addIndex($pdo,'bdc_competitors','idx_competitors_leaderboard_visibility','(status,show_on_leaderboard,career_group_id)');

  $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_competitor_career_groups(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,display_name VARCHAR(190) NOT NULL,created_by BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX idx_career_group_name(display_name)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  self::addIndex($pdo,'bdc_competitors','idx_competitors_career_group','(career_group_id)');

  $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_user_permissions(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,permission_key VARCHAR(100) NOT NULL,allowed TINYINT(1) NOT NULL DEFAULT 1,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE INDEX uq_user_permission(user_id,permission_key),CONSTRAINT fk_permission_user FOREIGN KEY(user_id) REFERENCES bdc_users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_point_adjustment_requests(
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   competitor_id BIGINT UNSIGNED NOT NULL,event_id BIGINT UNSIGNED NOT NULL,
   division ENUM('novice','intermediate','advanced','all_star','unknown') NOT NULL,
   dance_role ENUM('leader','follower','both','unknown') NOT NULL,
   existing_event_points DECIMAL(8,2) NOT NULL DEFAULT 0,
   additional_points DECIMAL(8,2) NOT NULL,reason TEXT NOT NULL,
   status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
   requested_by BIGINT UNSIGNED NOT NULL,requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   reviewed_by BIGINT UNSIGNED NULL,reviewed_at DATETIME NULL,review_reason TEXT NULL,
   point_transaction_id BIGINT UNSIGNED NULL,request_hash CHAR(64) NOT NULL,
   INDEX idx_adjustment_status(status,requested_at),INDEX idx_adjustment_competitor_event(competitor_id,event_id),
   UNIQUE INDEX uq_adjustment_request_hash(request_hash),
   CONSTRAINT fk_adjustment_competitor FOREIGN KEY(competitor_id) REFERENCES bdc_competitors(id) ON DELETE RESTRICT,
   CONSTRAINT fk_adjustment_event FOREIGN KEY(event_id) REFERENCES bdc_events(id) ON DELETE RESTRICT,
   CONSTRAINT fk_adjustment_requester FOREIGN KEY(requested_by) REFERENCES bdc_users(id) ON DELETE RESTRICT,
   CONSTRAINT fk_adjustment_reviewer FOREIGN KEY(reviewed_by) REFERENCES bdc_users(id) ON DELETE SET NULL,
   CONSTRAINT fk_adjustment_transaction FOREIGN KEY(point_transaction_id) REFERENCES bdc_point_transactions(id) ON DELETE SET NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_result_documents(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,event_id BIGINT UNSIGNED NULL,title VARCHAR(190) NOT NULL,document_category ENUM('heats','finals','points','full_results','other') NOT NULL DEFAULT 'other',file_type ENUM('pdf','csv','world_result','external') NOT NULL DEFAULT 'external',url VARCHAR(1000) NOT NULL,storage_path VARCHAR(500) NULL,version_number INT UNSIGNED NOT NULL DEFAULT 1,status ENUM('draft','published','archived') NOT NULL DEFAULT 'published',source ENUM('historical_import','manual_upload','scoring_engine') NOT NULL DEFAULT 'manual_upload',import_batch_id BIGINT UNSIGNED NULL,source_row_hash CHAR(64) NULL,created_by BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,CONSTRAINT fk_result_document_event FOREIGN KEY(event_id) REFERENCES bdc_events(id) ON DELETE SET NULL,CONSTRAINT fk_result_document_user FOREIGN KEY(created_by) REFERENCES bdc_users(id) ON DELETE SET NULL,UNIQUE INDEX uq_result_documents_source_hash(source_row_hash),INDEX idx_result_documents_event(event_id),INDEX idx_result_documents_status(status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_event_points_tiers(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,event_id BIGINT UNSIGNED NOT NULL,division ENUM('novice','intermediate','advanced','all_star','unknown') NOT NULL,dance_role ENUM('leader','follower','both','unknown') NOT NULL,points_tier ENUM('1','2','3') NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE INDEX uq_event_division_role(event_id,division,dance_role),INDEX idx_event_points_tiers_event(event_id),CONSTRAINT fk_event_points_tiers_event FOREIGN KEY(event_id) REFERENCES bdc_events(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_participant_results(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,event_id BIGINT UNSIGNED NOT NULL,competitor_id BIGINT UNSIGNED NOT NULL,division ENUM('novice','intermediate','advanced','all_star','unknown') NOT NULL DEFAULT 'unknown',dance_role ENUM('leader','follower','both','unknown') NOT NULL DEFAULT 'unknown',placement VARCHAR(50) NULL,finalist_status ENUM('participant','finalist','placed','winner') NOT NULL DEFAULT 'participant',partner_name VARCHAR(190) NULL,points_awarded DECIMAL(8,2) NOT NULL DEFAULT 0,source ENUM('historical_import','manual','scoring_engine') NOT NULL DEFAULT 'manual',point_transaction_id BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,CONSTRAINT fk_participant_result_event FOREIGN KEY(event_id) REFERENCES bdc_events(id) ON DELETE CASCADE,CONSTRAINT fk_participant_result_competitor FOREIGN KEY(competitor_id) REFERENCES bdc_competitors(id) ON DELETE CASCADE,CONSTRAINT fk_participant_result_transaction FOREIGN KEY(point_transaction_id) REFERENCES bdc_point_transactions(id) ON DELETE SET NULL,UNIQUE INDEX uq_participant_result_transaction(point_transaction_id),INDEX idx_participant_result_competitor(competitor_id),INDEX idx_participant_result_event(event_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

  // Stable public IDs for all existing competitors.
  $rows=$pdo->query("SELECT id FROM bdc_competitors WHERE bdc_id IS NULL OR bdc_id='' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
  $u=$pdo->prepare("UPDATE bdc_competitors SET bdc_id=:bid WHERE id=:id AND (bdc_id IS NULL OR bdc_id='')");
  foreach($rows as $id) $u->execute(['bid'=>'BDC-'.str_pad((string)$id,6,'0',STR_PAD_LEFT),'id'=>$id]);

  if(self::tableExists($pdo,'bdc_documents')) $pdo->exec("INSERT IGNORE INTO bdc_result_documents(event_id,title,document_category,file_type,url,status,source,import_batch_id,source_row_hash,created_at) SELECT event_id,title,CASE WHEN document_type IN('heats','finals','points') THEN document_type ELSE 'other' END,CASE WHEN LOWER(url) LIKE '%.pdf%' THEN 'pdf' WHEN LOWER(url) LIKE '%.csv%' THEN 'csv' ELSE 'external' END,url,'published','historical_import',import_batch_id,source_row_hash,created_at FROM bdc_documents");
  $pdo->exec("INSERT IGNORE INTO bdc_participant_results(event_id,competitor_id,division,dance_role,placement,finalist_status,points_awarded,source,point_transaction_id,created_at) SELECT p.event_id,p.competitor_id,p.division,p.dance_role,p.placement,CASE WHEN p.placement IN('1','1st','First') THEN 'winner' WHEN p.placement IS NOT NULL AND p.placement<>'' THEN 'placed' ELSE 'participant' END,p.points,'historical_import',p.id,p.created_at FROM bdc_point_transactions p WHERE p.event_id IS NOT NULL");
  $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_profile_requests(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,request_type ENUM('new_registration','profile_update') NOT NULL,competitor_id BIGINT UNSIGNED NULL,full_name VARCHAR(190) NOT NULL,email VARCHAR(190) NOT NULL,phone VARCHAR(80) NULL,instagram VARCHAR(190) NULL,country VARCHAR(120) NULL,dance_role ENUM('leader','follower','both','unknown') NOT NULL DEFAULT 'unknown',current_division ENUM('novice','intermediate','advanced','bachata_rising','bachata_open','bachata_invitational','salsa_rising','salsa_open','semi_pro','pro','professional','all_star','unknown') NOT NULL DEFAULT 'unknown',photo_url VARCHAR(1000) NULL,notes TEXT NULL,payload_json LONGTEXT NULL,status ENUM('pending','under_review','more_info','approved','rejected') NOT NULL DEFAULT 'pending',admin_notes TEXT NULL,reviewed_by BIGINT UNSIGNED NULL,reviewed_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,CONSTRAINT fk_profile_request_competitor FOREIGN KEY(competitor_id) REFERENCES bdc_competitors(id) ON DELETE SET NULL,CONSTRAINT fk_profile_request_reviewer FOREIGN KEY(reviewed_by) REFERENCES bdc_users(id) ON DELETE SET NULL,INDEX idx_profile_requests_status(status),INDEX idx_profile_requests_type(request_type),INDEX idx_profile_requests_email(email)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  self::addColumn($pdo,'bdc_events','description','LONGTEXT NULL AFTER name');
  self::addColumn($pdo,'bdc_events','venue','VARCHAR(255) NULL AFTER location');
  self::addColumn($pdo,'bdc_events','starts_at','DATETIME NULL AFTER event_date');
  self::addColumn($pdo,'bdc_events','ends_at','DATETIME NULL AFTER starts_at');
  self::addColumn($pdo,'bdc_events','registration_opens_at','DATETIME NULL AFTER ends_at');
  self::addColumn($pdo,'bdc_events','registration_closes_at','DATETIME NULL AFTER registration_opens_at');
  self::addColumn($pdo,'bdc_events','payment_instructions','LONGTEXT NULL AFTER website_url');
  self::addColumn($pdo,'bdc_events','terms_conditions','LONGTEXT NULL AFTER payment_instructions');
  self::addColumn($pdo,'bdc_events','banner_url','VARCHAR(1000) NULL AFTER terms_conditions');
  self::addColumn($pdo,'bdc_events','event_mode',"ENUM('bdc','independent') NOT NULL DEFAULT 'bdc' AFTER banner_url");
  $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_event_ticket_types(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,event_id BIGINT UNSIGNED NOT NULL,name VARCHAR(190) NOT NULL,description TEXT NULL,price DECIMAL(10,2) NOT NULL DEFAULT 0,currency CHAR(3) NOT NULL DEFAULT 'SGD',capacity INT UNSIGNED NULL,sold_count INT UNSIGNED NOT NULL DEFAULT 0,includes_workshop TINYINT(1) NOT NULL DEFAULT 0,includes_social TINYINT(1) NOT NULL DEFAULT 0,includes_jack_jill TINYINT(1) NOT NULL DEFAULT 0,sales_start_at DATETIME NULL,sales_end_at DATETIME NULL,status ENUM('active','inactive') NOT NULL DEFAULT 'active',sort_order INT NOT NULL DEFAULT 0,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,CONSTRAINT fk_ticket_event FOREIGN KEY(event_id) REFERENCES bdc_events(id) ON DELETE CASCADE,INDEX idx_ticket_event(event_id,status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_event_registrations(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,event_id BIGINT UNSIGNED NOT NULL,ticket_type_id BIGINT UNSIGNED NOT NULL,competitor_id BIGINT UNSIGNED NULL,registration_number VARCHAR(50) NOT NULL UNIQUE,full_name VARCHAR(190) NOT NULL,email VARCHAR(190) NOT NULL,phone VARCHAR(80) NOT NULL,instagram VARCHAR(190) NULL,country VARCHAR(120) NULL,dance_role ENUM('leader','follower','both','unknown') NOT NULL DEFAULT 'unknown',dance_category VARCHAR(100) NULL,includes_jack_jill TINYINT(1) NOT NULL DEFAULT 0,registration_status ENUM('pending_verification','approved','rejected','more_information','cancelled','checked_in','no_show') NOT NULL DEFAULT 'pending_verification',payment_status ENUM('pending','verified','rejected','cash_at_venue','refunded') NOT NULL DEFAULT 'pending',terms_accepted_at DATETIME NOT NULL,admin_notes TEXT NULL,submitted_ip VARCHAR(45) NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,CONSTRAINT fk_registration_event FOREIGN KEY(event_id) REFERENCES bdc_events(id) ON DELETE CASCADE,CONSTRAINT fk_registration_ticket FOREIGN KEY(ticket_type_id) REFERENCES bdc_event_ticket_types(id) ON DELETE RESTRICT,CONSTRAINT fk_registration_competitor FOREIGN KEY(competitor_id) REFERENCES bdc_competitors(id) ON DELETE SET NULL,INDEX idx_registration_event_status(event_id,registration_status),INDEX idx_registration_email(email),INDEX idx_registration_payment(payment_status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_registration_payments(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,registration_id BIGINT UNSIGNED NOT NULL,payment_method ENUM('paynow','bank_transfer','cash','other') NOT NULL,expected_amount DECIMAL(10,2) NOT NULL DEFAULT 0,paid_amount DECIMAL(10,2) NULL,currency CHAR(3) NOT NULL DEFAULT 'SGD',payment_reference VARCHAR(190) NULL,verification_status ENUM('pending','verified','rejected','cash_at_venue','refunded') NOT NULL DEFAULT 'pending',verified_by BIGINT UNSIGNED NULL,verified_at DATETIME NULL,admin_notes TEXT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,CONSTRAINT fk_payment_registration FOREIGN KEY(registration_id) REFERENCES bdc_event_registrations(id) ON DELETE CASCADE,CONSTRAINT fk_payment_verifier FOREIGN KEY(verified_by) REFERENCES bdc_users(id) ON DELETE SET NULL,UNIQUE INDEX uq_payment_registration(registration_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_registration_uploads(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,registration_id BIGINT UNSIGNED NOT NULL,file_type ENUM('payment_receipt','other') NOT NULL DEFAULT 'payment_receipt',original_filename VARCHAR(255) NOT NULL,stored_filename VARCHAR(255) NOT NULL,storage_path VARCHAR(500) NOT NULL,mime_type VARCHAR(120) NOT NULL,file_size BIGINT UNSIGNED NOT NULL,uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,CONSTRAINT fk_upload_registration FOREIGN KEY(registration_id) REFERENCES bdc_event_registrations(id) ON DELETE CASCADE,INDEX idx_upload_registration(registration_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_registration_history(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,registration_id BIGINT UNSIGNED NOT NULL,old_registration_status VARCHAR(50) NULL,new_registration_status VARCHAR(50) NULL,old_payment_status VARCHAR(50) NULL,new_payment_status VARCHAR(50) NULL,notes TEXT NULL,changed_by BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,CONSTRAINT fk_history_registration FOREIGN KEY(registration_id) REFERENCES bdc_event_registrations(id) ON DELETE CASCADE,CONSTRAINT fk_history_user FOREIGN KEY(changed_by) REFERENCES bdc_users(id) ON DELETE SET NULL,INDEX idx_history_registration(registration_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

  // Scoring round workflow additions.
  if(self::tableExists($pdo,'bdc_scoring_rounds')){
   self::addColumn($pdo,'bdc_scoring_rounds','parent_round_id','BIGINT UNSIGNED NULL AFTER event_id');
   self::addColumn($pdo,'bdc_scoring_rounds','source_round_id','BIGINT UNSIGNED NULL AFTER parent_round_id');
   self::addColumn($pdo,'bdc_scoring_rounds','witness_1','VARCHAR(190) NULL AFTER source_round_id');
   self::addColumn($pdo,'bdc_scoring_rounds','witness_2','VARCHAR(190) NULL AFTER witness_1');
   self::addColumn($pdo,'bdc_scoring_rounds','witness_3','VARCHAR(190) NULL AFTER witness_2');
   // Allow the intermediate round in existing installations.
   try{$pdo->exec("ALTER TABLE bdc_scoring_rounds MODIFY round_type ENUM('heats','semifinal','final') NOT NULL");}catch(\Throwable $e){}
   self::addColumn($pdo,'bdc_scoring_rounds','scheduled_at','DATETIME NULL AFTER round_type');
   self::addIndex($pdo,'bdc_scoring_rounds','idx_scoring_parent','(parent_round_id)');
  }
  $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_scoring_final_pairs(
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   round_id BIGINT UNSIGNED NOT NULL,
   pair_number INT UNSIGNED NOT NULL,
   leader_entry_id BIGINT UNSIGNED NOT NULL,
   follower_entry_id BIGINT UNSIGNED NULL,
   pairing_status ENUM('draft','confirmed') NOT NULL DEFAULT 'draft',
   created_by BIGINT UNSIGNED NULL,
   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
   UNIQUE INDEX uq_final_pair_number(round_id,pair_number),
   UNIQUE INDEX uq_final_pair_leader(round_id,leader_entry_id),
   UNIQUE INDEX uq_final_pair_follower(round_id,follower_entry_id),
   INDEX idx_final_pair_round(round_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_scoring_final_marks(
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   round_id BIGINT UNSIGNED NOT NULL,
   pair_id BIGINT UNSIGNED NOT NULL,
   judge_id BIGINT UNSIGNED NOT NULL,
   rank_value INT UNSIGNED NOT NULL,
   updated_by BIGINT UNSIGNED NULL,
   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
   UNIQUE INDEX uq_final_mark(round_id,pair_id,judge_id),
   INDEX idx_final_marks_round(round_id),
   INDEX idx_final_marks_judge(judge_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_scoring_final_results(
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   round_id BIGINT UNSIGNED NOT NULL,
   pair_id BIGINT UNSIGNED NOT NULL,
   final_rank INT UNSIGNED NOT NULL,
   majority_level INT UNSIGNED NULL,
   majority_count INT UNSIGNED NULL,
   placement_sum INT UNSIGNED NULL,
   chief_rank INT UNSIGNED NULL,
   decision_json LONGTEXT NULL,
   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
   UNIQUE INDEX uq_final_result_pair(round_id,pair_id),
   UNIQUE INDEX uq_final_result_rank(round_id,final_rank),
   INDEX idx_final_results_round(round_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

  if(self::tableExists($pdo,'bdc_scoring_rounds')){
   self::addColumn($pdo,'bdc_scoring_rounds','publication_id','BIGINT UNSIGNED NULL AFTER published_document_id');
   self::addColumn($pdo,'bdc_scoring_rounds','locked_at','DATETIME NULL AFTER publication_id');
   self::addColumn($pdo,'bdc_scoring_rounds','locked_by','BIGINT UNSIGNED NULL AFTER locked_at');
   self::addColumn($pdo,'bdc_scoring_rounds','scoring_administrator','VARCHAR(190) NULL AFTER witness_3');
   try{$pdo->exec("ALTER TABLE bdc_scoring_rounds MODIFY status VARCHAR(40) NOT NULL DEFAULT 'draft'");}catch(\Throwable $e){}
   self::addIndex($pdo,'bdc_scoring_rounds','idx_scoring_publication','(publication_id)');
  }
  try{$pdo->exec("ALTER TABLE bdc_point_transactions MODIFY source_type ENUM('manual','csv_import','correction','scoring_engine','rollback') NOT NULL DEFAULT 'manual'");}catch(\Throwable $e){}
  $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_scoring_publications(
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   event_id BIGINT UNSIGNED NOT NULL,
   final_round_id BIGINT UNSIGNED NOT NULL,
   division ENUM('novice','intermediate','advanced','all_star','unknown') NOT NULL DEFAULT 'unknown',
   points_tier ENUM('1','2','3') NOT NULL,
   repository_document_id BIGINT UNSIGNED NULL,
   report_storage_path VARCHAR(500) NULL,
   report_url VARCHAR(1000) NULL,
   status ENUM('published','rolled_back') NOT NULL DEFAULT 'published',
   published_by BIGINT UNSIGNED NULL,
   published_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   rolled_back_by BIGINT UNSIGNED NULL,
   rolled_back_at DATETIME NULL,
   rollback_reason TEXT NULL,
   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
   UNIQUE INDEX uq_scoring_publication_round(final_round_id),
   INDEX idx_scoring_publication_event(event_id),
   INDEX idx_scoring_publication_status(status)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

  if(self::tableExists($pdo,'bdc_scoring_publications')){
   try{$pdo->exec("ALTER TABLE bdc_scoring_publications MODIFY status ENUM('pending_approval','published','rejected','rolled_back') NOT NULL DEFAULT 'pending_approval'");}catch(\Throwable $e){}
   try{$pdo->exec("ALTER TABLE bdc_scoring_publications MODIFY published_at DATETIME NULL");}catch(\Throwable $e){}
   self::addColumn($pdo,'bdc_scoring_publications','submitted_by','BIGINT UNSIGNED NULL AFTER status');
   self::addColumn($pdo,'bdc_scoring_publications','submitted_at','DATETIME NULL AFTER submitted_by');
   self::addColumn($pdo,'bdc_scoring_publications','approved_by','BIGINT UNSIGNED NULL AFTER published_by');
   self::addColumn($pdo,'bdc_scoring_publications','approved_at','DATETIME NULL AFTER approved_by');
   self::addColumn($pdo,'bdc_scoring_publications','rejected_by','BIGINT UNSIGNED NULL AFTER approved_at');
   self::addColumn($pdo,'bdc_scoring_publications','rejected_at','DATETIME NULL AFTER rejected_by');
   self::addColumn($pdo,'bdc_scoring_publications','rejection_reason','TEXT NULL AFTER rejected_at');
  }

  $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_scoring_publication_documents(
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   publication_id BIGINT UNSIGNED NOT NULL,
   document_category ENUM('heats','finals','points') NOT NULL,
   repository_document_id BIGINT UNSIGNED NOT NULL,
   storage_path VARCHAR(500) NOT NULL,
   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   UNIQUE INDEX uq_publication_document(publication_id,document_category),
   INDEX idx_publication_repository_document(repository_document_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

  $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_scoring_publication_points(
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   publication_id BIGINT UNSIGNED NOT NULL,
   pair_id BIGINT UNSIGNED NOT NULL,
   competitor_id BIGINT UNSIGNED NOT NULL,
   dance_role ENUM('leader','follower') NOT NULL,
   final_rank INT UNSIGNED NOT NULL,
   points_awarded DECIMAL(8,2) NOT NULL DEFAULT 0,
   point_transaction_id BIGINT UNSIGNED NULL,
   participant_result_id BIGINT UNSIGNED NULL,
   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   UNIQUE INDEX uq_publication_competitor(publication_id,competitor_id,dance_role),
   INDEX idx_publication_points_tx(point_transaction_id),
   INDEX idx_publication_points_result(participant_result_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");



  // Scoring Tests Dashboard: isolated copies of the production scoring structures.
  // These tables never feed official points, repository, leaderboard or Hall of Fame.
  foreach([
   'bdc_events'=>'bdc_test_events',
   'bdc_competitors'=>'bdc_test_competitors',
   'bdc_scoring_rounds'=>'bdc_test_scoring_rounds',
   'bdc_scoring_entries'=>'bdc_test_scoring_entries',
   'bdc_scoring_judges'=>'bdc_test_scoring_judges',
   'bdc_scoring_marks'=>'bdc_test_scoring_marks',
   'bdc_scoring_results'=>'bdc_test_scoring_results',
   'bdc_scoring_final_pairs'=>'bdc_test_scoring_final_pairs',
   'bdc_scoring_final_marks'=>'bdc_test_scoring_final_marks',
   'bdc_scoring_final_results'=>'bdc_test_scoring_final_results',
   'bdc_scoring_audit'=>'bdc_test_scoring_audit',
   'bdc_scoring_publications'=>'bdc_test_scoring_publications',
   'bdc_scoring_publication_points'=>'bdc_test_scoring_publication_points',
   'bdc_result_documents'=>'bdc_test_result_documents',
  ] as $sourceTable=>$testTable){
   $pdo->exec("CREATE TABLE IF NOT EXISTS {$testTable} LIKE {$sourceTable}");
  }


  $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_registration_desk_links(
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   event_id BIGINT UNSIGNED NOT NULL,
   division ENUM('novice','intermediate','advanced','all_star') NOT NULL,
   token_hash CHAR(64) NOT NULL,
   token_hint VARCHAR(12) NOT NULL,
   is_enabled TINYINT(1) NOT NULL DEFAULT 1,
   expires_at DATETIME NULL,
   created_by BIGINT UNSIGNED NULL,
   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
   UNIQUE INDEX uq_registration_desk_event_division(event_id,division),
   UNIQUE INDEX uq_registration_desk_token_hash(token_hash),
   INDEX idx_registration_desk_enabled(is_enabled)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

  $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_registration_desk_activity(
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   desk_link_id BIGINT UNSIGNED NOT NULL,
   event_id BIGINT UNSIGNED NOT NULL,
   division ENUM('novice','intermediate','advanced','all_star') NOT NULL,
   action VARCHAR(80) NOT NULL,
   competitor_id BIGINT UNSIGNED NULL,
   competitor_name VARCHAR(190) NULL,
   details LONGTEXT NULL,
   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   INDEX idx_registration_desk_activity(desk_link_id,created_at),
   INDEX idx_registration_desk_event(event_id,division,created_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

  self::addColumn($pdo,'bdc_scoring_entries','desk_checked_in',"TINYINT(1) NOT NULL DEFAULT 0 AFTER entry_status");
  self::addColumn($pdo,'bdc_scoring_entries','desk_ready',"TINYINT(1) NOT NULL DEFAULT 0 AFTER desk_checked_in");
  self::addColumn($pdo,'bdc_scoring_entries','desk_updated_at',"DATETIME NULL AFTER desk_ready");

  $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_releases(
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   version VARCHAR(50) NOT NULL,
   release_status ENUM('development','testing','qa_approved','production_candidate','production','archived') NOT NULL DEFAULT 'development',
   release_notes LONGTEXT NULL,
   checksum CHAR(64) NULL,
   created_by BIGINT UNSIGNED NULL,
   approved_by BIGINT UNSIGNED NULL,
   approved_at DATETIME NULL,
   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
   UNIQUE INDEX uq_release_version(version)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

  $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_release_installations(
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   version VARCHAR(50) NOT NULL,
   environment ENUM('production','staging','development') NOT NULL,
   status ENUM('installed','failed','rolled_back') NOT NULL DEFAULT 'installed',
   installed_by BIGINT UNSIGNED NULL,
   installed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   notes TEXT NULL,
   updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
   UNIQUE INDEX uq_release_environment(version,environment),
   INDEX idx_release_environment(environment,installed_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

  $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_deployment_history(
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   version VARCHAR(50) NOT NULL,
   environment ENUM('production','staging','development') NOT NULL,
   action ENUM('install','approve','deploy','rollback','health_check') NOT NULL,
   status ENUM('pending','success','failed') NOT NULL DEFAULT 'pending',
   performed_by BIGINT UNSIGNED NULL,
   details LONGTEXT NULL,
   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   INDEX idx_deployment_environment(environment,created_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

  $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_backup_settings(
   id TINYINT UNSIGNED PRIMARY KEY,
   enabled TINYINT(1) NOT NULL DEFAULT 0,
   frequency ENUM('daily','weekly','monthly') NOT NULL DEFAULT 'daily',
   backup_time TIME NOT NULL DEFAULT '03:00:00',
   weekday TINYINT UNSIGNED NOT NULL DEFAULT 1,
   month_day TINYINT UNSIGNED NOT NULL DEFAULT 1,
   backup_type ENUM('database','site','full') NOT NULL DEFAULT 'full',
   keep_count INT UNSIGNED NOT NULL DEFAULT 7,
   google_drive_enabled TINYINT(1) NOT NULL DEFAULT 0,
   google_drive_folder_id VARCHAR(255) NULL,
   service_account_path VARCHAR(500) NULL,
   last_run_at DATETIME NULL,
   next_run_at DATETIME NULL,
   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

  $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_backup_runs(
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   backup_type ENUM('database','site','full') NOT NULL,
   status ENUM('running','success','failed','deleted') NOT NULL DEFAULT 'running',
   file_name VARCHAR(255) NULL,
   local_path VARCHAR(500) NULL,
   file_size BIGINT UNSIGNED NULL,
   checksum CHAR(64) NULL,
   google_drive_status ENUM('disabled','uploaded','failed') NOT NULL DEFAULT 'disabled',
   google_drive_file_id VARCHAR(255) NULL,
   google_drive_link VARCHAR(1000) NULL,
   error_message TEXT NULL,
   started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   completed_at DATETIME NULL,
   deleted_at DATETIME NULL,
   triggered_by BIGINT UNSIGNED NULL,
   INDEX idx_backup_runs_status(status),
   INDEX idx_backup_runs_completed(completed_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

  $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_backup_schedules(
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   schedule_name VARCHAR(120) NOT NULL,
   enabled TINYINT(1) NOT NULL DEFAULT 1,
   frequency ENUM('daily','weekly','monthly') NOT NULL DEFAULT 'daily',
   backup_time TIME NOT NULL DEFAULT '03:00:00',
   weekday TINYINT UNSIGNED NOT NULL DEFAULT 1,
   month_day TINYINT UNSIGNED NOT NULL DEFAULT 1,
   backup_type ENUM('database','site','full') NOT NULL DEFAULT 'full',
   last_run_at DATETIME NULL,
   next_run_at DATETIME NULL,
   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
   UNIQUE INDEX uq_backup_schedule_slot(backup_type,frequency,backup_time,weekday,month_day),
   INDEX idx_backup_schedule_due(enabled,next_run_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

  self::addColumn($pdo,'bdc_backup_settings','server_keep_count','INT UNSIGNED NOT NULL DEFAULT 7 AFTER keep_count');
  self::addColumn($pdo,'bdc_backup_settings','drive_keep_count','INT UNSIGNED NOT NULL DEFAULT 30 AFTER server_keep_count');

  $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_login_attempts(
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,ip_address VARCHAR(45) NOT NULL,email_hash CHAR(64) NOT NULL,
   attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_login_attempt_lookup(ip_address,email_hash,attempted_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

  $pdo->exec("INSERT INTO bdc_backup_settings(
   id,enabled,frequency,backup_time,weekday,month_day,backup_type,keep_count,
   google_drive_enabled,service_account_path
  ) VALUES(
   1,0,'daily','03:00:00',1,1,'full',7,0,'storage/private/google-drive-service-account.json'
  ) ON DUPLICATE KEY UPDATE id=id");

  $pdo->exec("INSERT INTO bdc_settings(setting_key,setting_value) VALUES('app_version','2.2.0') ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");


 }
 private static function tableExists(PDO $pdo,string $table):bool{$s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t');$s->execute(['t'=>$table]);return(int)$s->fetchColumn()>0;}
 private static function addColumn(PDO $pdo,string $table,string $column,string $definition):void{if(!self::tableExists($pdo,$table))return;$s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t AND COLUMN_NAME=:c');$s->execute(['t'=>$table,'c'=>$column]);if((int)$s->fetchColumn()===0)$pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");}
 private static function addIndex(PDO $pdo,string $table,string $index,string $columns,bool $unique=false):void{if(!self::tableExists($pdo,$table))return;$s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t AND INDEX_NAME=:i');$s->execute(['t'=>$table,'i'=>$index]);if((int)$s->fetchColumn()===0)$pdo->exec('ALTER TABLE `'.$table.'` ADD '.($unique?'UNIQUE ':'').'INDEX `'.$index.'` '.$columns);}
}
