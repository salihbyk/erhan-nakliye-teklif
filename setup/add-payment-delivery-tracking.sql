-- Quotes tablosuna ödeme ve teslimat durumu sütunları ekleme
ALTER TABLE quotes
ADD COLUMN payment_status ENUM('pending', 'paid', 'partial') DEFAULT 'pending' AFTER status,
ADD COLUMN payment_amount DECIMAL(10,2) DEFAULT 0.00 AFTER payment_status,
ADD COLUMN payment_date DATE NULL AFTER payment_amount,
ADD COLUMN delivery_status ENUM('pending', 'in_transit', 'delivered') DEFAULT 'pending' AFTER payment_date,
ADD COLUMN pickup_date DATE NULL AFTER delivery_status,
ADD COLUMN delivery_date DATE NULL AFTER pickup_date,
ADD COLUMN tracking_notes TEXT NULL AFTER delivery_date;