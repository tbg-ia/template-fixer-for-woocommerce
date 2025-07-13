# Configuración de Repositorio Remoto

## Opción 1: GitHub

### 1. Crear repositorio en GitHub
1. Ve a [github.com](https://github.com) y logueate
2. Click en "New repository" (botón verde)
3. Configuración:
   - Repository name: `template-fixer-for-woocommerce`
   - Description: `WordPress plugin to fix outdated WooCommerce templates`
   - Public/Private: Elige según tu preferencia
   - NO inicialices con README, .gitignore o license (ya los tenemos)
4. Click "Create repository"

### 2. Conectar repositorio local con GitHub
```bash
# Agregar GitHub como origen remoto
git remote add origin https://github.com/TU_USUARIO/template-fixer-for-woocommerce.git

# Verificar que se agregó correctamente
git remote -v

# Subir todas las ramas y tags
git push -u origin main
git push -u origin develop
git push --tags
```

## Opción 2: GitLab

### 1. Crear repositorio en GitLab
1. Ve a [gitlab.com](https://gitlab.com) y logueate
2. Click en "New project" > "Create blank project"
3. Configuración:
   - Project name: `template-fixer-for-woocommerce`
   - Project slug: `template-fixer-for-woocommerce`
   - Visibility: Public/Private según preferencia
   - NO inicialices con README
4. Click "Create project"

### 2. Conectar repositorio local con GitLab
```bash
# Agregar GitLab como origen remoto
git remote add origin https://gitlab.com/TU_USUARIO/template-fixer-for-woocommerce.git

# Verificar que se agregó correctamente
git remote -v

# Subir todas las ramas y tags
git push -u origin main
git push -u origin develop
git push --tags
```

## Opción 3: Bitbucket

### 1. Crear repositorio en Bitbucket
1. Ve a [bitbucket.org](https://bitbucket.org) y logueate
2. Click en "Create repository"
3. Configuración:
   - Repository name: `template-fixer-for-woocommerce`
   - Access level: Private/Public
   - Include a README: NO
   - Version control: Git
4. Click "Create repository"

### 2. Conectar repositorio local con Bitbucket
```bash
# Agregar Bitbucket como origen remoto
git remote add origin https://TU_USUARIO@bitbucket.org/TU_USUARIO/template-fixer-for-woocommerce.git

# Verificar que se agregó correctamente
git remote -v

# Subir todas las ramas y tags
git push -u origin main
git push -u origin develop
git push --tags
```

## Configuración SSH (Recomendado)

Para evitar escribir tu contraseña cada vez, configura SSH:

### 1. Generar clave SSH (si no tienes una)
```bash
ssh-keygen -t ed25519 -C "tu-email@ejemplo.com"
```

### 2. Agregar clave pública a tu cuenta
- GitHub: Settings > SSH and GPG keys > New SSH key
- GitLab: Preferences > SSH Keys
- Bitbucket: Personal settings > SSH keys

### 3. Cambiar URL remota a SSH
```bash
# Para GitHub
git remote set-url origin git@github.com:TU_USUARIO/template-fixer-for-woocommerce.git

# Para GitLab
git remote set-url origin git@gitlab.com:TU_USUARIO/template-fixer-for-woocommerce.git

# Para Bitbucket
git remote set-url origin git@bitbucket.org:TU_USUARIO/template-fixer-for-woocommerce.git
```

## Comandos útiles

```bash
# Ver estado actual
git status

# Ver ramas locales y remotas
git branch -a

# Actualizar desde remoto
git pull origin develop

# Subir cambios
git push origin develop

# Crear nueva rama feature
git checkout -b feature/nueva-funcionalidad
git push -u origin feature/nueva-funcionalidad

# Mergear a main (producción)
git checkout main
git merge develop
git push origin main
git tag -a v2.0.1 -m "Version 2.0.1"
git push --tags
```

## Flujo de trabajo recomendado

1. **develop** - Rama de desarrollo activo
2. **feature/xxx** - Ramas para nuevas características
3. **hotfix/xxx** - Ramas para correcciones urgentes
4. **main** - Solo código en producción con tags de versión