CREATE TABLE IF NOT EXISTS sync.tbl_projects_employees (
    projects_employees_id bigint NOT NULL,
    mitarbeiter_uid character varying(32) NOT NULL,
    project_task_id varchar(42),
    planstunden numeric(8, 2)
);

CREATE SEQUENCE IF NOT EXISTS sync.tbl_projects_employees_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;

GRANT SELECT, UPDATE ON sync.tbl_projects_employees_seq TO vilesci;

ALTER TABLE sync.tbl_projects_employees ALTER COLUMN projects_employees_id SET DEFAULT nextval('sync.tbl_projects_employees_seq');

DO $$
	BEGIN
		ALTER TABLE sync.tbl_projects_employees ADD CONSTRAINT tbl_projects_employees_pkey
		PRIMARY KEY (projects_employees_id);
	EXCEPTION WHEN OTHERS THEN NULL;
END $$;


DO $$
	BEGIN
		ALTER TABLE sync.tbl_projects_employees
		ADD CONSTRAINT tbl_projects_employees_mitarbeiter_uid_fkey
		FOREIGN KEY (mitarbeiter_uid) REFERENCES public.tbl_mitarbeiter(mitarbeiter_uid) ON UPDATE CASCADE ON DELETE RESTRICT;
	EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
    BEGIN
        ALTER TABLE sync.tbl_projects_employees ADD CONSTRAINT tbl_projects_employees_project_task_id_fkey FOREIGN KEY (project_task_id)
            REFERENCES sync.tbl_sap_projects_timesheets(project_task_id) ON UPDATE CASCADE ON DELETE RESTRICT;
    EXCEPTION WHEN OTHERS THEN NULL;
    END $$;

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE sync.tbl_projects_employees TO vilesci;
GRANT SELECT ON TABLE sync.tbl_projects_employees TO web;
