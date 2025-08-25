<?php
// app/Http/Controllers/Api/TransportesController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transporte;
use App\Models\Chofer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class TransportesController extends Controller
{
    /**
     * Búsqueda inteligente de transportes
     */
   public function search(Request $request): JsonResponse
{
    $search = $request->get('search', '');
    $limit = min($request->get('limit', 20), 50);
    
    $transportes = Transporte::where('VIGENCIA', 1)
        ->where(function($query) use ($search) {
            $query->where('NOMBRE_TRANSPORTES', 'like', "%{$search}%")
                  ->orWhere('RUT', 'like', "%{$search}%");
        })
        ->select([
            'ID_TRANSPORTE', 
            'NOMBRE_TRANSPORTES', 
            'RUT',
            'CONTACTO_TELEFONO'
        ])
        ->orderBy('NOMBRE_TRANSPORTES')
        ->limit($limit)
        ->get();

    return response()->json([
        'success' => true,
        'data' => $transportes,
        'total' => $transportes->count()
    ]);
}

    /**
     * Obtener transporte específico con detalles
     */
    public function show($id): JsonResponse
    {
        try {
            $transporte = Cache::remember("transporte_detail_{$id}", 1800, function() use ($id) {
                return Transporte::with(['choferes' => function($query) {
                        $query->where('VIGENCIA', 1)
                              ->orderBy('NOMBRE_CHOFER');
                    }])
                    ->where('ID_TRANSPORTE', $id)
                    ->where('VIGENCIA', 1)
                    ->first();
            });

            if (!$transporte) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transporte no encontrado'
                ], 404);
            }

            // Agregar estadísticas del transporte
            $transporte->estadisticas = [
                'choferes_activos' => $transporte->choferes->count(),
                'registros_totales' => $transporte->registros()->count(),
                'registros_mes_actual' => $transporte->registros()
                    ->whereMonth('FECHA_INICIO', now()->month)
                    ->whereYear('FECHA_INICIO', now()->year)
                    ->count()
            ];

            return response()->json([
                'success' => true,
                'data' => $transporte
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener transporte: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener choferes de un transporte específico
     */
    public function getChoferes(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'search' => 'nullable|string|min:2|max:100',
            'vigencia' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $search = $request->get('search', '');
            $vigencia = $request->get('vigencia', true);

            $query = Chofer::where('ID_TRANSPORTE', $id);

            if ($vigencia) {
                $query->where('VIGENCIA', 1);
            }

            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $searchTerm = '%' . $search . '%';
                    $q->where('NOMBRE_CHOFER', 'like', $searchTerm)
                      ->orWhere('RUT_CHOFER', 'like', $searchTerm);
                });
            }

            $choferes = $query->select([
                    'ID_CHOFER',
                    'RUT_CHOFER',
                    'NOMBRE_CHOFER',
                    'TELEFONO',
                    'ID_TRANSPORTE'
                ])
                ->orderBy('NOMBRE_CHOFER')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $choferes,
                'total' => $choferes->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener choferes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validar RUT de transporte
     */
    public function validateRut(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'rut' => 'required|string|max:12'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $rut = $request->get('rut');
        
        // Validar formato RUT chileno
        $isValid = $this->validateChileanRut($rut);
        
        if (!$isValid) {
            return response()->json([
                'success' => false,
                'message' => 'RUT inválido',
                'valid' => false
            ]);
        }

        // Verificar si el RUT ya existe
        $exists = Transporte::where('RUT', $rut)->where('VIGENCIA', 1)->exists();

        return response()->json([
            'success' => true,
            'valid' => true,
            'exists' => $exists,
            'formatted_rut' => $this->formatRut($rut)
        ]);
    }

    /**
     * Validar RUT chileno
     */
    private function validateChileanRut($rut): bool
    {
        // Limpiar RUT
        $rut = preg_replace('/[^0-9kK]/', '', strtoupper($rut));
        
        if (strlen($rut) < 8 || strlen($rut) > 9) {
            return false;
        }
        
        $dv = substr($rut, -1);
        $number = substr($rut, 0, -1);
        
        return $dv === $this->calculateDV($number);
    }

    /**
     * Calcular dígito verificador
     */
    private function calculateDV($number): string
    {
        $factor = 2;
        $sum = 0;
        
        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $sum += $number[$i] * $factor;
            $factor = $factor == 7 ? 2 : $factor + 1;
        }
        
        $dv = 11 - ($sum % 11);
        
        if ($dv == 11) return '0';
        if ($dv == 10) return 'K';
        
        return (string)$dv;
    }

    /**
     * Formatear RUT
     */
    private function formatRut($rut): string
    {
        $rut = preg_replace('/[^0-9kK]/', '', strtoupper($rut));
        $dv = substr($rut, -1);
        $number = substr($rut, 0, -1);
        
        return number_format($number, 0, '', '.') . '-' . $dv;
    }
}
