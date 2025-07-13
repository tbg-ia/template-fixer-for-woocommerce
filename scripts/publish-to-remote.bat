@echo off
echo ========================================
echo   PUBLICADOR AUTOMATICO DE REPOSITORIO
echo   Template Fixer for WooCommerce
echo ========================================
echo.

:: Solicitar información del usuario
set /p PLATFORM="Selecciona plataforma (1=GitHub, 2=GitLab, 3=Bitbucket): "
set /p USERNAME="Ingresa tu nombre de usuario: "
set /p REPONAME="Nombre del repositorio (default: template-fixer-for-woocommerce): "

:: Usar nombre por defecto si está vacío
if "%REPONAME%"=="" set REPONAME=template-fixer-for-woocommerce

:: Configurar URL según la plataforma
if "%PLATFORM%"=="1" (
    set REMOTE_URL=https://github.com/%USERNAME%/%REPONAME%.git
    set PLATFORM_NAME=GitHub
) else if "%PLATFORM%"=="2" (
    set REMOTE_URL=https://gitlab.com/%USERNAME%/%REPONAME%.git
    set PLATFORM_NAME=GitLab
) else if "%PLATFORM%"=="3" (
    set REMOTE_URL=https://%USERNAME%@bitbucket.org/%USERNAME%/%REPONAME%.git
    set PLATFORM_NAME=Bitbucket
) else (
    echo Plataforma no valida!
    pause
    exit /b 1
)

echo.
echo Configurando repositorio remoto en %PLATFORM_NAME%...
echo URL: %REMOTE_URL%
echo.

:: Verificar si ya existe un remoto
git remote get-url origin >nul 2>&1
if %errorlevel% equ 0 (
    echo Removiendo remoto existente...
    git remote remove origin
)

:: Agregar nuevo remoto
echo Agregando repositorio remoto...
git remote add origin %REMOTE_URL%

:: Verificar conexión
echo.
echo Verificando conexion...
git ls-remote origin >nul 2>&1
if %errorlevel% neq 0 (
    echo.
    echo ERROR: No se pudo conectar al repositorio remoto.
    echo.
    echo Posibles causas:
    echo 1. El repositorio no existe en %PLATFORM_NAME%
    echo 2. Credenciales incorrectas
    echo 3. No tienes permisos
    echo.
    echo Por favor:
    echo 1. Crea el repositorio en %PLATFORM_NAME%: %REMOTE_URL%
    echo 2. NO inicialices con README, .gitignore o license
    echo 3. Ejecuta este script nuevamente
    echo.
    pause
    exit /b 1
)

echo Conexion exitosa!
echo.

:: Cambiar a rama main para el push inicial
echo Preparando rama main...
git checkout main

:: Push de rama main
echo Subiendo rama main...
git push -u origin main
if %errorlevel% neq 0 (
    echo Error al subir rama main
    pause
    exit /b 1
)

:: Push de rama develop
echo.
echo Subiendo rama develop...
git checkout develop
git push -u origin develop
if %errorlevel% neq 0 (
    echo Error al subir rama develop
    pause
    exit /b 1
)

:: Push de tags
echo.
echo Subiendo tags...
git push origin --tags
if %errorlevel% neq 0 (
    echo Error al subir tags
    pause
    exit /b 1
)

:: Resumen final
echo.
echo ========================================
echo   PUBLICACION COMPLETADA CON EXITO!
echo ========================================
echo.
echo Repositorio: %REMOTE_URL%
echo Ramas subidas: main, develop
echo Tags subidos: v2.0.0
echo.
echo Proximos pasos:
echo 1. Visita tu repositorio en %PLATFORM_NAME%
echo 2. Configura los settings del repositorio
echo 3. Agrega colaboradores si es necesario
echo 4. Activa GitHub Actions (si usas GitHub)
echo.
echo Para clonar en otro lugar:
echo git clone %REMOTE_URL%
echo.
pause