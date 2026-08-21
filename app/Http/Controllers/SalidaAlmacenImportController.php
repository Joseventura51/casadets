<?php

namespace App\Http\Controllers;

use App\Models\Almacen;
use App\Models\Producto;
use App\Models\SalidaAlmacen;
use App\Services\InventarioAlmacenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class SalidaAlmacenImportController extends Controller
{
    private const COLUMNAS = [
        'fecha', 'documento', 'emisor', 'estado', 'codigo_producto',
        'descripcion_producto', 'medida', 'cantidad', 'precio', 'monto',
        'lote', 'receptor',
    ];

    public function __construct(private InventarioAlmacenService $inventario)
    {
    }

    public function create()
    {
        return view('casadets.salidas_almacen.import');
    }

    public function store(Request $request)
    {
        $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls', 'max:20480'],
        ]);

        try {
            $filas = $this->leerArchivo($request->file('archivo')->getRealPath());
            $resultado = $this->importar($filas, $request->file('archivo')->getClientOriginalName());
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            return back()->withInput()->with('error', 'No se pudo leer el Excel. Verifique que tenga el formato de reporte de transferencias.');
        }

        $mensaje = "Importación completada: {$resultado['creadas']} salida(s), {$resultado['lineas']} línea(s).";
        if ($resultado['omitidas'] > 0) {
            $mensaje .= " {$resultado['omitidas']} salida(s) ya existían y fueron omitidas.";
        }

        return redirect('/casadets/salidas-almacen')->with('success', $mensaje);
    }

    private function leerArchivo(string $ruta): array
    {
        $reader = IOFactory::createReaderForFile($ruta);
        $reader->setReadDataOnly(true);
        $sheet = $reader->load($ruta)->getActiveSheet();
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();
        $headers = array_map(fn ($value) => $this->normalizar((string) $value), $sheet->rangeToArray("A1:{$highestColumn}1", null, true, false)[0] ?? []);
        $esperadas = array_map(fn ($value) => $this->normalizar($value), self::COLUMNAS);

        foreach ($esperadas as $index => $header) {
            if (($headers[$index] ?? null) !== $header) {
                throw new RuntimeException(
                    'El Excel no tiene el formato esperado. La columna ' . ($index + 1) .
                    " debe ser \"{$header}\"."
                );
            }
        }

        $filas = [];
        for ($row = 2; $row <= $highestRow; $row++) {
            $values = $sheet->rangeToArray("A{$row}:{$highestColumn}{$row}", null, true, false)[0] ?? [];
            if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }
            $values = array_pad($values, count(self::COLUMNAS), null);
            $fila = array_combine(self::COLUMNAS, array_slice($values, 0, count(self::COLUMNAS)));
            $fila['_fila_excel'] = $row;
            $filas[] = $fila;
        }

        if (!$filas) {
            throw new RuntimeException('El Excel no contiene filas para importar.');
        }

        return $filas;
    }

    private function importar(array $filas, string $nombreArchivo): array
    {
        $almacenes = Almacen::where('activo', true)->get();
        $productos = Producto::where('activo', true)->get();
        $grupos = [];

        foreach ($filas as $fila) {
            $documento = trim((string) $fila['documento']);
            $codigo = trim((string) $fila['codigo_producto']);
            $emisor = trim((string) $fila['emisor']);
            $receptor = trim((string) $fila['receptor']);
            $cantidad = $this->numero($fila['cantidad']);

            if ($documento === '' || $codigo === '' || $emisor === '' || $receptor === '') {
                throw new RuntimeException("La fila {$fila['_fila_excel']} tiene documento, código u almacén vacío.");
            }
            if ($cantidad <= 0) {
                throw new RuntimeException("La fila {$fila['_fila_excel']} tiene una cantidad inválida.");
            }

            $origen = $this->buscarAlmacen($almacenes, $emisor);
            $destino = $this->buscarAlmacen($almacenes, $receptor);
            $producto = $productos->first(fn ($item) => $this->normalizar($item->codigo) === $this->normalizar($codigo));

            if (!$origen) {
                throw new RuntimeException("No existe un almacén activo para el emisor \"{$emisor}\" (fila {$fila['_fila_excel']}).");
            }
            if (!$destino) {
                throw new RuntimeException("No existe un almacén activo para el receptor \"{$receptor}\" (fila {$fila['_fila_excel']}).");
            }
            if ($origen->id === $destino->id) {
                throw new RuntimeException("El origen y destino son iguales en la fila {$fila['_fila_excel']}.");
            }
            if (!$producto) {
                throw new RuntimeException("No existe un producto activo con código \"{$codigo}\" (fila {$fila['_fila_excel']}).");
            }

            $clave = $documento;
            $grupos[$clave] ??= [
                'documento' => $documento,
                'fecha' => $this->fecha($fila['fecha'], $fila['_fila_excel']),
                'origen' => $origen,
                'destino' => $destino,
                'filas' => [],
            ];

            if ($grupos[$clave]['origen']->id !== $origen->id || $grupos[$clave]['destino']->id !== $destino->id) {
                throw new RuntimeException("El documento {$documento} tiene distintos orígenes o destinos.");
            }

            $grupos[$clave]['filas'][] = [
                'producto' => $producto,
                'codigo' => $codigo,
                'descripcion' => trim((string) $fila['descripcion_producto']) ?: $producto->nombre,
                'medida' => trim((string) $fila['medida']) ?: 'NIU',
                'cantidad' => $cantidad,
                'precio' => $this->numero($fila['precio']),
                'monto' => $this->numero($fila['monto']),
                'lote' => trim((string) $fila['lote']) ?: null,
            ];
        }

        return DB::transaction(function () use ($grupos, $nombreArchivo) {
            $creadas = 0;
            $lineas = 0;
            $omitidas = 0;

            foreach ($grupos as $grupo) {
                if (SalidaAlmacen::where('documento', $grupo['documento'])->exists()) {
                    $omitidas++;
                    continue;
                }

                $salida = SalidaAlmacen::create([
                    'documento' => $grupo['documento'],
                    'fecha' => $grupo['fecha'],
                    'almacen_origen_id' => $grupo['origen']->id,
                    'almacen_destino_id' => $grupo['destino']->id,
                    'estado' => SalidaAlmacen::REALIZADA,
                    'observaciones' => 'Importado desde reporte de transferencias valorizadas.',
                    'usuario_id' => auth()->id(),
                    'archivo_referencia' => $nombreArchivo,
                ]);

                foreach ($grupo['filas'] as $fila) {
                    $precio = $fila['precio'] > 0 ? $fila['precio'] : (float) $fila['producto']->precio_costo;
                    $salida->detalles()->create([
                        'producto_id' => $fila['producto']->id,
                        'codigo_producto' => $fila['codigo'],
                        'descripcion_original' => $fila['descripcion'],
                        'unidad_medida' => $fila['medida'],
                        'cantidad' => $fila['cantidad'],
                        'precio_unitario' => $precio,
                        'monto' => $fila['monto'] > 0 ? $fila['monto'] : round($fila['cantidad'] * $precio, 2),
                        'lote' => $fila['lote'],
                    ]);
                    $this->inventario->mover(
                        $fila['producto'], $grupo['origen'], 'salida', $fila['cantidad'],
                        'salida_almacen', $salida->id,
                        'Importación de salida ' . $grupo['documento'], $precio
                    );
                    $lineas++;
                }
                $creadas++;
            }

            return compact('creadas', 'lineas', 'omitidas');
        });
    }

    private function buscarAlmacen($almacenes, string $valor): ?Almacen
    {
        $valor = $this->normalizar($valor);
        return $almacenes->first(fn ($almacen) =>
            $this->normalizar($almacen->codigo) === $valor ||
            $this->normalizar($almacen->nombre) === $valor
        );
    }

    private function normalizar(?string $valor): string
    {
        $valor = trim((string) $valor);
        $valor = preg_replace('/\s+/', ' ', $valor);
        return mb_strtolower($valor, 'UTF-8');
    }

    private function numero($valor): float
    {
        $valor = trim((string) $valor);
        if ($valor === '') return 0;
        return (float) str_replace(',', '.', str_replace(',', '', $valor));
    }

    private function fecha($valor, int $fila): string
    {
        if (is_numeric($valor)) {
            return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($valor)->format('Y-m-d');
        }
        $valor = trim((string) $valor);
        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d'] as $formato) {
            $fecha = \DateTime::createFromFormat($formato, $valor);
            if ($fecha && $fecha->format($formato) === $valor) return $fecha->format('Y-m-d');
        }
        throw new RuntimeException("La fecha de la fila {$fila} no es válida.");
    }
}