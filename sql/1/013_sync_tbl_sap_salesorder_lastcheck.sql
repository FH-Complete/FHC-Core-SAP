DO $$
BEGIN
	ALTER TABLE sync.tbl_sap_salesorder ADD COLUMN lastcheck timestamp default now() NOT NULL;
	EXCEPTION WHEN OTHERS THEN NULL;
END $$;

COMMENT ON COLUMN sync.tbl_sap_salesorder.lastcheck IS 'Last Time checked if paid';
