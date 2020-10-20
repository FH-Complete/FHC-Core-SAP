DO $$
BEGIN
	ALTER TABLE sync.tbl_sap_projects_timesheets ADD COLUMN name VARCHAR(42);
	EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
BEGIN
	ALTER TABLE sync.tbl_sap_projects_timesheets ADD COLUMN responsible_unit VARCHAR(42);
	EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
BEGIN
	ALTER TABLE sync.tbl_sap_projects_timesheets ADD COLUMN deleted BOOLEAN NOT NULL DEFAULT false;
	EXCEPTION WHEN OTHERS THEN NULL;
END $$;

COMMENT ON COLUMN sync.tbl_sap_projects_timesheets.name IS 'Project/task name';
COMMENT ON COLUMN sync.tbl_sap_projects_timesheets.responsible_unit IS 'Project/task responsible unit';
COMMENT ON COLUMN sync.tbl_sap_projects_timesheets.deleted IS 'If the project/task is deleted in SAP';

