CREATE TABLE IF NOT EXISTS sync.tbl_sap_projects_timesheets (
	projects_timesheet_id bigint NOT NULL,
	project_id character varying(42) NOT NULL,
	project_object_id character varying(42) NOT NULL,
	project_task_id character varying(42),
	project_task_object_id character varying(42),
	start_date timestamp DEFAULT NULL,
	end_date timestamp DEFAULT NULL,
	status numeric NOT NULL,
	updateamum timestamp without time zone DEFAULT NULL
);

COMMENT ON TABLE sync.tbl_sap_projects_timesheets IS 'Table to save SAP ByD Poject IDs';
COMMENT ON COLUMN sync.tbl_sap_projects_timesheets.project_id IS 'SAP Project ID';
COMMENT ON COLUMN sync.tbl_sap_projects_timesheets.project_object_id IS 'SAP Project Object ID ';
COMMENT ON COLUMN sync.tbl_sap_projects_timesheets.project_task_id IS 'SAP Project Task ID';
COMMENT ON COLUMN sync.tbl_sap_projects_timesheets.project_task_object_id IS 'SAP Project Task Object ID ';
COMMENT ON COLUMN sync.tbl_sap_projects_timesheets.start_date IS 'SAP Project/Task start date';
COMMENT ON COLUMN sync.tbl_sap_projects_timesheets.end_date IS 'SAP Project/Task end date';
COMMENT ON COLUMN sync.tbl_sap_projects_timesheets.status IS 'SAP Project/Task status (3 => released)';
COMMENT ON COLUMN sync.tbl_sap_projects_timesheets.updateamum IS 'Last record update timestamp, if null then never updated';

CREATE SEQUENCE IF NOT EXISTS sync.tbl_sap_projects_timesheets_projects_timesheet_id_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;

GRANT SELECT, UPDATE ON sync.tbl_sap_projects_timesheets_projects_timesheet_id_seq TO vilesci;

ALTER TABLE sync.tbl_sap_projects_timesheets ALTER COLUMN projects_timesheet_id SET DEFAULT nextval('sync.tbl_sap_projects_timesheets_projects_timesheet_id_seq');

DO $$
BEGIN
	ALTER TABLE sync.tbl_sap_projects_timesheets ADD CONSTRAINT tbl_sap_projects_timesheets_pkey PRIMARY KEY (projects_timesheet_id);
	EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
BEGIN
	ALTER TABLE sync.tbl_sap_projects_timesheets ADD CONSTRAINT uk_sap_projects_timesheets
	UNIQUE (project_id, project_object_id, project_task_id, project_task_object_id);
	EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
BEGIN
	ALTER TABLE sync.tbl_sap_projects_timesheets ADD CONSTRAINT uk_sap_projects_timesheets_2
	UNIQUE (project_object_id, project_task_object_id);
	EXCEPTION WHEN OTHERS THEN NULL;
END $$;

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE sync.tbl_sap_projects_timesheets TO vilesci;
GRANT SELECT ON TABLE sync.tbl_sap_projects_timesheets TO web;

