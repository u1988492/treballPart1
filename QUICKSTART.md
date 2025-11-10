# Azure Deployment Quick Reference

## 🎯 Pasos Rápidos de Despliegue

### 1. Pre-requisitos

- [ ] Azure CLI instalado
- [ ] Azure for Students activado
- [ ] GitHub repository creado
- [ ] Cuenta Brevo/SendGrid para SMTP

### 2. Login Azure

```powershell
az login
az account set --subscription "Azure for Students"
```

### 3. Crear Recursos Azure

```powershell
# Resource Group
az group create --name rg-snake-game-claudia --location westeurope

# App Service Plan (Free)
az appservice plan create --name plan-snake-game --resource-group rg-snake-game-claudia --sku FREE --is-linux

# Web App (cambiar nombre por uno único)
az webapp create --name snake-game-ch1110 --resource-group rg-snake-game-claudia --plan plan-snake-game --runtime "PHP:8.2"

# Variables de entorno SMTP
az webapp config appsettings set --name snake-game-ch1110 --resource-group rg-snake-game-claudia --settings SMTP_HOST="smtp-relay.brevo.com" SMTP_PORT="587" SMTP_USERNAME="tu-email@dominio.com" SMTP_PASSWORD="tu-smtp-key" SMTP_FROM_EMAIL="tu-email@dominio.com" SMTP_FROM_NAME="Mossegam"

# Logging
az webapp log config --name snake-game-ch1110 --resource-group rg-snake-game-claudia --application-logging filesystem --level verbose

# Application Insights
az monitor app-insights component create --app snake-game-insights --location westeurope --resource-group rg-snake-game-claudia --application-type web

# Vincular App Insights
$KEY = az monitor app-insights component show --app snake-game-insights --resource-group rg-snake-game-claudia --query instrumentationKey -o tsv
az webapp config appsettings set --name snake-game-ch1110 --resource-group rg-snake-game-claudia --settings APPINSIGHTS_INSTRUMENTATIONKEY="$KEY"
```

### 4. Configurar GitHub Actions

```powershell
# Descargar publish profile
az webapp deployment list-publishing-profiles --name snake-game-ch1110 --resource-group rg-snake-game-claudia --xml > publish-profile.xml

# Copiar contenido y agregarlo como secret en GitHub:
# Settings → Secrets → New secret
# Name: AZURE_WEBAPP_PUBLISH_PROFILE
# Value: [contenido de publish-profile.xml]
```

### 5. Editar Workflow

Editar `.github/workflows/azure-deploy.yml` línea 45:

```yaml
app-name: "snake-game-ch1110" # TU NOMBRE ÚNICO
```

### 6. Deploy

```powershell
git add .
git commit -m "Configure Azure deployment"
git push origin main
```

### 7. Inicializar Bases de Datos

1. Azure Portal → Tu Web App → Advanced Tools → Go
2. Debug console → CMD
3. `cd D:\home\site\wwwroot`
4. `php init_azure_db.php`

### 8. Verificar

Abrir: `https://snake-game-ch1110.azurewebsites.net`

---

## 🔧 Comandos Útiles

### Ver logs en tiempo real

```powershell
az webapp log tail --name snake-game-ch1110 --resource-group rg-snake-game-claudia
```

### Descargar logs

```powershell
az webapp log download --name snake-game-ch1110 --resource-group rg-snake-game-claudia --log-file logs.zip
```

### Reiniciar app

```powershell
az webapp restart --name snake-game-ch1110 --resource-group rg-snake-game-claudia
```

### Ver URL de la app

```powershell
az webapp show --name snake-game-ch1110 --resource-group rg-snake-game-claudia --query defaultHostName -o tsv
```

### Ver configuración

```powershell
az webapp config appsettings list --name snake-game-ch1110 --resource-group rg-snake-game-claudia --output table
```

---

## 🗑️ Limpieza (Post-Calificación)

```powershell
az group delete --name rg-snake-game-claudia --yes --no-wait
```

---

## 📋 Checklist Final

- [ ] Recursos Azure creados
- [ ] Variables SMTP configuradas
- [ ] GitHub Actions funcionando
- [ ] Bases de datos inicializadas
- [ ] Sitio accesible via HTTPS
- [ ] Registro de usuario funciona
- [ ] Emails se envían correctamente
- [ ] Dos jugadores pueden jugar
- [ ] Application Insights capturando datos
- [ ] Screenshots tomados para informe
- [ ] DEPLOYMENT.md y MONITORING.md completados

---

## 🆘 Troubleshooting Rápido

### Error 500

```powershell
az webapp log tail --name snake-game-ch1110 --resource-group rg-snake-game-claudia
```

### BD no encontrada

Ejecutar de nuevo: `php init_azure_db.php` en Kudu console

### Emails no llegan

Verificar variables: `az webapp config appsettings list --name snake-game-ch1110 --resource-group rg-snake-game-claudia`

### GitHub Actions falla

Re-descargar publish profile y actualizar secret en GitHub

---

Ver documentación completa en: **[DEPLOYMENT.md](DEPLOYMENT.md)**
