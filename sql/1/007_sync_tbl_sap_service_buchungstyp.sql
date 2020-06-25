CREATE OR REPLACE FUNCTION sync.extension_sap_create_table () RETURNS TEXT AS $$
	CREATE TABLE IF NOT EXISTS sync.tbl_sap_service_buchungstyp (
		sapservicebuchungstyp_id bigint NOT NULL,
		buchungstyp_kurzbz varchar(32) NOT NULL,
		service_id varchar(255) NOT NULL,
		studiensemester_kurzbz varchar(32),
		studiengang_kz integer
	);

	COMMENT ON TABLE sync.tbl_sap_service_buchungstyp IS 'Synchronization table with SAP ByD Services for Payments';

	GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE sync.tbl_sap_service_buchungstyp TO vilesci;
	GRANT SELECT ON TABLE sync.tbl_sap_service_buchungstyp TO web;

	CREATE SEQUENCE sync.tbl_sap_service_buchungstyp_sapservicebuchungstyp_id_seq
	 INCREMENT BY 1
	 NO MAXVALUE
	 NO MINVALUE
	 CACHE 1;
	ALTER TABLE sync.tbl_sap_service_buchungstyp ALTER COLUMN sapservicebuchungstyp_id SET DEFAULT nextval('sync.tbl_sap_service_buchungstyp_sapservicebuchungstyp_id_seq');

	GRANT SELECT, UPDATE ON sync.tbl_sap_service_buchungstyp_sapservicebuchungstyp_id_seq TO vilesci;

	ALTER TABLE sync.tbl_sap_service_buchungstyp ADD CONSTRAINT pk_tbl_sap_service_buchungstyp PRIMARY KEY (sapservicebuchungstyp_id);
	ALTER TABLE sync.tbl_sap_service_buchungstyp ADD CONSTRAINT fk_sap_service_buchungstyp_buchungstyp_kurzbz FOREIGN KEY (buchungstyp_kurzbz) REFERENCES public.tbl_buchungstyp(buchungstyp_kurzbz) ON UPDATE CASCADE ON DELETE RESTRICT;
	ALTER TABLE sync.tbl_sap_service_buchungstyp ADD CONSTRAINT fk_sap_service_buchungstyp_studiensemester_kurzbz FOREIGN KEY (studiensemester_kurzbz) REFERENCES public.tbl_studiensemester(studiensemester_kurzbz) ON UPDATE CASCADE ON DELETE RESTRICT;
	ALTER TABLE sync.tbl_sap_service_buchungstyp ADD CONSTRAINT fk_sap_service_buchungstyp_studiengang_kz FOREIGN KEY (studiengang_kz) REFERENCES public.tbl_studiengang(studiengang_kz) ON UPDATE CASCADE ON DELETE RESTRICT;
	SELECT 'Table added'::text;
 $$
LANGUAGE 'sql';

SELECT
	CASE
	WHEN (SELECT true::BOOLEAN FROM pg_catalog.pg_tables WHERE schemaname = 'sync' AND tablename  = 'tbl_sap_service_buchungstyp')
	THEN (SELECT 'success'::TEXT)
	ELSE (SELECT sync.extension_sap_create_table())
END;

-- Drop function
DROP FUNCTION sync.extension_sap_create_table();
