-- Shared platform schema for Adlexone / Inlay (SQLite) — final shape.
-- Application packs must NOT add tables. They may store an application slug
-- on shared rows (e.g. saved_searches.application).
-- Live upgrades: Adlexone\Database\SchemaMigrator

PRAGMA foreign_keys = ON;
PRAGMA journal_mode = WAL;

BEGIN TRANSACTION;

CREATE TABLE IF NOT EXISTS schema_migrations (
  version INTEGER NOT NULL PRIMARY KEY,
  applied_at INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS users (
  user_id INTEGER NOT NULL PRIMARY KEY,
  first_name TEXT,
  last_name TEXT,
  user_name TEXT,
  email TEXT,
  password TEXT,
  office TEXT,
  phone TEXT,
  theme TEXT DEFAULT 'default',
  lastactive TEXT DEFAULT 'active',
  language TEXT DEFAULT 'English',
  time_offset TEXT DEFAULT '0',
  title TEXT,
  show_header TEXT,
  show_graphics TEXT,
  home_controller TEXT,
  home_controller_name TEXT,
  role INTEGER,
  settings TEXT
);

CREATE TABLE IF NOT EXISTS user_identities (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  provider TEXT NOT NULL,
  subject TEXT NOT NULL,
  email TEXT,
  created_at INTEGER NOT NULL,
  UNIQUE(provider, subject)
);

CREATE TABLE IF NOT EXISTS groups (
  group_id INTEGER NOT NULL PRIMARY KEY,
  group_name TEXT NOT NULL,
  description TEXT,
  role TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS user_groups (
  user_id INTEGER NOT NULL,
  group_id INTEGER NOT NULL,
  PRIMARY KEY (user_id, group_id)
);

CREATE TABLE IF NOT EXISTS group_permissions (
  group_id INTEGER NOT NULL,
  permission TEXT NOT NULL,
  PRIMARY KEY (group_id, permission)
);

CREATE TABLE IF NOT EXISTS item_types (
  item_type_id INTEGER NOT NULL PRIMARY KEY,
  item_type_name TEXT,
  user_security TEXT,
  enabled TEXT
);

CREATE TABLE IF NOT EXISTS item_type_groups (
  item_type_id INTEGER NOT NULL,
  group_id INTEGER NOT NULL,
  PRIMARY KEY (item_type_id, group_id)
);

CREATE TABLE IF NOT EXISTS custom_fields (
  custom_field_id INTEGER NOT NULL PRIMARY KEY,
  custom_field_name TEXT,
  field_type TEXT,
  default_value TEXT,
  sub_menu INTEGER DEFAULT 0,
  enabled TEXT,
  field_reference INTEGER,
  data TEXT,
  validation_type TEXT,
  required TEXT
);

CREATE TABLE IF NOT EXISTS custom_field_menu_values (
  menu_value_id INTEGER NOT NULL PRIMARY KEY,
  custom_field_id INTEGER NOT NULL,
  menu_value TEXT NOT NULL,
  parent_menu_value_id INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS item_type_custom_fields (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  item_type_id INTEGER NOT NULL DEFAULT 0,
  custom_field_id INTEGER NOT NULL DEFAULT 0,
  custom_field_order INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS action_definitions (
  action_id INTEGER NOT NULL PRIMARY KEY,
  action_name TEXT DEFAULT '0',
  action_condition_pre TEXT,
  action_condition_post TEXT,
  action_data TEXT,
  item_type_id INTEGER,
  action_parameters TEXT,
  action_type TEXT,
  package_file TEXT,
  package_function TEXT,
  enabled TEXT
);

CREATE TABLE IF NOT EXISTS items (
  item_id INTEGER NOT NULL PRIMARY KEY,
  create_date INTEGER NOT NULL,
  core_log_updated INTEGER,
  item_type_id INTEGER NOT NULL,
  creator_security INTEGER,
  user_security INTEGER NOT NULL,
  item_title TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS item_field_values (
  item_id INTEGER NOT NULL,
  custom_field_id INTEGER NOT NULL,
  value TEXT NOT NULL DEFAULT '',
  PRIMARY KEY (item_id, custom_field_id)
);

CREATE TABLE IF NOT EXISTS item_groups (
  item_id INTEGER NOT NULL,
  group_id INTEGER NOT NULL,
  PRIMARY KEY (item_id, group_id)
);

CREATE TABLE IF NOT EXISTS core_log (
  id INTEGER NOT NULL PRIMARY KEY,
  item_id INTEGER NOT NULL DEFAULT 0,
  create_date TEXT NOT NULL DEFAULT '0',
  item_identifier TEXT NOT NULL DEFAULT '0',
  log_item_sequence INTEGER NOT NULL,
  log_text TEXT,
  security_id INTEGER,
  role_id INTEGER
);

CREATE TABLE IF NOT EXISTS item_attachments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  item_id INTEGER,
  file_name TEXT,
  file_type TEXT,
  file_size INTEGER,
  downloads INTEGER NOT NULL DEFAULT 0,
  added_by INTEGER NOT NULL,
  create_date INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS system_log (
  item_id INTEGER NOT NULL,
  event_id TEXT NOT NULL,
  event_counter INTEGER NOT NULL,
  event_string TEXT,
  create_date INTEGER NOT NULL,
  security_id INTEGER,
  PRIMARY KEY (item_id, event_id)
);

CREATE TABLE IF NOT EXISTS applications (
  application_id INTEGER PRIMARY KEY,
  slug TEXT NOT NULL UNIQUE,
  name TEXT NOT NULL,
  hint TEXT NOT NULL DEFAULT '',
  icon TEXT NOT NULL DEFAULT 'ic-launch',
  permission TEXT NOT NULL,
  enabled INTEGER NOT NULL DEFAULT 1,
  sort_order INTEGER NOT NULL DEFAULT 0,
  entry_mode TEXT NOT NULL DEFAULT 'shell',
  legacy_controller TEXT NOT NULL DEFAULT '',
  legacy_key TEXT NOT NULL DEFAULT ''
);

CREATE TABLE IF NOT EXISTS application_nav (
  nav_id INTEGER PRIMARY KEY,
  application_id INTEGER NOT NULL,
  label TEXT NOT NULL,
  capability TEXT NOT NULL,
  permission TEXT NOT NULL DEFAULT '',
  icon TEXT NOT NULL DEFAULT '',
  config_json TEXT NOT NULL DEFAULT '{}',
  sort_order INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS announcements (
  id INTEGER NOT NULL PRIMARY KEY,
  "time" INTEGER DEFAULT 0,
  message TEXT,
  type TEXT,
  subject TEXT
);

CREATE TABLE IF NOT EXISTS saved_searches (
  search_id INTEGER PRIMARY KEY AUTOINCREMENT,
  "user" TEXT NOT NULL,
  search_name TEXT NOT NULL,
  search_description TEXT,
  saved_search_sql TEXT,
  application TEXT,
  criteria_json TEXT NOT NULL DEFAULT '{}'
);

CREATE INDEX IF NOT EXISTS idx_items_create_date ON items (create_date);
CREATE INDEX IF NOT EXISTS idx_items_title ON items (item_title);
CREATE INDEX IF NOT EXISTS idx_items_item_type_id ON items (item_type_id);
CREATE INDEX IF NOT EXISTS idx_items_user_security ON items (user_security);
CREATE INDEX IF NOT EXISTS idx_items_creator_security ON items (creator_security);
CREATE INDEX IF NOT EXISTS idx_core_log_item_id ON core_log (item_id);
CREATE INDEX IF NOT EXISTS idx_core_log_item_seq ON core_log (item_id, log_item_sequence);
CREATE INDEX IF NOT EXISTS idx_item_attachments_item_id ON item_attachments (item_id);
CREATE INDEX IF NOT EXISTS idx_user_groups_group ON user_groups (group_id);
CREATE INDEX IF NOT EXISTS idx_item_groups_group ON item_groups (group_id);
CREATE INDEX IF NOT EXISTS idx_item_type_groups_group ON item_type_groups (group_id);
CREATE INDEX IF NOT EXISTS idx_menu_values_field ON custom_field_menu_values (custom_field_id);
CREATE INDEX IF NOT EXISTS idx_type_fields_type ON item_type_custom_fields (item_type_id);
CREATE INDEX IF NOT EXISTS idx_saved_searches_app ON saved_searches (application);
CREATE INDEX IF NOT EXISTS idx_application_nav_app ON application_nav (application_id);
CREATE INDEX IF NOT EXISTS idx_user_identities_user ON user_identities (user_id);
CREATE INDEX IF NOT EXISTS idx_item_field_values_field ON item_field_values (custom_field_id);
CREATE INDEX IF NOT EXISTS idx_item_field_values_field_value ON item_field_values (custom_field_id, value);

COMMIT;
