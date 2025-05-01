-- Create the database
CREATE DATABASE IF NOT EXISTS clinic_management;
USE clinic_management;

-- Create the patients table
CREATE TABLE patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    last_name VARCHAR(255) NOT NULL,
    first_name VARCHAR(255) NOT NULL,
    middle_name VARCHAR(255),
    gender ENUM('Male', 'Female', 'Other') NOT NULL,
    category VARCHAR(100) NOT NULL,
    grade_year VARCHAR(50),
    program_section VARCHAR(100),
    guardian_contact VARCHAR(50),
    CONSTRAINT chk_category CHECK (category IN ('Pre School', 'Elementary', 'JHS', 'SHS', 'College', 'Alumni'))
);

-- Create the grade_years table
CREATE TABLE grade_years (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    category VARCHAR(100) NOT NULL,
    CONSTRAINT chk_gy_category CHECK (category IN ('Pre School', 'Elementary', 'JHS', 'SHS', 'College', 'Alumni'))
);

-- Create the program_sections table
CREATE TABLE program_sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    category VARCHAR(100) NOT NULL,
    CONSTRAINT chk_ps_category CHECK (category IN ('Pre School', 'Elementary', 'JHS', 'SHS', 'College', 'Alumni'))
);

-- Insert sample data for patients
INSERT INTO patients (last_name, first_name, middle_name, gender, category, grade_year, program_section, guardian_contact) VALUES
('Doe', 'John', 'A', 'Male', 'Pre School', 'Kinder', 'Section A', '123-456-7890'),
('Smith', 'Mary', 'B', 'Female', 'Elementary', 'Grade 1', 'Section B', '987-654-3210'),
('Johnson', 'Alice', 'C', 'Female', 'JHS', 'Grade 7', 'Section A', '555-123-4567'),
('Brown', 'Bob', 'D', 'Male', 'SHS', 'Grade 11', 'STEM', '555-987-6543'),
('Davis', 'Charlie', 'E', 'Male', 'College', '1st Year', 'BSIT', '555-111-2222'),
('Wilson', 'Emma', 'F', 'Female', 'Alumni', '4th Year', 'BSIT', '555-222-3333');

-- Insert sample data for grade_years
INSERT INTO grade_years (name, category) VALUES
('Kinder', 'Pre School'),
('Grade 1', 'Elementary'),
('Grade 2', 'Elementary'),
('Grade 3', 'Elementary'),
('Grade 4', 'Elementary'),
('Grade 5', 'Elementary'),
('Grade 6', 'Elementary'),
('Grade 7', 'JHS'),
('Grade 8', 'JHS'),
('Grade 9', 'JHS'),
('Grade 10', 'JHS'),
('Grade 11', 'SHS'),
('Grade 12', 'SHS'),
('1st Year', 'College'),
('2nd Year', 'College'),
('3rd Year', 'College'),
('4th Year', 'College'),
('Graduated', 'Alumni');

-- Insert sample data for program_sections
INSERT INTO program_sections (name, category) VALUES
('Section A', 'Pre School'),
('Section B', 'Pre School'),
('Section A', 'Elementary'),
('Section B', 'Elementary'),
('Section A', 'JHS'),
('Section B', 'JHS'),
('STEM', 'SHS'),
('ABM', 'SHS'),
('BSIT', 'College'),
('BSCS', 'College'),
('BSEd', 'College'),
('N/A', 'Alumni');