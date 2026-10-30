-- =========================================================
-- ARSI AGRI-MANAGE (AAM)
-- Migration : 2026_08_13_000036_fix_discrepancy_status_after_rejection.sql
-- Purpose   : Fix discrepancy records that were incorrectly
--             left as 'under_review' after GM rejection.
--
-- ROOT CAUSE:
--   gmRejectReceivingDiscrepancy() was setting
--   warehouse_discrepancies.status = 'under_review' instead of
--   'gm_rejected' (first rejection) or 'rejected' (final rejection).
--   This caused WM dashboard to still show "Under Review" even after
--   GM had rejected and stock was restored.
--
-- FIX:
--   1. Add 'gm_rejected' to warehouse_discrepancies.status ENUM
--   2. Update existing records:
--      - If gm_action = 'rejected' AND receiving is 'final_rejected'
--        → set discrepancy status = 'rejected'
--      - If gm_action = 'rejected' AND receiving is 'gm_rejected'
--        → set discrepancy status = 'gm_rejected'
-- =========================================================

USE `aam_db`;

-- ── 1. Extend ENUM to include 'gm_rejected' ──────────────────────────────────
ALTER TABLE `warehouse_discrepancies`
    MODIFY COLUMN `status`
        ENUM(
            'open',
            'under_review',
            'gm_rejected',
            'rejected',
            'final_rejected',
            'resolved',
            'discrepancy_pending'
        ) NOT NULL DEFAULT 'open';

-- ── 2. Fix records: GM rejected + receiving final_rejected → 'rejected' ──────
UPDATE `warehouse_discrepancies` wd
JOIN `warehouse_receiving` wr
    ON wr.id = wd.reference_id
   AND wd.reference_type = 'receiving'
SET wd.status = 'rejected'
WHERE wd.gm_action = 'rejected'
  AND wr.status IN ('final_rejected', 'gm_rejected')
  AND wr.final_rejected = 1
  AND wd.status = 'under_review';

-- ── 3. Fix records: GM rejected + receiving gm_rejected (first rejection)
--       → 'gm_rejected' ──────────────────────────────────────────────────────
UPDATE `warehouse_discrepancies` wd
JOIN `warehouse_receiving` wr
    ON wr.id = wd.reference_id
   AND wd.reference_type = 'receiving'
SET wd.status = 'gm_rejected'
WHERE wd.gm_action = 'rejected'
  AND wr.status = 'gm_rejected'
  AND wr.final_rejected = 0
  AND wd.status = 'under_review';

-- ── VERIFICATION ─────────────────────────────────────────────────────────────
SELECT
    wd.id,
    wd.discrepancy_number,
    wd.status AS discrepancy_status,
    wd.gm_action,
    wr.status AS receiving_status,
    wr.final_rejected,
    wr.rejection_count
FROM warehouse_discrepancies wd
JOIN warehouse_receiving wr
    ON wr.id = wd.reference_id AND wd.reference_type = 'receiving'
WHERE wd.gm_action = 'rejected'
ORDER BY wd.id DESC;

SELECT 'Migration 2026_08_13_000036 applied — discrepancy status fixed after GM rejection.' AS status;
