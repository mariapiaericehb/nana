-- HypeBang CRM - struttura database (MySQL 5.7+ / MariaDB 10.3+)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS utenti (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  spazio VARCHAR(12) NOT NULL DEFAULT '',
  ruolo ENUM('admin','utente') NOT NULL DEFAULT 'utente',
  cambia_password TINYINT(1) NOT NULL DEFAULT 0,
  attivo TINYINT(1) NOT NULL DEFAULT 1,
  password_hash VARCHAR(255) NOT NULL,
  totp_secret VARCHAR(64) NULL,
  totp_ultimo BIGINT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS accessi (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  utente_id INT UNSIGNED NOT NULL,
  esito VARCHAR(60) NOT NULL,
  ip VARCHAR(64) NULL,
  browser VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY (utente_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_tentativi (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(64) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS impostazioni (
  chiave VARCHAR(64) PRIMARY KEY,
  valore TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clienti (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  stato ENUM('potenziale','cliente','ex') NOT NULL DEFAULT 'cliente',
  ragione_sociale VARCHAR(190) NOT NULL,
  nome_breve VARCHAR(120) NULL,
  settore VARCHAR(120) NULL,
  fonte VARCHAR(120) NULL,
  piva VARCHAR(32) NULL,
  codice_fiscale VARCHAR(32) NULL,
  codice_sdi VARCHAR(16) NULL,
  pec VARCHAR(190) NULL,
  indirizzo VARCHAR(190) NULL,
  cap VARCHAR(10) NULL,
  citta VARCHAR(120) NULL,
  provincia VARCHAR(4) NULL,
  email VARCHAR(190) NULL,
  telefono VARCHAR(60) NULL,
  sito VARCHAR(190) NULL,
  instagram VARCHAR(190) NULL,
  colore VARCHAR(9) NULL,
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY (stato)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contatti (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cliente_id INT UNSIGNED NOT NULL,
  nome VARCHAR(160) NOT NULL,
  ruolo VARCHAR(120) NULL,
  email VARCHAR(190) NULL,
  telefono VARCHAR(60) NULL,
  principale TINYINT(1) NOT NULL DEFAULT 0,
  note TEXT NULL,
  KEY (cliente_id),
  CONSTRAINT fk_contatti_cliente FOREIGN KEY (cliente_id) REFERENCES clienti(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS trattative (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cliente_id INT UNSIGNED NOT NULL,
  titolo VARCHAR(190) NOT NULL,
  valore DECIMAL(12,2) NOT NULL DEFAULT 0,
  fase ENUM('nuova','contattato','preventivo','negoziazione','vinta','persa') NOT NULL DEFAULT 'nuova',
  probabilita TINYINT UNSIGNED NOT NULL DEFAULT 20,
  chiusura_prevista DATE NULL,
  prossimo_passo VARCHAR(190) NULL,
  prossimo_passo_data DATE NULL,
  motivo_perso VARCHAR(255) NULL,
  note TEXT NULL,
  ordine INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY (fase), KEY (cliente_id),
  CONSTRAINT fk_tratt_cliente FOREIGN KEY (cliente_id) REFERENCES clienti(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS preventivi (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cliente_id INT UNSIGNED NOT NULL,
  trattativa_id INT UNSIGNED NULL,
  numero VARCHAR(32) NOT NULL,
  data DATE NOT NULL,
  validita_giorni INT NOT NULL DEFAULT 30,
  oggetto VARCHAR(255) NOT NULL,
  stato ENUM('bozza','inviato','accettato','rifiutato') NOT NULL DEFAULT 'bozza',
  iva_percentuale DECIMAL(5,2) NOT NULL DEFAULT 22,
  sconto DECIMAL(12,2) NOT NULL DEFAULT 0,
  introduzione TEXT NULL,
  condizioni TEXT NULL,
  note_interne TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY (cliente_id), KEY (stato),
  CONSTRAINT fk_prev_cliente FOREIGN KEY (cliente_id) REFERENCES clienti(id) ON DELETE CASCADE,
  CONSTRAINT fk_prev_tratt FOREIGN KEY (trattativa_id) REFERENCES trattative(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS preventivo_righe (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  preventivo_id INT UNSIGNED NOT NULL,
  descrizione TEXT NOT NULL,
  quantita DECIMAL(10,2) NOT NULL DEFAULT 1,
  prezzo DECIMAL(12,2) NOT NULL DEFAULT 0,
  ordine INT NOT NULL DEFAULT 0,
  KEY (preventivo_id),
  CONSTRAINT fk_righe_prev FOREIGN KEY (preventivo_id) REFERENCES preventivi(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contratti (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cliente_id INT UNSIGNED NOT NULL,
  titolo VARCHAR(190) NOT NULL,
  importo DECIMAL(12,2) NOT NULL DEFAULT 0,
  periodicita ENUM('una_tantum','mensile','trimestrale','semestrale','annuale') NOT NULL DEFAULT 'mensile',
  data_inizio DATE NOT NULL,
  data_fine DATE NULL,
  rinnovo_automatico TINYINT(1) NOT NULL DEFAULT 0,
  preavviso_giorni INT NOT NULL DEFAULT 30,
  stato ENUM('attivo','concluso','disdetto') NOT NULL DEFAULT 'attivo',
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY (cliente_id), KEY (stato),
  CONSTRAINT fk_contr_cliente FOREIGN KEY (cliente_id) REFERENCES clienti(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fatture (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cliente_id INT UNSIGNED NOT NULL,
  preventivo_id INT UNSIGNED NULL,
  contratto_id INT UNSIGNED NULL,
  numero VARCHAR(32) NOT NULL,
  data DATE NOT NULL,
  scadenza DATE NULL,
  descrizione VARCHAR(255) NULL,
  imponibile DECIMAL(12,2) NOT NULL DEFAULT 0,
  iva_percentuale DECIMAL(5,2) NOT NULL DEFAULT 22,
  ritenuta_percentuale DECIMAL(5,2) NOT NULL DEFAULT 0,
  annullata TINYINT(1) NOT NULL DEFAULT 0,
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY (cliente_id), KEY (scadenza),
  CONSTRAINT fk_fatt_cliente FOREIGN KEY (cliente_id) REFERENCES clienti(id) ON DELETE CASCADE,
  CONSTRAINT fk_fatt_prev FOREIGN KEY (preventivo_id) REFERENCES preventivi(id) ON DELETE SET NULL,
  CONSTRAINT fk_fatt_contr FOREIGN KEY (contratto_id) REFERENCES contratti(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pagamenti (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  fattura_id INT UNSIGNED NOT NULL,
  data DATE NOT NULL,
  importo DECIMAL(12,2) NOT NULL,
  metodo VARCHAR(60) NULL,
  note VARCHAR(255) NULL,
  KEY (fattura_id),
  CONSTRAINT fk_pag_fatt FOREIGN KEY (fattura_id) REFERENCES fatture(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS progetti (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cliente_id INT UNSIGNED NULL,
  preventivo_id INT UNSIGNED NULL,
  titolo VARCHAR(190) NOT NULL,
  descrizione TEXT NULL,
  stato ENUM('da_iniziare','in_corso','in_pausa','completato','annullato') NOT NULL DEFAULT 'da_iniziare',
  data_inizio DATE NULL,
  scadenza DATE NULL,
  budget DECIMAL(12,2) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY (cliente_id), KEY (stato),
  CONSTRAINT fk_prog_cliente FOREIGN KEY (cliente_id) REFERENCES clienti(id) ON DELETE CASCADE,
  CONSTRAINT fk_prog_prev FOREIGN KEY (preventivo_id) REFERENCES preventivi(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS task (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  progetto_id INT UNSIGNED NULL,
  cliente_id INT UNSIGNED NULL,
  titolo VARCHAR(255) NOT NULL,
  descrizione TEXT NULL,
  stato ENUM('da_fare','in_corso','in_attesa','fatto') NOT NULL DEFAULT 'da_fare',
  priorita ENUM('bassa','media','alta') NOT NULL DEFAULT 'media',
  personale TINYINT(1) NOT NULL DEFAULT 0,
  scadenza DATE NULL,
  ora TIME NULL,
  ordine INT NOT NULL DEFAULT 0,
  completato_il DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY (progetto_id), KEY (cliente_id), KEY (scadenza), KEY (stato),
  CONSTRAINT fk_task_prog FOREIGN KEY (progetto_id) REFERENCES progetti(id) ON DELETE CASCADE,
  CONSTRAINT fk_task_cliente FOREIGN KEY (cliente_id) REFERENCES clienti(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS eventi (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cliente_id INT UNSIGNED NULL,
  titolo VARCHAR(190) NOT NULL,
  tipo ENUM('appuntamento','call','promemoria','personale','altro') NOT NULL DEFAULT 'appuntamento',
  data DATE NOT NULL,
  ora_inizio TIME NULL,
  ora_fine TIME NULL,
  luogo VARCHAR(190) NULL,
  note TEXT NULL,
  ripeti ENUM('no','settimana','mese','anno') NOT NULL DEFAULT 'no',
  ripeti_fino DATE NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY (data), KEY (cliente_id), KEY (ripeti),
  CONSTRAINT fk_ev_cliente FOREIGN KEY (cliente_id) REFERENCES clienti(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attivita (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cliente_id INT UNSIGNED NULL,
  tipo VARCHAR(30) NOT NULL DEFAULT 'sistema',
  testo TEXT NOT NULL,
  link VARCHAR(190) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY (cliente_id, created_at),
  CONSTRAINT fk_att_cliente FOREIGN KEY (cliente_id) REFERENCES clienti(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS allegati (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  entita VARCHAR(20) NOT NULL,
  entita_id INT UNSIGNED NOT NULL,
  nome VARCHAR(190) NOT NULL,
  file VARCHAR(190) NOT NULL,
  dimensione INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY (entita, entita_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
