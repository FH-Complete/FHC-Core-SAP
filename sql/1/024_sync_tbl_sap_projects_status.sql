CREATE TABLE IF NOT EXISTS sync.tbl_sap_projects_status_intern (
	status numeric,
	description character varying(255)
);

DO $$
BEGIN
	ALTER TABLE sync.tbl_sap_projects_status_intern ADD CONSTRAINT tbl_sap_projects_status_intern_pkey PRIMARY KEY (status);
	EXCEPTION WHEN OTHERS THEN NULL;
END $$;

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE sync.tbl_sap_projects_status_intern TO vilesci;
GRANT SELECT ON TABLE sync.tbl_sap_projects_status_intern TO web;

INSERT INTO sync.tbl_sap_projects_status_intern (status, description) VALUES
    (101, 'Projekt Idee'),
    (102, 'im Entstehen'),
    (103, 'nicht abgegeben/eingereicht/stattgefunden'),
    (104, 'abgegeben/eingereicht'),
    (105, 'abgelehnt/nicht angenommen'),
    (106, 'angenommen/genehmigt'),
    (107, 'laufend'),
    (108, 'abgebrochen'),
    (109, 'abgeschlossen (IP)'),
    (110, 'endberichtet/endabgerechnet'),
    (111, 'ausbezahlt'),
    (112, 'still od. ruhend gelegt')
ON CONFLICT (status) DO NOTHING;