-- Criação do banco de dados (o Docker já cria, mas mantemos por segurança)

CREATE DATABASE IF NOT EXISTS farmacia CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;


USE farmacia;


-- Configuração de timezone para Brasil
SET time_zone = '-03:00';


-- Desabilita checagem de FK temporariamente

SET FOREIGN_KEY_CHECKS = 0;

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;


-- Tabela de usuários

CREATE TABLE IF NOT EXISTS usuarios (

    id INT AUTO_INCREMENT PRIMARY KEY,

    nome VARCHAR(100) NOT NULL,

    email VARCHAR(100) UNIQUE NOT NULL,

    senha VARCHAR(255) NULL,

    perfil ENUM('admin', 'operador') NOT NULL,

    auth_type ENUM('local', 'ldap') NOT NULL DEFAULT 'local',

    ativo TINYINT(1) NOT NULL DEFAULT 1,

    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    ultimo_acesso DATETIME

) ENGINE=InnoDB;


-- Tabela para proteção contra força bruta
CREATE TABLE IF NOT EXISTS tentativas_login (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    sucesso TINYINT(1) DEFAULT 0,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,
    INDEX idx_email_timestamp (email, timestamp),
    INDEX idx_timestamp (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Tabela de medicamentos (DEVE VIR ANTES de paciente_medicamentos)

CREATE TABLE IF NOT EXISTS medicamentos (

    id INT AUTO_INCREMENT PRIMARY KEY,

    nome VARCHAR(100) NOT NULL,

    apresentacao ENUM(
        'Comprimido', 
        'Cápsula', 
        'Drágea', 
        'Solução', 
        'Suspensão', 
        'Xarope', 
        'Elixir', 
        'Gotas', 
        'Injetável', 
        'Ampola', 
        'Frasco-ampola', 
        'Seringa Preenchida', 
        'Pomada', 
        'Creme', 
        'Gel', 
        'Loção', 
        'Spray', 
        'Inalador', 
        'Inalação', 
        'Inalante', 
        'Colírio', 
        'Solução Oftálmica', 
        'Spray Nasal', 
        'Supositório', 
        'Adesivo', 
        'Implante', 
        'Bisnaga', 
        'Óvulo',
        'Pó Liofilizado',
        'Dispositivo',
        'Fórmula Nutricional',
        'Liquido',
        'Solução oral',
        'Frasco'
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,

    codigo VARCHAR(20) NOT NULL,

    miligramas VARCHAR(20),

    quantidade INT NOT NULL DEFAULT 0,

    ativo TINYINT(1) NOT NULL DEFAULT 1,

    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unq_nome (nome)

) ENGINE=InnoDB;


-- Tabela de lotes de medicamentos

CREATE TABLE IF NOT EXISTS lotes_medicamentos (

    id INT AUTO_INCREMENT PRIMARY KEY,

    medicamento_id INT NOT NULL,

    lote VARCHAR(50) NOT NULL,

    quantidade INT NOT NULL DEFAULT 0,

    validade DATE NOT NULL,

    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (medicamento_id) REFERENCES medicamentos(id) ON DELETE CASCADE,

    UNIQUE KEY unq_medicamento_lote (medicamento_id, lote)

) ENGINE=InnoDB;


-- Trigger para atualizar a quantidade total do medicamento

DELIMITER //

CREATE TRIGGER atualizar_quantidade_medicamento
AFTER INSERT ON lotes_medicamentos
FOR EACH ROW
BEGIN
    UPDATE medicamentos 
    SET quantidade = (
        SELECT SUM(quantidade) 
        FROM lotes_medicamentos 
        WHERE medicamento_id = NEW.medicamento_id
    )
    WHERE id = NEW.medicamento_id;
END//

CREATE TRIGGER atualizar_quantidade_medicamento_update
AFTER UPDATE ON lotes_medicamentos
FOR EACH ROW
BEGIN
    UPDATE medicamentos 
    SET quantidade = (
        SELECT SUM(quantidade) 
        FROM lotes_medicamentos 
        WHERE medicamento_id = NEW.medicamento_id
    )
    WHERE id = NEW.medicamento_id;
END//

CREATE TRIGGER atualizar_quantidade_medicamento_delete
AFTER DELETE ON lotes_medicamentos
FOR EACH ROW
BEGIN
    UPDATE medicamentos 
    SET quantidade = (
        SELECT COALESCE(SUM(quantidade), 0) 
        FROM lotes_medicamentos 
        WHERE medicamento_id = OLD.medicamento_id
    )
    WHERE id = OLD.medicamento_id;
END//

DELIMITER ;


-- Tabela de pacientes

CREATE TABLE IF NOT EXISTS pacientes (

    id INT AUTO_INCREMENT PRIMARY KEY,

    nome VARCHAR(100) NOT NULL,

    cpf VARCHAR(14) UNIQUE NOT NULL,

    sim VARCHAR(20),

    nascimento DATE NOT NULL,

    telefone VARCHAR(15) NOT NULL,

    telefone2 VARCHAR(15),

    validade DATE,

    observacao TEXT,

    renovado TINYINT(1) NOT NULL DEFAULT 0,

    ativo TINYINT(1) NOT NULL DEFAULT 1,

    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

) ENGINE=InnoDB;


-- Tabela de médicos

CREATE TABLE IF NOT EXISTS medicos (

    id INT AUTO_INCREMENT PRIMARY KEY,

    nome VARCHAR(100) NOT NULL,

    crm_numero VARCHAR(20) NOT NULL,

    crm_estado CHAR(2) NOT NULL,

    cns VARCHAR(15),

    ativo TINYINT(1) NOT NULL DEFAULT 1,

    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

) ENGINE=InnoDB;


-- Tabela de instituições de saúde
CREATE TABLE IF NOT EXISTS instituicoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    cnes VARCHAR(7) NOT NULL UNIQUE,
    endereco VARCHAR(200),
    telefone VARCHAR(15),
    email VARCHAR(100),
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;


-- Tabela de paciente_medicamentos

CREATE TABLE IF NOT EXISTS paciente_medicamentos (

    id INT AUTO_INCREMENT PRIMARY KEY,

    paciente_id INT NOT NULL,

    medicamento_id INT NOT NULL,

    nome_medicamento VARCHAR(100) NOT NULL,

    quantidade INT NOT NULL DEFAULT 0,

    quantidade_solicitada INT,

    cid VARCHAR(100),

    medico_id INT,

    medico_texto VARCHAR(100),

    renovacao VARCHAR(10),

    observacoes TEXT,

    tipo_prescritor ENUM('medico', 'instituicao'),

    instituicao_id INT,

    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    renovado TINYINT(1) NOT NULL DEFAULT 0,

    FOREIGN KEY (paciente_id) REFERENCES pacientes(id) ON DELETE CASCADE,

    FOREIGN KEY (medicamento_id) REFERENCES medicamentos(id) ON DELETE RESTRICT,

    FOREIGN KEY (medico_id) REFERENCES medicos(id) ON DELETE SET NULL,

    FOREIGN KEY (instituicao_id) REFERENCES instituicoes(id) ON DELETE SET NULL

) ENGINE=InnoDB;


-- Tabela de renovacao
CREATE TABLE IF NOT EXISTS renovacao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    paciente_id INT NOT NULL,
    data_renovacao DATE,
    status ENUM('pendente', 'em_andamento', 'concluido') DEFAULT 'pendente',
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (paciente_id) REFERENCES pacientes(id)
) ENGINE=InnoDB;


-- Tabela de transações

CREATE TABLE IF NOT EXISTS transacoes (

    id INT AUTO_INCREMENT PRIMARY KEY,

    medicamento_id INT NOT NULL,

    usuario_id INT NOT NULL,

    paciente_id INT NOT NULL,

    quantidade INT NOT NULL,

    observacoes TEXT,

    data TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (medicamento_id) REFERENCES medicamentos(id) ON DELETE RESTRICT,

    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT,

    FOREIGN KEY (paciente_id) REFERENCES pacientes(id) ON DELETE RESTRICT

) ENGINE=InnoDB;


-- Tabela de movimentacoes

CREATE TABLE IF NOT EXISTS movimentacoes (

    id INT AUTO_INCREMENT PRIMARY KEY,

    medicamento_id INT NOT NULL,

    tipo ENUM('IMPORTACAO', 'SAIDA', 'ENTRADA', 'AJUSTE', 'AJUSTE_ENTRADA', 'AJUSTE_SAIDA') NOT NULL,

    quantidade INT NOT NULL,

    quantidade_anterior INT NOT NULL,

    quantidade_nova INT NOT NULL,

    data TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    observacao TEXT,

    usuario_id INT NULL,

    FOREIGN KEY (medicamento_id) REFERENCES medicamentos(id) ON DELETE RESTRICT,

    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL

) ENGINE=InnoDB;


-- Tabela de logs de importação
CREATE TABLE IF NOT EXISTS logs_importacao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    usuario_nome VARCHAR(255),
    data_hora DATETIME,
    arquivo_nome VARCHAR(255),
    quantidade_registros INT,
    status VARCHAR(50),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- Tabela de detalhes de importação
CREATE TABLE IF NOT EXISTS logs_importacao_detalhes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    log_importacao_id INT NOT NULL,
    paciente_nome VARCHAR(255) DEFAULT NULL,
    medicamento_nome VARCHAR(255) DEFAULT NULL,
    quantidade INT DEFAULT NULL,
    lote VARCHAR(100) DEFAULT NULL,
    validade DATE DEFAULT NULL,
    observacao TEXT,
    FOREIGN KEY (log_importacao_id) REFERENCES logs_importacao(id) ON DELETE CASCADE
) ENGINE=InnoDB;


-- Tabela de pessoas autorizadas
CREATE TABLE IF NOT EXISTS pessoas_autorizadas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    paciente_id INT NOT NULL,
    nome VARCHAR(100) NOT NULL,
    cpf VARCHAR(14) NOT NULL,
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (paciente_id) REFERENCES pacientes(id) ON DELETE CASCADE
) ENGINE=InnoDB;


