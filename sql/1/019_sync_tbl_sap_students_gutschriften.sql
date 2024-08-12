CREATE TABLE IF NOT EXISTS sync.tbl_sap_students_gutschriften (
    id              VARCHAR(32),
    buchungsnr      bigint NOT NULL,
    done             BOOLEAN DEFAULT false,
    insertamum timestamp DEFAULT now(),
    updateamum timestamp
);

CREATE SEQUENCE IF NOT EXISTS sync.tbl_sap_students_gutschriften_id_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;

GRANT SELECT, UPDATE ON sync.tbl_sap_students_gutschriften_id_seq TO vilesci;

ALTER TABLE sync.tbl_sap_students_gutschriften ALTER COLUMN id SET DEFAULT nextval('sync.tbl_sap_students_gutschriften_id_seq');

DO $$
BEGIN
	ALTER TABLE sync.tbl_sap_students_gutschriften ADD CONSTRAINT tbl_sap_students_gutschriften_pkey PRIMARY KEY (id);
	EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
BEGIN
	ALTER TABLE sync.tbl_sap_students_gutschriften ADD CONSTRAINT tbl_sap_students_gutschriften_person_id_fkey FOREIGN KEY (person_id) REFERENCES public.tbl_person(person_id) ON UPDATE CASCADE ON DELETE RESTRICT;
	EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
    BEGIN
    ALTER TABLE ONLY sync.tbl_sap_students_gutschriften ADD CONSTRAINT tbl_sap_students_gutschriften_buchungsnr_fkey FOREIGN KEY (buchungsnr) REFERENCES public.tbl_konto(buchungsnr) ON UPDATE CASCADE ON DELETE RESTRICT;
    EXCEPTION WHEN OTHERS THEN NULL;
END $$;


GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE sync.tbl_sap_students_gutschriften TO vilesci;
GRANT SELECT ON TABLE sync.tbl_sap_students_gutschriften TO web;

