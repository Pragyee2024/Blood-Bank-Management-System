
--  Blood Bank Management System 


CREATE TABLE blood_groups (
  group_id    SERIAL PRIMARY KEY,
  group_name  VARCHAR(3) NOT NULL UNIQUE CHECK (group_name IN ('A+','A-','B+','B-','AB+','AB-','O+','O-'))
);

INSERT INTO blood_groups (group_name) VALUES
('A+'),('A-'),('B+'),('B-'),('AB+'),('AB-'),('O+'),('O-');

-- CORE TABLES 

CREATE TABLE blood_bank (
  bank_id         SERIAL PRIMARY KEY,
  name            VARCHAR(120) NOT NULL,
  location        VARCHAR(200),
  contact_phone   VARCHAR(20),
  contact_email   VARCHAR(100),
  total_capacity  INT DEFAULT 500,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE hospital (
  hospital_id     SERIAL PRIMARY KEY,
  name            VARCHAR(150) NOT NULL,
  location        VARCHAR(200),
  contact_phone   VARCHAR(20),
  contact_email   VARCHAR(100),
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

--  Auth / Donor module 

CREATE TABLE donor (
  donor_id        SERIAL PRIMARY KEY,
  name            VARCHAR(120) NOT NULL,
  dob             DATE,
  gender          VARCHAR(10) DEFAULT 'Male' CHECK (gender IN ('Male','Female','Other')),
  group_id        INT NOT NULL REFERENCES blood_groups(group_id),
  phone           VARCHAR(20) NOT NULL,
  email           VARCHAR(100),
  address         VARCHAR(255),
  last_donation   DATE,
  total_donations INT DEFAULT 0,
  is_eligible     BOOLEAN DEFAULT TRUE,
  health_status   VARCHAR(20) DEFAULT 'Healthy' CHECK (health_status IN ('Healthy','Minor Condition','Deferred')),
  registered_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE app_user (
  user_id       SERIAL PRIMARY KEY,
  username      VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          VARCHAR(20) DEFAULT 'staff' CHECK (role IN ('admin','staff','donor')),
  donor_id      INT REFERENCES donor(donor_id) ON DELETE SET NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

--   Inventory / Requests module

CREATE TABLE doctor (
  doctor_id      SERIAL PRIMARY KEY,
  hospital_id    INT NOT NULL REFERENCES hospital(hospital_id) ON DELETE CASCADE,
  name           VARCHAR(120) NOT NULL,
  specialization VARCHAR(100),
  phone          VARCHAR(20),
  email          VARCHAR(100),
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE patient (
  patient_id        SERIAL PRIMARY KEY,
  hospital_id       INT NOT NULL REFERENCES hospital(hospital_id) ON DELETE CASCADE,
  name              VARCHAR(120) NOT NULL,
  dob               DATE,
  gender            VARCHAR(10) DEFAULT 'Male' CHECK (gender IN ('Male','Female','Other')),
  group_id          INT REFERENCES blood_groups(group_id),
  phone             VARCHAR(20),
  address           VARCHAR(255),
  medical_condition VARCHAR(255),
  registered_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE blood_unit (
  unit_id         SERIAL PRIMARY KEY,
  donor_id        INT NOT NULL REFERENCES donor(donor_id) ON DELETE CASCADE,
  bank_id         INT NOT NULL REFERENCES blood_bank(bank_id) ON DELETE CASCADE,
  group_id        INT NOT NULL REFERENCES blood_groups(group_id),
  component       VARCHAR(20) DEFAULT 'Whole Blood' CHECK (component IN ('Whole Blood','RBC','Plasma','Platelets','Cryoprecipitate')),
  volume_ml       INT DEFAULT 450,
  collection_date DATE NOT NULL,
  expiry_date     DATE NOT NULL,
  status          VARCHAR(20) DEFAULT 'Available' CHECK (status IN ('Available','Reserved','Transfused','Expired','Discarded')),
  collected_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE blood_request (
  request_id   SERIAL PRIMARY KEY,
  patient_id   INT NOT NULL REFERENCES patient(patient_id) ON DELETE CASCADE,
  doctor_id    INT NOT NULL REFERENCES doctor(doctor_id) ON DELETE CASCADE,
  group_id     INT NOT NULL REFERENCES blood_groups(group_id),
  component    VARCHAR(20) DEFAULT 'Whole Blood' CHECK (component IN ('Whole Blood','RBC','Plasma','Platelets','Cryoprecipitate')),
  units_needed INT DEFAULT 1,
  urgency      VARCHAR(10) DEFAULT 'Medium' CHECK (urgency IN ('Low','Medium','High','Critical')),
  status       VARCHAR(15) DEFAULT 'Pending' CHECK (status IN ('Pending','Processing','Fulfilled','Cancelled')),
  request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  notes        TEXT
);

--  Admin / Reports module 

CREATE TABLE transfusion (
  transfusion_id   SERIAL PRIMARY KEY,
  request_id       INT NOT NULL REFERENCES blood_request(request_id) ON DELETE CASCADE,
  unit_id          INT NOT NULL REFERENCES blood_unit(unit_id) ON DELETE CASCADE,
  doctor_id        INT NOT NULL REFERENCES doctor(doctor_id) ON DELETE CASCADE,
  transfusion_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  outcome          VARCHAR(15) DEFAULT 'Pending' CHECK (outcome IN ('Successful','Pending','Reaction','Incomplete')),
  notes            TEXT
);

CREATE TABLE inventory_log (
  log_id       SERIAL PRIMARY KEY,
  bank_id      INT NOT NULL REFERENCES blood_bank(bank_id) ON DELETE CASCADE,
  unit_id      INT,
  group_id     INT REFERENCES blood_groups(group_id),
  component    VARCHAR(50),
  action       VARCHAR(15) DEFAULT 'Added' CHECK (action IN ('Added','Reserved','Transfused','Expired','Discarded')),
  quantity     INT DEFAULT 1,
  performed_by VARCHAR(100) DEFAULT 'System',
  log_date     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ─── FUNCTION + TRIGGER: auto-expire units on update ─────────

CREATE OR REPLACE FUNCTION trg_expire_units() RETURNS TRIGGER AS $$
BEGIN
  IF NEW.status = 'Available' AND NEW.expiry_date < CURRENT_DATE THEN
    NEW.status := 'Expired';
  END IF;
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_blood_unit_expiry
BEFORE UPDATE ON blood_unit
FOR EACH ROW EXECUTE FUNCTION trg_expire_units();

-- Daily sweep (call manually or via pg_cron/a scheduled job — Postgres has no built-in EVENT):
-- UPDATE blood_unit SET status = 'Expired' WHERE status = 'Available' AND expiry_date < CURRENT_DATE;

-- ─── FUNCTION: add blood unit with auto expiry + donor stats + log ───

CREATE OR REPLACE FUNCTION sp_add_blood_unit(
  p_donor_id  INT,
  p_bank_id   INT,
  p_component VARCHAR,
  p_volume_ml INT
) RETURNS TABLE(unit_id INT, expiry_date DATE) AS $$
DECLARE
  v_group_id  INT;
  v_days      INT;
  v_collect   DATE := CURRENT_DATE;
  v_expiry    DATE;
  v_unit_id   INT;
BEGIN
  SELECT group_id INTO v_group_id FROM donor WHERE donor_id = p_donor_id;

  v_days := CASE p_component
    WHEN 'Whole Blood'     THEN 35
    WHEN 'RBC'             THEN 42
    WHEN 'Plasma'          THEN 365
    WHEN 'Platelets'       THEN 5
    WHEN 'Cryoprecipitate' THEN 365
    ELSE 35
  END;

  v_expiry := v_collect + v_days;

  INSERT INTO blood_unit (donor_id, bank_id, group_id, component, volume_ml, collection_date, expiry_date, status)
  VALUES (p_donor_id, p_bank_id, v_group_id, p_component, p_volume_ml, v_collect, v_expiry, 'Available')
  RETURNING blood_unit.unit_id INTO v_unit_id;

  UPDATE donor SET last_donation = v_collect, total_donations = total_donations + 1 WHERE donor_id = p_donor_id;

  INSERT INTO inventory_log (bank_id, unit_id, group_id, component, action, performed_by)
  VALUES (p_bank_id, v_unit_id, v_group_id, p_component, 'Added', 'System');

  RETURN QUERY SELECT v_unit_id, v_expiry;
END;
$$ LANGUAGE plpgsql;

-- ─── SEED DATA ───────────────────────────────────────────────

INSERT INTO blood_bank (name, location, contact_phone, contact_email, total_capacity) VALUES
('City Blood Bank',      'Kathmandu, Bagmati', '01-4250000', 'city@bloodbank.np',    600),
('TUTH Blood Bank',      'Maharajgunj, KTM',   '01-4412303', 'tuth@bloodbank.np',    400),
('Bir Hospital BB',      'Mahabauddha, KTM',   '01-4221119', 'bir@bloodbank.np',     350),
('Pokhara Blood Center', 'Lakeside, Pokhara',  '061-520000', 'pokhara@bloodbank.np', 300);

INSERT INTO hospital (name, location, contact_phone, contact_email) VALUES
('Tribhuvan University Teaching Hospital', 'Maharajgunj, Kathmandu', '01-4412303', 'info@tuth.edu.np'),
('Bir Hospital',                           'Mahabauddha, Kathmandu', '01-4221119', 'info@bir.gov.np'),
('Patan Hospital',                         'Lagankhel, Lalitpur',    '01-5522266', 'info@patan.np'),
('Manipal Teaching Hospital',              'Pokhara, Gandaki',       '061-526416', 'info@manipal.np');

INSERT INTO donor (name, dob, gender, group_id, phone, email, address, last_donation, total_donations, is_eligible, health_status) VALUES
('Ramesh Shrestha', '1990-03-15', 'Male',   (SELECT group_id FROM blood_groups WHERE group_name='O+'), '9841000001', 'ramesh@mail.com', 'Koteshwor, KTM', CURRENT_DATE - 95, 8, TRUE, 'Healthy'),
('Sunita Tamang',   '1995-07-22', 'Female', (SELECT group_id FROM blood_groups WHERE group_name='A+'), '9841000002', 'sunita@mail.com', 'Baneshwor, KTM', CURRENT_DATE - 40, 3, FALSE,'Healthy'),
('Bikash Rai',      '1988-11-10', 'Male',   (SELECT group_id FROM blood_groups WHERE group_name='B+'), '9841000003', 'bikash@mail.com', 'Lazimpat, KTM',  CURRENT_DATE - 120,12, TRUE, 'Healthy'),
('Priya Karki',     '2000-01-05', 'Female', (SELECT group_id FROM blood_groups WHERE group_name='AB+'),'9841000004', 'priya@mail.com',  'Patan, Lalitpur',CURRENT_DATE - 200, 2, TRUE, 'Healthy');

INSERT INTO doctor (hospital_id, name, specialization, phone, email) VALUES
(1, 'Dr. Ananda Sharma', 'Hematology',       '9851100001', 'ananda@tuth.edu.np'),
(2, 'Dr. Ram Bdr Thapa', 'Internal Medicine','9851100003', 'ram@bir.gov.np');

INSERT INTO patient (hospital_id, name, dob, gender, group_id, phone, address, medical_condition) VALUES
(1, 'Hari Prasad Gautam', '1955-06-10', 'Male',   (SELECT group_id FROM blood_groups WHERE group_name='O+'), '9841200001', 'Balaju, KTM',     'Anemia — chronic'),
(2, 'Raju Tamang',        '1990-03-14', 'Male',   (SELECT group_id FROM blood_groups WHERE group_name='B+'), '9841200003', 'Swayambhu, KTM',  'Accident — internal bleeding');

SELECT 'blood_bank database created and seeded successfully!' AS status;