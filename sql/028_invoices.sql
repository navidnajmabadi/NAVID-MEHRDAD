-- SIPAW 028: invoices. Each paid order keeps the card, the tax name and when the invoice was last emailed.
ALTER TABLE billing_orders ADD COLUMN card_brand VARCHAR(24) NULL DEFAULT NULL;
ALTER TABLE billing_orders ADD COLUMN card_last4 CHAR(4) NULL DEFAULT NULL;
ALTER TABLE billing_orders ADD COLUMN tax_label VARCHAR(20) NULL DEFAULT NULL;
ALTER TABLE billing_orders ADD COLUMN invoice_sent_at DATETIME NULL DEFAULT NULL;
