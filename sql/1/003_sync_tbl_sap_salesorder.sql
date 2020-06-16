CREATE TABLE IF NOT EXISTS sync.tbl_sap_salesorder (
	buchungsnr bigint NOT NULL,
	sap_sales_order_id character varying(42) NOT NULL,
	insertamum timestamp DEFAULT now()
);

COMMENT ON TABLE sync.tbl_sap_salesorder IS 'Synchronization table with SAP ByD SalesOrders';
COMMENT ON COLUMN sync.tbl_sap_salesorder.buchungsnr IS 'Buchungsnr from FH Complete';
COMMENT ON COLUMN sync.tbl_sap_salesorder.sap_sales_order_id IS 'SalesOrder id from SAP ByD';

DO $$
BEGIN
	ALTER TABLE ONLY sync.tbl_sap_salesorder ADD CONSTRAINT pk_tbl_sap_salesorder PRIMARY KEY (buchungsnr);
	EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
BEGIN
	ALTER TABLE ONLY sync.tbl_sap_salesorder ADD CONSTRAINT fk_tbl_sap_salesorder_buchungsnr FOREIGN KEY (buchungsnr) REFERENCES public.tbl_konto(buchungsnr) ON UPDATE CASCADE ON DELETE RESTRICT;
	EXCEPTION WHEN OTHERS THEN NULL;
END $$;

GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE sync.tbl_sap_salesorder TO vilesci;
GRANT SELECT ON TABLE sync.tbl_sap_salesorder TO web;
