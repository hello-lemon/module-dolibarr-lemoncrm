-- LemonCRM v3 migration : événement agenda de relance lié
ALTER TABLE llx_lemoncrm_interaction ADD COLUMN IF NOT EXISTS fk_actioncomm_followup INTEGER DEFAULT NULL AFTER fk_actioncomm;
