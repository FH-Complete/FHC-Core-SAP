DO $$
BEGIN
ALTER TABLE sync.tbl_sap_mitarbeiter ADD COLUMN last_update_workagreement timestamp without time zone DEFAULT NULL;
EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
BEGIN
ALTER TABLE sync.tbl_sap_mitarbeiter ADD COLUMN last_update timestamp without time zone DEFAULT NULL;
EXCEPTION WHEN OTHERS THEN NULL;
END $$;

COMMENT ON COLUMN sync.tbl_sap_mitarbeiter.last_update_workagreement IS 'Last work agreement record update timestamp, if null then never updated';
COMMENT ON COLUMN sync.tbl_sap_mitarbeiter.last_update IS 'Last record update timestamp, if null then never updated';
