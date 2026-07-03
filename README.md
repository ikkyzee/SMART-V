<h1 align="center">🛡️ SMART-V (Sistem Monitoring Anti-Maling Real Time Vehicle)</h1>

<p align="center">
  <img src="https://img.shields.io/badge/Platform-ESP32-blue.svg" alt="Platform: ESP32">
  <img src="https://img.shields.io/badge/Architecture-FreeRTOS-orange.svg" alt="Architecture: FreeRTOS">
  <img src="https://img.shields.io/badge/Protocol-MQTT-success.svg" alt="Protocol: MQTT">
  <img src="https://img.shields.io/badge/Frontend-Leaflet.js-lightgreen.svg" alt="Frontend: Leaflet.js">
  <img src="https://img.shields.io/badge/Alert-Push_Notification-red.svg" alt="Alert: Push Notification">
</p>

<div align="center">
  <b>Dikembangkan oleh Kelompok 8:</b><br>
  Rizky Arya Aditya (23552011215) &nbsp; dan &nbsp; Bonardo Mandopa Lubis (23552011061)
</div>
<br>

<p align="center">
  <b>Sistem keamanan cerdas berbasis Internet of Things (IoT) dengan fitur pelacakan <i>real-time</i>, pencegahan pencurian otonom berbasis <i>Geofencing</i>, dan sistem peringatan darurat lintas platform.</b>
</p>

---

