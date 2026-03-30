-- Copyright (C) 2026 SASU LEMON <https://hellolemon.fr>
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

CREATE TABLE llx_lemoncrm_interaction (
    rowid             INTEGER AUTO_INCREMENT PRIMARY KEY,
    ref               VARCHAR(128) NOT NULL,
    fk_actioncomm     INTEGER NOT NULL,
    interaction_type  VARCHAR(32) NOT NULL,
    fk_soc            INTEGER,
    fk_socpeople      INTEGER,
    fk_user_author    INTEGER NOT NULL,
    summary           TEXT,
    followup_action   TEXT,
    followup_date     DATE,
    followup_time     TIME,
    followup_done     SMALLINT DEFAULT 0,
    followup_mode     VARCHAR(32),
    date_interaction  DATETIME NOT NULL,
    duration_minutes  INTEGER DEFAULT 0,
    direction         VARCHAR(16) DEFAULT 'OUT',
    sentiment         VARCHAR(64),
    prospect_status   VARCHAR(64),
    fk_parent         INTEGER DEFAULT NULL,
    fk_project        INTEGER DEFAULT NULL,
    status            SMALLINT DEFAULT 1,
    datec             DATETIME,
    tms               TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    entity            INTEGER DEFAULT 1
) ENGINE=InnoDB;
