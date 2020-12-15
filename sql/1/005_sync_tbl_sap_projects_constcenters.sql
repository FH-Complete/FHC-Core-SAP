CREATE TABLE IF NOT EXISTS sync.tbl_sap_projects_costcenters (
	project_id character varying(42) NOT NULL,
	project_object_id character varying(42) NOT NULL,
	project_task_id character varying(42) NOT NULL,
	project_task_object_id character varying(42) NOT NULL,
	project_task_type character varying(42) NOT NULL,
	studiensemester_kurzbz character varying(16) NOT NULL,
	oe_kurzbz_sap character varying(20) NOT NULL
);

COMMENT ON TABLE sync.tbl_sap_projects_costcenters IS 'Synchronization table with SAP ByD projects tasks and FHC cost centers';
COMMENT ON COLUMN sync.tbl_sap_projects_costcenters.project_id IS 'SAP Project ID';
COMMENT ON COLUMN sync.tbl_sap_projects_costcenters.project_object_id IS 'SAP Project Object ID ';
COMMENT ON COLUMN sync.tbl_sap_projects_costcenters.project_task_id IS 'SAP Project Task ID';
COMMENT ON COLUMN sync.tbl_sap_projects_costcenters.project_task_object_id IS 'SAP Project Task Object ID';
COMMENT ON COLUMN sync.tbl_sap_projects_costcenters.project_task_type IS 'SAP Project Task type';
COMMENT ON COLUMN sync.tbl_sap_projects_costcenters.studiensemester_kurzbz IS 'FH Complete study semester';
COMMENT ON COLUMN sync.tbl_sap_projects_costcenters.oe_kurzbz_sap IS 'SAP Cost Center ID';

DO $$
BEGIN
	ALTER TABLE sync.tbl_sap_projects_costcenters
	ADD CONSTRAINT tbl_sap_projects_costcenters_pkey
	PRIMARY KEY (project_id, project_object_id, studiensemester_kurzbz, project_task_id, project_task_object_id, oe_kurzbz_sap, project_task_type);
	EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
BEGIN
	ALTER TABLE sync.tbl_sap_projects_costcenters
	ADD CONSTRAINT tbl_sap_projects_costcenters_studiensemester_kurzbz_fkey FOREIGN KEY (studiensemester_kurzbz)
	REFERENCES public.tbl_studiensemester(studiensemester_kurzbz) ON UPDATE CASCADE ON DELETE RESTRICT;
	EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
BEGIN
	ALTER TABLE sync.tbl_sap_projects_costcenters
	ADD CONSTRAINT tbl_sap_projects_costcenters_oe_kurzbz_sap_fkey FOREIGN KEY (oe_kurzbz_sap)
	REFERENCES sync.tbl_sap_organisationsstruktur(oe_kurzbz_sap) ON UPDATE CASCADE ON DELETE RESTRICT;
	EXCEPTION WHEN OTHERS THEN NULL;
END $$;

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE sync.tbl_sap_projects_costcenters TO vilesci;
GRANT SELECT ON TABLE sync.tbl_sap_projects_costcenters TO web;

