CREATE TABLE sync.tbl_sap_students (
    person_id bigint NOT NULL,
    sap_user_id character varying(42) NOT NULL
);

COMMENT ON TABLE sync.tbl_sap_students IS 'Synchronization table with SAP ByD users';
COMMENT ON COLUMN sync.tbl_sap_students.person_id IS 'Person id from FH Complete';
COMMENT ON COLUMN sync.tbl_sap_students.sap_user_id IS 'User id from SAP ByD';

ALTER TABLE ONLY sync.tbl_sap_students ADD CONSTRAINT tbl_sap_students_pkey PRIMARY KEY (person_id, sap_user_id);

ALTER TABLE ONLY sync.tbl_sap_students ADD CONSTRAINT tbl_sap_students_person_id_fkey FOREIGN KEY (person_id) REFERENCES public.tbl_person(person_id) ON UPDATE CASCADE ON DELETE RESTRICT;

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE sync.tbl_sap_students TO vilesci;
GRANT SELECT ON TABLE sync.tbl_sap_students TO web;

