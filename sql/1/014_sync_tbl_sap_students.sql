DO $$
BEGIN
	ALTER TABLE sync.tbl_sap_students ADD COLUMN last_update timestamp DEFAULT NULL;
	EXCEPTION WHEN OTHERS THEN NULL;
END $$;

COMMENT ON COLUMN sync.tbl_sap_students.last_update IS 'Timestamp of the last update';

