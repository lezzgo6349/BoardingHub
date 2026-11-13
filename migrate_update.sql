-- Run once in phpMyAdmin (database: boardinghub) if you already imported the old boardinghub.sql.
ALTER TABLE users ADD COLUMN address VARCHAR(255) DEFAULT NULL AFTER phone;
ALTER TABLE users ADD COLUMN deleted_at DATETIME DEFAULT NULL;
ALTER TABLE payments ALTER COLUMN method SET DEFAULT 'gcash';
