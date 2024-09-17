DO $$
BEGIN
	ALTER TABLE sync.tbl_sap_projects_timesheets ADD COLUMN time_recording_work_description boolean DEFAULT FALSE;
	EXCEPTION WHEN OTHERS THEN NULL;
END $$;

COMMENT ON COLUMN sync.tbl_sap_projects_timesheets.time_recording_work_description IS 'Project/task time recording description on/off';

