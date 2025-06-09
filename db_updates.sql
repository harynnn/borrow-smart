-- Create roles table
CREATE TABLE IF NOT EXISTS roles (
    role_id INT PRIMARY KEY AUTO_INCREMENT,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default roles
INSERT INTO roles (role_name, description) VALUES
('admin', 'System administrator with full access'),
('staff', 'Staff member with instrument management access'),
('student', 'Student with borrowing privileges')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- Create users table
CREATE TABLE IF NOT EXISTS users (
    uid INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    matric VARCHAR(20) UNIQUE,
    department VARCHAR(50),
    password VARCHAR(255) NOT NULL,
    role_id INT NOT NULL,
    status ENUM('pending', 'active', 'suspended', 'deleted') DEFAULT 'pending',
    email_verified BOOLEAN DEFAULT FALSE,
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    verification_token VARCHAR(255),
    email_verified_at TIMESTAMP NULL,
    password_changed_at TIMESTAMP NULL,
    last_login TIMESTAMP NULL,
    last_logout TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(role_id)
);

-- Create sessions table
CREATE TABLE IF NOT EXISTS sessions (
    session_id VARCHAR(255) PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    payload TEXT,
    last_activity TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(uid)
);

-- Create remember_tokens table
CREATE TABLE IF NOT EXISTS remember_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(uid)
);

-- Create password_resets table
CREATE TABLE IF NOT EXISTS password_resets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(uid)
);

-- Create two_factor_codes table
CREATE TABLE IF NOT EXISTS two_factor_codes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    code VARCHAR(6) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(uid)
);

-- Create security_logs table
CREATE TABLE IF NOT EXISTS security_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    event_type VARCHAR(50) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(uid)
);

-- Create instruments table
CREATE TABLE IF NOT EXISTS instruments (
    instrument_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    category VARCHAR(50) NOT NULL,
    condition_status ENUM('excellent', 'good', 'fair', 'poor', 'maintenance') NOT NULL,
    serial_number VARCHAR(100) UNIQUE,
    purchase_date DATE,
    last_maintenance_date DATE,
    next_maintenance_date DATE,
    status ENUM('available', 'borrowed', 'maintenance', 'retired') DEFAULT 'available',
    notes TEXT,
    created_by INT,
    updated_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(uid),
    FOREIGN KEY (updated_by) REFERENCES users(uid)
);

-- Create instrument_images table
CREATE TABLE IF NOT EXISTS instrument_images (
    image_id INT PRIMARY KEY AUTO_INCREMENT,
    instrument_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (instrument_id) REFERENCES instruments(instrument_id)
);

-- Create maintenance_records table
CREATE TABLE IF NOT EXISTS maintenance_records (
    record_id INT PRIMARY KEY AUTO_INCREMENT,
    instrument_id INT NOT NULL,
    maintenance_type VARCHAR(50) NOT NULL,
    description TEXT,
    maintenance_date DATE NOT NULL,
    cost DECIMAL(10,2),
    performed_by VARCHAR(100),
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (instrument_id) REFERENCES instruments(instrument_id),
    FOREIGN KEY (created_by) REFERENCES users(uid)
);

-- Create borrowing_requests table
CREATE TABLE IF NOT EXISTS borrowing_requests (
    request_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    instrument_id INT NOT NULL,
    request_date TIMESTAMP NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    purpose TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
    approved_by INT,
    approved_at TIMESTAMP NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(uid),
    FOREIGN KEY (instrument_id) REFERENCES instruments(instrument_id),
    FOREIGN KEY (approved_by) REFERENCES users(uid)
);

-- Create borrowing_records table
CREATE TABLE IF NOT EXISTS borrowing_records (
    record_id INT PRIMARY KEY AUTO_INCREMENT,
    request_id INT NOT NULL,
    user_id INT NOT NULL,
    instrument_id INT NOT NULL,
    checkout_date TIMESTAMP NOT NULL,
    due_date TIMESTAMP NOT NULL,
    return_date TIMESTAMP NULL,
    condition_out ENUM('excellent', 'good', 'fair', 'poor') NOT NULL,
    condition_in ENUM('excellent', 'good', 'fair', 'poor') NULL,
    checkout_notes TEXT,
    return_notes TEXT,
    checked_out_by INT NOT NULL,
    checked_in_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES borrowing_requests(request_id),
    FOREIGN KEY (user_id) REFERENCES users(uid),
    FOREIGN KEY (instrument_id) REFERENCES instruments(instrument_id),
    FOREIGN KEY (checked_out_by) REFERENCES users(uid),
    FOREIGN KEY (checked_in_by) REFERENCES users(uid)
);

-- Create notifications table
CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(uid)
);

-- Create settings table
CREATE TABLE IF NOT EXISTS settings (
    setting_id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value TEXT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default settings
INSERT INTO settings (setting_key, setting_value, description) VALUES
('max_borrow_days', '14', 'Maximum number of days a student can borrow an instrument'),
('max_active_borrows', '2', 'Maximum number of instruments a student can borrow at once'),
('maintenance_interval_days', '90', 'Default interval in days between instrument maintenance'),
('late_return_penalty', '5.00', 'Daily penalty fee for late returns (in MYR)'),
('system_maintenance_mode', 'false', 'Whether the system is in maintenance mode'),
('email_notifications', 'true', 'Whether to send email notifications'),
('allow_renewals', 'true', 'Whether to allow borrowing renewals'),
('max_renewal_times', '1', 'Maximum number of times a borrowing can be renewed'),
('renewal_days', '7', 'Number of days added when renewing a borrowing')
ON DUPLICATE KEY UPDATE 
    setting_value = VALUES(setting_value),
    description = VALUES(description);

-- Create indexes for performance
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_matric ON users(matric);
CREATE INDEX idx_users_status ON users(status);
CREATE INDEX idx_instruments_status ON instruments(status);
CREATE INDEX idx_borrowing_requests_status ON borrowing_requests(status);
CREATE INDEX idx_borrowing_records_dates ON borrowing_records(checkout_date, due_date, return_date);
CREATE INDEX idx_notifications_user ON notifications(user_id, read_at);
CREATE INDEX idx_security_logs_user ON security_logs(user_id, created_at);
