-- Migration: add session timestamp columns to attendance_tbl
-- Run this in Supabase SQL editor
BEGIN;

-- Add columns for detailed session timestamps
ALTER TABLE attendance_tbl
    ADD COLUMN IF NOT EXISTS am_login_time TIME NULL,
    ADD COLUMN IF NOT EXISTS am_logout_time TIME NULL,
    ADD COLUMN IF NOT EXISTS pm_login_time TIME NULL,
    ADD COLUMN IF NOT EXISTS pm_logout_time TIME NULL;

-- Optional: add a partial index to speed up today's attendance lookups
CREATE INDEX IF NOT EXISTS idx_attendance_tbl_lrn_date ON attendance_tbl (student_lrn, date);

COMMIT;

-- Note: after applying this migration, existing code that inserts only login_time/logout_time remains compatible,
-- but the new logger will write the session columns. You may consider migrating old login_time/logout_time into am/pm columns
-- if they follow the same semantics. Example conversion (run once if safe):
-- UPDATE attendance_tbl SET am_login_time = login_time WHERE am_login_time IS NULL AND login_time IS NOT NULL AND date_part('hour', login_time) < 12;
-- UPDATE attendance_tbl SET pm_logout_time = logout_time WHERE pm_logout_time IS NULL AND logout_time IS NOT NULL AND date_part('hour', logout_time) >= 12;