## 📑 Daftar Isi
1. [Deskripsi Proyek](#-deskripsi-proyek)
2. [Fitur Unggulan](#-fitur-unggulan)
3. [Arsitektur Sistem & Perangkat Lunak](#️-arsitektur-sistem--perangkat-lunak)
4. [Spesifikasi & Konfigurasi Perangkat Keras](#️-spesifikasi--konfigurasi-perangkat-keras)
5. [Protokol Komunikasi Data](#-protokol-komunikasi-data)
6. [Alur & Cara Kerja Sistem Secara Lengkap](#-alur--cara-kerja-sistem-secara-lengkap)
7. [Pengujian Sistem (Blackbox Testing)](#-pengujian-sistem-blackbox-testing)
8. [Kesimpulan](#-kesimpulan)
9. [Lampiran](#-lampiran)

---

## 📖 Deskripsi Proyek
**SMART-V** dikembangkan untuk mengatasi kerentanan sistem keamanan kendaraan konvensional (seperti kunci stang mekanis) yang mudah dibobol. Proyek ini memindahkan kendali keamanan dari perangkat fisik murni ke dalam ekosistem awan (*Cloud/IoT*). 

Sistem ini tidak hanya berfungsi sebagai pelacak (GPS Tracker), tetapi juga sebagai **Sistem Eksekutor Otonom**. Jika kendaraan digeser atau dicuri saat mode keamanan aktif, mikrokontroler akan menghitung pergeseran koordinat bumi secara mandiri dan langsung memutus aliran listrik mesin tanpa perlu menunggu persetujuan dari server.

---

## ✨ Fitur Unggulan
* **📍 Live Telemetry Tracking:** Pelacakan koordinat kendaraan secara *real-time* dengan visualisasi antarmuka peta interaktif menggunakan OpenStreetMap & API Leaflet.js.
* **⚡ Kunci Kontak Virtual (Duplex):** Kontrol jarak jauh untuk menyalakan atau mematikan mesin secara manual dari *dashboard* web.
* **🔒 Autonomous Geofencing (Anti-Maling):** Kalkulasi matematis jarak spasial (menggunakan formula *Haversine*) langsung di dalam ESP32 (*Edge Computing*). Jika kendaraan bergeser > 15 meter saat diparkir, sistem otomatis memutus kelistrikan.
* **🔔 Real-Time Emergency Notification:** Pengiriman notifikasi peringatan darurat langsung ke perangkat pengguna (seperti *Web Push Notification* / Telegram Bot) yang akan tetap masuk meskipun *dashboard* web sedang tidak dibuka di layar utama.
* **⏱️ Non-Blocking Multitasking:** Pemanfaatan **FreeRTOS** pada ESP32 untuk membagi tugas (*task*) pembacaan GPS, komunikasi jaringan MQTT, dan kontrol *hardware* agar berjalan paralel tanpa *lag/blocking*.
* **📱 Kiosk-Mode Dashboard:** *Dashboard* web responsif satu halaman penuh (tanpa *scroll*) yang memberikan pengalaman layaknya aplikasi *native*.

---

## ⚙️ Arsitektur Sistem dan Perangkat Lunak

### 1. Manajemen Multitasking (FreeRTOS)
Karena pembacaan sinyal serial dari modul GPS membutuhkan waktu, pendekatan *sequential programming* biasa akan menyebabkan sistem kehilangan koneksi MQTT. Proyek ini menyelesaikan masalah tersebut dengan arsitektur berikut:

| Nama Task | Core CPU | Prioritas | Fungsi Utama |
| :--- | :---: | :---: | :--- |
| `TaskGPS` | Core 1 | 1 (Low) | Membaca data NMEA dari UART secara terus-menerus dan melakukan *parsing* menggunakan `TinyGPS++`. Jika Mode Keamanan ON, ia memantau jarak pergeseran. |
| `TaskMQTT` | Core 0 | 2 (Medium) | Menjaga stabilitas koneksi Wi-Fi & Broker HiveMQ, merangkai data sensor menjadi JSON, dan menerbitkannya setiap 1 detik. |
| `TaskControl`| Core 1 | 3 (High) | Mengevaluasi status keamanan. Jika bahaya, memprioritaskan pemutusan status Relay secara instan (Override). |

### 2. Pustaka (Libraries)
* **Backend (ESP32):** `PubSubClient.h`, `TinyGPS++.h`, `WiFi.h`.
* **Frontend (Web):** `MQTT.js` (WebSockets), `Leaflet.js`, `Bootstrap 5`.

---

## 🛠️ Spesifikasi dan Konfigurasi Perangkat Keras

### Tabel Pinout (Wiring Diagram)
Sistem ini menggunakan isolasi kelistrikan. Baterai beban dipisahkan dari tegangan logika ESP32 untuk mencegah *Inductive Kickback*.

| Komponen | Pin Modul | Pin ESP32 | Keterangan |
| :--- | :---: | :---: | :--- |
| **GPS NEO-6M** | TX | **GPIO 16 (RX2)** | Menerima data NMEA satelit |
| | RX | **GPIO 17 (TX2)** | Mengirim konfigurasi ke GPS |
| **Relay 5V (Active Low)** | IN / SIG | **GPIO 14** | Sinyal Kontrol Mesin |
| **Buzzer** | Positif (+) | **GPIO 13** | Sinyal Kontrol Alarm |
| **Dinamo (Beban)** | Positif | Terminal **NO** Relay | Beban diputus/disambung oleh Relay |
| **Baterai 18650** | Positif (+) | Terminal **COM** Relay| Sumber tenaga utama dinamo |

---

## 📡 Protokol Komunikasi Data
Sistem menggunakan arsitektur *Publish-Subscribe* via MQTT Publik (`broker.hivemq.com`).
* **Topik Status:** `smartv_kel8/kendaraan/status`
* **Topik Perintah:** `smartv_kel8/kendaraan/perintah`

**Contoh Payload JSON:**
```json
{
  "lat": -6.914744,
  "lng": 107.609810,
  "keamanan": "AKTIF",
  "mesin": "MATI",
  "gps": "REAL",
  "alarm": "BAHAYA"
}
```
---

## 🔄 Alur & Cara Kerja Sistem Secara Lengkap

Sistem SMART-V beroperasi berdasarkan logika status (*State Machine*) yang terbagi ke dalam tiga skenario utama. Seluruh proses ini berjalan secara paralel tanpa *lag* berkat arsitektur *multitasking* FreeRTOS pada ESP32.

### 1. Mode Normal (Kunci Kontak Jarak Jauh)
Pada kondisi ini, Mode Keamanan berada dalam status **NONAKTIF**. Kendaraan digunakan secara normal oleh pemilik yang sah.
* **Kontrol Manual:** Pengguna dapat menghidupkan dan mematikan kelistrikan mesin (*relay*) murni melalui tombol "Kunci Kontak Virtual" (ON/OFF) di *dashboard* web. 
* **Pengabaian Geofence:** Meskipun kendaraan bergerak jauh melintasi jalan raya dan koordinat GPS terus berubah, sistem akan secara cerdas **mengabaikan** perhitungan jarak tersebut. Mesin tidak akan mati mendadak, dan alarm tidak akan berbunyi.

### 2. Aktivasi Mode Keamanan (Kondisi Parkir)
Saat pengguna meninggalkan kendaraannya di area parkir dan menekan tombol **"🔒 AKTIFKAN KEAMANAN"** di web:
* ESP32 menerima perintah via MQTT dan mengubah variabel status keamanan internalnya menjadi aktif.
* **Penguncian Jangkar Spasial:** Pada detik tersebut, sistem mengambil titik Latitude dan Longitude paling akurat dari konstelasi satelit, lalu menguncinya ke dalam memori variabel jangkar (*Anchor Point*).
* Status indikator di antarmuka web seketika berubah menjadi merah (TERKUNCI).

### 3. Deteksi Pencurian, Eksekusi Otonom & Notifikasi Darurat
Jika terjadi skenario pembobolan di mana pelaku mencoba mendorong motor secara diam-diam atau menyalakan mesin secara paksa saat mode keamanan aktif:
* **Kalkulasi Pergeseran:** Modul GPS membaca perubahan kordinat lokasi secara *real-time*. Mikrokontroler ESP32 kemudian menghitung selisih jarak antara titik awal parkir dengan lokasi terkini menggunakan formula matematis *Haversine*.
* **Pelanggaran Radius (15 Meter):** Jika jarak pergeseran melampaui batas toleransi **15 meter**, sistem langsung mengidentifikasinya sebagai tindakan pencurian.
* **Eksekusi Hak Prioritas (Override):** Tanpa perlu menunggu instruksi dari server web, perangkat keras (ESP32) akan secara otonom memutus aliran daya kelistrikan secara paksa (Mesin mati total) dan menyalakan *buzzer* peringatan. 
* **Notifikasi Latar Belakang (Push Alert):** Bersamaan dengan pemutusan mesin, sistem memicu pengiriman notifikasi darurat langsung ke perangkat *(smartphone/PC)* pengguna. Peringatan ini dijamin akan tetap masuk untuk memberikan peringatan dini, meskipun *browser* web sedang tertutup atau berjalan di latar belakang.

---

## 🧪 Pengujian Sistem (Blackbox Testing)

Tabel berikut menunjukkan hasil validasi fungsionalitas sistem secara langsung di lapangan:

| Skenario Pengujian | Aksi yang Dilakukan | Hasil Aktual | Status |
| :--- | :--- | :--- | :---: |
| **Kunci Kontak (Duplex)** | Menekan tombol "⚡ ON" pada antarmuka web. | Relay menyala (*Active Low*), dinamo berputar. Status Web = "MENYALA". | ✅ Valid |
| **Aktivasi Keamanan** | Menekan "🔒 AKTIFKAN" saat kendaraan parkir. | ESP32 menyimpan koordinat jangkar. Sistem memasuki mode siaga penuh. | ✅ Valid |
| **Simulasi Pencurian (Geofence)** | Membawa alat bergeser > 15 Meter dari titik awal. | Buzzer menyala, Relay mati paksa. **Notifikasi darurat masuk ke perangkat (HP/PC)**. Indikator web = BAHAYA. | ✅ Valid |
| **Deaktivasi Alarm** | Menekan "🔓 NONAKTIF" dari antarmuka web. | Alarm seketika mati, sistem kembali ke mode manual (bisa dikendalikan). | ✅ Valid |

---

## 🎯 Kesimpulan

Proyek SMART-V berhasil membuktikan bahwa integrasi antara mikrokontroler ESP32, teknologi GPS, dan protokol MQTT dapat menghasilkan sistem keamanan kendaraan yang komprehensif, responsif, dan proaktif. Penggunaan arsitektur **FreeRTOS** terbukti sangat krusial dalam mengatasi masalah *blocking* pada pembacaan sensor serial satelit, sehingga komunikasi data dua arah berjalan tanpa hambatan (*zero-lag*). 

Keberhasilan penerapan algoritma **Geofencing Otonom** di dalam ESP32 (*Edge Computing*) memastikan sistem mampu mengambil keputusan kritis (memutus kelistrikan) secara seketika saat radius terlanggar. Ditambah dengan integrasi **Sistem Notifikasi Darurat** yang berjalan di latar belakang, SMART-V berevolusi dari sekadar alat pelacak pasif menjadi purwarupa sistem proteksi anti-maling aktif yang tangguh, cerdas, dan menjamin ketenangan (*peace of mind*) penggunanya kapan pun dan di mana pun.

---

## 📎 Lampiran

* 🎥 **Video Demo Sistem:** [Tonton Pengujian Lapangan SMART-V di YouTube](https://youtube.com/...)
* 💼 **Profil Pengembang:** [Rizky Arya Aditya - LinkedIn](https://www.linkedin.com/in/...)

<br>
<p align="center">
  <i>Proyek UAS Mata Kuliah Mikrokontroler - Program Studi Teknik Informatika</i><br>
  <b>Kelompok 8</b>
</p>
