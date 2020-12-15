DO $$
BEGIN
	ALTER TABLE sync.tbl_sap_projects_costcenters ADD COLUMN project_task_type VARCHAR(42) DEFAULT '' NOT NULL;
	EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
BEGIN
	ALTER TABLE sync.tbl_sap_projects_costcenters DROP CONSTRAINT tbl_sap_projects_costcenters_pkey;

	ALTER TABLE sync.tbl_sap_projects_costcenters
	ADD CONSTRAINT tbl_sap_projects_costcenters_pkey
	PRIMARY KEY (project_id, project_object_id, studiensemester_kurzbz, project_task_id, project_task_object_id, oe_kurzbz_sap, project_task_type);
	EXCEPTION WHEN OTHERS THEN NULL;
END $$;

COMMENT ON COLUMN sync.tbl_sap_projects_costcenters.project_task_type IS 'SAP Project Task type';
