CREATE DATABASE IF NOT EXISTS dental_clinic;
USE dental_clinic;

-- ============================================
-- 1. USERS TABLE (for authentication)
-- ============================================
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('doctor', 'assistant', 'patient') NOT NULL,
    phone VARCHAR(20),
    profile_image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL
);

-- ============================================
-- 2. PATIENTS TABLE (detailed patient info)
-- ============================================
CREATE TABLE patients (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    full_name VARCHAR(100) NOT NULL,
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other'),
    phone VARCHAR(20),
    email VARCHAR(100),
    emergency_contact_name VARCHAR(100),
    emergency_contact_phone VARCHAR(20),
    emergency_contact_relation VARCHAR(50),
    
    -- Insurance Information
    insurance_provider VARCHAR(100),
    insurance_id VARCHAR(50),
    insurance_type ENUM('Private', 'Social Security', 'Medicaid', 'None') DEFAULT 'None',
    insurance_coverage INT DEFAULT 0, -- percentage
    
    -- Medical History
    medical_history TEXT,
    allergies TEXT,
    current_medications TEXT,
    
    -- Dental History
    dental_history TEXT,
    last_visit_date DATE,
    
    -- Contact & Address
    address VARCHAR(255),
    country VARCHAR(50) DEFAULT 'LB',
    
    -- System Fields
    points INT DEFAULT 0,
    subscription_type ENUM('none', 'basic', 'premium', 'family') DEFAULT 'none',
    subscription_start_date DATE,
    subscription_end_date DATE,
    referral_code VARCHAR(20) UNIQUE,
    referred_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT, -- user who created this record
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (referred_by) REFERENCES patients(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_email (email),
    INDEX idx_phone (phone),
    INDEX idx_insurance (insurance_type)
);

-- ============================================
-- 3. APPOINTMENTS TABLE
-- ============================================
CREATE TABLE appointments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL, -- user with role 'doctor'
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    duration INT DEFAULT 30, -- in minutes
    end_time TIME GENERATED ALWAYS AS (ADDTIME(appointment_time, SEC_TO_TIME(duration * 60))) VIRTUAL,
    
    treatment_type VARCHAR(100) NOT NULL,
    description TEXT,
    chair_number INT,
    
    status ENUM(
        'scheduled', 
        'checked-in', 
        'in-treatment', 
        'completed', 
        'cancelled', 
        'no-show', 
        'follow-up'
    ) DEFAULT 'scheduled',
    
    cancellation_reason TEXT,
    notes TEXT,
    
    -- Reminder tracking
    reminder_sent_48h BOOLEAN DEFAULT FALSE,
    reminder_sent_24h BOOLEAN DEFAULT FALSE,
    reminder_sent_at TIMESTAMP NULL,
    
    -- Billing link (optional)
    invoice_id INT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT, -- user who created this record
    
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_date (appointment_date),
    INDEX idx_status (status),
    INDEX idx_patient (patient_id),
    INDEX idx_doctor (doctor_id),
    
    UNIQUE KEY unique_slot (appointment_date, appointment_time, chair_number)
);

-- ============================================
-- 4. TREATMENT PLANS TABLE
-- ============================================
CREATE TABLE treatment_plans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    plan_name VARCHAR(100) NOT NULL,
    description TEXT,
    
    -- Teeth affected (store as JSON or comma-separated)
    teeth_affected TEXT, -- e.g., "18,19,20"
    
    estimated_cost DECIMAL(10,2),
    actual_cost DECIMAL(10,2),
    discount DECIMAL(10,2) DEFAULT 0,
    
    status ENUM('proposed', 'approved', 'in-progress', 'completed', 'cancelled') DEFAULT 'proposed',
    priority ENUM('low', 'medium', 'high', 'emergency') DEFAULT 'medium',
    
    start_date DATE,
    estimated_end_date DATE,
    actual_end_date DATE,
    
    notes TEXT,
    patient_approved BOOLEAN DEFAULT FALSE,
    approval_date TIMESTAMP NULL,
    approval_signature VARCHAR(255), -- path to signature image
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================
-- 5. TREATMENT STEPS (for multi-step plans)
-- ============================================
CREATE TABLE treatment_steps (
    id INT PRIMARY KEY AUTO_INCREMENT,
    plan_id INT NOT NULL,
    step_number INT NOT NULL,
    procedure_name VARCHAR(100) NOT NULL,
    description TEXT,
    tooth_numbers TEXT,
    duration_minutes INT,
    cost DECIMAL(10,2),
    status ENUM('pending', 'in-progress', 'completed', 'skipped') DEFAULT 'pending',
    completed_date DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (plan_id) REFERENCES treatment_plans(id) ON DELETE CASCADE
);

