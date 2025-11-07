CREATE TABLE results (
    email VARCHAR(255) PRIMARY KEY,
    self_total_words INT,
    conc_total_words INT,
    adj_total_words INT,
    self_a FLOAT,
    self_b FLOAT,
    self_c FLOAT,
    self_d FLOAT,
    self_avg FLOAT,
    dec_approach FLOAT,
    conc_a FLOAT,
    conc_b FLOAT,
    conc_c FLOAT,
    conc_d FLOAT,
    conc_avg FLOAT,
    original_test_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    latest_test_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    tests_taken_count INT DEFAULT 1
);

CREATE TABLE input (
    email VARCHAR(255) PRIMARY KEY,
    self_words JSON NOT NULL,
    concept_words JSON NOT NULL
);
