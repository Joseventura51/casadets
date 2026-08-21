<?php

namespace App\Http\Controllers;

use App\Models\Almacen;
use App\Models\Caja;
use App\Models\Producto;
use App\Models\Serie;
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
            $caja = Caja::with('almacen')->find(session('caja_id'));
            if (!$caja?->almacen) {
                throw new RuntimeException('La caja activa no tiene un almacén asignado. Asígnelo en Administración → Cajas antes de importar.');
            }
            $resultado = $this->importar($filas, $request->file('archivo')->getClientOriginalName(), $caja);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            return back()->withInput()->with('error', 'No se pudo leer el Excel. Verifique que tenga el formato de reporte de transferencias.');
        }

        $mensaje = "Importación completada: {$resultado['creadas']} salida(s), {$resultado['lineas']} línea(s).";
        if ($resultado['almacenesCreados'] > 0 || $resultado['productosCreados'] > 0) {
            $mensaje .= " Se crearon {$resultado['almacenesCreados']} almacén(es) y {$resultado['productosCreados']} producto(s).";
        }
        if ($resultado['omitidasFiltro'] > 0) {
            $mensaje .= " {$resultado['omitidasFiltro']} fila(s) fueron ignoradas porque no pertenecen al almacén o series de la caja activa.";
        }
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

    private function importar(array $filas, string $nombreArchivo, Caja $caja): array
    {
        $almacenes = Almacen::where('activo', true)->get();
        $productos = Producto::where('activo', true)->get();
        $grupos = [];
        $omitidasFiltro = 0;
        $seriesCaja = Serie::where('tipo_documento', 'salida_almacen')
            ->where('almacen_id', $caja->almacen_id)
            ->where('activa', true)
            ->get()
            ->keyBy(fn ($serie) => $this->normalizar($serie->codigo));

        foreach ($filas as $fila) {
            $documento = trim((string) $fila['documento']);
            $codigo = trim((string) $fila['codigo_producto']);
            $emisor = trim((string) $fila['emisor']);
            $receptor = trim((string) $fila['receptor']);
            $cantidad = $this->numero($fila['cantidad']);
            $serieCodigo = $this->serieDesdeDocumento($documento);
            $serieConfigurada = $serieCodigo !== null
                ? $seriesCaja->get($this->normalizar($serieCodigo))
                : null;
            $origenCoincide = $this->buscarAlmacen(collect([$caja->almacen]), $emisor) !== null;

            if ($documento === '' || $codigo === '' || $emisor === '' || $receptor === '') {
                throw new RuntimeException("La fila {$fila['_fila_excel']} tiene documento, código u almacén vacío.");
            }
            if ($cantidad <= 0) {
                throw new RuntimeException("La fila {$fila['_fila_excel']} tiene una cantidad inválida.");
            }
            if (!$origenCoincide && !$serieConfigurada) {
                $omitidasFiltro++;
                continue;
            }

            $origen = $this->buscarAlmacen($almacenes, $emisor);
            $destino = $this->buscarAlmacen($almacenes, $receptor);
            $producto = $productos->first(fn ($item) => $this->normalizar($item->codigo) === $this->normalizar($codigo));

            if ($origen && $destino && $origen->id === $destino->id) {
                throw new RuntimeException("El origen y destino son iguales en la fila {$fila['_fila_excel']}.");
            }

            $clave = $documento;
            $grupos[$clave] ??= [
                'documento' => $documento,
                'fecha' => $this->fecha($fila['fecha'], $fila['_fila_excel']),
                'origen_nombre' => $emisor,
                'destino_nombre' => $receptor,
                'serie_codigo' => $serieCodigo,
                'filas' => [],
            ];

            if ($this->normalizar($grupos[$clave]['origen_nombre']) !== $this->normalizar($emisor)
                || $this->normalizar($grupos[$clave]['destino_nombre']) !== $this->normalizar($receptor)) {
                throw new RuntimeException("El documento {$documento} tiene distintos orígenes o destinos.");
            }

            $grupos[$clave]['filas'][] = [
                'producto_codigo' => $codigo,
                'codigo' => $codigo,
                'descripcion' => trim((string) $fila['descripcion_producto']) ?: "Producto {$codigo}",
                'medida' => trim((string) $fila['medida']) ?: 'NIU',
                'cantidad' => $cantidad,
                'precio' => $this->numero($fila['precio']),
                'monto' => $this->numero($fila['monto']),
                'lote' => trim((string) $fila['lote']) ?: null,
            ];
        }

        if (!$grupos) {
            throw new RuntimeException('El Excel no contiene movimientos de la caja activa. Revise el almacén asignado y las series de salida.');
        }

        return DB::transaction(function () use ($grupos, $nombreArchivo, $caja, $omitidasFiltro) {
            $creadas = 0;
            $lineas = 0;
            $omitidas = 0;
            $almacenesCreados = 0;
            $productosCreados = 0;
            $seriesCreadas = 0;

            foreach ($grupos as $grupo) {
                if (SalidaAlmacen::where('documento', $grupo['documento'])->exists()) {
                    $omitidas++;
                    continue;
                }

                $origen = $caja->almacen;
                if (!$origen) {
                    $origen = $this->crearAlmacenImportado($grupo['origen_nombre']);
                    $almacenesCreados++;
                }
                $destino = $this->buscarAlmacen(Almacen::where('activo', true)->get(), $grupo['destino_nombre']);
                if (!$destino) {
                    $destino = $this->crearAlmacenImportado($grupo['destino_nombre']);
                    $almacenesCreados++;
                }
                if ($origen->id === $destino->id) {
                    throw new RuntimeException("El documento {$grupo['documento']} tiene origen y destino iguales.");
                }
                $serie = null;
                if ($grupo['serie_codigo']) {
                    $serie = Serie::where('tipo_documento', 'salida_almacen')
                        ->where('codigo', $grupo['serie_codigo'])
                        ->where('almacen_id', $origen->id)
                        ->first();
                    if (!$serie) {
                        $serie = Serie::create([
                            'codigo' => strtoupper($grupo['serie_codigo']),
                            'tipo_documento' => 'salida_almacen',
                            'correlativo_actual' => 0,
                            'activa' => true,
                            'caja_id' => $caja->id,
                            'almacen_id' => $origen->id,
                        ]);
                        $seriesCreadas++;
                    }
                }

                $salida = SalidaAlmacen::create([
                    'documento' => $grupo['documento'],
                    'fecha' => $grupo['fecha'],
                    'almacen_origen_id' => $origen->id,
                    'almacen_destino_id' => $destino->id,
                    'estado' => SalidaAlmacen::REALIZADA,
                    'observaciones' => 'Importado desde reporte de transferencias valorizadas.',
                    'usuario_id' => auth()->id(),
                    'archivo_referencia' => $nombreArchivo,
                ]);

                foreach ($grupo['filas'] as $fila) {
                    $producto = Producto::whereRaw('LOWER(codigo) = ?', [mb_strtolower($fila['producto_codigo'], 'UTF-8')])
                        ->where('activo', true)
                        ->first();
                    if (!$producto) {
                        $producto = Producto::create([
                            'codigo' => $fila['producto_codigo'],
                            'nombre' => $fila['descripcion'],
                            'empresa' => 'casadets',
                            'precio_venta' => 0,
                            'precio_costo' => $fila['precio'],
                            'stock_actual' => 0,
                            'activo' => true,
                        ]);
                        $productosCreados++;
                    }
                    $precio = $fila['precio'] > 0 ? $fila['precio'] : (float) $producto->precio_costo;
                    $salida->detalles()->create([
                        'producto_id' => $producto->id,
                        'codigo_producto' => $fila['codigo'],
                        'descripcion_original' => $fila['descripcion'],
                        'unidad_medida' => $fila['medida'],
                        'cantidad' => $fila['cantidad'],
                        'precio_unitario' => $precio,
                        'monto' => $fila['monto'] > 0 ? $fila['monto'] : round($fila['cantidad'] * $precio, 2),
                        'lote' => $fila['lote'],
                    ]);
                    $this->inventario->mover(
                        $producto, $origen, 'salida', $fila['cantidad'],
                        'salida_almacen', $salida->id,
                        'Importación de salida ' . $grupo['documento'], $precio, true
                    );
                    $lineas++;
                }
                if ($serie) {
                    $numero = $this->correlativoDesdeDocumento($grupo['documento']);
                    if ($numero !== null && $numero > (int) $serie->correlativo_actual) {
                        $serie->update(['correlativo_actual' => $numero]);
                    }
                }
                $creadas++;
            }

            return compact('creadas', 'lineas', 'omitidas', 'omitidasFiltro', 'almacenesCreados', 'productosCreados', 'seriesCreadas');
        });
    }

    private function serieDesdeDocumento(string $documento): ?string
    {
        $posicion = strrpos($documento, '-');
        if ($posicion === false || !ctype_digit(substr($documento, $posicion + 1))) {
            return null;
        }
        return trim(substr($documento, 0, $posicion));
    }

    private function correlativoDesdeDocumento(string $documento): ?int
    {
        $posicion = strrpos($documento, '-');
        $numero = $posicion === false ? null : substr($documento, $posicion + 1);
        return $numero !== null && ctype_digit($numero) ? (int) $numero : null;
    }

    private function crearAlmacenImportado(string $nombre): Almacen
    {
        $base = strtoupper(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $nombre)));
        $base = trim($base, '-') ?: 'ALMACEN';
        $codigo = 'IMP-' . mb_substr($base, 0, 40, 'UTF-8');
        if (Almacen::where('codigo', $codigo)->exists()) {
            $codigo = mb_substr($codigo, 0, 44, 'UTF-8') . '-' . substr(md5($nombre), 0, 5);
        }

        return Almacen::create([
            'codigo' => $codigo,
            'nombre' => trim($nombre),
            'empresa' => 'casadets',
            'activo' => true,
            'observaciones' => 'Creado automáticamente al importar transferencias.',
        ]);
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