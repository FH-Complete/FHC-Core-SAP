CREATE TABLE IF NOT EXISTS sync.tbl_sap_projects_timesheets (
	status numeric,
	description character varying(255)
);

DO $$
BEGIN
	ALTER TABLE sync.tbl_sap_projects_status ADD CONSTRAINT tbl_sap_projects_status_pkey PRIMARY KEY (status);
	EXCEPTION WHEN OTHERS THEN NULL;
END $$;

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE sync.tbl_sap_projects_status TO vilesci;
GRANT SELECT ON TABLE sync.tbl_sap_projects_status TO web;

INSERT INTO sync.tbl_sap_projects_status (status, description) VALUES
(1, 'Planning'),
(2, 'Start'),
(3, 'Released'),
(4, 'Stopped'),
(5, 'Closed'),
(6, 'Completed'),
(99, 'Deleted [still synced]')
ON CONFLICT (status) DO NOTHING;