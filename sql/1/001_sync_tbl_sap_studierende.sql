CREATE TABLE sync.tbl_sap_studierende (
    person_id bigint NOT NULL,
    sap_id character varying(42) NOT NULL
);

COMMENT ON TABLE sync.tbl_sap_studierende IS 'Synchronization table with SAP ByD';
COMMENT ON COLUMN sync.tbl_sap_studierende.person_id IS 'Person id from FH Complete';
COMMENT ON COLUMN sync.tbl_sap_studierende.sap_id IS 'Person id from SAP ByD';

ALTER TABLE ONLY sync.tbl_sap_studierende ADD CONSTRAINT tbl_sap_studierende_pkey PRIMARY KEY (person_id, sap_id);

ALTER TABLE ONLY sync.tbl_sap_studierende ADD CONSTRAINT tbl_sap_studierende_person_id_fkey FOREIGN KEY (person_id) REFERENCES public.tbl_person(person_id) ON UPDATE CASCADE ON DELETE RESTRICT;

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE sync.tbl_sap_studierende TO vilesci;
GRANT SELECT ON TABLE sync.tbl_sap_studierende TO web;

