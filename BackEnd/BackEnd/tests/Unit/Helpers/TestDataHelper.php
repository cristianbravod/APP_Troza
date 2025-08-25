<?php

namespace Tests\Unit\Helpers;

class TestDataHelper
{
    /**
     * Generar datos de prueba para sincronización offline
     */
    public static function generateOfflineSyncData($loadCount = 1, $banksPerLoad = 2)
    {
        $loads = [];
        $photos = [];

        for ($i = 1; $i <= $loadCount; $i++) {
            $tempLoadId = "temp_load_{$i}";
            $banks = [];

            for ($j = 1; $j <= $banksPerLoad; $j++) {
                $tempBankId = "temp_bank_{$i}_{$j}";
                
                $banks[] = [
                    'temp_id' => $tempBankId,
                    'numero_banco' => $j,
                    'estado' => 'CERRADO',
                    'ubicacion_gps' => '-38.7369, -72.5986',
                    'fecha_cierre' => '2024-07-08 10:30:00',
                    'trozas' => [
                        ['diametro' => 22, 'cantidad' => 10],
                        ['diametro' => 24, 'cantidad' => 8],
                        ['diametro' => 26, 'cantidad' => 6]
                    ]
                ];

                $photos[] = [
                    'temp_bank_id' => $tempBankId,
                    'photo_base64' => base64_encode("fake_photo_data_{$i}_{$j}"),
                    'filename' => "banco_{$i}_{$j}.jpg"
                ];
            }

            $loads[] = [
                'temp_id' => $tempLoadId,
                'patente' => "TST" . str_pad($i, 3, '0', STR_PAD_LEFT),
                'ID_TRANSPORTE' => 1,
                'ID_CHOFER' => 1,
                'fecha_carga' => '2024-07-08',
                'ubicacion_gps' => '-38.7369, -72.5986',
                'banks' => $banks
            ];
        }

        return [
            'loads' => $loads,
            'photos' => $photos
        ];
    }

    /**
     * Generar datos de trozas válidos
     */
    public static function generateTrozasData($bankNumber = 1)
    {
        return [
            'numero_banco' => $bankNumber,
            'trozas' => [
                ['diametro' => 22, 'cantidad' => 10],
                ['diametro' => 24, 'cantidad' => 8],
                ['diametro' => 26, 'cantidad' => 6],
                ['diametro' => 28, 'cantidad' => 4],
                ['diametro' => 30, 'cantidad' => 2]
            ]
        ];
    }

    /**
     * Generar patentes válidas chilenas
     */
    public static function generateValidPatentes($count = 5)
    {
        $patentes = [];
        $patterns = [
            'ABCD##',  // 4 letras + 2 números
            'AB####',  // 2 letras + 4 números
            'AB##CD'   // 2 letras + 2 números + 2 letras
        ];

        for ($i = 0; $i < $count; $i++) {
            $pattern = $patterns[$i % count($patterns)];
            $patente = '';
            
            for ($j = 0; $j < strlen($pattern); $j++) {
                $char = $pattern[$j];
                if ($char === '#') {
                    $patente .= rand(0, 9);
                } else {
                    $patente .= chr(rand(65, 90)); // A-Z
                }
            }
            
            $patentes[] = $patente;
        }

        return $patentes;
    }

    /**
     * Generar coordenadas GPS válidas
     */
    public static function generateValidGpsCoordinates($count = 5)
    {
        $coordinates = [];
        
        for ($i = 0; $i < $count; $i++) {
            $lat = rand(-90 * 1000000, 90 * 1000000) / 1000000;
            $lng = rand(-180 * 1000000, 180 * 1000000) / 1000000;
            $coordinates[] = "{$lat}, {$lng}";
        }

        return $coordinates;
    }
}