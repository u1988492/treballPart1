# 🐍 Snake Game Multijugador - Despliegue en Azure

[![Azure](https://img.shields.io/badge/Azure-App%20Service-blue)](https://azure.microsoft.com/)
[![PHP](https://img.shields.io/badge/PHP-8.2-purple)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-Academic-green)](LICENSE)

Juego Snake multijugador desarrollado con PHP, SQLite y JavaScript, desplegado en Azure App Service. Proyecto académico para la asignatura de Desarrollo de Juegos Multijugador.

## 🎮 Características

- ✅ **Autenticación segura** con verificación 2FA por email
- ✅ **Recuperación de contraseña** mediante token temporal
- ✅ **Juego multijugador** en tiempo real con medición de latencia
- ✅ **Personalización** de colores de serpiente
- ✅ **Seguridad robusta** (CSRF, XSS, SQL Injection protection)
- ✅ **CI/CD automatizado** con GitHub Actions
- ✅ **Monitoreo** con Application Insights

## 🚀 Demo en Vivo

**URL:** `https://[TU-APP-NAME].azurewebsites.net`

_(Reemplazar después del despliegue)_

## 📋 Requisitos Previos

### Para Desarrollo Local

- PHP 7.4+ con extensiones: PDO, SQLite3
- Composer (opcional)
- Servidor web (Apache/Nginx) o PHP built-in server

### Para Despliegue en Azure

- Cuenta de Azure (Azure for Students recomendado)
- Azure CLI instalado ([Descargar](https://docs.microsoft.com/cli/azure/install-azure-cli))
- Cuenta de GitHub
- Cuenta de Brevo para emails ([Registrarse gratis](https://www.brevo.com/))

## 🏗️ Arquitectura

```
├── public/                 # Web root
│   ├── index.php          # Entry point
│   ├── config.php         # Configuración (auto-detecta Azure)
│   ├── api/               # REST endpoints
│   ├── pages/             # HTML pages
│   ├── js/                # JavaScript cliente
│   └── styles/            # CSS
├── private/               # Fuera del web root
│   ├── users.db          # SQLite - Usuarios (no en Git)
│   └── games.db          # SQLite - Partidas (no en Git)
├── setup/                 # Scripts de inicialización
├── .github/workflows/     # GitHub Actions CI/CD
└── azure-deploy.ps1       # Script de despliegue automatizado
```

## 🔧 Instalación Local

### 1. Clonar el Repositorio

```bash
git clone https://github.com/u1988492/treballPart1.git
cd treballPart1
```

### 2. Inicializar Bases de Datos

**Windows:**

```powershell
cd setup
.\create_databases.cmd
```

**Linux/Mac:**

```bash
cd setup
sqlite3 ../private/users.db ".read create_databases.sql"
sqlite3 ../private/games.db ".read create_databases.sql"
```

### 3. Configurar Email (Opcional para local)

Edita `public/config.php`:

```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USERNAME', 'tu-email@gmail.com');
define('SMTP_PASSWORD', 'tu-app-password');
```

### 4. Iniciar Servidor

```bash
cd public
php -S localhost:8000
```

Abre: http://localhost:8000

## ☁️ Despliegue en Azure

### Opción A: Script Automatizado (Recomendado)

#### 1. Configurar Credenciales de Email

Edita `azure-deploy.ps1`:

```powershell
$SMTP_USERNAME = "tu-email@ejemplo.com"
$SMTP_PASSWORD = "tu-api-key-brevo"
$SMTP_FROM_EMAIL = "tu-email@ejemplo.com"
```

#### 2. Ejecutar Script de Despliegue

```powershell
# Windows PowerShell
.\azure-deploy.ps1
```

```bash
# Linux/Mac
chmod +x azure-deploy.sh
./azure-deploy.sh
```

El script:

- ✅ Crea Resource Group
- ✅ Crea App Service Plan (Free F1)
- ✅ Crea Web App con PHP 8.2
- ✅ Configura variables de entorno
- ✅ Habilita Application Insights
- ✅ Genera perfil de publicación

#### 3. Configurar GitHub Actions

**a) Agregar Secret de GitHub:**

1. Ve a tu repositorio en GitHub
2. `Settings` → `Secrets and variables` → `Actions`
3. Click en `New repository secret`
4. Nombre: `AZURE_WEBAPP_PUBLISH_PROFILE`
5. Valor: Contenido del archivo `publish-profile.xml` generado
6. Click `Add secret`

**b) Actualizar Workflow:**

Edita `.github/workflows/azure-deploy.yml`:

```yaml
app-name: "snake-game-TU-ID-UNICO" # Reemplazar con tu app name
```

#### 4. Desplegar desde GitHub

```bash
git add .
git commit -m "Configure Azure deployment"
git push origin main
```

Ve a GitHub → Actions para ver el progreso.

#### 5. Inicializar Bases de Datos (CRÍTICO)

Después del primer despliegue:

**Opción A - Azure Portal (Kudu):**

1. Azure Portal → Tu Web App → `Advanced Tools` → `Go`
2. `Debug console` → `CMD`
3. Navega a: `D:\home\site\wwwroot`
4. Ejecuta: `php init_azure_db.php`

**Opción B - Azure CLI:**

```bash
az webapp ssh --name TU-APP-NAME --resource-group rg-snake-game
cd /home/site/wwwroot
php init_azure_db.php
```

#### 6. Verificar Despliegue

```bash
curl -I https://TU-APP-NAME.azurewebsites.net
# Debería retornar: HTTP/2 200
```

### Opción B: Comandos Manuales

Ver archivo `azure-commands-reference.ps1` para comandos individuales.

## 📧 Configuración de Email (Brevo)

### 1. Crear Cuenta en Brevo

1. Registrarse en https://www.brevo.com/
2. Verificar email
3. Ir a `SMTP & API` → `SMTP`
4. Crear nuevo `SMTP key`

### 2. Configurar en Azure

```powershell
az webapp config appsettings set `
  --name TU-APP-NAME `
  --resource-group rg-snake-game `
  --settings `
    SMTP_HOST="smtp-relay.brevo.com" `
    SMTP_PORT="587" `
    SMTP_USERNAME="tu-email@ejemplo.com" `
    SMTP_PASSWORD="tu-smtp-key-aqui" `
    SMTP_FROM_EMAIL="tu-email@ejemplo.com" `
    SMTP_FROM_NAME="Mossegam"
```

**Alternativa:** SendGrid (100 emails/día gratis)

## 🧪 Pruebas

### Checklist Post-Despliegue

- [ ] Sitio carga via HTTPS
- [ ] Registro de usuario funciona
- [ ] Email de verificación se envía
- [ ] Login funciona
- [ ] Crear partida funciona
- [ ] Segundo jugador puede unirse
- [ ] Juego funciona correctamente
- [ ] Latencia se muestra

### Tests de Carga

```bash
# Apache Bench
ab -n 100 -c 10 https://TU-APP-NAME.azurewebsites.net/

# Métricas objetivo (Free Tier):
# - Tiempo medio: < 500ms
# - Tasa de éxito: 100%
```

## 📊 Monitoreo

### Ver Logs en Tiempo Real

```powershell
az webapp log tail --name TU-APP-NAME --resource-group rg-snake-game
```

### Application Insights

Azure Portal → Application Insights → snake-game-insights

**Dashboards:**

- Performance (tiempos de respuesta)
- Failures (errores)
- Users (actividad)

**Queries Útiles:**

```kql
// Peticiones por hora
requests
| where timestamp > ago(24h)
| summarize count() by bin(timestamp, 1h)
| render timechart

// Errores recientes
exceptions
| where timestamp > ago(1h)
| order by timestamp desc
```

## 🛠️ Troubleshooting

### Problema: Error 500

```powershell
# Ver logs
az webapp log tail --name TU-APP-NAME --resource-group rg-snake-game

# Reiniciar app
az webapp restart --name TU-APP-NAME --resource-group rg-snake-game
```

### Problema: Base de Datos No Encontrada

```bash
# Conectar via SSH
az webapp ssh --name TU-APP-NAME --resource-group rg-snake-game

# Verificar bases de datos
ls -la /home/data/

# Si no existen, inicializar
cd /home/site/wwwroot
php init_azure_db.php
```

### Problema: Email No Se Envía

```powershell
# Verificar configuración
az webapp config appsettings list `
  --name TU-APP-NAME `
  --resource-group rg-snake-game | findstr SMTP

# Actualizar si es necesario
az webapp config appsettings set --name TU-APP-NAME ...
```

### Problema: Cold Start (30s de carga)

✅ **Esperado en Free Tier**

- La app se suspende tras inactividad
- Primera carga tarda ~30 segundos
- Para producción: Upgrade a Basic B1 con "Always On"

## 💰 Costes

### Tier Actual (Académico)

| Servicio             | Tier     | Coste      |
| -------------------- | -------- | ---------- |
| App Service          | Free F1  | **€0/mes** |
| Application Insights | Free     | **€0/mes** |
| Bandwidth            | Incluido | **€0/mes** |
| **TOTAL**            |          | **€0/mes** |

**Limitaciones:**

- 60 minutos CPU/día
- 1 GB RAM
- Cold starts tras inactividad
- ~10-20 usuarios concurrentes

### Para Producción

**100-500 usuarios:** ~€63/mes  
**1000+ usuarios:** ~€340/mes

Ver `DEPLOYMENT.md` para detalles completos.

## 🔐 Seguridad

### Características Implementadas

✅ HTTPS obligatorio (SSL gratuito de Azure)  
✅ CSRF Protection con tokens únicos  
✅ XSS Protection (`htmlspecialchars`)  
✅ SQL Injection Protection (Prepared Statements)  
✅ Password Hashing (bcrypt)  
✅ Rate Limiting (5 intentos, 15 min bloqueo)  
✅ HaveIBeenPwned API (contraseñas comprometidas)  
✅ Email Verification 2FA  
✅ Secure Cookies en HTTPS

### Recomendaciones para Producción

- [ ] WAF (Web Application Firewall)
- [ ] Azure Key Vault para secretos
- [ ] DDoS Protection
- [ ] Penetration testing
- [ ] Compliance (GDPR, LOPD)

## 📚 Documentación

- **[DEPLOYMENT.md](DEPLOYMENT.md)** - Documentación completa de despliegue
- **[MONITORING.md](MONITORING.md)** - Guía de monitoreo y observabilidad
- **[azure-commands-reference.ps1](azure-commands-reference.ps1)** - Comandos Azure CLI útiles

## 🧹 Limpieza Post-Evaluación

### Eliminar Todos los Recursos

```powershell
# ADVERTENCIA: Esto elimina TODO el Resource Group

az group delete --name rg-snake-game --yes --no-wait

# Verificar eliminación
az group list --output table
```

## 🤝 Contribuciones

Este es un proyecto académico. No se aceptan contribuciones externas.

## 📝 Licencia

Proyecto académico - Universidad de Lleida  
**Asignatura:** Desarrollo de Juegos Multijugador  
**Estudiante:** Claudia Hodoroga  
**Fecha:** Noviembre 2025

## 🎓 Contexto Académico

Este proyecto demuestra:

- ✅ Desarrollo full-stack (PHP + JavaScript)
- ✅ Despliegue cloud (Azure)
- ✅ CI/CD con GitHub Actions
- ✅ Seguridad web
- ✅ Arquitectura escalable
- ✅ Monitoreo y observabilidad
- ✅ Documentación profesional

---

## 🔗 Enlaces Útiles

- **Repositorio:** https://github.com/u1988492/treballPart1
- **Azure Portal:** https://portal.azure.com
- **Brevo (Email):** https://www.brevo.com
- **Azure CLI Docs:** https://docs.microsoft.com/cli/azure/
- **PHP on Azure:** https://docs.microsoft.com/azure/app-service/quickstart-php

---

## 📞 Soporte

Para dudas sobre el proyecto académico:

- **Email:** [Tu email académico]
- **GitHub Issues:** https://github.com/u1988492/treballPart1/issues

---

**⚡ Hecho con PHP, JavaScript y ❤️ para aprender Azure**
