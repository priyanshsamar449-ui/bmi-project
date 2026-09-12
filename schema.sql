-- BMI Calculator database schema
-- Import this in phpMyAdmin (or run via the MySQL CLI) before opening the project.
-- Re-importing this wipes existing data - fine during development, just don't
-- re-run it in production after you have real submitted data you want to keep.

CREATE DATABASE IF NOT EXISTS bmi_calculator;
USE bmi_calculator;

DROP TABLE IF EXISTS bmi_records;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE bmi_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    height FLOAT NOT NULL,
    weight FLOAT NOT NULL,
    age INT NOT NULL,
    gender VARCHAR(10) NOT NULL,
    bmi FLOAT NOT NULL,
    category VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
