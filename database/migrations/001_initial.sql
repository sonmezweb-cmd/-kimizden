-- İkimizden — ilk şema. Tüm zamanlar UTC (DATETIME, uygulama UTC yazar).

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(190) NOT NULL,
  phone VARCHAR(40) NULL,
  role ENUM('customer','designer','admin') NOT NULL DEFAULT 'customer',
  locale CHAR(2) NOT NULL DEFAULT 'tr',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  last_login_at DATETIME NULL,
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_resets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_pr_token (token_hash),
  KEY ix_pr_user (user_id),
  CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  bucket CHAR(64) NOT NULL,
  created_at DATETIME NOT NULL,
  KEY ix_la_bucket (bucket, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
  k VARCHAR(100) NOT NULL PRIMARY KEY,
  v MEDIUMTEXT NULL,
  is_secret TINYINT(1) NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE translations (
  lang CHAR(2) NOT NULL,
  k VARCHAR(150) NOT NULL,
  v TEXT NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (lang, k)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE themes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(60) NOT NULL,
  name_json JSON NOT NULL,
  description_json JSON NOT NULL,
  config_json JSON NOT NULL,
  sound_volume TINYINT UNSIGNED NOT NULL DEFAULT 60,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort INT NOT NULL DEFAULT 0,
  media_rev INT UNSIGNED NOT NULL DEFAULT 1,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_theme_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE packages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL,
  name_json JSON NOT NULL,
  scope_json JSON NOT NULL,
  revisions INT UNSIGNED NOT NULL DEFAULT 2,
  delivery_days INT UNSIGNED NOT NULL DEFAULT 5,
  price_minor INT UNSIGNED NULL,
  currency CHAR(3) NOT NULL DEFAULT 'TRY',
  max_languages TINYINT UNSIGNED NOT NULL DEFAULT 3,
  guest_groups TINYINT(1) NOT NULL DEFAULT 1,
  is_on_sale TINYINT(1) NOT NULL DEFAULT 1,
  sort INT NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_pkg_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE addons (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL,
  name_json JSON NOT NULL,
  description_json JSON NOT NULL,
  price_minor INT UNSIGNED NULL,
  extra_revisions INT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort INT NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_addon_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_no VARCHAR(20) NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  theme_id INT UNSIGNED NOT NULL,
  package_id INT UNSIGNED NOT NULL,
  snapshot_json JSON NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'TRY',
  total_minor INT UNSIGNED NOT NULL DEFAULT 0,
  payment_status ENUM('unpaid','awaiting','paid','test_paid','review') NOT NULL DEFAULT 'unpaid',
  design_status ENUM('info_pending','in_design','awaiting_approval','revision_requested','approved') NOT NULL DEFAULT 'info_pending',
  publish_status ENUM('unpublished','published','archived') NOT NULL DEFAULT 'unpublished',
  publish_right TINYINT(1) NOT NULL DEFAULT 0,
  needs_review TINYINT(1) NOT NULL DEFAULT 0,
  designer_id INT UNSIGNED NULL,
  revisions_allowed INT UNSIGNED NOT NULL DEFAULT 0,
  info_submitted_at DATETIME NULL,
  paid_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_order_no (public_no),
  KEY ix_order_user (user_id),
  KEY ix_order_designer (designer_id),
  CONSTRAINT fk_order_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_order_theme FOREIGN KEY (theme_id) REFERENCES themes(id),
  CONSTRAINT fk_order_pkg FOREIGN KEY (package_id) REFERENCES packages(id),
  CONSTRAINT fk_order_designer FOREIGN KEY (designer_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payment_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  merchant_oid VARCHAR(64) NOT NULL,
  merchant_id VARCHAR(40) NOT NULL,
  mode ENUM('test','live') NOT NULL,
  amount_minor INT UNSIGNED NOT NULL,
  currency CHAR(3) NOT NULL,
  status ENUM('created','token_failed','awaiting','success','failed') NOT NULL DEFAULT 'created',
  token VARCHAR(255) NULL,
  error_text VARCHAR(500) NULL,
  collected_minor INT UNSIGNED NULL,
  callback_status VARCHAR(20) NULL,
  callback_count INT UNSIGNED NOT NULL DEFAULT 0,
  processed_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_attempt_oid (merchant_oid),
  KEY ix_attempt_order (order_id),
  CONSTRAINT fk_attempt_order FOREIGN KEY (order_id) REFERENCES orders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  attempt_id INT UNSIGNED NULL,
  mode ENUM('test','live','manual') NOT NULL,
  amount_minor INT UNSIGNED NOT NULL,
  collected_minor INT UNSIGNED NOT NULL,
  currency CHAR(3) NOT NULL,
  payment_type VARCHAR(30) NULL,
  note VARCHAR(500) NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_payment_attempt (attempt_id),
  KEY ix_payment_order (order_id),
  CONSTRAINT fk_payment_order FOREIGN KEY (order_id) REFERENCES orders(id),
  CONSTRAINT fk_payment_attempt FOREIGN KEY (attempt_id) REFERENCES payment_attempts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payment_callbacks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  merchant_oid VARCHAR(64) NULL,
  status VARCHAR(20) NULL,
  mode VARCHAR(10) NULL,
  hash_valid TINYINT(1) NOT NULL,
  outcome VARCHAR(40) NOT NULL,
  payload_json JSON NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL,
  KEY ix_cb_oid (merchant_oid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE invitations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  slug VARCHAR(40) NOT NULL,
  live_version_id INT UNSIGNED NULL,
  published_at DATETIME NULL,
  archived_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_inv_order (order_id),
  UNIQUE KEY uq_inv_slug (slug),
  CONSTRAINT fk_inv_order FOREIGN KEY (order_id) REFERENCES orders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE invitation_drafts (
  invitation_id INT UNSIGNED NOT NULL PRIMARY KEY,
  theme_id INT UNSIGNED NOT NULL,
  content_json JSON NOT NULL,
  field_versions_json JSON NOT NULL,
  lock_version INT UNSIGNED NOT NULL DEFAULT 1,
  updated_by INT UNSIGNED NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_draft_inv FOREIGN KEY (invitation_id) REFERENCES invitations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE invitation_versions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invitation_id INT UNSIGNED NOT NULL,
  version_no INT UNSIGNED NOT NULL,
  theme_id INT UNSIGNED NOT NULL,
  content_json JSON NOT NULL,
  draft_lock_version INT UNSIGNED NOT NULL,
  status ENUM('sent','revision_requested','approved','superseded') NOT NULL DEFAULT 'sent',
  designer_note TEXT NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  approved_by INT UNSIGNED NULL,
  approved_at DATETIME NULL,
  UNIQUE KEY uq_ver_no (invitation_id, version_no),
  CONSTRAINT fk_ver_inv FOREIGN KEY (invitation_id) REFERENCES invitations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE invitations ADD CONSTRAINT fk_inv_live FOREIGN KEY (live_version_id) REFERENCES invitation_versions(id);

CREATE TABLE revision_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  version_id INT UNSIGNED NOT NULL,
  message TEXT NOT NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_rev_version (version_id),
  KEY ix_rev_order (order_id),
  CONSTRAINT fk_rev_order FOREIGN KEY (order_id) REFERENCES orders(id),
  CONSTRAINT fk_rev_version FOREIGN KEY (version_id) REFERENCES invitation_versions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE media (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  mkey CHAR(32) NOT NULL,
  path VARCHAR(255) NOT NULL,
  mime VARCHAR(40) NOT NULL,
  width INT UNSIGNED NOT NULL,
  height INT UNSIGNED NOT NULL,
  bytes INT UNSIGNED NOT NULL,
  original_name VARCHAR(190) NULL,
  uploaded_by INT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_media_key (mkey),
  KEY ix_media_order (order_id),
  CONSTRAINT fk_media_order FOREIGN KEY (order_id) REFERENCES orders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE guest_groups (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invitation_id INT UNSIGNED NOT NULL,
  name VARCHAR(190) NOT NULL,
  token CHAR(32) NOT NULL,
  max_guests TINYINT UNSIGNED NOT NULL DEFAULT 2,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_gg_token (token),
  KEY ix_gg_inv (invitation_id),
  CONSTRAINT fk_gg_inv FOREIGN KEY (invitation_id) REFERENCES invitations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rsvps (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invitation_id INT UNSIGNED NOT NULL,
  group_id INT UNSIGNED NULL,
  edit_token_hash CHAR(64) NULL,
  name VARCHAR(190) NOT NULL,
  attending TINYINT(1) NOT NULL,
  adults TINYINT UNSIGNED NOT NULL DEFAULT 0,
  children TINYINT UNSIGNED NOT NULL DEFAULT 0,
  note VARCHAR(1000) NULL,
  lang CHAR(2) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_rsvp_group (group_id),
  UNIQUE KEY uq_rsvp_edit (edit_token_hash),
  KEY ix_rsvp_inv (invitation_id),
  CONSTRAINT fk_rsvp_inv FOREIGN KEY (invitation_id) REFERENCES invitations(id) ON DELETE CASCADE,
  CONSTRAINT fk_rsvp_group FOREIGN KEY (group_id) REFERENCES guest_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE preview_tokens (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invitation_id INT UNSIGNED NOT NULL,
  version_id INT UNSIGNED NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  revoked_at DATETIME NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_pt_hash (token_hash),
  KEY ix_pt_inv (invitation_id),
  CONSTRAINT fk_pt_inv FOREIGN KEY (invitation_id) REFERENCES invitations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE email_queue (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dedupe_key VARCHAR(120) NULL,
  to_email VARCHAR(190) NOT NULL,
  lang CHAR(2) NOT NULL,
  template VARCHAR(60) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  body_text MEDIUMTEXT NOT NULL,
  status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  last_error VARCHAR(500) NULL,
  created_at DATETIME NOT NULL,
  sent_at DATETIME NULL,
  UNIQUE KEY uq_mail_dedupe (dedupe_key),
  KEY ix_mail_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(60) NOT NULL,
  entity VARCHAR(40) NULL,
  entity_id INT UNSIGNED NULL,
  details_json JSON NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL,
  KEY ix_audit_entity (entity, entity_id),
  KEY ix_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pages (
  k VARCHAR(40) NOT NULL,
  lang CHAR(2) NOT NULL,
  title VARCHAR(190) NOT NULL,
  body MEDIUMTEXT NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (k, lang)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contact_messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  email VARCHAR(190) NOT NULL,
  message TEXT NOT NULL,
  lang CHAR(2) NOT NULL,
  ip VARCHAR(45) NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
