@echo off
setlocal enabledelayedexpansion

REM Diretório base do script
set "BASE_DIR=%~dp0"

REM Remover barra invertida final, se houver
if "%BASE_DIR:~-1%"=="\" set "BASE_DIR=%BASE_DIR:~0,-1%"

REM Configurações
set "GITHUB_REPO=rafaelcavalheri/farmaload"
set "DEST=%BASE_DIR%\farmacia"
set "BACKUP_DIR=%BASE_DIR%\backup_farmacia_%date:~-4,4%%date:~-10,2%%date:~-7,2%_%time:~0,2%%time:~3,2%%time:~6,2%"
set "TEMP_DIR=%BASE_DIR%\temp_farmacia_%date:~-4,4%%date:~-10,2%%date:~-7,2%_%time:~0,2%%time:~3,2%%time:~6,2%"

REM Limpar variáveis de tempo
set "BACKUP_DIR=%BACKUP_DIR: =0%"
set "TEMP_DIR=%TEMP_DIR: =0%"

echo ========================================
echo    SCRIPT DE ATUALIZACAO FARMALOAD
echo ========================================
echo.
echo [INFO] Iniciando processo de atualização...
echo [INFO] Destino: %DEST%
echo [INFO] Backup: %BACKUP_DIR%
echo [INFO] Temp: %TEMP_DIR%
echo.

REM Criar diretório temporário logo no início
if not exist "%TEMP_DIR%" mkdir "%TEMP_DIR%"

REM Verificar dependências
echo [INFO] Verificando dependências...
set "missing="

REM Verificar PowerShell
powershell -Command "exit" >nul 2>&1
if errorlevel 1 (
    set "missing=%missing% PowerShell"
)

REM Verificar curl
curl --version >nul 2>&1
if errorlevel 1 (
    set "missing=%missing% curl"
)

REM Verificar unzip ou tar
where unzip >nul 2>&1
if errorlevel 1 (
    where tar >nul 2>&1
    if errorlevel 1 (
        set "missing=%missing% unzip/tar"
    )
)

if not "%missing%"=="" (
    echo [ERROR] Dependências faltando: %missing%
    echo [INFO] Instale as dependências necessárias antes de continuar.
    pause
    exit /b 1
)

echo [SUCCESS] Todas as dependências estão disponíveis.
echo.

REM Obter última versão do GitHub (versão alternativa)
echo [INFO] Buscando última versão no GitHub...
set "api_url=https://api.github.com/repos/%GITHUB_REPO%/releases/latest"

REM Tentar com curl primeiro
echo [INFO] Tentando com curl...
curl -s -H "User-Agent: Farmaload-Updater" "%api_url%" > "%TEMP_DIR%\github_response.json" 2>nul
if errorlevel 1 (
    echo [WARNING] curl falhou, tentando com PowerShell...
    
    REM Usar PowerShell com headers apropriados
    powershell -Command "try { $headers = @{'User-Agent'='Farmaload-Updater'}; $response = Invoke-RestMethod -Uri '%api_url%' -Headers $headers -UseBasicParsing; $response.tag_name + '|' + $response.assets[0].browser_download_url } catch { Write-Host 'ERROR: ' + $_.Exception.Message; exit 1 }" > "%TEMP_DIR%\version_info.txt" 2>nul
    
    if errorlevel 1 (
        echo [ERROR] Falha ao conectar com GitHub API
        echo [INFO] Verifique sua conexão com a internet
        echo [INFO] Verifique se o repositório existe: https://github.com/%GITHUB_REPO%
        pause
        exit /b 1
    )
    
    if exist "%TEMP_DIR%\version_info.txt" (
        set /p version_info=<"%TEMP_DIR%\version_info.txt"
    ) else (
        echo [ERROR] Arquivo de resposta não foi criado
        pause
        exit /b 1
    )
) else (
    echo [SUCCESS] Resposta obtida com curl
    REM Parsear JSON com PowerShell
    powershell -Command "try { $json = Get-Content '%TEMP_DIR%\github_response.json' | ConvertFrom-Json; $json.tag_name + '|' + $json.assets[0].browser_download_url } catch { Write-Host 'ERROR: ' + $_.Exception.Message; exit 1 }" > "%TEMP_DIR%\version_info.txt" 2>nul
    
    if errorlevel 1 (
        echo [ERROR] Falha ao processar resposta do GitHub
        pause
        exit /b 1
    )
    
    if exist "%TEMP_DIR%\version_info.txt" (
        set /p version_info=<"%TEMP_DIR%\version_info.txt"
    ) else (
        echo [ERROR] Arquivo de resposta não foi criado
        pause
        exit /b 1
    )
)

