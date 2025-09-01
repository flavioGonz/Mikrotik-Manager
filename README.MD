# 📘 Documentación Técnica — **MikroTik Manager**

## 1. Visión General

Aplicación web desarrollada en **PHP + MySQL** con frontend en **LeafletJS + JavaScript** para la **gestión y monitoreo de routers MikroTik** vía  **REST API RouterOS** .

Objetivos principales:

* Administrar routers (alta, edición, baja).
* Consultar estado en tiempo real (modelo, uptime, CPU, ARP, logs).
* Mostrar la ubicación de cada router en un mapa interactivo.
* Guardar posiciones manuales arrastrando marcadores o clic derecho.
* Monitoreo programado con cron y generación de logs.
* Importar/exportar routers en formato JSON.
* Búsqueda por nombre/IP y búsqueda de direcciones (OpenStreetMap/Nominatim).
* Panel de configuración con pestañas  **General, Backup, Logs, Routers** .

---

## 2. Arquitectura

* **Frontend** : HTML + CSS (custom, dark/light theme) + JavaScript (Leaflet).
* **Backend** : PHP 8, librería personalizada `ros_rest_client.php` para consumo de REST MikroTik.
* **Base de datos** : MySQL (tabla principal `routers`).
* **Infraestructura** : Desplegado en  **XAMPP (Windows)** , con cron configurado vía  *Programador de Tareas* .
* **Integraciones externas** :
* OpenStreetMap (Leaflet tiles).
* Nominatim (geocodificación direcciones).
* RouterOS REST API (`/rest/system/resource`, `/rest/log`, `/rest/ip/arp`, etc.).

---

## 3. Estructura de Archivos

<pre class="overflow-visible!" data-start="1714" data-end="2184"><div class="contain-inline-size rounded-2xl relative bg-token-sidebar-surface-primary"><div class="sticky top-9"><div class="absolute end-0 bottom-0 flex h-9 items-center pe-2"><div class="bg-token-bg-elevated-secondary text-token-text-secondary flex items-center gap-4 rounded-sm px-2 font-sans text-xs"></div></div></div><div class="overflow-y-auto p-4" dir="ltr"><code class="whitespace-pre!"><span><span>mk/
 ├── api/
 │    ├── add_router.php
 │    ├── edit_router.php
 │    ├── delete_router.php
 │    ├── update_location.php
 │    ├── get_routers.php
 │    ├── get_router_details.php
 │    ├── heartbeat.php
 │    ├── trigger_action.php
 │    ├── db_connect.php
 │    ├── ros_rest_client.php
 │    └── cache/ (JSON de routers para tooltips)
 │
 ├── cron/
 │    ├── check_status.php
 │    └── logs/ (logs diarios cron)
 │
 ├── index.html
 ├── map.js
 └── styles.css
</span></span></code></div></div></pre>

---

## 4. Base de Datos

 **Tabla `routers`** :

| Campo            | Tipo                  | Descripción           |
| ---------------- | --------------------- | ---------------------- |
| `id`           | INT PK AI             | Identificador único   |
| `name`         | VARCHAR(100)          | Nombre amigable        |
| `ip_address`   | VARCHAR(255)          | IP o dominio           |
| `api_user`     | VARCHAR(100)          | Usuario API            |
| `api_password` | VARCHAR(100)          | Contraseña API        |
| `api_port`     | INT                   | Puerto REST (ej. 8333) |
| `api_ssl`      | TINYINT               | 0 = http, 1 = https    |
| `lat`          | DOUBLE                | Latitud                |
| `lng`          | DOUBLE                | Longitud               |
| `status`       | ENUM(OK,FAIL,PENDING) | Estado                 |
| `model`        | VARCHAR(100)          | Modelo Router          |
| `version`      | VARCHAR(50)           | Versión RouterOS      |
| `uptime`       | VARCHAR(50)           | Tiempo encendido       |
| `cpu_load`     | INT                   | Carga CPU              |
| `last_checked` | DATETIME              | Última verificación  |

---

## 5. Flujo de Trabajo

### 5.1. Alta de router

* Se abre modal con clic derecho en mapa o botón “Agregar”.
* Se completan  **nombre, IP, user, pass, puerto** .
* Se guardan **lat/lng** de la posición clickeada.
* Inserción vía `add_router.php`.

### 5.2. Edición

* Desde popup del marcador → botón “Editar”.
* Se actualizan campos en `edit_router.php`.

### 5.3. Eliminación

* Desde popup → botón “Eliminar”.
* Se elimina con `delete_router.php`.

### 5.4. Actualización de estado

* Automática: cada 5 min con `cron/check_status.php`.
* Manual: botón popup “🔄” → `check_single_router.php`.
* Manual global: botón flotante “Verificar todos ahora”.

### 5.5. Posicionamiento

