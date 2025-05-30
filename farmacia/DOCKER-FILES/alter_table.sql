USE farmacia;

-- Verifica se a coluna existe antes de tentar adicioná-la
SET @dbname = 'farmacia';
SET @tablename = 'usuarios';
SET @columnname = 'auth_type';
SET @preparedStatement = (SELECT IF(
    (
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = @dbname
        AND TABLE_NAME = @tablename
        AND COLUMN_NAME = @columnname
    ) > 0,
    "SELECT 'Column already exists'",
    "ALTER TABLE usuarios ADD COLUMN auth_type ENUM('local', 'ldap') NOT NULL DEFAULT 'local' AFTER perfil"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Atualiza os usuários existentes para serem do tipo 'local'
UPDATE usuarios SET auth_type = 'local' WHERE auth_type IS NULL; 