-- ============================================
-- 6. TOOTH CHART (per patient)
-- ============================================
CREATE TABLE tooth_chart (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    tooth_number INT NOT NULL, -- 1-32
    status ENUM('healthy', 'cavity', 'filled', 'crown', 'root-canal', 'missing', 'implant', 'bridge') DEFAULT 'healthy',
    diagnosis TEXT,
    treatment TEXT,
    notes TEXT,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT,
    
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    
    UNIQUE KEY unique_patient_tooth (patient_id, tooth_number)
);

-- ============================================
-- 7. X-RAYS / IMAGES TABLE
-- ============================================
CREATE TABLE xrays (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT, -- in bytes
    mime_type VARCHAR(50),
    
    xray_type ENUM('Panoramic', 'Bitewing', 'Periapical', 'CBCT', 'Intraoral', 'Other') DEFAULT 'Other',
    tooth_numbers TEXT, -- which teeth are visible
    findings TEXT,
    notes TEXT,
    
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    uploaded_by INT,
    
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================
-- 8. BILLING / INVOICES TABLE
-- ============================================
CREATE TABLE invoices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    patient_id INT NOT NULL,
    appointment_id INT NULL,
    
    invoice_date DATE NOT NULL,
    due_date DATE NOT NULL,
    
    subtotal DECIMAL(10,2) NOT NULL,
    discount_type ENUM('percentage', 'fixed') DEFAULT 'fixed',
    discount_value DECIMAL(10,2) DEFAULT 0,
    discount_amount DECIMAL(10,2) GENERATED ALWAYS AS (
        CASE 
            WHEN discount_type = 'percentage' THEN subtotal * discount_value / 100
            ELSE discount_value
        END
    ) VIRTUAL,
    
    tax_rate DECIMAL(5,2) DEFAULT 0,
    tax_amount DECIMAL(10,2) GENERATED ALWAYS AS ((subtotal - discount_amount) * tax_rate / 100) VIRTUAL,
    
    total_amount DECIMAL(10,2) GENERATED ALWAYS AS (subtotal - discount_amount + tax_amount) VIRTUAL,
    paid_amount DECIMAL(10,2) DEFAULT 0,
    balance_due DECIMAL(10,2) GENERATED ALWAYS AS (total_amount - paid_amount) VIRTUAL,
    
    insurance_type VARCHAR(50),
    insurance_claim_id VARCHAR(100),
    insurance_coverage DECIMAL(10,2) DEFAULT 0,
    insurance_status ENUM('pending', 'approved', 'denied', 'paid') DEFAULT 'pending',
    
    payment_status ENUM('paid', 'partial', 'pending', 'overdue', 'cancelled') DEFAULT 'pending',
    payment_method ENUM('cash', 'card', 'insurance', 'online', 'check') NULL,
    
    notes TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT,
    paid_at TIMESTAMP NULL,
    
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_patient (patient_id),
    INDEX idx_status (payment_status),
    INDEX idx_date (invoice_date)
);

