#!/bin/bash

echo "========================================"
echo "  PUBLICADOR AUTOMATICO DE REPOSITORIO"
echo "  Template Fixer for WooCommerce"
echo "========================================"
echo ""

# Función para solicitar información
get_platform() {
    echo "Selecciona plataforma:"
    echo "1) GitHub"
    echo "2) GitLab"
    echo "3) Bitbucket"
    read -p "Opción (1-3): " PLATFORM
    
    read -p "Ingresa tu nombre de usuario: " USERNAME
    read -p "Nombre del repositorio (default: template-fixer-for-woocommerce): " REPONAME
    
    # Usar nombre por defecto si está vacío
    REPONAME=${REPONAME:-template-fixer-for-woocommerce}
    
    # Configurar URL según la plataforma
    case $PLATFORM in
        1)
            REMOTE_URL="https://github.com/${USERNAME}/${REPONAME}.git"
            PLATFORM_NAME="GitHub"
            ;;
        2)
            REMOTE_URL="https://gitlab.com/${USERNAME}/${REPONAME}.git"
            PLATFORM_NAME="GitLab"
            ;;
        3)
            REMOTE_URL="https://${USERNAME}@bitbucket.org/${USERNAME}/${REPONAME}.git"
            PLATFORM_NAME="Bitbucket"
            ;;
        *)
            echo "Plataforma no válida!"
            exit 1
            ;;
    esac
}

# Función para configurar SSH (opcional)
setup_ssh() {
    read -p "¿Quieres usar SSH en lugar de HTTPS? (y/n): " USE_SSH
    
    if [ "$USE_SSH" = "y" ]; then
        case $PLATFORM in
            1)
                REMOTE_URL="git@github.com:${USERNAME}/${REPONAME}.git"
                ;;
            2)
                REMOTE_URL="git@gitlab.com:${USERNAME}/${REPONAME}.git"
                ;;
            3)
                REMOTE_URL="git@bitbucket.org:${USERNAME}/${REPONAME}.git"
                ;;
        esac
        
        echo ""
        echo "Usando SSH. Asegúrate de tener tu clave SSH configurada."
        echo "Si no, visita: https://docs.github.com/en/authentication/connecting-to-github-with-ssh"
    fi
}

# Obtener información del usuario
get_platform
setup_ssh

echo ""
echo "Configurando repositorio remoto en ${PLATFORM_NAME}..."
echo "URL: ${REMOTE_URL}"
echo ""

# Verificar si ya existe un remoto
if git remote get-url origin >/dev/null 2>&1; then
    echo "Removiendo remoto existente..."
    git remote remove origin
fi

# Agregar nuevo remoto
echo "Agregando repositorio remoto..."
git remote add origin "${REMOTE_URL}"

# Verificar conexión
echo ""
echo "Verificando conexión..."
if ! git ls-remote origin >/dev/null 2>&1; then
    echo ""
    echo "ERROR: No se pudo conectar al repositorio remoto."
    echo ""
    echo "Posibles causas:"
    echo "1. El repositorio no existe en ${PLATFORM_NAME}"
    echo "2. Credenciales incorrectas"
    echo "3. No tienes permisos"
    echo ""
    echo "Por favor:"
    echo "1. Crea el repositorio en ${PLATFORM_NAME}: ${REMOTE_URL}"
    echo "2. NO inicialices con README, .gitignore o license"
    echo "3. Ejecuta este script nuevamente"
    echo ""
    exit 1
fi

echo "Conexión exitosa!"
echo ""

# Cambiar a rama main para el push inicial
echo "Preparando rama main..."
git checkout main

# Push de rama main
echo "Subiendo rama main..."
if ! git push -u origin main; then
    echo "Error al subir rama main"
    exit 1
fi

# Push de rama develop
echo ""
echo "Subiendo rama develop..."
git checkout develop
if ! git push -u origin develop; then
    echo "Error al subir rama develop"
    exit 1
fi

# Push de tags
echo ""
echo "Subiendo tags..."
if ! git push origin --tags; then
    echo "Error al subir tags"
    exit 1
fi

# Resumen final
echo ""
echo "========================================"
echo "  PUBLICACION COMPLETADA CON EXITO!"
echo "========================================"
echo ""
echo "Repositorio: ${REMOTE_URL}"
echo "Ramas subidas: main, develop"
echo "Tags subidos: v2.0.0"
echo ""
echo "Próximos pasos:"
echo "1. Visita tu repositorio en ${PLATFORM_NAME}"
echo "2. Configura los settings del repositorio"
echo "3. Agrega colaboradores si es necesario"
echo "4. Activa GitHub Actions (si usas GitHub)"
echo ""
echo "Para clonar en otro lugar:"
echo "git clone ${REMOTE_URL}"
echo ""