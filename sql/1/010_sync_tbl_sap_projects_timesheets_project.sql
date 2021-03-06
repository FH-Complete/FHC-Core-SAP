CREATE TABLE IF NOT EXISTS sync.tbl_projects_timesheets_project (
	projects_timesheets_project_id bigint NOT NULL,
	projects_timesheet_id bigint NOT NULL,
	projekt_id integer NOT NULL,
	projektphase_id integer
);

COMMENT ON TABLE sync.tbl_projects_timesheets_project IS 'Table to map SAP ByD projects and FHC projects';
COMMENT ON COLUMN sync.tbl_projects_timesheets_project.projects_timesheet_id IS 'ID of SAP Project saved in FHC';
COMMENT ON COLUMN sync.tbl_projects_timesheets_project.projekt_id IS 'FHC projekt ID';
COMMENT ON COLUMN sync.tbl_projects_timesheets_project.projektphase_id IS 'FHC projektphase ID';

CREATE SEQUENCE IF NOT EXISTS sync.tbl_projects_timesheets_project_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;

GRANT SELECT, UPDATE ON sync.tbl_projects_timesheets_project_seq TO vilesci;

ALTER TABLE sync.tbl_projects_timesheets_project ALTER COLUMN projects_timesheets_project_id SET DEFAULT nextval('sync.tbl_projects_timesheets_project_seq');

DO $$
BEGIN
	ALTER TABLE sync.tbl_projects_timesheets_project ADD CONSTRAINT tbl_sap_projects_timesheets_project_pkey
	PRIMARY KEY (projects_timesheets_project_id);
	EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
BEGIN
	ALTER TABLE sync.tbl_projects_timesheets_project ADD CONSTRAINT tbl_projects_timesheets_project_projects_timesheet_id_fkey FOREIGN KEY (projects_timesheet_id)
	REFERENCES sync.tbl_sap_projects_timesheets(projects_timesheet_id) ON UPDATE CASCADE ON DELETE RESTRICT;
	EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
BEGIN
	ALTER TABLE sync.tbl_projects_timesheets_project ADD CONSTRAINT tbl_projects_timesheets_project_projekt_id_fkey FOREIGN KEY (projekt_id)
	REFERENCES fue.tbl_projekt(projekt_id) ON UPDATE CASCADE ON DELETE RESTRICT;
	EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
BEGIN
	ALTER TABLE sync.tbl_projects_timesheets_project ADD CONSTRAINT tbl_projects_timesheets_project_projektphase_id_fkey FOREIGN KEY (projektphase_id)
	REFERENCES fue.tbl_projektphase(projektphase_id) ON UPDATE CASCADE ON DELETE RESTRICT;
	EXCEPTION WHEN OTHERS THEN NULL;
END $$;

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE sync.tbl_projects_timesheets_project TO vilesci;
GRANT SELECT ON TABLE sync.tbl_projects_timesheets_project TO web;

