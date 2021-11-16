UPDATE sync.tbl_sap_students SET last_update = NOW() WHERE last_update IS NULL;

