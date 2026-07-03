#include <WiFi.h>
#include <PubSubClient.h>
#include <TinyGPS++.h>
#include <HardwareSerial.h>

// ==========================================
// KONFIGURASI PIN & JARINGAN
// ==========================================
#define PIN_RELAY  14  
#define PIN_BUZZER 13
#define RXD2       16
#define TXD2       17

const char* ssid        = "Dandellion";     
const char* password    = "123456789p"; 
const char* mqtt_server = "broker.hivemq.com";
const int   mqtt_port   = 1883;

const char* topic_pub   = "smartv_kel8/kendaraan/status";
const char* topic_sub   = "smartv_kel8/kendaraan/perintah";

const char encryption_key = 'K'; 

// ==========================================
// OBJEK & VARIABEL GLOBAL
// ==========================================
WiFiClient espClient;
PubSubClient client(espClient);
TinyGPSPlus gps;
HardwareSerial gpsSerial(2);

volatile bool isSecurityModeON  = false;
volatile bool isAlarmActive = false;
volatile bool isEngineOn = false;

double currentLat = 0.0; 
double currentLng = 0.0;
double parkedLat  = 0.0;       
double parkedLng  = 0.0;
bool hasRealGPS   = false;

TaskHandle_t TaskGPS_Handle;
TaskHandle_t TaskMQTT_Handle;
TaskHandle_t TaskControl_Handle;

// ==========================================
// FUNGSI KEAMANAN
// ==========================================
String encryptDecrypt(String data) {
  String output = "";
  for (int i = 0; i < data.length(); i++) {
    output += (char)(data[i] ^ encryption_key);
  }
  return output;
}

// ==========================================
// FUNGSI JARINGAN & MQTT
// ==========================================
void setup_wifi() {
  Serial.print("Menghubungkan ke Wi-Fi Dandellion...");
  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println("\n[SUKSES] Wi-Fi Terhubung!");
}

void mqttCallback(char* topic, byte* payload, unsigned int length) {
  String message = "";
  for (int i = 0; i < length; i++) {
    message += (char)payload[i];
  }
  
  Serial.print("\n[PERINTAH WEB] : ");
  Serial.println(message);

  if (message == "SECURITY_ON") {
    isSecurityModeON = true;
    isAlarmActive = false; 
    parkedLat = currentLat;
    parkedLng = currentLng;
    Serial.println("-> MODE KEAMANAN AKTIF: Koordinat Parkir Dikunci!");
  } 
  else if (message == "SECURITY_OFF") {
    isSecurityModeON = false;
    isAlarmActive = false;
    Serial.println("-> MODE KEAMANAN NONAKTIF: Kendaraan Bebas Digunakan.");
  }
  // --- TAMBAHAN PERINTAH KUNCI KONTAK ---
  else if (message == "ENGINE_ON") {
    isEngineOn = true;
    Serial.println("-> KUNCI KONTAK: MESIN DIHIDUPKAN.");
  }
  else if (message == "ENGINE_OFF") {
    isEngineOn = false;
    Serial.println("-> KUNCI KONTAK: MESIN DIMATIKAN.");
  }
}

void reconnectMQTT() {
  while (!client.connected()) {
    Serial.print("Menghubungkan ke Broker MQTT...");
    String clientId = "SMARTV-ESP32-" + String(random(0xffff), HEX);
    
    if (client.connect(clientId.c_str())) {
      Serial.println(" [TERHUBUNG!]");
      if(client.subscribe(topic_sub)) {
        Serial.print("[OK] Sistem siap menerima perintah dari: ");
        Serial.println(topic_sub);
      }
    } else {
      Serial.print(" Gagal, rc=");
      Serial.print(client.state());
      Serial.println(" Coba lagi...");
      vTaskDelay(3000 / portTICK_PERIOD_MS);
    }
  }
}

