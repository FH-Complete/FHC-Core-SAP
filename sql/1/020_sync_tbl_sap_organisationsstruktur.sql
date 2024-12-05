CREATE TABLE IF NOT EXISTS sync.tbl_sap_organisationsstruktur
(
	oe_kurzbz varchar(32),
	oe_kurzbz_sap varchar(20)
);
COMMENT ON TABLE sync.tbl_sap_organisationsstruktur IS 'SAP Organisationseinheiten Uebersetzungstabelle';


DO $$
BEGIN
	ALTER TABLE sync.tbl_sap_organisationsstruktur ADD CONSTRAINT pk_tbl_sap_organisationsstruktur_oe_kurzbz PRIMARY KEY (oe_kurzbz);
END $$;

DO $$
BEGIN
	ALTER TABLE sync.tbl_sap_organisationsstruktur ADD CONSTRAINT fk_sap_organisationsstruktur_oe_kurzbz FOREIGN KEY (oe_kurzbz) REFERENCES public.tbl_organisationseinheit(oe_kurzbz) ON UPDATE CASCADE ON DELETE RESTRICT;
END $$;

GRANT SELECT, INSERT, UPDATE, DELETE ON sync.tbl_sap_organisationsstruktur TO vilesci;

