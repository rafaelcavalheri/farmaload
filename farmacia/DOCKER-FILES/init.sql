-- Criação do banco de dados (o Docker já cria, mas mantemos por segurança)

CREATE DATABASE IF NOT EXISTS farmacia CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;


USE farmacia;


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

    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    renovado TINYINT(1) NOT NULL DEFAULT 0,

    FOREIGN KEY (paciente_id) REFERENCES pacientes(id) ON DELETE CASCADE,

    FOREIGN KEY (medicamento_id) REFERENCES medicamentos(id) ON DELETE RESTRICT,

    FOREIGN KEY (medico_id) REFERENCES medicos(id) ON DELETE SET NULL

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

    tipo ENUM('IMPORTACAO', 'SAIDA', 'ENTRADA', 'AJUSTE') NOT NULL,

    quantidade INT NOT NULL,

    quantidade_anterior INT NOT NULL,

    quantidade_nova INT NOT NULL,

    data TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    observacao TEXT,

    FOREIGN KEY (medicamento_id) REFERENCES medicamentos(id) ON DELETE RESTRICT

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
    tipo ENUM('medicamento', 'paciente') NOT NULL,
    nome VARCHAR(255) NOT NULL,
    quantidade INT DEFAULT 0,
    lote VARCHAR(100),
    validade VARCHAR(20),
    cpf VARCHAR(14),
    observacoes TEXT,
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


-- Dados iniciais ESSENCIAIS

INSERT IGNORE INTO usuarios (nome, email, senha, perfil) VALUES
('Administrador', 'admin', '$2y$10$5/RjfGxPhekTP3ewR/RCX.ryG7Ja3PIwaIID2Q7XcCByTKQRQ60DS', 'admin');


-- Reabilita checagem de FK

SET FOREIGN_KEY_CHECKS = 1;

