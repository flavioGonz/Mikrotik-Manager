# 📘 MikroTik Manager

Aplicación web desarrollada en **PHP + MySQL** con frontend en **LeafletJS + JavaScript** para la gestión y monitoreo de routers MikroTik vía  **REST API RouterOS** .

---

## 🚀 Visión General

**Objetivos principales:**

* Administrar routers ( **alta, edición, baja** ).
* Consultar estado en tiempo real ( **modelo, uptime, CPU, ARP, logs** ).
* Mostrar la ubicación de cada router en un  **mapa interactivo** .
* Guardar posiciones manuales ( **arrastrando marcadores o clic derecho** ).
* Monitoreo programado con **cron** y generación de logs.
* **Importar/exportar routers** en formato JSON.
* Búsqueda por **nombre/IP** y búsqueda de direcciones (OpenStreetMap/Nominatim).
* Panel de configuración con pestañas:  **General, Backup, Logs, Routers** .

---

## 🏗️ Arquitectura

* **Frontend:**

  HTML + CSS (dark/light theme) + JavaScript (LeafletJS).
* **Backend:**

  PHP 8 + librería personalizada `ros_rest_client.php` (consumo REST Mikrotik).
* **Base de datos:**

  MySQL (tabla principal `routers`).
* **Infraestructura:**

  Desplegado en **XAMPP (Windows)** con cron vía Programador de Tareas.
* **Integraciones externas:**

  * OpenStreetMap (Leaflet tiles).
  * Nominatim (geocodificación direcciones).
  * RouterOS REST API (`/rest/system/resource`, `/rest/log`, `/rest/ip/arp`, etc.).

---

## 📂 Estructura de Archivos

<pre class="overflow-visible!" data-start="1583" data-end="2053"><div class="contain-inline-size rounded-2xl relative bg-token-sidebar-surface-primary"><div class="sticky top-9"><div class="absolute end-0 bottom-0 flex h-9 items-center pe-2"><div class="bg-token-bg-elevated-secondary text-token-text-secondary flex items-center gap-4 rounded-sm px-2 font-sans text-xs"></div></div></div><div class="overflow-y-auto p-4" dir="ltr"><code class="whitespace-pre!"><span><span>mk/
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

## 🗄️ Base de Datos

**Tabla `routers`:**

| Campo        | Tipo                  | Descripción           |
| ------------ | --------------------- | ---------------------- |
| id           | INT PK AI             | Identificador único   |
| name         | VARCHAR(100)          | Nombre amigable        |
| ip_address   | VARCHAR(255)          | IP o dominio           |
| api_user     | VARCHAR(100)          | Usuario API            |
| api_password | VARCHAR(100)          | Contraseña API        |
| api_port     | INT                   | Puerto REST (ej. 8333) |
| api_ssl      | TINYINT               | 0 = http, 1 = https    |
| lat          | DOUBLE                | Latitud                |
| lng          | DOUBLE                | Longitud               |
| status       | ENUM(OK,FAIL,PENDING) | Estado actual          |
| model        | VARCHAR(100)          | Modelo Router          |
| version      | VARCHAR(50)           | Versión RouterOS      |
| uptime       | VARCHAR(50)           | Tiempo encendido       |
| cpu_load     | INT                   | Carga CPU              |
| last_checked | DATETIME              | Última verificación  |

---

## 🔄 Flujo de Trabajo

1. **Alta de router**
   * Modal con clic derecho en mapa o botón “Agregar”.
   * Se completan  **nombre, IP, user, pass, puerto** .
   * Inserción vía `add_router.php`.
2. **Edición**
   * Popup → botón “Editar”.
   * Actualización vía `edit_router.php`.
3. **Eliminación**
   * Popup → botón “Eliminar”.
   * `delete_router.php`.
4. **Actualización de estado**
   * Automática: cada 5 min con `cron/check_status.php`.
   * Manual: botón “🔄” en popup (`check_single_router.php`).
   * Global: botón “Verificar todos ahora”.
