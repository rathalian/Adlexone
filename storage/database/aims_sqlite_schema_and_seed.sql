BEGIN TRANSACTION;

-- aims_action_definitions
CREATE TABLE IF NOT EXISTS aims_action_definitions (
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

INSERT INTO aims_action_definitions (action_id, action_name, action_condition_pre, action_condition_post, action_data, item_type_id, action_parameters, action_type, package_file, package_function, enabled) VALUES
(1, 'Email owner and group members when ticket log updated', NULL, NULL, 'return@email.com}-{Helpdesk Ticket ITEM_ID has had a log update}-{The AIMS ticket number ITEM_ID - ITEM_TITLE - has a new log entry.\r\n\r\nYou can view the ticket here: AIMS_URL/index.php?controller=app_oneorzerohelpdesk_main&subcontroller=item_management_manage&option=show_item&item_id=ITEM_ID&log_entry=yes&attachments=yes\r\n\r\nRegards,\r\n\r\nThe AIMS Team}-{AIMS', NULL, '}-{owner_groups}-{yes', NULL, 'Email_Notifications.actions.php', 'SendEmail', 'Yes'),
(2, 'Email owner and group members when ticket closed', NULL, NULL, 'return@email.com}-{Helpdesk Ticket ITEM_ID has been closed}-{The AIMS ticket number ITEM_ID - ITEM_TITLE - is now closed.\r\n\r\nYou can view this ticket here: AIMS_URL/index.php?controller=app_oneorzerohelpdesk_main&subcontroller=item_management_manage&option=show_item&item_id=ITEM_ID&log_entry=yes&attachments=yes\r\n\r\nRegards,\r\n\r\nThe AIMS Team}-{AIMS', NULL, '}-{owner_groups}-{yes', NULL, 'Email_Notifications.actions.php', 'SendEmail', 'Yes'),
(4, 'Trigger when ticket status = Closed', 'custom_field_3}-{<>}-{Closed', 'custom_field_3}-{==}-{Closed}-{2', '2', 1, NULL, 'update_item_trigger_met', 'Action_Triggers.actions.php', 'TriggerActionCustomField', 'Yes'),
(5, 'Email owner and group members when ticket created', NULL, NULL, 'return@email.com}-{Helpdesk Ticket ITEM_ID has created}-{The AIMS ticket number ITEM_ID - ITEM_TITLE - has been created.\r\n\r\nYou can view the ticket here: AIMS_URL/index.php?controller=app_oneorzerohelpdesk_main&subcontroller=item_management_manage&option=show_item&item_id=ITEM_ID&log_entry=yes&attachments=yes\r\n\r\nRegards,\r\n\r\nThe AIMS Team}-{AIMS', NULL, '}-{owner_groups}-{yes', NULL, 'Email_Notifications.actions.php', 'SendEmail', 'Yes'),
(13, 'Trigger when new ticket created', 'custom_field_6}-{=}-{', 'custom_field_6}-{==}-{}-{2', '5', 1, NULL, 'create_item_every_item', 'Action_Triggers.actions.php', 'TriggerActionCustomField', 'Yes'),
(7, 'Email new owner when ownership changes', NULL, NULL, 'return@email.com}-{Helpdesk Ticket ITEM_ID has had a log update}-{The AIMS ticket number ITEM_ID - ITEM_TITLE - is now owned by you.\r\n\r\nYou can view the ticket here: AIMS_URL/index.php?controller=app_oneorzerohelpdesk_main&subcontroller=item_management_manage&option=show_item&item_id=ITEM_ID&log_entry=yes&attachments=yes\r\n\r\nRegards,\r\n\r\nThe AIMS Team}-{AIMS', NULL, '}-{owner}-{yes', NULL, 'Email_Notifications.actions.php', 'SendEmail', 'Yes'),
(8, 'Trigger when the ticket ownership changes', 'user_security', NULL, '7', 1, NULL, 'update_item', 'Action_Triggers.actions.php', 'TriggerActionSystemField', 'Yes'),
(9, 'Email owner when ticket group members change', NULL, NULL, 'return@email.com}-{Helpdesk Ticket ITEM_ID has updated group members}-{The AIMS ticket number ITEM_ID - ITEM_TITLE - has had its group members updated.\r\n\r\nYou can view this ticket here: AIMS_URL/index.php?controller=app_oneorzerohelpdesk_main&subcontroller=item_management_manage&option=show_item&item_id=ITEM_ID&log_entry=yes&attachments=yes\r\n\r\nRegards,\r\n\r\nThe AIMS Team}-{AIMS', NULL, '}-{owner}-{yes', NULL, 'Email_Notifications.actions.php', 'SendEmail', 'Yes'),
(10, 'Trigger when new ticket log entry is added', 'custom_field_6}-{==}-{}-{5', 'custom_field_6}-{==}-{}-{5', '1', 1, NULL, 'update_item_log_entry', 'Action_Triggers.actions.php', 'TriggerActionCustomField', 'Yes'),
(11, 'Trigger when new ticket group members change', 'group_security', NULL, '9', 1, NULL, 'update_item', 'Action_Triggers.actions.php', 'TriggerActionSystemField', 'Yes'),
(12, 'Helpdesk Ticket : Time Spent', NULL, NULL, NULL, 1, '11', 'main_page_content', 'Time_Manager.actions.php', 'AddTime', 'Yes');

-- aims_announcements
CREATE TABLE IF NOT EXISTS aims_announcements (
  id INTEGER NOT NULL PRIMARY KEY,
  "time" INTEGER DEFAULT 0,
  message TEXT,
  type TEXT,
  subject TEXT
);

INSERT INTO aims_announcements (id, "time", message, type, subject) VALUES
(1, 1237462523, 'What does AIMS mean ? - Action and Information Management System.\r\n\r\nThe AIMS is a system that manages information i.e. a Helpdesk Ticket and uses actions to ''do things''.\r\n\r\nActions can include sending an email, adding a log entry etc.', 'user', 'Welcome to OneOrZero AIMS'),
(2, 1237463993, 'Using Item Types (Administration &gt; Item Settings &gt; Add Item Types) you can create many different ways to capture information in the AIMS.\r\n\r\nWe have included the item type ''Helpdesk Ticket'' - but you can at any time add additional types  i.e. ''Computer Issue, Project Task, Product Defect.\r\n\r\nItem types allow you to customise what information is captured in each item in a specific way as each item type has it''s own custom fields, security and actions.', 'user', 'What are item types and why are they so important?'),
(3, 1237464245, 'Actions are the ''things that happen'' in AIMS.  Actions can include email notifications, log updates, automatic field value updates, integration with other applications etc.\r\n\r\nWe currently ship 3 action packages - Log Updates, Email Notifications and Trigger Action.  Each of these are important in providing automation in the AIMS.\r\n\r\nYou can create your own action package (via PHP code) and include them in the /actions directory.  They will then be available for configuration in the AIMS via Administration &gt; Actions &gt; Add (or Manage) Actions', 'user', 'What are actions and why are they so important?'),
(4, 1237464302, 'Yes!  We also provide custom field types including Menu with SubMenu (i.e. dependant fields), dynamicURL field type etc.', 'user', 'Can I configure my own custom fields?');

-- aims_core_log
CREATE TABLE IF NOT EXISTS aims_core_log (
  id INTEGER NOT NULL PRIMARY KEY,
  item_id INTEGER NOT NULL DEFAULT 0,
  create_date TEXT NOT NULL DEFAULT '0',
  item_identifier TEXT NOT NULL DEFAULT '0',
  log_item_sequence INTEGER NOT NULL,
  log_text TEXT,
  security_id INTEGER,
  role_id INTEGER
);
CREATE INDEX IF NOT EXISTS idx_core_log_item_id ON aims_core_log (item_id);

-- aims_custom_fields
CREATE TABLE IF NOT EXISTS aims_custom_fields (
  custom_field_id INTEGER NOT NULL PRIMARY KEY,
  custom_field_name TEXT,
  field_type TEXT,
  default_value TEXT,
  sub_menu INTEGER DEFAULT 0,
  enabled TEXT,
  field_reference INTEGER,
  data TEXT,
  validation_type TEXT,
  required TEXT,
  data_source_name TEXT,
  menu_relationship TEXT,
  menu_value_links TEXT,
  menu_levels INTEGER DEFAULT 0
);

INSERT INTO aims_custom_fields (custom_field_id, custom_field_name, field_type, default_value, sub_menu, enabled, field_reference, data, validation_type, required) VALUES
(6, 'Ticket Category', 'subMenuChild', '', 0, 'Yes', 6, '', NULL, NULL),
(7, 'Ticket Group', 'subMenu', '', 6, 'Yes', 7, '', NULL, NULL),
(1, 'Description', 'textArea', '', 0, 'Yes', 1, NULL, NULL, NULL),
(2, 'Priority', 'menu', 'Please Select', 0, 'Yes', 2, NULL, NULL, NULL),
(3, 'Status', 'menu', 'Please Select', 0, 'Yes', 3, NULL, NULL, NULL),
(4, 'Severity', 'menu', 'Please Select', 0, 'Yes', 4, NULL, NULL, NULL),
(5, 'Project', 'menu', 'Please Select', 0, 'Yes', 5, NULL, NULL, NULL),
(8, 'Cost Center', 'menu', '', 0, 'Yes', 8, '', '', 'No'),
(9, 'Job Code', 'menu', '', 0, 'Yes', 9, '', '', 'No'),
(10, 'Fixed Cost', 'menu', '', 0, 'Yes', 10, '', '', 'No'),
(11, 'Time Spent (Minutes)', 'workerField', '', 0, 'Yes', 11, '', 'numeric', 'No'),
(12, 'Categories', 'subMenuChild', '', 0, 'Yes', 12, '', '', 'Yes'),
(13, 'Category Group', 'subMenu', '', 12, 'Yes', 13, '', '', 'Yes'),
(14, 'Subject', 'menu', '', 0, 'Yes', 14, '', '', 'Yes'),
(15, 'Article Summary', 'textArea', '', 0, 'Yes', 15, '', '', 'Yes'),
(16, 'Keywords', 'textBox', '', 0, 'Yes', 16, '', '', 'No'),
(17, 'Featured Article', 'menu', 'No', 0, 'Yes', 17, '', '', 'Yes');

-- aims_custom_field_menu_values
CREATE TABLE IF NOT EXISTS aims_custom_field_menu_values (
  menu_value_id INTEGER NOT NULL PRIMARY KEY,
  custom_field_id INTEGER NOT NULL,
  menu_value TEXT NOT NULL,
  sub_menu_values TEXT,
  parent_menu_value_id INTEGER NOT NULL DEFAULT 0
);

INSERT INTO aims_custom_field_menu_values (menu_value_id, custom_field_id, menu_value, sub_menu_values) VALUES
(13, 7, 'Software Issue', 'Corrupted Installation,Require Installation,Update Required,Vendor Issue'),
(2, 2, '1', NULL),
(3, 2, '2', NULL),
(4, 2, '3', NULL),
(5, 2, '4', NULL),
(6, 4, 'Workaround In Place', NULL),
(7, 4, 'System Outage', NULL),
(8, 4, 'Minor Impact', NULL),
(9, 3, 'Open', NULL),
(10, 3, 'Closed', NULL),
(11, 3, 'Pending Client Update', NULL),
(12, 3, 'On Hold', NULL),
(14, 7, 'Hardware Issue', 'Fault,Upgrade Required'),
(15, 7, 'Other', 'Network Issue,Security Issue,Training Required'),
(16, 5, 'Sample Project', NULL),
(17, 17, 'Yes', NULL),
(18, 17, 'No', NULL);

-- aims_groups
CREATE TABLE IF NOT EXISTS aims_groups (
  group_id INTEGER NOT NULL PRIMARY KEY,
  group_name TEXT NOT NULL,
  description TEXT,
  role TEXT NOT NULL
);

INSERT INTO aims_groups (group_id, group_name, description, role) VALUES
(1, 'All Supporters', 'Default OneOrZero group for non-assigned users', '3'),
(2, 'Standard User', 'Standard user privileges', '4'),
(3, 'Administrator', 'Standard item administrator privileges', '2'),
(4, 'Manager', 'Standard Item Manager', '3'),
(5, 'Report Manager', 'Group to manage reports', '3');

-- aims_group_members
CREATE TABLE IF NOT EXISTS aims_group_members (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  "groups" TEXT NOT NULL
);

INSERT INTO aims_group_members (id, user_id, "groups") VALUES
(14, 1, '}-{1}-{2}-{3}-{4}-{5}-{');

-- aims_items
CREATE TABLE IF NOT EXISTS aims_items (
  item_id INTEGER NOT NULL PRIMARY KEY,
  create_date INTEGER NOT NULL,
  core_log_updated INTEGER,
  item_type_id INTEGER NOT NULL,
  creator_security INTEGER,
  user_security INTEGER NOT NULL,
  group_security TEXT,
  item_title TEXT NOT NULL,
  custom_field_1 TEXT,
  custom_field_2 TEXT,
  custom_field_3 TEXT,
  custom_field_4 TEXT,
  custom_field_5 TEXT,
  custom_field_6 TEXT,
  custom_field_7 TEXT,
  custom_field_8 TEXT,
  custom_field_9 TEXT,
  custom_field_10 TEXT,
  custom_field_12 TEXT,
  custom_field_13 TEXT,
  custom_field_14 TEXT,
  custom_field_15 TEXT,
  custom_field_16 TEXT,
  custom_field_17 TEXT
);
CREATE INDEX IF NOT EXISTS idx_items_create_date ON aims_items (create_date);
CREATE INDEX IF NOT EXISTS idx_items_title ON aims_items (item_title);

INSERT INTO aims_items (item_id, create_date, core_log_updated, item_type_id, creator_security, user_security, group_security, item_title, custom_field_1, custom_field_2, custom_field_3, custom_field_4, custom_field_5, custom_field_6, custom_field_7, custom_field_8, custom_field_9, custom_field_10, custom_field_12, custom_field_13, custom_field_14, custom_field_15, custom_field_16, custom_field_17) VALUES
(1, 1237463797, 1237463797, 1, 1, 1, '}-{1}-{2}-{3}-{4}-{5}-{', 'Sample Ticket', 'Always good to put a description in!', '1', 'Open', 'Workaround In Place', 'Sample Project', 'Corrupted Installation', 'Software Issue', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- aims_item_attachments
CREATE TABLE IF NOT EXISTS aims_item_attachments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  item_id INTEGER,
  file_name TEXT,
  file_type TEXT,
  file_size INTEGER,
  downloads INTEGER NOT NULL DEFAULT 0,
  added_by INTEGER NOT NULL,
  create_date INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS idx_item_attachments_item_id ON aims_item_attachments (item_id);

-- aims_item_types
CREATE TABLE IF NOT EXISTS aims_item_types (
  item_type_id INTEGER NOT NULL PRIMARY KEY,
  item_type_name TEXT,
  user_security TEXT,
  group_security TEXT,
  enabled TEXT
);

INSERT INTO aims_item_types (item_type_id, item_type_name, user_security, group_security, enabled) VALUES
(1, 'Helpdesk Ticket', '', '}-{1}-{2}-{3}-{4}-{5}-{', 'Yes'),
(2, 'Knowledge Base Article', NULL, '}-{1}-{2}-{3}-{4}-{5}-{', 'Yes');

-- aims_item_type_custom_fields
CREATE TABLE IF NOT EXISTS aims_item_type_custom_fields (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  item_type_id INTEGER NOT NULL DEFAULT 0,
  custom_field_id INTEGER NOT NULL DEFAULT 0,
  custom_field_order INTEGER NOT NULL DEFAULT 0
);

INSERT INTO aims_item_type_custom_fields (id, item_type_id, custom_field_id, custom_field_order) VALUES
(49, 1, 5, 2),
(48, 1, 4, 5),
(47, 1, 3, 3),
(46, 1, 2, 4),
(45, 1, 1, 1),
(44, 1, 7, 6),
(43, 1, 6, 7),
(94, 1, 11, 10),
(95, 2, 1, 2),
(96, 2, 12, 4),
(97, 2, 13, 3),
(98, 2, 14, 2),
(99, 2, 15, 1),
(100, 2, 16, 6),
(101, 2, 17, 5);

-- aims_AIMS_reportmanager_multi
CREATE TABLE IF NOT EXISTS aims_AIMS_reportmanager_multi (
  report_id INTEGER NOT NULL PRIMARY KEY,
  report_name TEXT NOT NULL,
  bound_reports TEXT,
  security_group TEXT NOT NULL
);

INSERT INTO aims_AIMS_reportmanager_multi (report_id, report_name, bound_reports, security_group) VALUES
(3, 'Sample Multi Report for business area X', '1}-{0}-{2', '1');

-- aims_AIMS_reportmanager_reports
CREATE TABLE IF NOT EXISTS aims_AIMS_reportmanager_reports (
  report_id INTEGER NOT NULL PRIMARY KEY,
  report_name TEXT NOT NULL,
  saved_searches TEXT,
  security_group TEXT NOT NULL
);

INSERT INTO aims_AIMS_reportmanager_reports (report_id, report_name, saved_searches, security_group) VALUES
(1, 'Status Report', '8}-{9', '1'),
(0, 'Priority Report', '10}-{11}-{12}-{13', '1'),
(2, 'Service Level Report', '8}-{10}-{14}-{15', '1');

-- aims_AIMS_timemanager_temp_time_table
CREATE TABLE IF NOT EXISTS aims_AIMS_timemanager_temp_time_table (
  time_entry_id INTEGER NOT NULL PRIMARY KEY,
  start_date INTEGER,
  add_date INTEGER,
  ammended_add_date INTEGER,
  user_id INTEGER,
  item_id INTEGER,
  entry_identifier TEXT,
  sequence INTEGER,
  minutes INTEGER,
  project TEXT,
  job_code TEXT,
  cost_center TEXT,
  fixed_cost TEXT
);

-- aims_AIMS_timemanager_time_table
CREATE TABLE IF NOT EXISTS aims_AIMS_timemanager_time_table (
  time_entry_id INTEGER NOT NULL PRIMARY KEY,
  start_date INTEGER,
  add_date INTEGER,
  ammended_add_date INTEGER,
  user_id INTEGER,
  item_id INTEGER,
  entry_identifier TEXT,
  sequence INTEGER,
  minutes INTEGER,
  project TEXT,
  job_code TEXT,
  cost_center TEXT,
  fixed_cost TEXT
);

-- aims_saved_searches
CREATE TABLE IF NOT EXISTS aims_saved_searches (
  search_id INTEGER PRIMARY KEY AUTOINCREMENT,
  "user" TEXT NOT NULL,
  search_name TEXT NOT NULL,
  search_description TEXT,
  saved_search_sql TEXT,
  application TEXT
);

INSERT INTO aims_saved_searches (search_id, "user", search_name, search_description, saved_search_sql, application) VALUES
(2, 'all', 'Closed Tickets', 'Item type ''Helpdesk Ticket'' where the status is equal to ''Closed''', 'SELECT item_id, item_type_id, item_title FROM aims_items WHERE item_type_id = ''1'' AND custom_field_3 = ''Closed'' ', 'app_oneorzerohelpdesk_main'),
(3, 'all', 'Open Tickets', 'Item type''Helpdesk Ticket'' where the status is not equal to ''Closed''', 'SELECT item_id, item_type_id, item_title FROM aims_items WHERE item_type_id = ''1'' AND custom_field_3 <> ''Closed'' ', 'app_oneorzerohelpdesk_main'),
(4, 'all', 'Open Tickets - Priority 1', 'Item type ''Helpdesk Ticket'' where the status is not equal to ''Closed'' and priority is equal to 1', 'SELECT item_id, item_type_id, item_title FROM aims_items WHERE item_type_id = ''1'' AND custom_field_2 = ''1'' AND custom_field_3 <> ''Closed'' ', 'app_oneorzerohelpdesk_main'),
(5, 'all', 'Open Tickets - System Outage', 'Item type ''Helpdesk Ticket'' where the status is not equal to ''Closed'' and severity is equal to ''System Outage''', 'SELECT item_id, item_type_id, item_title FROM aims_items WHERE item_type_id = ''1'' AND custom_field_4 = ''System Outage'' AND custom_field_3 <> ''Closed'' ', 'app_oneorzerohelpdesk_main'),
(7, 'all', 'Open Tickets - Last Update Less Than 1 Hour Ago', 'Items of type ''Helpdesk Ticket'' that were updated less than 1 hour ago and are not closed', 'SELECT item_id, item_type_id, item_title FROM aims_items WHERE item_type_id = ''1'' AND core_log_updated >= (UNIX_TIMESTAMP() - 3600) AND custom_field_3 <> ''Closed'' ', 'app_oneorzerohelpdesk_main'),
(6, 'all', 'Open Tickets - Last Update More Than 12 Hours Ago', 'Items of type ''Helpdesk Ticket'' that were updated more than 12 hours ago and are not closed', 'SELECT item_id, item_type_id, item_title FROM aims_items WHERE item_type_id = ''1'' AND core_log_updated <= (UNIX_TIMESTAMP() - 43200) AND custom_field_3 <> ''Closed'' ', 'app_oneorzerohelpdesk_main'),
(8, 'system', 'Open Tickets', 'Report Manager criteria (hidden outside report manager)', 'SELECT item_id, item_type_id, item_title FROM aims_items WHERE item_type_id = ''1'' AND custom_field_3 <> ''Closed'' ', 'app_oneorzeroreportmanager_main'),
(9, 'system', 'Closed Tickets', 'Report Manager criteria (hidden outside report manager)', 'SELECT item_id, item_type_id, item_title FROM aims_items WHERE item_type_id = ''1'' AND custom_field_3 = ''Closed'' ', 'app_oneorzeroreportmanager_main'),
(10, 'system', 'Priority 1 Tickets', 'Report Manager criteria (hidden outside report manager)', 'SELECT item_id, item_type_id, item_title FROM aims_items WHERE item_type_id = ''1'' AND custom_field_2 = ''1'' ', 'app_oneorzeroreportmanager_main'),
(11, 'system', 'Priority 2 Tickets', 'Report Manager criteria (hidden outside report manager)', 'SELECT item_id, item_type_id, item_title FROM aims_items WHERE item_type_id = ''1'' AND custom_field_2 = ''2'' ', 'app_oneorzeroreportmanager_main'),
(12, 'system', 'Priority 3 Tickets', 'Report Manager criteria (hidden outside report manager)', 'SELECT item_id, item_type_id, item_title FROM aims_items WHERE item_type_id = ''1'' AND custom_field_2 = ''3'' ', 'app_oneorzeroreportmanager_main'),
(13, 'system', 'Priority 4 Tickets', 'Report Manager criteria (hidden outside report manager)', 'SELECT item_id, item_type_id, item_title FROM aims_items WHERE item_type_id = ''1'' AND custom_field_2 = ''4'' ', 'app_oneorzeroreportmanager_main'),
(16, 'system', 'Open Tickets - Priority 1 (sort by priority)', 'Shows open items of type ''Helpdesk Ticket'' sorted by priority.  This is a system search used for the Helpdesk Application only.', 'SELECT item_id, item_title, create_date, custom_field_2, custom_field_3 FROM aims_items WHERE item_type_id = ''1'' AND custom_field_3 <> ''Closed''  ORDER BY custom_field_2 ', 'app_oneorzerohelpdesk_main');

-- aims_system_log
CREATE TABLE IF NOT EXISTS aims_system_log (
  item_id INTEGER NOT NULL,
  event_id TEXT NOT NULL,
  event_counter INTEGER NOT NULL,
  event_string TEXT,
  create_date INTEGER NOT NULL,
  security_id INTEGER,
  PRIMARY KEY (item_id, event_id)
);

-- aims_users
CREATE TABLE IF NOT EXISTS aims_users (
  user_id INTEGER NOT NULL PRIMARY KEY,
  first_name TEXT,
  last_name TEXT,
  user_name TEXT,
  email TEXT,
  pager_email TEXT,
  password TEXT,
  office TEXT,
  phone TEXT,
  theme TEXT DEFAULT 'default',
  secret_question TEXT,
  secret_answer TEXT,
  lastactive TEXT DEFAULT '0',
  language TEXT DEFAULT 'English',
  time_offset TEXT DEFAULT '0',
  title TEXT,
  address TEXT,
  city TEXT,
  state_province TEXT,
  zip_postal TEXT,
  country TEXT,
  website TEXT,
  other TEXT,
  show_header TEXT,
  show_graphics TEXT,
  home_controller TEXT,
  home_controller_name TEXT,
  role INTEGER,
  settings TEXT
);

INSERT INTO aims_users (user_id, first_name, last_name, user_name, email, pager_email, password, office, phone, theme, secret_question, secret_answer, lastactive, language, time_offset, title, address, city, state_province, zip_postal, country, website, other, show_header, show_graphics, home_controller, home_controller_name, role, settings) VALUES
(1, 'Administrative', 'User', 'Administrator', 'email.test@email.com', '', '5f4dcc3b5aa765d61d8327deb882cf99', 'office', '', 'adlexone', 'my', 'secret', 'active', 'English', '', 'TITLE', '', '', '', '', '', '', '', 'Yes', 'Yes', 'home', 'Home', 0, '{SHOW-HIDE=TRUE}');

-- user_identities (OIDC / SSO links)
CREATE TABLE IF NOT EXISTS user_identities (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  provider TEXT NOT NULL,
  subject TEXT NOT NULL,
  email TEXT,
  created_at INTEGER NOT NULL,
  UNIQUE(provider, subject)
);

COMMIT;
