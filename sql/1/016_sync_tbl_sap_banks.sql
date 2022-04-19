DO $$
BEGIN
        ALTER TABLE sync.tbl_sap_banks ADD COLUMN sap_bank_default_national_code VARCHAR(42) DEFAULT NULL;
        EXCEPTION WHEN OTHERS THEN NULL;
END $$;

COMMENT ON COLUMN sync.tbl_sap_banks.sap_bank_default_national_code IS 'SAP bank default national code';

