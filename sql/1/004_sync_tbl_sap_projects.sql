CREATE TABLE IF NOT EXISTS sync.tbl_sap_projects (
	project_id character varying(42) NOT NULL,
	project_object_id character varying(42) NOT NULL,
	studiensemester_kurzbz character varying(16) NOT NULL
);

COMMENT ON TABLE sync.tbl_sap_projects IS 'Synchronization table with SAP ByD projects';
COMMENT ON COLUMN sync.tbl_sap_projects.project_id IS 'SAP Project ID';
COMMENT ON COLUMN sync.tbl_sap_projects.project_object_id IS 'SAP Project Object ID ';
COMMENT ON COLUMN sync.tbl_sap_projects.studiensemester_kurzbz IS 'FH Complete study semester';

DO $$
BEGIN
	ALTER TABLE sync.tbl_sap_projects
	ADD CONSTRAINT tbl_sap_projects_pkey PRIMARY KEY (project_id, project_object_id, studiensemester_kurzbz);
	EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
BEGIN
	ALTER TABLE sync.tbl_sap_projects
	ADD CONSTRAINT tbl_sap_projects_studiensemester_kurzbz_fkey FOREIGN KEY (studiensemester_kurzbz)
	REFERENCES public.tbl_studiensemester(studiensemester_kurzbz) ON UPDATE CASCADE ON DELETE RESTRICT;
	EXCEPTION WHEN OTHERS THEN NULL;
END $$;

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE sync.tbl_sap_projects TO vilesci;
GRANT SELECT ON TABLE sync.tbl_sap_projects TO web;