-- Tabela de agenda
CREATE TABLE IF NOT EXISTS agenda (
    id INT AUTO_INCREMENT PRIMARY KEY,
    data DATE NOT NULL,
    horario TIME NOT NULL,
    paciente_id INT NOT NULL,
    observacoes TEXT,
    status ENUM('agendado', 'confirmado', 'cancelado', 'realizado') DEFAULT 'agendado',
    encaixe TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Indica se é um agendamento de encaixe (extra)',
    usuario_id INT NOT NULL,
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (paciente_id) REFERENCES pacientes(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;
-- Removida constraint única para permitir múltiplos pacientes por hora
-- UNIQUE KEY unique_agendamento_ativo (data, horario, status)


-- Tabela de agenda bloqueada
CREATE TABLE IF NOT EXISTS agenda_bloqueada (
    id INT AUTO_INCREMENT PRIMARY KEY,
    data DATE NOT NULL UNIQUE,
    motivo TEXT,
    usuario_id INT,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;


-- Dados iniciais ESSENCIAIS

INSERT IGNORE INTO usuarios (nome, email, senha, perfil, auth_type) VALUES
('Administrador', 'admin@farmacia.local', '$2y$10$rlbuVloC6pE..JNlgmysZeSrI2kCP7wOnPiXjK6ykn3OV8kNWBLke', 'admin', 'local');


-- Reabilita checagem de FK

SET FOREIGN_KEY_CHECKS = 1;

-- Adicionar coluna codigo_paciente à tabela pacientes
ALTER TABLE pacientes
ADD COLUMN codigo_paciente VARCHAR(50) NULL AFTER nome,
ADD INDEX idx_codigo_paciente (codigo_paciente);

-- Adicionar coluna codigo_paciente à tabela paciente_medicamentos
ALTER TABLE paciente_medicamentos
ADD COLUMN codigo_paciente VARCHAR(50) NULL AFTER paciente_id,
ADD INDEX idx_codigo_paciente (codigo_paciente);