-- Copyright (C) 2026 SASU LEMON <https://hellolemon.fr>

CREATE TABLE llx_c_lemoncrm_prospect_status (
    rowid     INTEGER AUTO_INCREMENT PRIMARY KEY,
    code      VARCHAR(32) NOT NULL,
    label     VARCHAR(128) NOT NULL,
    color     VARCHAR(7),
    position  INTEGER DEFAULT 0,
    active    TINYINT DEFAULT 1,
    entity    INTEGER DEFAULT 1,
    UNIQUE KEY uk_code (code, entity)
) ENGINE=InnoDB;
