-- 1. Membuat database jika belum ada
CREATE DATABASE IF NOT EXISTS `login_system` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 2. Menggunakan database login_system
USE `login_system`;

-- 3. Membuat tabel `users`
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'Manajemen', 'Pegawai') NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `username_UNIQUE` (`username` ASC),
  INDEX `idx_role` (`role` ASC)
) ENGINE = InnoDB;

-- 4. Membuat tabel `activities`
CREATE TABLE IF NOT EXISTS `activities` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `activity_name` VARCHAR(255) NOT NULL,
  `activity_date` DATE NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `unique_code` VARCHAR(20) NOT NULL,
  `user_id` INT NOT NULL,
  `barcode` VARCHAR(32) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `unique_code_UNIQUE` (`unique_code` ASC),
  INDEX `fk_activities_users_idx` (`user_id` ASC),
  INDEX `idx_activity_date` (`activity_date` ASC),
  INDEX `idx_barcode` (`barcode` ASC),
  CONSTRAINT `fk_activities_users`
    FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION
) ENGINE = InnoDB;

-- 5. Membuat tabel `joined_activities`
CREATE TABLE IF NOT EXISTS `joined_activities` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `activity_id` INT NOT NULL,
  `status` ENUM('pending', 'confirmed', 'completed') NULL DEFAULT 'pending',
  `joined_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `attendance_time` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_joined_activities_users_idx` (`user_id` ASC),
  INDEX `fk_joined_activities_activities_idx` (`activity_id` ASC),
  UNIQUE INDEX `user_activity_UNIQUE` (`user_id` ASC, `activity_id` ASC),
  CONSTRAINT `fk_joined_activities_users`
    FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_joined_activities_activities`
    FOREIGN KEY (`activity_id`)
    REFERENCES `activities` (`id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION
) ENGINE = InnoDB;

-- 6. Menambahkan akun admin default SETELAH semua tabel dibuat
INSERT INTO `users` (`username`, `password`, `role`)
SELECT 'admin', MD5('admin123'), 'admin'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `username` = 'admin');