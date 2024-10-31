DO $$
BEGIN
    ALTER TABLE sync.tbl_sap_projects_timesheets ADD COLUMN sap_custom_fields jsonb;
EXCEPTION WHEN OTHERS THEN NULL;
END $$;

COMMENT ON COLUMN sync.tbl_sap_projects_timesheets.sap_custom_fields IS 'Custom SAP fields';


DO $$
BEGIN
    ALTER TABLE sync.tbl_sap_projects_timesheets ADD COLUMN project_leader character varying(32);
EXCEPTION WHEN OTHERS THEN NULL;
END $$;

COMMENT ON COLUMN sync.tbl_sap_projects_timesheets.project_leader IS 'SAP Project Leader';

DO $$
BEGIN
ALTER TABLE sync.tbl_sap_projects_timesheets
    ADD CONSTRAINT tbl_sap_projects_timesheets_project_leader_fkey
        FOREIGN KEY (mitarbeiter_uid) REFERENCES public.tbl_mitarbeiter(mitarbeiter_uid) ON UPDATE CASCADE ON DELETE RESTRICT;
EXCEPTION WHEN OTHERS THEN NULL;
END $$;
