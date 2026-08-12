-- =============================================================================
-- GreenLeaf Nursery — PHASE B API Unification
-- Login / Register / Account / Addresses
-- =============================================================================
--
-- AUDIT FINDING
--   Existing schema already supports Phase B fully:
--     users, refresh_tokens, customer_profiles, addresses, password_reset_tokens
--   Auth uses JWT + opaque refresh_tokens (NOT Sanctum personal_access_tokens).
--
--   NO CREATE TABLE / ALTER TABLE required for API unification.
--
-- This file documents authoritative address/default rules and optional
-- verification queries. It does NOT drop or recreate tables.
--
-- PREREQUISITE
--   Laravel migrations applied + nursery_sample_data.sql loaded
--
-- HOW TO LOAD (optional — mostly documentation / verification)
--   /Applications/XAMPP/xamppfiles/bin/mysql -u root nursery_local < database/phase_b_api_unification.sql
-- =============================================================================

SET NAMES utf8mb4;
SET time_zone = '+05:30';

-- -----------------------------------------------------------------------------
-- 1) Schema reference (already present via migrations)
-- -----------------------------------------------------------------------------
-- users(
--   id, name, email UNIQUE, phone UNIQUE NULL, password, status,
--   email_verified_at, phone_verified_at, last_login_at, meta, soft deletes
-- )
-- refresh_tokens(user_id, token_hash, platform, device_id, expires_at, revoked_at, replaced_by_token_id)
-- customer_profiles(user_id, preferred_language, marketing_opt_in, default_address_id UNUSED)
-- addresses(
--   id, user_id, label, name, phone, line1, line2, city, state, postal_code,
--   country CHAR(2) DEFAULT 'IN', is_default, soft deletes
-- )
--
-- NOTE: customer_profiles.default_address_id is intentionally unused.
--       Authoritative default is addresses.is_default (enforced in AddressService).

-- -----------------------------------------------------------------------------
-- 2) Business rules (enforced in PHP, not DB triggers)
-- -----------------------------------------------------------------------------
-- * At most one is_default=1 per user (cleared in transaction on set-default).
-- * Deleting a default address does NOT auto-promote another address.
-- * Cross-user address access is blocked by user_id ownership scope.

-- -----------------------------------------------------------------------------
-- 3) Verification queries (safe SELECT)
-- -----------------------------------------------------------------------------

-- Sample customer + address counts
SELECT u.id, u.email, u.status,
       (SELECT COUNT(*) FROM addresses a WHERE a.user_id = u.id AND a.deleted_at IS NULL) AS address_count,
       (SELECT COUNT(*) FROM addresses a WHERE a.user_id = u.id AND a.is_default = 1 AND a.deleted_at IS NULL) AS default_count
FROM users u
WHERE u.email IN ('asha@example.com', 'ravi@example.com')
  AND u.deleted_at IS NULL;

-- Detect users with more than one default (should be empty after normal API use)
SELECT user_id, COUNT(*) AS defaults
FROM addresses
WHERE is_default = 1 AND deleted_at IS NULL
GROUP BY user_id
HAVING COUNT(*) > 1;

-- =============================================================================
-- Done. Seed passwords remain hashed via Laravel (see SAMPLE_LOGIN_CREDENTIALS.md).
-- =============================================================================
