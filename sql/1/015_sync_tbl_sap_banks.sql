CREATE TABLE IF NOT EXISTS sync.tbl_sap_banks (
	sap_bank_id character varying(42) NOT NULL,
	sap_bank_swift character varying(42) NOT NULL,
	sap_bank_name character varying(1024) NOT NULL
);

COMMENT ON TABLE sync.tbl_sap_banks IS 'Synchronization table with SAP ByD banks';
COMMENT ON COLUMN sync.tbl_sap_banks.sap_bank_id IS 'SAP bank id';
COMMENT ON COLUMN sync.tbl_sap_banks.sap_bank_swift IS 'SAP bank swift code';
COMMENT ON COLUMN sync.tbl_sap_banks.sap_bank_name IS 'SAP bank name';

DO $$
BEGIN
	ALTER TABLE sync.tbl_sap_banks ADD CONSTRAINT tbl_sap_banks_pkey PRIMARY KEY (sap_bank_id, sap_bank_swift);
	EXCEPTION WHEN OTHERS THEN NULL;
END $$;

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE sync.tbl_sap_banks TO vilesci;
GRANT SELECT ON TABLE sync.tbl_sap_banks TO web;

