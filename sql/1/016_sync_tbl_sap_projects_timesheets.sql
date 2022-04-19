DO $$
BEGIN
	ALTER TABLE sync.tbl_sap_projects_timesheets ADD COLUMN time_recording boolean DEFAULT FALSE;
	EXCEPTION WHEN OTHERS THEN NULL;
END $$;

COMMENT ON COLUMN sync.tbl_sap_projects_timesheets.time_recording IS 'Project/task time recording on/off';

