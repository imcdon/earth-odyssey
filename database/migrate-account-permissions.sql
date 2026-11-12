-- migrate-account-permissions.sql - Account approval status and customer permission flags.
-- Safe to re-run. No USE statement: on cPanel, select your database in phpMyAdmin first;
-- locally: C:\xampp\mysql\bin\mysql.exe -u root earth_odyssey < database/migrate-account-permissions.sql

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS account_status ENUM('pending', 'approved', 'denied') NOT NULL DEFAULT 'pending' AFTER role,
    ADD COLUMN IF NOT EXISTS perm_library TINYINT(1) NOT NULL DEFAULT 0 AFTER account_status,
    ADD COLUMN IF NOT EXISTS perm_tools TINYINT(1) NOT NULL DEFAULT 0 AFTER perm_library,
    ADD COLUMN IF NOT EXISTS perm_own_ranges TINYINT(1) NOT NULL DEFAULT 0 AFTER perm_tools,
    ADD COLUMN IF NOT EXISTS perm_fishing_reports TINYINT(1) NOT NULL DEFAULT 0 AFTER perm_own_ranges,
    ADD COLUMN IF NOT EXISTS reviewed_at DATETIME NULL AFTER perm_fishing_reports,
    ADD COLUMN IF NOT EXISTS reviewed_by INT NULL AFTER reviewed_at;

-- Staff accounts are always usable; grant full customer+staff access.
UPDATE users
   SET account_status = 'approved',
       perm_library = 1,
       perm_tools = 1,
       perm_own_ranges = 1,
       perm_fishing_reports = 1,
       reviewed_at = COALESCE(reviewed_at, CURRENT_TIMESTAMP)
 WHERE role IN ('author', 'editor');

-- Existing public members keep access (grandfather). After this migration, new signups stay pending.
UPDATE users
   SET account_status = 'approved',
       perm_library = 1,
       perm_tools = 1,
       perm_own_ranges = 1,
       perm_fishing_reports = 1,
       reviewed_at = COALESCE(reviewed_at, CURRENT_TIMESTAMP)
 WHERE role = 'member';