// ==========================================
// TASK FREERTOS
// ==========================================
void TaskGPS(void *pvParameters) {
  static unsigned long lastGpsPrint = 0; 
  
  for (;;) {
    // 1. Bagian pemrosesan data (Tetap sama)
    while (gpsSerial.available() > 0) {
      if (gps.encode(gpsSerial.read())) {
        
        // Logika untuk kunci lokasi & mesin (Tetap ada di dalam)
        if (gps.location.isValid() && gps.location.lat() != 0.0) {
          hasRealGPS = true;
          currentLat = gps.location.lat();
          currentLng = gps.location.lng();
          
          // Di dalam loop TaskGPS, tepat setelah currentLat & currentLng diupdate:
          if (isSecurityModeON) {
            double distance = TinyGPSPlus::distanceBetween(
              currentLat, currentLng, parkedLat, parkedLng
            );
            
            if (distance > 15.0) {
              isAlarmActive = true; // Jarak tembus! Picu status bahaya.
            }
          }
        }
      }
    }

    // 2. Bagian DIAGNOSA (Tambahan - Berjalan setiap 3 detik)
    if (millis() - lastGpsPrint > 3000) {
      lastGpsPrint = millis();
      
      Serial.println("--- [DEBUG GPS] ---");
      Serial.print("Satelit Terdeteksi: "); 
      Serial.println(gps.satellites.value());
      Serial.print("Kualitas Sinyal (HDOP): "); 
      Serial.println(gps.hdop.value());
      
      if (gps.location.isValid()) {
         Serial.println("Status: FIX! Lokasi Akurat.");
      } else {
         Serial.println("Status: MENCARI SINYAL...");
      }
      Serial.println("-------------------");
    }
    
    vTaskDelay(100 / portTICK_PERIOD_MS);
  }
}

void TaskMQTT(void *pvParameters) {
  for (;;) {
    if (!client.connected()) { reconnectMQTT(); }
    client.loop();

    static unsigned long lastMsg = 0;
    unsigned long now = millis();
    
    if (now - lastMsg > 1000) { 
      lastMsg = now;
      
      //membaca status mode keamanan
      String tipeData = hasRealGPS ? "REAL" : "MENCARI_SINYAL";
      String statusKeamanan = isSecurityModeON ? "AKTIF" : "NONAKTIF";
      String statusAlarm = isAlarmActive ? "BAHAYA" : "AMAN";
      String statusMesin = isEngineOn ? "MENYALA" : "MATI";
      String jsonPayload = "{\"lat\":" + String(currentLat, 6) + 
                           ",\"lng\":" + String(currentLng, 6) + 
                           ",\"keamanan\":\"" + statusKeamanan + "\"" +
                           ",\"mesin\":\"" + statusMesin + "\"" +
                           ",\"gps\":\"" + tipeData + "\"" +
                           ",\"alarm\":\"" + statusAlarm + "\"}";
      
      //String encryptedPayload = encryptDecrypt(jsonPayload);
      client.publish(topic_pub, jsonPayload.c_str());
      
      // Mengubah log kirim data agar menampilkan ringkasan koordinat yang dikirim ke web
      Serial.print("[KIRIM DATA -> WEB] Lat: ");
      Serial.print(currentLat, 6);
      Serial.print(" | Lng: ");
      Serial.print(currentLng, 6);
      Serial.print(" | Status GPS: ");
      Serial.println(tipeData);
    }
    vTaskDelay(50 / portTICK_PERIOD_MS);
  }
}

void TaskControl(void *pvParameters) {
  for (;;) {
    // --- LOGIKA MESIN  ---
    if (isAlarmActive) {
      // Jika bahaya terdeteksi, PAKSA RELAY MATI (OVERRIDE KUNCI KONTAK)
      digitalWrite(PIN_RELAY, HIGH); 
    } else {
      // Jika kondisi aman, status dinamo mengikuti tombol Kunci Kontak
      if (isEngineOn) {
        digitalWrite(PIN_RELAY, LOW);
      }else{
        digitalWrite(PIN_RELAY, HIGH);
      }
    }

    // --- LOGIKA BUZZER ---
    if (isAlarmActive) {
      digitalWrite(PIN_BUZZER, HIGH);
      vTaskDelay(200 / portTICK_PERIOD_MS); 
      digitalWrite(PIN_BUZZER, LOW);
      vTaskDelay(200 / portTICK_PERIOD_MS);
    } else {
      digitalWrite(PIN_BUZZER, LOW);
      vTaskDelay(100 / portTICK_PERIOD_MS);
    }
  }
}

void setup() {
  Serial.begin(115200);
  gpsSerial.begin(9600, SERIAL_8N1, RXD2, TXD2);

  pinMode(PIN_RELAY, OUTPUT);
  pinMode(PIN_BUZZER, OUTPUT);
  digitalWrite(PIN_RELAY, HIGH);  
  digitalWrite(PIN_BUZZER, LOW); 

  setup_wifi();
  client.setServer(mqtt_server, mqtt_port);
  client.setCallback(mqttCallback);

  xTaskCreatePinnedToCore(TaskGPS, "GPS", 4096, NULL, 1, &TaskGPS_Handle, 1);
  xTaskCreatePinnedToCore(TaskMQTT, "MQTT", 4096, NULL, 2, &TaskMQTT_Handle, 0);
  xTaskCreatePinnedToCore(TaskControl, "Control", 2048, NULL, 3, &TaskControl_Handle, 1);
}

void loop() {}