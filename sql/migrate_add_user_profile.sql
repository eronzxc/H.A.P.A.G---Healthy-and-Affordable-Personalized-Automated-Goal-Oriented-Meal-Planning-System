-- ============================================================
--  H.A.P.A.G. — Migration: Add user profile columns
--  Run this ONCE on your existing database:
--    mysql -u root -p hapag_db < sql/migrate_add_user_profile.sql
-- ============================================================

USE hapag_db;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS sex         ENUM('male','female') NOT NULL DEFAULT 'male'       AFTER goal,
  ADD COLUMN IF NOT EXISTS age         TINYINT UNSIGNED      NOT NULL DEFAULT 25            AFTER sex,
  ADD COLUMN IF NOT EXISTS weight_kg   DECIMAL(5,1)          NOT NULL DEFAULT 65.0          AFTER age,
  ADD COLUMN IF NOT EXISTS height_cm   DECIMAL(5,1)          NOT NULL DEFAULT 165.0         AFTER weight_kg,
  ADD COLUMN IF NOT EXISTS activity    ENUM('sedentary','light','moderate','active','very_active')
                                                             NOT NULL DEFAULT 'moderate'    AFTER height_cm,
  ADD COLUMN IF NOT EXISTS custom_kcal SMALLINT UNSIGNED     NULL     DEFAULT NULL
                                       COMMENT 'User-set calorie target; NULL = auto-calculate'
                                       AFTER activity;

SELECT 'User profile columns added successfully.' AS status;
