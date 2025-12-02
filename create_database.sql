-- Database for ADESCO accounting system
CREATE DATABASE IF NOT EXISTS adesco_accounting;

USE adesco_accounting;

-- Table for accounting entries (general entries)
CREATE TABLE accounting_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    description VARCHAR(255) NOT NULL,
    entry_type ENUM('entrada', 'salida') NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table for members
CREATE TABLE members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    address VARCHAR(255),
    phone VARCHAR(20),
    email VARCHAR(255),
    registration_date DATE NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table for water payments
CREATE TABLE water_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    payment_month INT NOT NULL, -- 1-12
    payment_year INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_date DATE,
    status ENUM('pending', 'paid', 'overdue') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
);

-- Insert some sample members
INSERT INTO members (name, address, phone, email, registration_date) VALUES
('Juan Pérez', 'Calle 123, Ciudad', '123-456-7890', 'juan@example.com', '2024-01-15'),
('María López', 'Avenida 456, Ciudad', '098-765-4321', 'maria@example.com', '2024-02-20'),
('Carlos García', 'Boulevard 789, Ciudad', '555-123-4567', 'carlos@example.com', '2024-03-10'),
('Ana Rodríguez', 'Carrera 321, Ciudad', '444-987-6543', 'ana@example.com', '2024-04-05');

-- Insert some sample water payments
INSERT INTO water_payments (member_id, payment_month, payment_year, amount, payment_date, status) VALUES
(1, 1, 2025, 25.00, '2025-01-15', 'paid'),   -- Juan - Enero 2025
(1, 2, 2025, 25.00, '2025-02-15', 'paid'),   -- Juan - Febrero 2025
(1, 3, 2025, 25.00, NULL, 'pending'),        -- Juan - Marzo 2025
(2, 1, 2025, 25.00, '2025-01-20', 'paid'),   -- María - Enero 2025
(2, 2, 2025, 25.00, NULL, 'pending'),        -- María - Febrero 2025
(2, 3, 2025, 25.00, NULL, 'pending'),        -- María - Marzo 2025
(3, 1, 2025, 25.00, '2025-01-10', 'paid'),   -- Carlos - Enero 2025
(3, 2, 2025, 25.00, '2025-02-10', 'paid'),   -- Carlos - Febrero 2025
(3, 3, 2025, 25.00, '2025-03-10', 'paid'),   -- Carlos - Marzo 2025
(4, 1, 2025, 25.00, '2025-01-25', 'paid'),   -- Ana - Enero 2025
(4, 2, 2025, 25.00, '2025-02-25', 'paid'),   -- Ana - Febrero 2025
(4, 3, 2025, 25.00, NULL, 'pending');        -- Ana - Marzo 2025

-- Insert some sample general accounting entries
INSERT INTO accounting_entries (date, description, entry_type, amount) VALUES
('2025-01-15', 'Cuota mensual socios', 'entrada', 500.00),
('2025-01-20', 'Pago proveedor', 'salida', 200.00),
('2025-02-01', 'Ventas Enero', 'entrada', 1200.00),
('2025-02-05', 'Gastos operativos', 'salida', 350.00),
('2025-03-01', 'Pagos agua marzo', 'entrada', 100.00);  -- Water payments for March