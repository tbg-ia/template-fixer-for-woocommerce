@echo off
echo ========================================
echo   CREADOR AUTOMATICO DE REPO EN GITHUB
echo   Template Fixer for WooCommerce
echo ========================================
echo.

:: Verificar si GitHub CLI está instalado
where gh >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: GitHub CLI no está instalado.
    echo.
    echo Por favor instala GitHub CLI:
    echo 1. Visita: https://cli.github.com/
    echo 2. Descarga e instala gh
    echo 3. Ejecuta: gh auth login
    echo 4. Vuelve a ejecutar este script
    echo.
    pause
    exit /b 1
)

:: Verificar autenticación
gh auth status >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: No estás autenticado en GitHub CLI.
    echo.
    echo Ejecuta: gh auth login
    echo.
    pause
    exit /b 1
)

echo GitHub CLI detectado y autenticado!
echo.

:: Solicitar información
set /p VISIBILITY="Repositorio publico o privado? (public/private): "
set REPONAME=template-fixer-for-woocommerce
set DESCRIPTION="WordPress plugin to automatically fix outdated WooCommerce templates while preserving customizations"

:: Crear repositorio
echo Creando repositorio en GitHub...
gh repo create %REPONAME% --%VISIBILITY% --description "%DESCRIPTION%" --source=. --remote=origin --push

if %errorlevel% equ 0 (
    echo.
    echo ========================================
    echo   REPOSITORIO CREADO EXITOSAMENTE!
    echo ========================================
    echo.
    
    :: Obtener URL del repositorio
    for /f "tokens=*" %%i in ('gh repo view --json url -q .url') do set REPO_URL=%%i
    
    echo URL del repositorio: %REPO_URL%
    echo.
    echo El repositorio ha sido creado y todo el código ha sido subido.
    echo.
    echo Proximos pasos:
    echo 1. Visita tu repositorio: %REPO_URL%
    echo 2. Ve a Settings > Actions y habilita GitHub Actions
    echo 3. Agrega una descripción más detallada
    echo 4. Configura las GitHub Pages si deseas documentación
    echo.
    
    :: Abrir en navegador
    set /p OPEN_BROWSER="Quieres abrir el repositorio en el navegador? (s/n): "
    if /i "%OPEN_BROWSER%"=="s" (
        start %REPO_URL%
    )
) else (
    echo.
    echo ERROR: No se pudo crear el repositorio.
    echo Posibles causas:
    echo - Ya existe un repositorio con ese nombre
    echo - No tienes permisos para crear repositorios
    echo - Problema de conexión
)

echo.
pause