* Clic derecho → abre modal con coordenadas de clic.
* Arrastre marcador → `update_location.php`.

---

## 6. Cron (check_status.php)

* Corre cada 5 minutos vía Programador de Tareas:
  <pre class="overflow-visible!" data-start="3964" data-end="4040"><div class="contain-inline-size rounded-2xl relative bg-token-sidebar-surface-primary"><div class="sticky top-9"><div class="absolute end-0 bottom-0 flex h-9 items-center pe-2"><div class="bg-token-bg-elevated-secondary text-token-text-secondary flex items-center gap-4 rounded-sm px-2 font-sans text-xs"></div></div></div><div class="overflow-y-auto p-4" dir="ltr"><code class="whitespace-pre!"><span><span>C</span><span>:</span><span>\</span><span>xampp</span><span>\</span><span>php</span><span>\</span><span>php</span><span>.</span><span>exe</span><span></span><span>-</span><span>f</span><span></span><span>C</span><span>:</span><span>\</span><span>xampp</span><span>\</span><span>htdocs</span><span>\</span><span>mk</span><span>\</span><span>cron</span><span>\</span><span>check_status</span><span>.</span><span>php</span><span>
  </span></span></code></div></div></pre>
* Acciones:
  * Recorre todos los routers de la DB.
  * Llama a `/system/resource`, `/system/routerboard`, `/log`, `/ip/arp`.
  * Guarda estado en DB y JSON (`api/cache/router_ID.json`).
  * Filtra logs solo en  **error, critical, warning** .
  * Escribe archivo de log en `cron/logs/cron-rest-YYYY-MM-DD.log`.

---

## 7. Frontend (map.js + Leaflet)

### Funciones principales:

* **createMarker(router)** : crea marcador en mapa.
* **updateRouterLocation()** : guarda nuevas coordenadas al arrastrar.
* **getRouterDetailsCached()** : lee JSON de cache y arma tooltip.
* **buildTooltipHTML()** : muestra modelo, uptime, CPU, ARP, últimos eventos.
* **fetchAndDrawRouters()** : sincroniza DB ↔ mapa.
* **checkRouterNow()** : verificación individual.
* **deleteRouter()** : eliminación.
* **searchInput** : búsqueda de routers por nombre/IP.
* **addressInput** : búsqueda de direcciones OSM.
* **renderRoutersTable()** : rellena tabla en pestaña Routers.

### Botones flotantes:

* 🌙  **theme-btn** : alterna tema.
* ⚙  **settings-btn** : abre modal configuración.
* 🔒  **lock-btn** : activa/desactiva arrastre de marcadores.
* 🔄  **check-all-btn** : verifica todos los routers.

---

## 8. Backend

### Archivos clave:

* **add_router.php / edit_router.php** : guardan datos de router (con puerto REST).
* **delete_router.php** : elimina router (maneja error si no existe).
* **update_location.php** : guarda lat/lng.
* **get_router_details.php** : devuelve JSON con cache de router.
* **heartbeat.php** : notifica cambios para refrescar mapa en vivo.
* **trigger_action.php** : dispara acciones globales (ej: FORCE_CHECK_ALL).
* **ros_rest_client.php** : wrapper para consultas REST a RouterOS.

---

## 9. Panel de Configuración (settings-modal)

* **General** : cron + vista mapa.
* **Backup** : importar/exportar routers con barra de progreso.
* **Logs** : selector (cron, routers, auditoría) con fetch dinámico.
* **Routers** : tabla HTML con listado de routers (id, nombre, ip, puerto, estado).

---

## 10. Mejoras Pendientes

* [ ] Sincronizar correctamente posición al agregar router con clic derecho (bug de lng=-55).
* [ ] Mejorar validaciones (ej. IP/host válidos).
* [ ] Cifrar `api_password` en DB.
* [ ] Paginación/scroll infinito para tabla de routers.
* [ ] Logs de auditoría: registrar acciones de usuario (alta, baja, mover).
* [ ] Migrar cron a **systemd** o Docker en Linux (si se despliega fuera de Windows).
* [ ] Optimizar tooltips para que muestren más métricas (RAM, interfaces activas).

---

## 11. Notas finales

* La aplicación ya es funcional: se pueden agregar, editar, eliminar y monitorear routers con  **REST MikroTik** .
* Todos los datos clave se guardan en **MySQL** y el estado en **cache JSON** para tooltips rápidos.
* Se soportan **import/export** de routers en JSON.
* Arquitectura modular: cualquier dev puede ampliar backend en `api/` o nuevas pestañas en frontend.

---

👉 Con esta documentación, otro profesional tendrá toda la  **foto completa del proyecto** , sus módulos, qué hace cada archivo y dónde están los puntos de mejora.

¿Querés que además prepare un **diagrama visual (mapa de arquitectura)** en PNG para incluir en esta documentación?