-- ============================================
-- 9. PAYMENTS TABLE (for tracking partial payments)
-- ============================================
CREATE TABLE payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    payment_method ENUM('cash', 'card', 'insurance', 'online', 'check') NOT NULL,
    reference_number VARCHAR(100), -- transaction ID, check number
    notes TEXT,
    received_by INT,
    
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================
-- 10. INVENTORY TABLE
-- ============================================
CREATE TABLE inventory (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_name VARCHAR(100) NOT NULL,
    category VARCHAR(50),
    description TEXT,
    quantity INT DEFAULT 0,
    unit VARCHAR(20), -- pcs, boxes, ml, etc.
    
    reorder_level INT DEFAULT 10,
    reorder_quantity INT DEFAULT 0,
    
    supplier_name VARCHAR(100),
    supplier_contact VARCHAR(100),

    
    cost_per_unit DECIMAL(10,2),
    selling_price DECIMAL(10,2),
    
    expiry_date DATE,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================
-- 11. INVENTORY TRANSACTIONS (stock movements)
-- ============================================
CREATE TABLE inventory_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    inventory_id INT NOT NULL,
    transaction_type ENUM('purchase', 'use', 'adjustment', 'return') NOT NULL,
    quantity_change INT NOT NULL,
    new_quantity INT NOT NULL,
    reason TEXT,
    performed_by INT,
    performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (inventory_id) REFERENCES inventory(id) ON DELETE CASCADE,
    FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================
-- 12. WAITING QUEUE (daily and weekly)
-- ============================================
CREATE TABLE waiting_queue (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NULL,
    patient_name VARCHAR(100), -- for walk-ins without account
    queue_type ENUM('daily', 'weekly') NOT NULL,
    priority ENUM('emergency', 'high', 'medium', 'low') DEFAULT 'medium',
    
    reason VARCHAR(100),
    preferred_treatment VARCHAR(100),
    preferred_day VARCHAR(20), -- for weekly queue
    
    estimated_wait_minutes INT,
    position INT,
    
    status ENUM('waiting', 'notified', 'checked-in', 'cancelled') DEFAULT 'waiting',
    
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notified_at TIMESTAMP NULL,
    checked_in_at TIMESTAMP NULL,
    
    notes TEXT,
    
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE SET NULL
);

-- ============================================
-- 13. NOTIFICATIONS TABLE
-- ============================================
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type ENUM('appointment_reminder', 'treatment_instructions', 'payment_reminder', 'promotion', 'queue_update') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    
    sent_via ENUM('sms', 'email', 'push', 'in-app') DEFAULT 'in-app',
    sent_at TIMESTAMP NULL,
    read_at TIMESTAMP NULL,
    
    related_appointment_id INT NULL,
    related_invoice_id INT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (related_appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
    FOREIGN KEY (related_invoice_id) REFERENCES invoices(id) ON DELETE SET NULL
);

-- ============================================
-- 14. POST-TREATMENT INSTRUCTIONS TEMPLATES
-- ============================================
CREATE TABLE treatment_instructions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    treatment_type VARCHAR(100) NOT NULL,
    instructions TEXT NOT NULL,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default instructions
INSERT INTO treatment_instructions (treatment_type, instructions, is_default) VALUES
('Cleaning', '• No eating/drinking for 30 minutes\n• Avoid hot beverages for 2 hours\n• Brush gently tonight\n• Use desensitizing toothpaste if sensitive', TRUE),
('Filling', '• Do not eat for 2 hours until numbness wears off\n• Avoid hard/sticky foods for 24 hours\n• Brush gently around the area\n• If sensitivity persists, use sensitive toothpaste', TRUE),
('Root Canal', '• Avoid chewing on that side for 24 hours\n• Take prescribed antibiotics as directed\n• No hot drinks for 4 hours\n• Temporary crown may feel different - avoid flossing\n• Call if severe pain or swelling', TRUE),
('Extraction', '• Do not rinse or spit for 24 hours\n• No drinking through straw for 3 days\n• Apply ice packs for first 24 hours\n• Eat soft foods only\n• Slight bleeding is normal - bite on gauze\n• Call if bleeding persists', TRUE),
('Crown', '• Avoid sticky/hard foods for 24 hours\n• Temporary crown - do not floss\n• Permanent crown placement in 2 weeks\n• Sensitivity to hot/cold is normal', TRUE),
('Whitening', '• Avoid dark foods/drinks for 48 hours (coffee, tea, wine)\n• No smoking for 24 hours\n• Use whitening toothpaste provided\n• Temporary sensitivity is normal', TRUE),
('Implant', '• Soft foods only for 2 weeks\n• No chewing on implant site\n• Apply ice packs\n• Take all prescribed medications\n• Gentle rinsing with salt water\n• Follow up in 1 week', TRUE),
('Orthodontics', '• Avoid hard/sticky foods\n• Brush after every meal\n• Use orthodontic wax if brackets irritate\n• Mild soreness is normal\n• Next adjustment in 4 weeks', TRUE);

-- ============================================
-- 15. AUDIT LOG (for compliance)
-- ============================================
CREATE TABLE audit_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    action VARCHAR(50) NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================
-- CREATE INDEXES FOR PERFORMANCE
-- ============================================
CREATE INDEX idx_appointments_date_status ON appointments(appointment_date, status);
CREATE INDEX idx_invoices_patient_status ON invoices(patient_id, payment_status);
CREATE INDEX idx_notifications_user_read ON notifications(user_id, read_at);
CREATE INDEX idx_patients_insurance ON patients(insurance_type);
CREATE INDEX idx_treatment_plans_patient ON treatment_plans(patient_id);


INSERT INTO users (username, email, password_hash, full_name, role, phone)
VALUES
('doctor1','doctor@clinic.com','hash1','Dr. John Smith','doctor','1111111111'),
('assistant1','assistant@clinic.com','hash2','Anna White','assistant','2222222222'),

('sajadyab','p1@mail.com','hash','Saja Dyab','patient','3000000001'),
('zeinaayoub','p2@mail.com','hash','Zeina Ayoub','patient','3000000002'),
('abdali','p3@mail.com','hash','Abd Ali','patient','3000000003'),
('rayankhanafer','p4@mail.com','hash','Rayan Khanafer','patient','3000000004'),
('mousadia','p5@mail.com','hash','Mousa Dia','patient','3000000005'),
('salamamil','p6@mail.com','hash','Salam Amil','patient','3000000006'),
('yasminsrarida','p7@mail.com','hash','Yasmin Srarida','patient','3000000007'),
('jadmostafa','p8@mail.com','hash','Jad Mostafa','patient','3000000008'),
('mohammadali','p9@mail.com','hash','Mohammad Ali','patient','3000000009'),
('genaarbid','p23@mail.com','hash','Gena Arbid','patient','3000000023');

INSERT INTO patients (user_id, full_name, gender, phone, email, insurance_type, points, referral_code, created_by)
VALUES
(3,'Saja Dyab','female','3000000001','p1@mail.com','Private',10,'REF1',2),
(4,'Zeina Ayoub','female','3000000002','p2@mail.com','None',20,'REF2',2),
(5,'Abd Ali','male','3000000003','p3@mail.com','Private',15,'REF3',2),
(6,'Rayan Khanafer','female','3000000004','p4@mail.com','None',5,'REF4',2),
(7,'Mousa Dia','male','3000000005','p5@mail.com','Private',0,'REF5',2),
(8,'Salam Amil','female','3000000006','p6@mail.com','None',8,'REF6',2),
(9,'Yasmin Srarida','male','3000000007','p7@mail.com','Private',12,'REF7',2),
(10,'Jad Mostafa','female','3000000008','p8@mail.com','None',6,'REF8',2),
(11,'Mohammad Ali','male','3000000009','p9@mail.com','Private',3,'REF9',2),
(12,'Gena Arbid','female','3000000010','p10@mail.com','None',11,'REF10',2),
(NULL,'Walk-in 24','male','4000000024','walk24@mail.com','None',0,'REF24',2),
(NULL,'Walk-in 25','female','4000000025','walk25@mail.com','None',0,'REF25',2);

INSERT INTO appointments
(patient_id, doctor_id, appointment_date, appointment_time, treatment_type, chair_number, status, created_by)
VALUES
(3,1,'2026-04-01','11:00:00','Root Canal',2,'scheduled',2),
(4,1,'2026-05-02','09:00:00','Extraction',1,'scheduled',2),
(5,1,'2026-04-02','10:00:00','Whitening',2,'scheduled',2),
(6,1,'2026-05-02','11:00:00','Crown',1,'completed',2),
(7,1,'2026-04-03','09:00:00','Cleaning',1,'scheduled',2),
(8,1,'2026-04-23','10:00:00','Filling',2,'scheduled',2),
(9,1,'2026-04-23','11:00:00','Root Canal',1,'scheduled',2),
(10,1,'2026-03-04','09:00:00','Extraction',2,'scheduled',2),
(10,1,'2026-03-04','10:00:00','Whitening',1,'scheduled',2),
(2,1,'2026-04-14','11:00:00','Crown',2,'scheduled',2),
(3,1,'2026-04-13','09:00:00','Cleaning',1,'scheduled',2),
(4,1,'2026-04-15','10:00:00','Filling',2,'scheduled',2),
(5,1,'2026-04-15','11:00:00','Root Canal',1,'scheduled',2),
(6,1,'2026-04-16','09:00:00','Extraction',2,'scheduled',2);




INSERT INTO treatment_plans
(patient_id, plan_name, description, teeth_affected, estimated_cost, actual_cost, status, priority, start_date, created_by)
VALUES

(3,'Plan 3','Root canal treatment','19',800,NULL,'in-progress','high','2026-03-01',1),
(4,'Plan 4','Extraction case','30',300,NULL,'approved','high','2026-03-02',1),
(5,'Plan 5','Whitening session','',250,NULL,'approved','low','2026-03-02',1),
(6,'Plan 6','Crown placement','3',600,600,'completed','medium','2026-03-02',1),
(7,'Plan 7','Cleaning','8',100,NULL,'approved','low','2026-03-03',1),
(8,'Plan 8','Filling','21',150,NULL,'approved','medium','2026-03-03',1),
(9,'Plan 9','Root canal','17',750,NULL,'approved','high','2026-03-03',1),
(10,'Plan 10','Extraction','2',250,NULL,'approved','high','2026-03-04',1);



INSERT INTO treatment_steps
(plan_id, step_number, procedure_name, duration_minutes, cost, status)
VALUES
(1,1,'Scaling',30,100,'completed'),
(2,1,'Composite Filling',45,200,'completed'),
(3,1,'Canal Preparation',60,400,'in-progress'),
(4,1,'Tooth Removal',40,300,'pending'),
(5,1,'Whitening Gel Application',50,250,'pending'),
(6,1,'Crown Fixation',60,600,'completed'),
(7,1,'Scaling',30,100,'pending'),
(8,1,'Filling',45,150,'pending'),
(9,1,'Root Canal',60,750,'pending'),
(10,1,'Extraction',40,250,'pending'),
(11,1,'Whitening',45,300,'pending'),
(12,1,'Crown',60,550,'pending'),
(13,1,'Cleaning',30,100,'pending'),
(14,1,'Filling',45,200,'pending'),
(15,1,'Root Canal',60,850,'pending'),
(16,1,'Extraction',40,350,'pending'),
(17,1,'Whitening',45,200,'pending'),
(18,1,'Crown',60,500,'pending'),
(19,1,'Cleaning',30,120,'pending'),
(20,1,'Filling',45,180,'pending'),
(21,1,'Root Canal',60,780,'pending'),
(22,1,'Extraction',40,300,'pending'),
(23,1,'Whitening',45,260,'pending'),
(24,1,'Crown',60,650,'pending'),
(25,1,'Cleaning',30,110,'pending');



INSERT INTO tooth_chart
(patient_id, tooth_number, status, updated_by)
VALUES
(1,11,'healthy',1),
(2,14,'filled',1),
(3,19,'root-canal',1),
(4,30,'missing',1),
(5,8,'healthy',1),
(6,3,'crown',1),
(7,8,'cavity',1),
(8,21,'filled',1),
(9,17,'cavity',1),
(10,2,'missing',1),
(11,6,'healthy',1),
(12,6,'crown',1),
(13,9,'healthy',1),
(14,25,'cavity',1),
(15,18,'root-canal',1),
(16,29,'missing',1),
(17,12,'healthy',1),
(18,12,'crown',1),
(19,4,'healthy',1),
(20,27,'filled',1),
(21,10,'cavity',1),
(22,32,'missing',1),
(23,5,'healthy',1),
(24,15,'crown',1),
(25,22,'healthy',1);



INSERT INTO xrays
(patient_id, file_name, file_path, xray_type, uploaded_by)
VALUES
(3,'x3.jpg','/xrays/x3.jpg','Panoramic',1),
(4,'x4.jpg','/xrays/x4.jpg','Periapical',1),
(5,'x5.jpg','/xrays/x5.jpg','Other',1),
(6,'x6.jpg','/xrays/x6.jpg','CBCT',1),
(7,'x7.jpg','/xrays/x7.jpg','Bitewing',1),
(8,'x8.jpg','/xrays/x8.jpg','Panoramic',1),
(9,'x9.jpg','/xrays/x9.jpg','Periapical',1),
(10,'x10.jpg','/xrays/x10.jpg','Other',1);


INSERT INTO invoices
(invoice_number, patient_id, appointment_id, invoice_date, due_date, subtotal, payment_status, created_by)
VALUES

('INV003',3,3,'2026-04-01','2026-03-10',800,'pending',2),
('INV004',4,4,'2026-04-05','2026-03-11',300,'pending',2),
('INV005',5,5,'2026-04-11','2026-03-11',250,'pending',2),
('INV006',6,6,'2026-04-16','2026-03-11',600,'paid',2),
('INV007',7,7,'2026-04-17','2026-03-12',100,'pending',2),
('INV008',8,8,'2026-04-13','2026-03-12',150,'pending',2),
('INV009',9,9,'2026-04-13','2026-03-12',750,'pending',2),
('INV010',10,10,'2026-04-14','2026-03-13',250,'pending',2);


INSERT INTO payments (invoice_id, amount, payment_method, received_by)
VALUES
(1,100,'cash',2),
(2,200,'card',2),
(6,600,'cash',2);


INSERT INTO inventory (item_name, category, quantity, unit, reorder_level, cost_per_unit, created_by)
VALUES
('Gloves','Consumable',200,'box',20,5,2),
('Masks','Consumable',300,'box',30,3,2),
('Syringes','Equipment',150,'pcs',15,2,2),
('Anesthetic','Medicine',50,'ml',10,15,2),
('Filling Material','Material',100,'pcs',10,20,2),
('Crown Kit','Material',40,'pcs',5,50,2),
('Whitening Gel','Material',60,'pcs',10,25,2),
('Implant Screw','Material',30,'pcs',5,100,2),
('Cotton Rolls','Consumable',500,'pcs',50,1,2),
('Disinfectant','Consumable',80,'bottle',10,8,2),
('Dental Mirror','Equipment',20,'pcs',5,15,2),
('Scaler Tip','Equipment',25,'pcs',5,30,2),
('Composite','Material',70,'pcs',10,18,2),
('Etchant','Material',40,'pcs',5,12,2),
('Bonding Agent','Material',35,'pcs',5,14,2),
('X-ray Film','Material',90,'pcs',10,4,2),
('Cement','Material',60,'pcs',10,16,2),
('Orthodontic Wire','Material',45,'pcs',5,22,2),
('Bracket Set','Material',30,'set',5,60,2),
('Saliva Ejector','Consumable',400,'pcs',40,0.5,2),
('Needles','Consumable',250,'pcs',20,1,2),
('Tray Covers','Consumable',150,'pcs',15,2,2),
('Impression Material','Material',55,'pcs',5,20,2),
('Surgical Blade','Equipment',75,'pcs',10,5,2),
('Gauze','Consumable',600,'pcs',60,0.2,2); INSERT INTO inventory (item_name, category, quantity, unit, reorder_level, cost_per_unit, created_by)
VALUES
('Gloves','Consumable',200,'box',20,5,2),
('Masks','Consumable',300,'box',30,3,2),
('Syringes','Equipment',150,'pcs',15,2,2),
('Anesthetic','Medicine',50,'ml',10,15,2),
('Filling Material','Material',100,'pcs',10,20,2),
('Crown Kit','Material',40,'pcs',5,50,2),
('Whitening Gel','Material',60,'pcs',10,25,2),
('Implant Screw','Material',30,'pcs',5,100,2),
('Cotton Rolls','Consumable',500,'pcs',50,1,2),
('Disinfectant','Consumable',80,'bottle',10,8,2),
('Dental Mirror','Equipment',20,'pcs',5,15,2),
('Scaler Tip','Equipment',25,'pcs',5,30,2),
('Composite','Material',70,'pcs',10,18,2),
('Etchant','Material',40,'pcs',5,12,2),
('Bonding Agent','Material',35,'pcs',5,14,2),
('X-ray Film','Material',90,'pcs',10,4,2),
('Cement','Material',60,'pcs',10,16,2),
('Orthodontic Wire','Material',45,'pcs',5,22,2),
('Bracket Set','Material',30,'set',5,60,2),
('Saliva Ejector','Consumable',400,'pcs',40,0.5,2),
('Needles','Consumable',250,'pcs',20,1,2),
('Tray Covers','Consumable',150,'pcs',15,2,2),
('Impression Material','Material',55,'pcs',5,20,2),
('Surgical Blade','Equipment',75,'pcs',10,5,2),
('Gauze','Consumable',600,'pcs',60,0.2,2);


INSERT INTO inventory_transactions
(inventory_id, transaction_type, quantity_change, new_quantity, performed_by)
VALUES
(1,'purchase',200,200,2),
(2,'purchase',300,300,2),
(3,'purchase',150,150,2),
(4,'purchase',50,50,2),
(5,'purchase',100,100,2),
(6,'purchase',40,40,2),
(7,'purchase',60,60,2),
(8,'purchase',30,30,2),
(9,'purchase',500,500,2),
(10,'purchase',80,80,2),
(11,'purchase',20,20,2),
(12,'purchase',25,25,2),
(13,'purchase',70,70,2),
(14,'purchase',40,40,2),
(15,'purchase',35,35,2),
(16,'purchase',90,90,2),
(17,'purchase',60,60,2),
(18,'purchase',45,45,2),
(19,'purchase',30,30,2),
(20,'purchase',400,400,2),
(21,'purchase',250,250,2),
(22,'purchase',150,150,2),
(23,'purchase',55,55,2),
(24,'purchase',75,75,2),
(25,'purchase',600,600,2);


INSERT INTO waiting_queue (patient_id, queue_type, priority, reason)
VALUES
(1,'daily','medium','Cleaning'),
(2,'daily','high','Pain'),
(3,'weekly','medium','Root Canal'),
(4,'daily','high','Emergency'),
(5,'weekly','low','Whitening'),
(6,'daily','medium','Crown'),
(7,'daily','medium','Cleaning'),
(8,'weekly','medium','Filling'),
(9,'daily','high','Pain'),
(10,'weekly','medium','Extraction'),
(11,'daily','low','Whitening'),
(12,'weekly','medium','Crown'),
(13,'daily','medium','Cleaning'),
(14,'weekly','medium','Filling'),
(15,'daily','high','Root Canal'),
(16,'weekly','high','Extraction'),
(17,'daily','low','Whitening'),
(18,'weekly','medium','Crown'),
(19,'daily','medium','Cleaning'),
(20,'weekly','medium','Filling'),
(21,'daily','high','Root Canal'),
(22,'weekly','high','Extraction'),
(23,'daily','low','Whitening'),
(24,'weekly','medium','Crown'),
(25,'daily','medium','Cleaning');

INSERT INTO waiting_queue (patient_id, queue_type, priority, reason)
VALUES
(3,'weekly','medium','Root Canal'),
(4,'daily','high','Emergency'),
(5,'weekly','low','Whitening'),
(6,'daily','medium','Crown'),
(7,'daily','medium','Cleaning'),
(8,'weekly','medium','Filling'),
(9,'daily','high','Pain'),
(10,'weekly','medium','Extraction'); INSERT INTO waiting_queue (patient_id, queue_type, priority, reason)
VALUES

(3,'weekly','medium','Root Canal'),
(4,'daily','high','Emergency'),
(5,'weekly','low','Whitening'),
(6,'daily','medium','Crown'),
(7,'daily','medium','Cleaning'),
(8,'weekly','medium','Filling'),
(9,'daily','high','Pain'),
(10,'weekly','medium','Extraction'),


INSERT INTO notifications (user_id, type, title, message)
VALUES
(3,'appointment_reminder','Appointment Reminder','Your appointment is tomorrow'),
(4,'payment_reminder','Payment Due','Please complete payment'),
(5,'treatment_instructions','Post Treatment','Follow instructions carefully'),
(6,'promotion','Special Offer','20% discount available'),
(7,'queue_update','Queue Update','You are next in line'),
(8,'appointment_reminder','Reminder','Upcoming visit'),
(9,'payment_reminder','Pending Invoice','Invoice still unpaid'),
(10,'promotion','Offer','Teeth whitening discount'),
(11,'queue_update','Queue','Position updated'),
(12,'appointment_reminder','Reminder','Appointment tomorrow'),
(13,'promotion','Offer','Cleaning discount'),
(14,'payment_reminder','Invoice','Please pay soon'),
(15,'appointment_reminder','Reminder','Visit reminder'),
(16,'queue_update','Queue','Emergency priority'),
(17,'promotion','Offer','Whitening special'),
(18,'appointment_reminder','Reminder','Upcoming visit'),
(19,'payment_reminder','Due','Invoice reminder'),
(20,'promotion','Offer','Discount on crowns'),
(21,'queue_update','Queue','You are #2'),
(22,'appointment_reminder','Reminder','Visit tomorrow'),
(23,'promotion','Offer','Free consultation'),
(24,'payment_reminder','Due','Please pay'),
(25,'queue_update','Queue','Your turn soon'),
(1,'promotion','Clinic Update','New services available'); INSERT INTO notifications (user_id, type, title, message)
VALUES
(3,'appointment_reminder','Appointment Reminder','Your appointment is tomorrow'),
(4,'payment_reminder','Payment Due','Please complete payment'),
(5,'treatment_instructions','Post Treatment','Follow instructions carefully'),
(6,'promotion','Special Offer','20% discount available'),
(7,'queue_update','Queue Update','You are next in line'),
(8,'appointment_reminder','Reminder','Upcoming visit'),
(9,'payment_reminder','Pending Invoice','Invoice still unpaid'),
(10,'promotion','Offer','Teeth whitening discount'),
(11,'queue_update','Queue','Position updated'),
(12,'appointment_reminder','Reminder','Appointment tomorrow'),
(13,'promotion','Offer','Cleaning discount'),
(14,'payment_reminder','Invoice','Please pay soon'),
(15,'appointment_reminder','Reminder','Visit reminder'),
(16,'queue_update','Queue','Emergency priority'),
(17,'promotion','Offer','Whitening special'),
(18,'appointment_reminder','Reminder','Upcoming visit'),
(19,'payment_reminder','Due','Invoice reminder'),
(20,'promotion','Offer','Discount on crowns'),
(21,'queue_update','Queue','You are #2'),
(22,'appointment_reminder','Reminder','Visit tomorrow'),
(23,'promotion','Offer','Free consultation'),
(24,'payment_reminder','Due','Please pay'),
(25,'queue_update','Queue','Your turn soon'),
(1,'promotion','Clinic Update','New services available');



INSERT INTO audit_log (user_id, action, table_name, record_id)
VALUES
(1,'INSERT','patients',1),
(1,'INSERT','appointments',1),
(2,'INSERT','invoices',1),
(2,'UPDATE','patients',2),
(1,'INSERT','treatment_plans',3),
(1,'UPDATE','appointments',2),
(2,'INSERT','inventory',1),
(2,'INSERT','inventory_transactions',1),
(1,'INSERT','xrays',1),
(2,'INSERT','payments',1),
(1,'UPDATE','tooth_chart',3),
(2,'INSERT','notifications',1),
(1,'INSERT','treatment_steps',1),
(2,'UPDATE','invoices',2),
(1,'INSERT','waiting_queue',1),
(2,'INSERT','patients',5),
(1,'UPDATE','appointments',5),
(2,'INSERT','inventory',5),
(1,'INSERT','xrays',5),
(2,'UPDATE','payments',1),
(1,'INSERT','treatment_plans',10),
(2,'INSERT','notifications',10),
(1,'UPDATE','patients',10),
(2,'INSERT','audit_log',1),
(1,'UPDATE','invoices',6); INSERT INTO audit_log (user_id, action, table_name, record_id)
VALUES
(1,'INSERT','patients',1),
(1,'INSERT','appointments',1),
(2,'INSERT','invoices',1),
(2,'UPDATE','patients',2),
(1,'INSERT','treatment_plans',3),
(1,'UPDATE','appointments',2),
(2,'INSERT','inventory',1),
(2,'INSERT','inventory_transactions',1),
(1,'INSERT','xrays',1),
(2,'INSERT','payments',1),
(1,'UPDATE','tooth_chart',3),
(2,'INSERT','notifications',1),
(1,'INSERT','treatment_steps',1),
(2,'UPDATE','invoices',2),
(1,'INSERT','waiting_queue',1),
(2,'INSERT','patients',5),
(1,'UPDATE','appointments',5),
(2,'INSERT','inventory',5),
(1,'INSERT','xrays',5),
(2,'UPDATE','payments',1),
(1,'INSERT','treatment_plans',10),
(2,'INSERT','notifications',10),
(1,'UPDATE','patients',10),
(2,'INSERT','audit_log',1),
(1,'UPDATE','invoices',6);

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `patient_id` (`patient_id`);

ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;
ALTER TABLE `password_resets`
  ADD CONSTRAINT `fk_password_resets_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;
COMMIT;

CREATE TABLE IF NOT EXISTS monthly_expenses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    month_year DATE NOT NULL, -- first day of month (e.g., 2025-03-01)
    salaries_total DECIMAL(10,2) DEFAULT 0,
    assistants_count INT DEFAULT 0,
    electricity DECIMAL(10,2) DEFAULT 0,
    rent DECIMAL(10,2) DEFAULT 0,
    other_expenses DECIMAL(10,2) DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_month (month_year)
);





