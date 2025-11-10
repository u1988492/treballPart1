# 🚀 Instrucciones de Deployment - Paso a Paso

## Estado Actual
✅ Recursos Azure creados
✅ Variables SMTP configuradas  
✅ Application Insights vinculado
✅ Archivos de configuración en el repo local
⏳ Falta: Configurar GitHub Actions y hacer deployment

---

## 📋 PASO 1: Configurar GitHub Actions desde Azure Portal

1. **Abre Azure Portal:** https://portal.azure.com/
2. **Busca tu Web App:** `snake-game-snakegame20251110123137`
3. **En el menú lateral:** Click en **"Deployment Center"**
4. **Click en la pestaña "Settings"**
5. **Configurar:**
   - **Source:** GitHub
   - **Autorizar Azure** si te lo pide (aparecerá popup de GitHub)
   - **Organization:** u1988492
   - **Repository:** treballPart1
   - **Branch:** main
   - **Build provider / Workflow Option:** GitHub Actions
   - **Runtime stack:** PHP
   - **Version:** 8.2
6. **Click "Save"** arriba (botón azul)

### ¿Qué pasa al hacer Save?

Azure automáticamente:
- ✅ Crea el secret `AZURE_WEBAPP_PUBLISH_PROFILE` en tu repo de GitHub
- ✅ Crea/actualiza el archivo `.github/workflows/` con workflow de PHP
- ✅ Hace un commit a tu repo (desde Azure)
- ✅ Inicia el primer deployment automáticamente

**Espera 2-3 minutos** y verás:
- En Azure Portal → Deployment Center → Logs: El deployment en progreso
- En GitHub → Actions: El workflow ejecutándose

---

## 📋 PASO 2: Sincronizar Cambios Locales

Después de que Azure haga su commit, necesitas traer esos cambios:

```powershell
# En tu terminal local:
git pull origin main
```

Si hay conflictos (porque ya tenías un workflow):
```powershell
# Ver conflictos
git status

# Aceptar la versión de Azure (recomendado para primera vez)
git checkout --theirs .github/workflows/

# O editar manualmente y resolver conflictos
# Luego:
git add .
git commit -m "Merge Azure workflow"
```

---

## 📋 PASO 3: Verificar Deployment

### 3.1 En Azure Portal

1. Ve a: **Deployment Center → Logs**
2. Deberías ver el deployment más reciente con estado "Success"
3. Si hay error, click en el log para ver detalles

### 3.2 En GitHub

1. Ve a: https://github.com/u1988492/treballPart1/actions
2. Verás el workflow ejecutándose o completado
3. Click en el workflow para ver detalles de cada paso

### 3.3 Probar la URL

Abre en el navegador:
```
https://snake-game-snakegame20251110123137.azurewebsites.net
```

**Si ves un error 500 o "The page cannot be displayed":**
- Es normal la primera vez (bases de datos no inicializadas)
- Continúa con el Paso 4

---

## 📋 PASO 4: Inicializar Bases de Datos

Las bases de datos SQLite necesitan crearse **una vez** en Azure.

### Via Kudu Console (Recomendado)

1. **Azure Portal** → Tu Web App → **Advanced Tools** → **Go**
2. Se abre Kudu en nueva pestaña
3. **Debug console** (menú superior) → **CMD**
4. Navegar a: `D:\home\site\wwwroot`
   ```cmd
   cd D:\home\site\wwwroot
   ```
5. Verificar archivos:
   ```cmd
   dir
   ```
   Deberías ver: `public`, `private`, `setup`, `.deployment`, etc.

6. **Inicializar bases de datos:**
   ```cmd
   cd public
   php init_azure_db.php
   ```

7. **Output esperado:**
   ```
   === Inicialización de Bases de Datos en Azure ===
   
   Entorno: Azure
   -------------------------------------------
   
   Inicializando Users Database...
     ✓ Directorio creado: /home/data
     ✓ Base de datos creada: /home/data/users.db
     ✓ Tablas creadas: users
     ✓ Permisos establecidos (0644)
   
   Inicializando Games Database...
     ✓ Base de datos creada: /home/data/games.db
     ✓ Tablas creadas: game_state, player_latency
     ✓ Permisos establecidos (0644)
   
   === Inicialización completada ===
   ```

8. **Verificar archivos creados:**
   ```cmd
   cd D:\home\data
   dir
   ```
   Deberías ver: `users.db` y `games.db`

---

## 📋 PASO 5: Probar la Aplicación

### 5.1 Abrir el sitio

```
https://snake-game-snakegame20251110123137.azurewebsites.net
```

