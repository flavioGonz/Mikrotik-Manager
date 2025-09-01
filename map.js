document.addEventListener('DOMContentLoaded', () => {
    console.log("DOM cargado. Iniciando script del mapa.");

    // --- 1. VARIABLES GLOBALES ---
    let markers = {};
    let allRouters = [];
    let lastUpdateTimestamp = '0';
    const HEARTBEAT_INTERVAL_MS = 7000;
    let heartbeatInterval;
    let isLocked = true; // candado activado por defecto
    const TOOLTIP_CACHE_TTL_MS = 300000; // 5 minutos

    // --- 2. ELEMENTOS DEL DOM ---
    const modalBackdrop   = document.getElementById('modal-backdrop');
    const routerModal     = document.getElementById('router-modal');
    const settingsModal   = document.getElementById('settings-modal');
    const searchContainer = document.getElementById('top-ui-container');
    const searchInput     = document.getElementById('router-search-input');
    const searchResults   = document.getElementById('router-search-results');
    const searchBtn       = document.getElementById('search-btn');
    const themeBtn        = document.getElementById('theme-btn');
    const settingsBtn     = document.getElementById('settings-btn');
    const routerForm      = document.getElementById('router-form');
    const forceCheckAllBtn= settingsModal.querySelector('#force-check-all-btn');
    const cronStatusDiv   = settingsModal.querySelector('#cron-status');
    const lockBtn         = document.getElementById('lock-btn');
    const lockIcon        = lockBtn.querySelector('img');
    const tabButtons      = settingsModal.querySelectorAll('.tab-btn');
    const tabPanels       = settingsModal.querySelectorAll('.tab-panel');
    const mapLatInput     = document.getElementById('map-lat-input');
    const mapLngInput     = document.getElementById('map-lng-input');
    const mapZoomInput    = document.getElementById('map-zoom-input');
    const getCurrentViewBtn = document.getElementById('get-current-view-btn');
    const saveViewBtn     = document.getElementById('save-view-btn');

    const exportBtnGlobal = document.getElementById('export-btn-global');
    const importBtnGlobal = document.getElementById('import-btn-global');

    // --- 3. MAPA LEAFLET ---
    const savedLat  = localStorage.getItem('mapLat')  || -34.9011;
    const savedLng  = localStorage.getItem('mapLng')  || -56.1645;
    const savedZoom = localStorage.getItem('mapZoom') || 13;

    const map = L.map('map', { contextmenu: true }).setView([savedLat, savedLng], savedZoom);

    const tileLayers = {
        light: L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'),
        dark:  L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png')
    };

    // Tema
    const themeIcon = themeBtn.querySelector('img');
    function setTheme(theme) {
        document.body.classList.toggle('dark-mode', theme === 'dark');
        if (theme === 'dark') {
            tileLayers.dark.addTo(map);
            map.removeLayer(tileLayers.light);
            themeIcon.src = "https://img.icons8.com/ios-filled/50/ffffff/moon-symbol.png";
        } else {
            tileLayers.light.addTo(map);
            map.removeLayer(tileLayers.dark);
            themeIcon.src = "https://img.icons8.com/ios-filled/50/ffffff/sun.png";
        }
        localStorage.setItem('mapTheme', theme);
    }
    themeBtn.addEventListener('click', () =>
        setTheme(document.body.classList.contains('dark-mode') ? 'light' : 'dark')
    );
    setTheme(localStorage.getItem('mapTheme') || 'light');

    // --- 4. MARCADORES ---
    function getIconByStatus(status) {
        let css = (status === 'OK') ? 'pulse-green' :
                  (status === 'FAIL') ? 'pulse-red' : 'pulse-yellow';
        return L.divIcon({
            className: `pulse-marker ${css}`,
            iconSize: [16, 16],
            iconAnchor: [8, 8],
            popupAnchor: [0, -8],
            tooltipAnchor: [0, -8]
        });
    }

    async function getRouterDetailsCached(routerId) {
    const marker = markers[routerId];
    const now = Date.now();
    if (marker?.__detailsCache && (now - marker.__detailsCache.ts) < TOOLTIP_CACHE_TTL_MS) {
        return marker.__detailsCache.data;
    }
    const res = await fetch(`api/get_router_details.php?id=${routerId}`);
    const j = await res.json();
   if (!j.success) {
    return { __error: "ERROR de API" };
}
    if (marker) marker.__detailsCache = { ts: now, data: j.data };
    return j.data;
}


    function buildTooltipHTML(name, details) {
    if (details?.__error) {
        return `
          <div class="router-tooltip">
            <div><b>${name}</b></div>
            <div style="color:#dc3545; font-weight:bold;">⚠ ${details.__error}</div>
          </div>`;
    }

    const model  = details?.model ?? '—';
    const uptime = details?.uptime ?? '—';
    const cpuTxt = details?.cpu !== null && details?.cpu !== undefined ? details.cpu + "%" : "—";
    const arp    = details?.arp_count ?? '—';

    let logsHTML = '';
    const events = Array.isArray(details?.events) ? details.events : [];
    if (events.length) {
        logsHTML = '<ul class="router-log-list">' + events.map(ev => {
            return `<li><span class="log-time">${ev.time||''}</span>
                      <span class="log-topic">${ev.topics||''}</span>
                      <span class="log-msg">${ev.message||''}</span></li>`;
        }).join('') + '</ul>';
    } else {
        logsHTML = '<div class="router-no-logs">Sin eventos recientes.</div>';
    }

    return `
      <div class="router-tooltip">
        <div><b>${name}</b></div>
        <div><span class="rt-label">Modelo:</span> ${model}</div>
        <div><span class="rt-label">Uptime:</span> ${uptime}</div>
        <div><span class="rt-label">CPU:</span> ${cpuTxt}</div>
        <div><span class="rt-label">ARP:</span> ${arp}</div>
        <div class="rt-sep"></div>
        <div class="rt-subtitle">Últimos eventos</div>
        ${logsHTML}
      </div>`;
}


async function checkRouterNow(id) {
    const marker = markers[id];
    if (!marker) return;
    marker.setIcon(getIconByStatus('PENDING'));
    try {
        const res = await fetch('api/check_single_router.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const result = await res.json();
        if (result.success) {
            marker.setIcon(getIconByStatus(result.new_status));
        } else {
            alert("Error: " + (result.message || "No se pudo verificar."));
            marker.setIcon(getIconByStatus('FAIL'));
        }
    } catch (e) {
        alert("Error de red al verificar.");
        marker.setIcon(getIconByStatus('FAIL'));
    }
}

async function deleteRouter(id) {
    if (!confirm("¿Eliminar router?")) return;
    try {
        const res = await fetch('api/delete_router.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const result = await res.json();
        if (result.success) {
            if (markers[id]) {
                map.removeLayer(markers[id]);
                delete markers[id];
            }
            allRouters = allRouters.filter(r => r.id != id);
            alert("Router eliminado correctamente.");
        } else {
            alert("Error: " + result.message);
        }
    } catch (e) {
        alert("Error de red al eliminar.");
    }
}


function createMarker(router) {
    if (!router.lat || !router.lng) return;

    const marker = L.marker([router.lat, router.lng], {
        icon: getIconByStatus(router.status),
        draggable: !isLocked,
        routerId: router.id
    }).addTo(map);

    const popupContent = `
        <b>${router.name}</b><br>
        IP: ${router.ip_address}<br>
        <div class="popup-buttons">
            <button class="check-btn" data-id="${router.id}">🔄</button>
            <button class="edit-btn" data-id="${router.id}">Editar</button>
            <button class="delete-btn" data-id="${router.id}">Eliminar</button>
        </div>
    `;
    marker.bindPopup(popupContent);

    // 🔑 Enganchar los listeners cada vez que se abre el popup
    marker.on("popupopen", (e) => {
        const popup = e.popup._container;
        popup.querySelector(".check-btn").onclick  = () => checkRouterNow(router.id);
        popup.querySelector(".edit-btn").onclick   = () => openRouterModal(router.id);
        popup.querySelector(".delete-btn").onclick = () => deleteRouter(router.id);
    });

    marker.on('mouseover', async () => {
        try {
            const details = await getRouterDetailsCached(router.id);
            marker.bindTooltip(buildTooltipHTML(router.name, details),
                { direction: 'top', offset: [0,-10], sticky: true, opacity: 0.95 }).openTooltip();
        } catch (e) {
            console.error("Tooltip error:", e);
        }
    });

    marker.on('dragend', (e) => {
        updateRouterLocation({
            id: e.target.options.routerId,
            lat: e.target.getLatLng().lat,
            lng: e.target.getLatLng().lng
        });
    });

    markers[router.id] = marker;
}

// --- ACTUALIZAR UBICACIÓN DEL ROUTER ---
async function updateRouterLocation(newPos) {
    try {
        const res = await fetch('api/update_location.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(newPos)
        });
        const result = await res.json();
        if (!result.success) {
            console.error("Error al actualizar ubicación:", result.message);
        } else {
            console.log("Ubicación guardada:", newPos);
            // ✅ actualizar también en memoria
            const r = allRouters.find(r => r.id == newPos.id);
            if (r) {
                r.lat = newPos.lat;
                r.lng = newPos.lng;
            }
        }
    } catch (err) {
        console.error("Error al llamar update_location.php:", err);
    }
}

    // --- 5. IMPORTAR/EXPORTAR GLOBAL ---
    function exportRoutersGlobal() {
        const blob = new Blob([JSON.stringify(allRouters,null,2)], {type:'application/json'});
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `routers_backup.json`;
        a.click();
        URL.revokeObjectURL(url);
    }

    function importRoutersGlobal() {
        const input = document.createElement('input');
        input.type='file'; input.accept='application/json';
        input.onchange = async ()=> {
            const file = input.files[0]; if(!file) return;
            const data = JSON.parse(await file.text());
            if (Array.isArray(data)) {
                for (const r of data) {
                    await fetch('api/add_router.php',{
                        method:'POST',
                        headers:{'Content-Type':'application/json'},
                        body: JSON.stringify(r)
                    });
                }
            }
            await fetchAndDrawRouters();
        };
        input.click();
    }

    if (exportBtnGlobal) exportBtnGlobal.addEventListener('click', exportRoutersGlobal);
    if (importBtnGlobal) importBtnGlobal.addEventListener('click', importRoutersGlobal);

    // --- 6. FETCH DATA ---
    async function fetchAndDrawRouters() {
        try {
            const res = await fetch('api/get_routers.php?full=true');
            allRouters = await res.json();

            allRouters.forEach(r => {
                if (markers[r.id]) {
                    markers[r.id].setLatLng([r.lat, r.lng]);
                    markers[r.id].setIcon(getIconByStatus(r.status));
                } else {
                    createMarker(r);
                }
            });
        await renderRoutersTable(); // 👈 aquí

        } catch (err) {
            console.error("Error al cargar routers:", err);
        }
    }

    async function checkHeartbeat() {
        try {
            const res = await fetch(`api/heartbeat.php?last_update=${lastUpdateTimestamp}`);
            const data = await res.json();
            if (data.update_available) {
                lastUpdateTimestamp = data.new_timestamp;
                await fetchAndDrawRouters();
            }
        } catch (err) {
            console.error("Error en heartbeat:", err);
        }
    }




   // =============== 7) POPUP EVENTOS (delegación) ===============
  map.on('popupopen', (e) => {
    const popupEl = e.popup._container;
    const id = e.popup._source?.options?.routerId;

    popupEl.querySelector('.edit-btn')?.addEventListener('click', () => openRouterModal(id));
    popupEl.querySelector('.delete-btn')?.addEventListener('click', () => deleteRouter(id));
    popupEl.querySelector('.check-btn')?.addEventListener('click', () => checkRouterNow(id));
  });

  // =============== 8) BÚSQUEDA FLOTANTE ===============
  searchBtn?.addEventListener('click', () => {
    searchContainer.classList.toggle('visible');
    if (searchContainer.classList.contains('visible')) searchInput?.focus();
  });

  searchInput?.addEventListener('input', (e) => {
    const q = (e.target.value || '').toLowerCase();
    searchResults.innerHTML = '';
    if (!q) return;
    const filtered = allRouters.filter(r =>
      (r.name || '').toLowerCase().includes(q) ||
      (r.ip_address || '').toLowerCase().includes(q)
    );
    filtered.forEach(r => {
      const item = document.createElement('div');
      item.className = 'search-result-item';
      item.textContent = `${r.name} (${r.ip_address})`;
      item.addEventListener('click', () => {
        map.setView([r.lat, r.lng], 18);
        markers[r.id]?.openPopup();
        searchContainer.classList.remove('visible');
      });
      searchResults.appendChild(item);
    });
  });

  // --- BÚSQUEDA DE DIRECCIONES ---
const addressInput = document.getElementById('address-search-input');
const addressResults = document.getElementById('address-search-results');
let tempAddressMarker = null;

addressInput?.addEventListener('input', async (e) => {
  const q = (e.target.value || '').trim();
  addressResults.innerHTML = '';
  if (!q) return;

  try {
    const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(q)}&limit=5&countrycodes=uy`;
    const res = await fetch(url, { headers: { 'User-Agent': 'MikroTik-Manager/1.0' } });
    const data = await res.json();

    data.forEach(place => {
      const item = document.createElement('div');
      item.className = 'search-result-item';
      item.textContent = place.display_name;
      item.addEventListener('click', () => {
        const lat = parseFloat(place.lat);
        const lon = parseFloat(place.lon);

        if (tempAddressMarker) map.removeLayer(tempAddressMarker);
        tempAddressMarker = L.marker([lat, lon], {
          icon: L.icon({
            iconUrl: "https://img.icons8.com/color/48/marker.png",
            iconSize: [32, 32],
            iconAnchor: [16, 32]
          })
        }).addTo(map).bindPopup(place.display_name).openPopup();

        map.flyTo([lat, lon], 16);
        addressResults.innerHTML = '';
        addressInput.value = place.display_name;
      });
      addressResults.appendChild(item);
    });
  } catch (err) {
    console.error("Error buscando dirección:", err);
  }
});

    // --- 9. LOCK MOVIMIENTO ---
    lockBtn.classList.add('locked');
    lockIcon.src = 'https://img.icons8.com/ios-filled/50/ffffff/lock.png';
    lockBtn.addEventListener('click', () => {
        isLocked = !isLocked;
        lockBtn.classList.toggle('locked', isLocked);
        lockIcon.src = isLocked
            ? 'https://img.icons8.com/ios-filled/50/ffffff/lock.png'
            : 'https://img.icons8.com/ios-filled/50/ffffff/unlock.png';
        Object.values(markers).forEach(m => isLocked ? m.dragging.disable() : m.dragging.enable());
    });

   // =============== 10) SETTINGS MODAL ===============
  settingsBtn?.addEventListener('click', async () => {
    try {
      if (cronStatusDiv) {
        cronStatusDiv.innerHTML = `<span class="status-dot status-pending"></span> Verificando...`;
        const res = await fetch('api/get_cron_status.php');
        const data = await res.json();
        const css = (data.status === 'active') ? 'status-ok'
                  : (data.status === 'inactive') ? 'status-fail'
                  : 'status-pending';
        cronStatusDiv.innerHTML = `<span class="status-dot ${css}"></span> ${data.message || ''}`;
      }
    } catch {
      if (cronStatusDiv) cronStatusDiv.innerHTML = `<span class="status-dot status-fail"></span> Error al verificar.`;
    }
    openModal(settingsModal);
  });

  // Tabs dentro del modal de Configuración (robusto)
  tabButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      tabButtons.forEach(b => b.classList.remove('active'));
      tabPanels.forEach(p => p.classList.remove('active'));
      btn.classList.add('active');
      const targetSel = btn.getAttribute('data-target');
      const target = targetSel ? document.querySelector(targetSel) : null;
      if (target) target.classList.add('active');
    });
  });

  // --- LOGS PANEL ---
const logsSelect = document.getElementById('logs-select');
const logsOutput = document.getElementById('logs-output');

async function loadLogs(type) {
  logsOutput.textContent = "Cargando logs...";
  let file = null;

  if (type === 'cron') {
    file = 'cron-rest-' + new Date().toISOString().slice(0,10) + '.log';
    try {
      const res = await fetch(`cron/logs/${file}?_=${Date.now()}`);
      if (!res.ok) throw new Error("No se encontró el log del cron.");
      logsOutput.textContent = await res.text();
    } catch (e) {
      logsOutput.textContent = "❌ No hay logs del cron disponibles.";
    }
  }

  if (type === 'routers') {
    try {
      const res = await fetch('api/logs/routers.log?_=' + Date.now());
      if (!res.ok) throw new Error("No se encontró log de routers.");
      logsOutput.textContent = await res.text();
    } catch (e) {
      logsOutput.textContent = "❌ No hay logs de routers disponibles.";
    }
  }

  if (type === 'audit') {
    try {
      const res = await fetch('api/logs/audit.log?_=' + Date.now());
      if (!res.ok) throw new Error("No se encontró log de auditoría.");
      logsOutput.textContent = await res.text();
    } catch (e) {
      logsOutput.textContent = "❌ No hay logs de auditoría disponibles.";
    }
  }
}

logsSelect?.addEventListener('change', (e) => {
  loadLogs(e.target.value);
});


  // Vista actual del mapa → inputs
  getCurrentViewBtn?.addEventListener('click', () => {
    const c = map.getCenter();
    const z = map.getZoom();
    if (mapLatInput)  mapLatInput.value  = c.lat.toFixed(6);
    if (mapLngInput)  mapLngInput.value  = c.lng.toFixed(6);
    if (mapZoomInput) mapZoomInput.value = z;
  });

  // Guardar vista default (localStorage)
  saveViewBtn?.addEventListener('click', () => {
    const lat  = mapLatInput?.value;
    const lng  = mapLngInput?.value;
    const zoom = mapZoomInput?.value;
    if (lat && lng && zoom) {
      localStorage.setItem('mapLat', lat);
      localStorage.setItem('mapLng', lng);
      localStorage.setItem('mapZoom', zoom);
      alert('Vista del mapa guardada. Se aplicará la próxima vez que cargues la página.');
      closeAllModals();
    } else {
      alert('Por favor, completa todos los campos antes de guardar.');
    }
  });

  // Botón "Forzar verificación de todos"
  forceCheckAllBtn?.addEventListener('click', async () => {
    try {
      forceCheckAllBtn.disabled = true;
      forceCheckAllBtn.textContent = 'Verificando...';
      const res = await fetch('api/trigger_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'FORCE_CHECK_ALL' })
      });
      const j = await res.json();
      alert(j.message || (j.success ? 'OK' : 'Error'));
      await fetchAndDrawRouters();
    } catch (e) {
      alert('Error al forzar verificación.');
    } finally {
      forceCheckAllBtn.disabled = false;
      forceCheckAllBtn.textContent = 'Verificar Todos Ahora';
    }
  });

  // =============== 11) ROUTER MODAL (alta/edición) ===============
  function openRouterModal(routerId = null) {
    routerForm?.reset();
    const modalTitle = document.getElementById('modal-title');
    const idInput    = document.getElementById('router-id');
    const nameInput  = document.getElementById('router-name');
    const ipInput    = document.getElementById('router-ip');
    const userInput  = document.getElementById('router-user');

    if (idInput) idInput.value = '';

    if (routerId) {
      if (modalTitle) modalTitle.textContent = 'Editar MikroTik';
      const routerData = allRouters.find(r => r.id == routerId);
      if (routerData) {
        if (idInput)   idInput.value   = routerData.id;
        if (nameInput) nameInput.value = routerData.name;
        if (ipInput)   ipInput.value   = routerData.ip_address;
        if (userInput) userInput.value = routerData.api_user || '';
      }
    } else {
      if (modalTitle) modalTitle.textContent = 'Agregar Nuevo MikroTik';
    }
    openModal(routerModal);
  }

  function openModal(el) {
    if (!el) return;
    modalBackdrop?.classList.remove('hidden');
    el.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
  }

  function closeAllModals() {
    modalBackdrop?.classList.add('hidden');
    routerModal?.classList.add('hidden');
    settingsModal?.classList.add('hidden');
    document.body.style.overflow = '';
  }

  // Cierre por backdrop / cancel
  modalBackdrop?.addEventListener('click', closeAllModals);
  document.querySelectorAll('.cancel-btn').forEach(btn => btn.addEventListener('click', closeAllModals));
  // Cierre por ESC
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeAllModals();
  });

// Guardar router (alta/edición)
routerForm?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const idInput    = document.getElementById('router-id');
  const isEditing  = !!(idInput && idInput.value);

  const portEl = document.getElementById('router-port');
  const apiPort = portEl && portEl.value ? parseInt(portEl.value, 10) : 8333;

const payload = {
  name:         document.getElementById('router-name')?.value,
  ip_address:   document.getElementById('router-ip')?.value,
  api_user:     document.getElementById('router-user')?.value,
  api_password: document.getElementById('router-pass')?.value,
  api_port:     apiPort,
  lat:          parseFloat(document.getElementById('router-lat')?.value || '0'),
  lng:          parseFloat(document.getElementById('router-lng')?.value || '0')
};


  let url = 'api/add_router.php';
  if (isEditing) {
    payload.id = idInput.value;
    url = 'api/edit_router.php';
  } else {
    payload.lat = parseFloat(document.getElementById('router-lat')?.value || '0');
    payload.lng = parseFloat(document.getElementById('router-lng')?.value || '0');
  }

  try {
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const result = await res.json();
    if (result.success) {
      closeAllModals();
      await fetchAndDrawRouters();
    } else {
      alert('Error: ' + (result.message || 'No se pudo guardar.'));
    }
  } catch {
    alert('Error de red.');
  }
});


// --- Agregar MikroTik con clic derecho o presionado largo ---
function prepareNewRouterAt(lat, lng) {
  openRouterModal();
  document.getElementById('router-lat').value = lat;
  document.getElementById('router-lng').value = lng;
}

map.on('contextmenu', (e) => {
  prepareNewRouterAt(e.latlng.lat, e.latlng.lng);
});

// Mantener clic 2s también abre modal
let pressTimer;
map.on('mousedown', (e) => {
  pressTimer = window.setTimeout(() => {
    prepareNewRouterAt(e.latlng.lat, e.latlng.lng);
  }, 2000); // 2 segundos
});
map.on('mouseup', () => clearTimeout(pressTimer));
map.on('dragstart', () => clearTimeout(pressTimer));



function renderRoutersTable() {
  const tbody = document.querySelector('#routers-table tbody');
  if (!tbody) return;
  tbody.innerHTML = '';
  allRouters.forEach(r => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${r.id}</td>
      <td>${r.name}</td>
      <td>${r.ip_address}</td>
      <td>${r.api_port || 8333}</td>
      <td>${r.status}</td>
    `;
    tbody.appendChild(tr);
  });
}

    // --- 12. INIT APP ---
    async function initializeApp() {
        await fetchAndDrawRouters();
        try {
            const res = await fetch(`api/heartbeat.php`);
            const data = await res.json();
            if (data.new_timestamp) lastUpdateTimestamp = data.new_timestamp;
        } catch {}
        heartbeatInterval = setInterval(checkHeartbeat, HEARTBEAT_INTERVAL_MS);
        console.log("App inicializada, heartbeat cada 7s.");
    }

    initializeApp();
});