5. **Posicionamiento**
   * Clic derecho → modal con coordenadas.
   * Arrastre marcador → `update_location.php`.

---

## ⏲️ Cron — `check_status.php`

Configurado cada 5 minutos en Windows:

<pre class="overflow-visible!" data-start="3993" data-end="4068"><div class="contain-inline-size rounded-2xl relative bg-token-sidebar-surface-primary"><div class="sticky top-9"><div class="absolute end-0 bottom-0 flex h-9 items-center pe-2"><div class="bg-token-bg-elevated-secondary text-token-text-secondary flex items-center gap-4 rounded-sm px-2 font-sans text-xs"></div></div></div><div class="overflow-y-auto p-4" dir="ltr"><code class="whitespace-pre! language-bat"><span>C:\xampp\php\php.exe -f C:\xampp\htdocs\mk\cron\check_status.php
</span></code></div></div></pre>

**Acciones:**

* Recorre todos los routers en DB.
* Llama a `/system/resource`, `/system/routerboard`, `/log`, `/ip/arp`.
* Actualiza DB y genera **JSON cache** en `api/cache/router_ID.json`.
* Filtra logs en  **error, critical, warning** .
* Escribe log diario en `cron/logs/cron-rest-YYYY-MM-DD.log`.

---

## 🖥️ Frontend (map.js + Leaflet)

**Funciones principales:**

* `createMarker(router)` → crea marcador en mapa.
* `updateRouterLocation()` → guarda nuevas coordenadas.
* `getRouterDetailsCached()` → lee JSON cache y arma tooltip.
* `buildTooltipHTML()` → modelo, uptime, CPU, ARP, eventos.
* `fetchAndDrawRouters()` → sincroniza DB ↔ mapa.
* `checkRouterNow()` → verificación individual.
* `deleteRouter()` → elimina router.
* `searchInput` → búsqueda por nombre/IP.
* `addressInput` → búsqueda de direcciones OSM.
* `renderRoutersTable()` → tabla de routers en pestaña  *Routers* .

**Botones flotantes:**

* 🌙 **theme-btn** → alternar tema.
* ⚙ **settings-btn** → abrir modal configuración.
* 🔒 **lock-btn** → activar/desactivar arrastre.
* 🔄 **check-all-btn** → verificar todos los routers.

---

## 🔧 Backend

**Archivos clave:**

* `add_router.php`, `edit_router.php` → CRUD de routers.
* `delete_router.php` → elimina router.
* `update_location.php` → guarda coordenadas.
* `get_router_details.php` → devuelve JSON cache.
* `heartbeat.php` → refresco en vivo del mapa.
* `trigger_action.php` → acciones globales (ej: `FORCE_CHECK_ALL`).
* `ros_rest_client.php` → wrapper REST RouterOS.

---

## ⚙️ Panel de Configuración

* **General:** parámetros de cron + vista mapa.
* **Backup:** importar/exportar routers con progreso.
* **Logs:** selector dinámico (cron, routers, auditoría).
* **Routers:** tabla HTML con listado y estado.

---

## 📌 Mejoras Pendientes

* [ ] Sincronizar posición al agregar router con clic derecho ( **bug lng=-55** ).
* [ ] Mejorar validaciones (IP/host).
* [ ] Cifrar `api_password` en DB.
* [ ] Paginación/scroll infinito en tabla de routers.
* [ ] Auditoría: registrar acciones de usuario.
* [ ] Migrar cron a systemd o Docker en Linux.
* [ ] Tooltips más completos (RAM, interfaces activas).

---

## ✅ Estado actual

* La aplicación es  **funcional** : agregar, editar, eliminar y monitorear routers Mikrotik vía REST.
* Datos en **MySQL** y estado en  **JSON cache** .
* Soporta **import/export** en JSON.
* Arquitectura modular: fácil extender tanto **backend (api/)** como  **frontend (pestañas, UI)** .
