USE form_db;

-- Таблица заявок
CREATE TABLE applications (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100) NOT NULL,
    birth_date DATE NOT NULL,
    gender ENUM('male', 'female') NOT NULL,
    biography TEXT,
    contract_agreed TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Справочник языков программирования
CREATE TABLE programming_languages (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- Таблица связи (один ко многим)
CREATE TABLE application_languages (
    application_id INT UNSIGNED NOT NULL,
    language_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (application_id, language_id),
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    FOREIGN KEY (language_id) REFERENCES programming_languages(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Наполнение справочника языками
INSERT INTO programming_languages (name) VALUES
('Pascal'), ('C'), ('C++'), ('JavaScript'), ('PHP'), ('Python'),
('Java'), ('Haskell'), ('Clojure'), ('Prolog'), ('Scala'), ('Go');
/*CREATE TABLE application (
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  full_name varchar(150) NOT NULL DEFAULT '',
  phone varchar(12) NOT NULL DEFAULT '',
  email varchar(100) NOT NULL DEFAULT '',
  birth_date DATE NOT NULL,
  gender ENUM('male','fmale') NOT NULL,
  bio TEXT,
    contract_accepted TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
)ENGINE=InnoDB;;
-- Таблица заявок

CREATE TABLE language(
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  name_language varchar(50) NOT NULL DEFAULT '',
  PRIMARY KEY (id)
)ENGINE=InnoDB;;
-- Таблица языков программирования

-- Вставка допустимых языков
INSERT INTO language (name_language) VALUES
('Pascal'), ('C'), ('C++'), ('JavaScript'), ('PHP'), ('Python'),
('Java'), ('Haskell'), ('Clojure'), ('Prolog'), ('Scala'), ('Go');

CREATE TABLE favorite_language(
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  id_application int(10) unsigned NOT NULL
  id_language int(10) unsigned NOT NULL
  PRIMARY KEY(id),
  FOREIGN KEY(id_application) REFERENCES application(id) ON DELETE CASCADE,
  FOREIGN KEY(id_language) REFERENCES language(id) ON DELETE CASCADE

    FOREIGN KEY (language_id) REFERENCES programming_languages(id) ON DELETE CASCADE
) ENGINE=InnoDB;
-- Таблица связей (один ко многим)
*/