INSERT INTO system.tbl_jobtypes (type, description) VALUES
('SAPServicesCreate', 'Create services on SAP Business by design'),
('SAPServicesUpdate', 'Update services on SAP Business by design'),
('SAPPaymentCreate', 'Create payments on SAP Business by design'),
('SAPPaymentGutschrift', 'Credit payments on SAP Business by design'),
('SAPUsersCreate', 'Create new users on SAP Business by design'),
('SAPUsersUpdate', 'Update users on SAP Business by design'),
('SyncTimesheetFromSAP', 'Synchronize employees time sheets in FH from SAP'),
('SAPEmployeesCreate', 'Create employee on SAP Business by design')
ON CONFLICT (type) DO NOTHING;

