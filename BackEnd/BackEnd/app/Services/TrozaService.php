<?php

namespace App\Services;

use App\Models\TrozaHead;
use App\Models\TrozaDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TrozaService
{
    public function createRegistro($data, $userId)
    {
        return DB::transaction(function () use ($data, $userId) {
            return TrozaHead::create([
                'PATENTE_CAMION' => strtoupper($data['patente_camion']),
                'ID_CHOFER' => $data['id_chofer'],
                'ID_TRANSPORTE' => $data['id_transporte'],
                'FECHA_INICIO' => now(),
                'ESTADO' => 'ABIERTO',
                'USER_ID' => $userId,
                'OBSERVACIONES' => $data['observaciones'] ?? null
            ]);
        });
    }

    public function addTrozasToBanco($registroId, $numeroBanco, $trozas)
    {
        return DB::transaction(function () use ($registroId, $numeroBanco, $trozas) {
            // Eliminar registros existentes del banco
            TrozaDetail::where('ID_REGISTRO', $registroId)
                       ->where('NUMERO_BANCO', $numeroBanco)
                       ->delete();

            // Insertar nuevos registros
            foreach ($trozas as $troza) {
                if ($troza['cantidad'] > 0) {
                    TrozaDetail::create([
                        'ID_REGISTRO' => $registroId,
                        'NUMERO_BANCO' => $numeroBanco,
                        'DIAMETRO_CM' => $troza['diametro_cm'],
                        'CANTIDAD_TROZAS' => $troza['cantidad'],
                        'BANCO_CERRADO' => false
                    ]);
                }
            }
        });
    }

    public function cerrarBanco($registroId, $numeroBanco, $foto, $gpsData)
    {
        return DB::transaction(function () use ($registroId, $numeroBanco, $foto, $gpsData) {
            // Subir foto
            $fotoPath = null;
            if ($foto) {
                $nombreArchivo = 'banco_' . $registroId . '_' . $numeroBanco . '_' . time() . '.' . $foto->getClientOriginalExtension();
                $fotoPath = $foto->storeAs('fotos', $nombreArchivo, 'public');
            }

            // Cerrar banco
            TrozaDetail::where('ID_REGISTRO', $registroId)
                       ->where('NUMERO_BANCO', $numeroBanco)
                       ->update([
                           'FECHA_CIERRE_BANCO' => now(),
                           'FOTO_PATH' => $fotoPath,
                           'GPS_LATITUD' => $gpsData['latitud'] ?? null,
                           'GPS_LONGITUD' => $gpsData['longitud'] ?? null,
                           'BANCO_CERRADO' => true
                       ]);

            return $fotoPath;
        });
    }

    public function cerrarRegistro($registroId)
    {
        return DB::transaction(function () use ($registroId) {
            $registro = TrozaHead::findOrFail($registroId);
            $totalTrozas = $registro->detalles()->sum('CANTIDAD_TROZAS');
            
            $registro->update([
                'FECHA_CIERRE' => now(),
                'ESTADO' => 'CERRADO',
                'TOTAL_TROZAS' => $totalTrozas
            ]);

            return $registro;
        });
    }

    public function getRegistroResumen($registroId)
    {
        $registro = TrozaHead::with(['chofer', 'transporte', 'detalles'])
                             ->findOrFail($registroId);

        $resumen = [
            'registro' => $registro,
            'total_trozas' => $registro->detalles->sum('CANTIDAD_TROZAS'),
            'bancos_cerrados' => $registro->detalles->where('BANCO_CERRADO', true)->groupBy('NUMERO_BANCO')->count(),
            'bancos_abiertos' => $registro->detalles->where('BANCO_CERRADO', false)->groupBy('NUMERO_BANCO')->count(),
            'trozas_por_diametro' => $registro->detalles->groupBy('DIAMETRO_CM')->map(function($grupo) {
                return $grupo->sum('CANTIDAD_TROZAS');
            }),
            'fotos' => $registro->detalles->whereNotNull('FOTO_PATH')->pluck('FOTO_PATH')
        ];

        return $resumen;
    }
}
