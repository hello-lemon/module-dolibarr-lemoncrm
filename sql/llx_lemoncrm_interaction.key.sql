-- Copyright (C) 2026 SASU LEMON <https://hellolemon.fr>

ALTER TABLE llx_lemoncrm_interaction ADD UNIQUE INDEX uk_lemoncrm_interaction_ref (ref, entity);
ALTER TABLE llx_lemoncrm_interaction ADD INDEX idx_lemoncrm_interaction_fk_soc (fk_soc);
ALTER TABLE llx_lemoncrm_interaction ADD INDEX idx_lemoncrm_interaction_fk_socpeople (fk_socpeople);
ALTER TABLE llx_lemoncrm_interaction ADD INDEX idx_lemoncrm_interaction_fk_actioncomm (fk_actioncomm);
ALTER TABLE llx_lemoncrm_interaction ADD INDEX idx_lemoncrm_interaction_fk_user_author (fk_user_author);
ALTER TABLE llx_lemoncrm_interaction ADD INDEX idx_lemoncrm_interaction_type (interaction_type);
ALTER TABLE llx_lemoncrm_interaction ADD INDEX idx_lemoncrm_interaction_followup (followup_done, followup_date);
ALTER TABLE llx_lemoncrm_interaction ADD INDEX idx_lemoncrm_interaction_date (date_interaction);
ALTER TABLE llx_lemoncrm_interaction ADD INDEX idx_lemoncrm_interaction_entity (entity);

ALTER TABLE llx_lemoncrm_interaction ADD CONSTRAINT fk_lemoncrm_interaction_fk_soc FOREIGN KEY (fk_soc) REFERENCES llx_societe(rowid);
ALTER TABLE llx_lemoncrm_interaction ADD CONSTRAINT fk_lemoncrm_interaction_fk_user FOREIGN KEY (fk_user_author) REFERENCES llx_user(rowid);
