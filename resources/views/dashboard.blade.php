<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMART-V Sistem Monitoring Anti-Maling Real Time Vehicle</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/mqtt/dist/mqtt.min.js"></script>
    
    <style>
        body, html {
            height: 100vh;
            overflow: hidden;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
        }
        .status-card { transition: all 0.3s ease; }
        .bg-danger-custom { background-color: #dc3545 !important; color: white; }
        .bg-success-custom { background-color: #198754 !important; color: white; }
        .bg-primary-custom { background-color: #0d6efd !important; color: white; }
        
        #map-container {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            min-height: 0; 
            margin-bottom: 10px;
        }
        #map {
            flex-grow: 1;
            width: 100%;
            border-radius: 0 0 8px 8px;
            z-index: 1;
        }
        .control-panel { padding: 0.75rem; }
    </style>
</head>
<body>

    <div class="container d-flex flex-column vh-100 py-2">

        <div class="text-center mb-2 flex-shrink-0">
            <h3 class="mb-0 fw-bold">🛡️ SMART-V</h3>
            <h6 class="mb-0 fw-bold text-secondary">Sistem Monitoring Anti-Maling Real Time Vehicle</h6>
        </div>
        
        <div class="row g-2 mb-2 flex-shrink-0">
            
            <div class="col-md-4 d-flex flex-column gap-2">
                <div class="card shadow-sm border-0 flex-grow-1">
                    <div class="card-body text-center control-panel">
                        <small class="text-muted fw-bold d-block mb-1">KUNCI KONTAK VIRTUAL</small>
                        <div class="d-flex justify-content-center gap-2">
                            <button class="btn btn-success flex-grow-1 fw-bold" onclick="setEngine('ON')">⚡ ON</button>
                            <button class="btn btn-danger flex-grow-1 fw-bold" onclick="setEngine('OFF')">OFF</button>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 flex-grow-1 bg-dark text-white">
                    <div class="card-body text-center control-panel">
                        <small class="fw-bold d-block mb-1 opacity-75">MODE KEAMANAN (ANTI-MALING)</small>
                        <h6 id="txtKeamanan" class="text-warning fw-bold mb-2">MEMUAT...</h6>
                        <div class="d-flex justify-content-center gap-2">
                            <button class="btn btn-warning btn-sm fw-bold flex-grow-1" onclick="setSecurity('ON')">🔒 AKTIF</button>
                            <button class="btn btn-outline-light btn-sm flex-grow-1" onclick="setSecurity('OFF')">🔓 NONAKTIF</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div id="cardStatus" class="card h-100 shadow-sm status-card border-0">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="card-title m-0 fw-bold">Kondisi Kendaraan Terkini</h6>
                            <span id="txtAlarmBadge" class="badge bg-secondary">MENUNGGU DATA</span>
                        </div>
                        
                        <div class="row g-2">
                            <div class="col-4">
                                <small class="text-uppercase fw-bold opacity-75 d-block">Status Alarm</small>
                                <span id="txtAlarm" class="fs-6 fw-bold">-</span>
                            </div>
                            <div class="col-3">
                                <small class="text-uppercase fw-bold opacity-75 d-block">Mesin</small>
                                <span id="txtMesin" class="fs-6 fw-bold">-</span>
                            </div>
                            <div class="col-5">
                                <small class="text-uppercase fw-bold opacity-75 d-block">Koordinat (GPS)</small>
                                <span class="small fw-mono d-block" id="txtLat">0.000000</span>
                                <span class="small fw-mono d-block" id="txtLng">0.000000</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="map-container" class="card shadow-sm border-0">
            <div class="card-header bg-white py-1 flex-shrink-0">
                <small class="mb-0 fw-bold">📍 Pelacakan Lokasi Langsung</small>
            </div>
            <div id="map"></div>
        </div>

    </div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    const map = L.map('map').setView([-6.914744, 107.609810], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    let vehicleMarker = null;
    setTimeout(() => { map.invalidateSize(); }, 500);

    const topicSub = "smartv_kel8/kendaraan/status";     
    const topicPub = "smartv_kel8/kendaraan/perintah";  
    const client = mqtt.connect('ws://broker.hivemq.com:8000/mqtt');

    client.on('connect', () => {
        client.subscribe(topicSub);
    });

    client.on('message', (topic, payload) => {
        if (topic === topicSub) {
            try {
                const data = JSON.parse(payload.toString());

                // Update UI Teks
                document.getElementById('txtLat').innerText = "Lat: " + data.lat.toFixed(6);
                document.getElementById('txtLng').innerText = "Lng: " + data.lng.toFixed(6);
                document.getElementById('txtMesin').innerText = data.mesin;

                const txtKeamanan = document.getElementById('txtKeamanan');
                txtKeamanan.innerText = (data.keamanan === "AKTIF") ? "TERKUNCI" : "BEBAS";
                txtKeamanan.className = (data.keamanan === "AKTIF") ? "text-danger fw-bold mb-2" : "text-success fw-bold mb-2";

                // Update Status Visual Card
                const cardStatus = document.getElementById('cardStatus');
                const txtAlarm = document.getElementById('txtAlarm');
                const badge = document.getElementById('txtAlarmBadge');
                
                if (data.alarm === "BAHAYA") {
                    txtAlarm.innerText = "TERDETEKSI PERGERAKAN!";
                    badge.innerText = "⚠️ MESIN DIPAKSA MATI";
                    badge.className = "badge bg-light text-danger";
                    cardStatus.className = "card h-100 shadow-sm status-card bg-danger-custom";
                } 
                else if (data.keamanan === "AKTIF" && data.alarm === "AMAN") {
                    txtAlarm.innerText = "PARKIR AMAN";
                    badge.innerText = "🔒 TERKUNCI";
                    badge.className = "badge bg-light text-success";
                    cardStatus.className = "card h-100 shadow-sm status-card bg-success-custom";
                }
                else {
                    txtAlarm.innerText = "KONTROL MANUAL";
                    badge.innerText = "✔️ BEBAS";
                    badge.className = "badge bg-dark";
                    cardStatus.className = "card h-100 shadow-sm status-card bg-white";
                }

                // Update Peta
                if (data.lat !== 0 && data.lng !== 0) {
                    const latLng = [data.lat, data.lng];
                    if (!vehicleMarker) {
                        vehicleMarker = L.marker(latLng).addTo(map).bindPopup('<b>SMART-V</b>').openPopup();
                        map.setView(latLng, 18); 
                    } else {
                        vehicleMarker.setLatLng(latLng);
                    }
                }
            } catch (e) {
                console.error("Gagal parsing JSON:", e);
            }
        }
    });

    // Fungsi Kirim Perintah Keamanan
    function setSecurity(mode) {
        let command = (mode === 'ON') ? "SECURITY_ON" : "SECURITY_OFF";
        client.publish(topicPub, command, { qos: 1 });
    }

    // Fungsi Kirim Perintah Kunci Kontak
    function setEngine(mode) {
        let command = (mode === 'ON') ? "ENGINE_ON" : "ENGINE_OFF";
        client.publish(topicPub, command, { qos: 1 });
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>