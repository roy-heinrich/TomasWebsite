-- Recommended indexes for PostgreSQL (Supabase) to speed attendance and section queries
-- Run these once in your Supabase SQL editor or psql shell.

-- 1) Composite index on attendance by date and student for fast joins and date filters
CREATE INDEX IF NOT EXISTS idx_attendance_date_student ON attendance_tbl (date, student_lrn);

-- 2) Covering index for queries that look up by student_lrn then date
CREATE INDEX IF NOT EXISTS idx_attendance_student_date ON attendance_tbl (student_lrn, date);

-- 3) Index to accelerate filtering students by year_section and status
CREATE INDEX IF NOT EXISTS idx_student_year_section_status ON student_tbl (year_section) WHERE status = 'Active';

-- 4) Optional: index on attendance times to speed ORDER BY on login_time/logout_time
CREATE INDEX IF NOT EXISTS idx_attendance_login_time ON attendance_tbl (date, login_time DESC);
CREATE INDEX IF NOT EXISTS idx_attendance_logout_time ON attendance_tbl (date, logout_time DESC);

-- Notes:
-- - Supabase may already maintain some indexes for primary keys; these add targeted indexes for the reports.
-- - After creating indexes, monitor query performance and remove unused indexes. Indexes speed reads but add write overhead.
-- - Run EXPLAIN ANALYZE on slow queries to verify the planner uses these indexes.