if "%version_info%"=="" (
    echo [ERROR] Não foi possível obter informações da última versão
    pause
    exit /b 1
)

for /f "tokens=1,2 delims=|" %%a in ("%version_info%") do (
    set "version=%%a"
    set "download_url=%%b"
)

echo [SUCCESS] Última versão disponível: %version%
echo [INFO] URL de download: %download_url%
echo.

REM Fazer backup
echo [INFO] Fazendo backup da versão atual...
if exist "%DEST%" (
    if not exist "%BACKUP_DIR%" mkdir "%BACKUP_DIR%"
    xcopy "%DEST%" "%BACKUP_DIR%" /E /I /H /Y >nul
    if errorlevel 1 (
        echo [ERROR] Erro ao criar backup
        pause
        exit /b 1
    )
    echo [SUCCESS] Backup criado em: %BACKUP_DIR%
) else (
    echo [WARNING] Diretório de destino não existe, pulando backup
)
echo.

REM Baixar versão
echo [INFO] Baixando versão %version%...

set "zip_file=%TEMP_DIR%\farmaload_%version%.zip"
curl -L -H "User-Agent: Farmaload-Updater" -o "%zip_file%" "%download_url%"
if errorlevel 1 (
    echo [ERROR] Erro ao baixar arquivo
    echo [INFO] URL: %download_url%
    pause
    exit /b 1
)

echo [SUCCESS] Arquivo baixado com sucesso.
echo.

REM Descompactar
echo [INFO] Descompactando arquivo...
where unzip >nul 2>&1
if not errorlevel 1 (
    unzip -q "%zip_file%" -d "%TEMP_DIR%"
) else (
    tar -xf "%zip_file%" -C "%TEMP_DIR%"
)

if errorlevel 1 (
    echo [ERROR] Erro ao descompactar arquivo
    pause
    exit /b 1
)

echo [SUCCESS] Arquivo descompactado com sucesso.
echo.

REM Procurar pela pasta farmacia
echo [INFO] Procurando pasta farmacia...
set "farmacia_dir="
if exist "%TEMP_DIR%\farmacia" (
    set "farmacia_dir=%TEMP_DIR%\farmacia"
) else if exist "%TEMP_DIR%\farmaload\farmacia" (
    set "farmacia_dir=%TEMP_DIR%\farmaload\farmacia"
) else (
    REM Procurar recursivamente usando PowerShell
    for /f "delims=" %%i in ('powershell -Command "Get-ChildItem -Path '%TEMP_DIR%' -Recurse -Directory -Name 'farmacia' | Select-Object -First 1"') do (
        set "farmacia_dir=%TEMP_DIR%\%%i"
    )
)

if "%farmacia_dir%"=="" (
    echo [ERROR] Pasta 'farmacia' não encontrada no arquivo baixado
    echo [INFO] Conteúdo do diretório temporário:
    dir "%TEMP_DIR%"
    pause
    exit /b 1
)

echo [SUCCESS] Pasta farmacia encontrada: %farmacia_dir%
echo.

REM Aplicar nova versão
echo [INFO] Aplicando nova versão...
for %%i in ("%DEST%") do set "dest_parent=%%~dpi"
if not exist "%dest_parent%" mkdir "%dest_parent%"

if exist "%DEST%" rmdir /S /Q "%DEST%"
xcopy "%farmacia_dir%" "%DEST%" /E /I /H /Y >nul
if errorlevel 1 (
    echo [ERROR] Erro ao aplicar nova versão
    pause
    exit /b 1
)

echo [SUCCESS] Nova versão aplicada com sucesso!
echo.

REM Limpar arquivos temporários
echo [INFO] Limpando arquivos temporários...
if exist "%TEMP_DIR%" rmdir /S /Q "%TEMP_DIR%"
echo [SUCCESS] Arquivos temporários removidos.
echo.

echo ========================================
echo    ATUALIZACAO CONCLUIDA COM SUCESSO!
echo ========================================
echo.
echo [INFO] Nova versão instalada em: %DEST%
echo [INFO] Backup salvo em: %BACKUP_DIR%
echo.

pause
endlocal 