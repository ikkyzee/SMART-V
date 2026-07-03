<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

class MqttController extends Controller
{
    public function sendCommand(Request $request)
    {
        // Ambil status dari request (0 untuk mati, 1 untuk nyala)
        $status = $request->input('status');
        
        $server   = env('MQTT_HOST', 'broker.hivemq.com');
        $port     = env('MQTT_PORT', 1883);
        $clientId = env('MQTT_CLIENT_ID', 'laravel_backend_' . uniqid());

        try {
            $mqtt = new MqttClient($server, $port, $clientId);
            
            $settings = (new ConnectionSettings)
                ->setKeepAliveInterval(60)
                ->setUseTls(false); // Set true jika pakai port 8883

            $mqtt->connect($settings, true);
            
            // Publish pesan ke topik kontrol
            $mqtt->publish('motor/kontrol', $status, 0);
            $mqtt->disconnect();

            return response()->json(['message' => 'Perintah berhasil dikirim!', 'status' => $status]);
            
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}