Ahora debería cargar correctamente ✅

### 5.2 Tests Funcionales

1. **Registro de usuario:**
   - Crear cuenta nueva
   - Verificar validación de contraseña
   
2. **Email de verificación:**
   - Revisar tu email (u1988492@udg.edu)
   - Copiar código 2FA
   - Verificar cuenta

3. **Login:**
   - Iniciar sesión
   - Verificar que entraste

4. **Crear partida:**
   - Ir a lobby
   - Crear nueva partida
   
5. **Segundo jugador:**
   - Abrir en modo incógnito u otro navegador
   - Unirse a la partida
   
6. **Jugar:**
   - Verificar que ambos jugadores se ven
   - Verificar latencia
   - Probar controles

---

## 🔧 Troubleshooting

### Error: "Site can't be reached" o "502 Bad Gateway"

**Causa:** La app está arrancando (cold start en Free tier)

**Solución:** Esperar 30-60 segundos y recargar

---

### Error 500: "Internal Server Error"

**Causa probable:** Bases de datos no inicializadas

**Solución:** Ejecutar `php init_azure_db.php` en Kudu Console

**Ver logs:**
```powershell
az webapp log tail --name snake-game-snakegame20251110123137 --resource-group rg-snake-game
```

---

### Emails no llegan

**Verificar variables SMTP:**
```powershell
az webapp config appsettings list --name snake-game-snakegame20251110123137 --resource-group rg-snake-game --output table | Select-String "SMTP"
```

**Reconfigurar si están en null:**
```powershell
az webapp config appsettings set `
  --name snake-game-snakegame20251110123137 `
  --resource-group rg-snake-game `
  --settings `
    SMTP_HOST="smtp-relay.brevo.com" `
    SMTP_PORT="587" `
    SMTP_USERNAME="u1988492@udg.edu" `
    SMTP_PASSWORD="tu-smtp-key" `
    SMTP_FROM_EMAIL="u1988492@udg.edu" `
    SMTP_FROM_NAME="Mossegam"
```

---

### GitHub Actions falla

**Ver logs en GitHub:**
https://github.com/u1988492/treballPart1/actions

**Problemas comunes:**
- Secret mal configurado → Reconfigurar desde Azure Portal
- Permisos insuficientes → Re-autorizar Azure en GitHub
- Conflicto de workflow → Resolver merge conflicts

---

## 📊 PASO 6: Verificar Monitorización

### Application Insights

1. **Azure Portal** → `snake-game-insights`
2. **Overview** → Ver métricas básicas
3. **Logs** → Ejecutar queries (ver MONITORING.md)

### Queries útiles:

```kusto
// Requests en las últimas 24h
requests
| where timestamp > ago(24h)
| summarize count() by bin(timestamp, 1h)
| render timechart
```

```kusto
// Errores
exceptions
| where timestamp > ago(24h)
| project timestamp, type, outerMessage
| order by timestamp desc
```

---

## ✅ Checklist Final

- [ ] Deployment Center configurado con GitHub
- [ ] Secret `AZURE_WEBAPP_PUBLISH_PROFILE` creado en GitHub
- [ ] Primer deployment completado exitosamente
- [ ] `git pull` ejecutado para sincronizar workflow
- [ ] Bases de datos inicializadas via Kudu
- [ ] Sitio accesible via HTTPS
- [ ] Registro de usuario funciona
- [ ] Emails de verificación llegan
- [ ] Login funciona
- [ ] Dos jugadores pueden jugar simultáneamente
- [ ] Application Insights muestra datos

---

## 📚 Próximos Pasos

Una vez que todo funcione:

1. **Screenshots para el informe** (ver DEPLOYMENT.md sección "Screenshots")
2. **Análisis de métricas** (usar queries de MONITORING.md)
3. **Documentar resultados** en tu informe académico
4. **Presentación/Demo** (hacer warm-up antes: curl la URL 5 min antes)

---

## 🆘 Necesitas Ayuda?

**Ver logs en tiempo real:**
```powershell
az webapp log tail --name snake-game-snakegame20251110123137 --resource-group rg-snake-game
```

**Reiniciar app:**
```powershell
az webapp restart --name snake-game-snakegame20251110123137 --resource-group rg-snake-game
```

**Acceso SSH (si está habilitado):**
```powershell
az webapp ssh --name snake-game-snakegame20251110123137 --resource-group rg-snake-game
```

---

**URL de tu aplicación:** https://snake-game-snakegame20251110123137.azurewebsites.net

**¡Éxito con tu deployment! 🚀**
