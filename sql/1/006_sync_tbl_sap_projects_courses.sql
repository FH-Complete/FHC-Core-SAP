CREATE TABLE IF NOT EXISTS sync.tbl_sap_projects_courses (
	project_id character varying(42) NOT NULL,
	project_object_id character varying(42) NOT NULL,
	studiensemester_kurzbz character varying(16) NOT NULL,
	studiengang_kz integer NOT NULL
);

COMMENT ON TABLE sync.tbl_sap_projects_courses IS 'Synchronization table with SAP ByD projects tasks and FHC courses';
COMMENT ON COLUMN sync.tbl_sap_projects_courses.project_id IS 'SAP Project ID';
COMMENT ON COLUMN sync.tbl_sap_projects_courses.project_object_id IS 'SAP Project Object ID ';
COMMENT ON COLUMN sync.tbl_sap_projects_courses.studiensemester_kurzbz IS 'FH Complete study semester';
COMMENT ON COLUMN sync.tbl_sap_projects_courses.studiengang_kz IS 'FH Course ID';

DO $$
BEGIN
	ALTER TABLE sync.tbl_sap_projects_courses
	ADD CONSTRAINT tbl_sap_projects_courses_pkey
	PRIMARY KEY (project_id, project_object_id, studiensemester_kurzbz, project_task_id, project_task_object_id, studiengang_kz);
	EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
BEGIN
	ALTER TABLE sync.tbl_sap_projects_courses ADD CONSTRAINT tbl_sap_projects_courses_studiensemester_kurzbz_fkey FOREIGN KEY (studiensemester_kurzbz)
	REFERENCES public.tbl_studiensemester(studiensemester_kurzbz) ON UPDATE CASCADE ON DELETE RESTRICT;
	EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
BEGIN
	ALTER TABLE sync.tbl_sap_projects_courses ADD CONSTRAINT tbl_sap_projects_courses_studiengang_kz_fkey FOREIGN KEY (studiengang_kz)
	REFERENCES public.tbl_studiengang(studiengang_kz) ON UPDATE CASCADE ON DELETE RESTRICT;
	EXCEPTION WHEN OTHERS THEN NULL;
END $$;

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE sync.tbl_sap_projects_courses TO vilesci;
GRANT SELECT ON TABLE sync.tbl_sap_projects_courses TO web;

