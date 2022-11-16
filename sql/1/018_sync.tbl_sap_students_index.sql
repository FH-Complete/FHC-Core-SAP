DO $$
BEGIN
	CREATE UNIQUE INDEX idx_sap_students_sap_user_id ON sync.tbl_sap_students USING btree (sap_user_id);
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
