-- Veritabanı Sıfırlama Scripti
-- DİKKAT: Bu script tüm verileri silecektir!

-- 1. Önce bağımlı tablolardaki verileri temizle
SET FOREIGN_KEY_CHECKS = 0;

-- Yoklama kayıtlarını sil
TRUNCATE TABLE attendance;

-- Ödeme kayıtlarını sil
TRUNCATE TABLE payments;

-- Gider kayıtlarını sil
TRUNCATE TABLE expenses;

-- İşlenen konuları sil
TRUNCATE TABLE topics;

-- Notları sil
TRUNCATE TABLE notes;

-- Öğrenci-dönem ilişkilerini sil
TRUNCATE TABLE student_periods;

-- Öğrenci-veli ilişkilerini sil (eğer çoklu veli tablosu varsa)
TRUNCATE TABLE student_parents;

-- Öğrencileri sil
TRUNCATE TABLE students;

-- Velileri sil
TRUNCATE TABLE parents;

-- Dersleri sil
TRUNCATE TABLE lessons;

-- Sınıfları sil
TRUNCATE TABLE classrooms;

-- Dönemleri sil
TRUNCATE TABLE periods;

-- Admin dışındaki kullanıcıları temizle (ilk admini koruyalım)
DELETE FROM admins WHERE id > 1;

-- 2. Auto increment değerlerini sıfırla
ALTER TABLE attendance AUTO_INCREMENT = 1;
ALTER TABLE payments AUTO_INCREMENT = 1;
ALTER TABLE expenses AUTO_INCREMENT = 1;
ALTER TABLE topics AUTO_INCREMENT = 1;
ALTER TABLE notes AUTO_INCREMENT = 1;
ALTER TABLE student_periods AUTO_INCREMENT = 1;
ALTER TABLE student_parents AUTO_INCREMENT = 1;
ALTER TABLE students AUTO_INCREMENT = 1;
ALTER TABLE parents AUTO_INCREMENT = 1;
ALTER TABLE lessons AUTO_INCREMENT = 1;
ALTER TABLE classrooms AUTO_INCREMENT = 1;
ALTER TABLE periods AUTO_INCREMENT = 1;
ALTER TABLE admins AUTO_INCREMENT = 2;

-- 3. Varsayılan değerleri ekle

-- Yeni bir dönem ekle
INSERT INTO periods (name, type, start_date, end_date, status) 
VALUES ('2024-2025 Güz Dönemi', 'fall', '2024-09-01', '2025-01-31', 'active');

-- Örnek sınıflar ekle
INSERT INTO classrooms (name, capacity, age_group, status) VALUES 
('Robotik 101', 6, '6-8 yaş', 'active'),
('Robotik 102', 6, '9-12 yaş', 'active'),
('Robotik 103', 6, '13+ yaş', 'active');

SET FOREIGN_KEY_CHECKS = 1;

-- Sıfırlama tamamlandı
SELECT 'Veritabanı başarıyla sıfırlandı!' as message;