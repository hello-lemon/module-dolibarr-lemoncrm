-- LemonCRM v2 migration: threads + project link
ALTER TABLE llx_lemoncrm_interaction ADD COLUMN IF NOT EXISTS fk_parent INTEGER DEFAULT NULL AFTER prospect_status;
ALTER TABLE llx_lemoncrm_interaction ADD COLUMN IF NOT EXISTS fk_project INTEGER DEFAULT NULL AFTER fk_parent;
CREATE INDEX IF NOT EXISTS idx_lemoncrm_fk_parent ON llx_lemoncrm_interaction (fk_parent);
