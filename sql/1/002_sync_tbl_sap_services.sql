CREATE TABLE sync.tbl_sap_services (
    person_id bigint NOT NULL,
    sap_service_id character varying(42) NOT NULL
);

COMMENT ON TABLE sync.tbl_sap_services IS 'Synchronization table with SAP ByD services';
COMMENT ON COLUMN sync.tbl_sap_services.person_id IS 'Person id from FH Complete';
COMMENT ON COLUMN sync.tbl_sap_services.sap_service_id IS 'Service id from SAP ByD';

ALTER TABLE ONLY sync.tbl_sap_services ADD CONSTRAINT tbl_sap_services_pkey PRIMARY KEY (person_id, sap_service_id);

ALTER TABLE ONLY sync.tbl_sap_services ADD CONSTRAINT tbl_sap_services_person_id_fkey FOREIGN KEY (person_id) REFERENCES public.tbl_person(person_id) ON UPDATE CASCADE ON DELETE RESTRICT;

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE sync.tbl_sap_services TO vilesci;
GRANT SELECT ON TABLE sync.tbl_sap_services TO web;

