CREATE TABLE IF NOT EXISTS sync.tbl_sap_mitarbeiter (
    mitarbeiter_uid character varying(32) NOT NULL,
    sap_eeid character varying(32) NOT NULL
);

COMMENT ON TABLE sync.tbl_sap_mitarbeiter IS 'Synchronization table with SAP ByD users';
COMMENT ON COLUMN sync.tbl_sap_mitarbeiter.mitarbeiter_uid IS 'Mitarbeiter UID from FH Complete';
COMMENT ON COLUMN sync.tbl_sap_mitarbeiter.sap_eeid IS 'User EEID from SAP ByD';

DO $$
BEGIN
	ALTER TABLE sync.tbl_sap_mitarbeiter ADD CONSTRAINT tbl_sap_mitarbeiter_pkey PRIMARY KEY (mitarbeiter_uid);
	EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
BEGIN
	ALTER TABLE sync.tbl_sap_mitarbeiter
	ADD CONSTRAINT tbl_sap_mitarbeiter_mitarbeiter_uid_fkey
	FOREIGN KEY (mitarbeiter_uid) REFERENCES public.tbl_mitarbeiter(mitarbeiter_uid) ON UPDATE CASCADE ON DELETE RESTRICT;
	EXCEPTION WHEN OTHERS THEN NULL;
END $$;

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE sync.tbl_sap_mitarbeiter TO vilesci;
GRANT SELECT ON TABLE sync.tbl_sap_mitarbeiter TO web;

