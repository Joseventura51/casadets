<?php

namespace App\Support;

class PermisoCatalog
{
    const MODULOS = [
        'dashboard'      => ['label' => 'Dashboard',                  'grupo' => 'General'],
        'caja'           => ['label' => 'Caja',                       'grupo' => 'CASADETS'],
        'ventas'         => ['label' => 'Ventas',                     'grupo' => 'CASADETS'],
        'pendientes'     => ['label' => 'Pendientes',                 'grupo' => 'CASADETS'],
        'compras'        => ['label' => 'Compras',                    'grupo' => 'CASADETS'],
        'productos'      => ['label' => 'Productos',                  'grupo' => 'CASADETS'],
        'inventario'     => ['label' => 'Inventario ZENDY',           'grupo' => 'ZENDY'],
        'almacenes'      => ['label' => 'Almacenes ZENDY',             'grupo' => 'ZENDY'],
        'clientes'       => ['label' => 'Clientes',                   'grupo' => 'CASADETS'],
        'vendedores'     => ['label' => 'Vendedores',                 'grupo' => 'CASADETS'],
        'saldos-favor'   => ['label' => 'Saldos a favor',            'grupo' => 'CASADETS'],
        'movimientos'    => ['label' => 'Movimientos',                'grupo' => 'CASADETS'],
        'devoluciones'   => ['label' => 'Devoluciones / Anulados',   'grupo' => 'CASADETS'],
        'zendy'          => ['label' => 'Zendy',                      'grupo' => 'ZENDY'],
        'reportes'       => ['label' => 'Reportes',                   'grupo' => 'General'],
        'reportes-caja'  => ['label' => 'Reportes de Caja',           'grupo' => 'General'],
        'admin.usuarios' => ['label' => 'Administración / Usuarios',  'grupo' => 'Administración'],
        'admin.roles'    => ['label' => 'Administración / Roles',     'grupo' => 'Administración'],
        'admin.cajas'    => ['label' => 'Administración / Cajas',     'grupo' => 'Administración'],
        'admin.series'   => ['label' => 'Administración / Series',    'grupo' => 'Administración'],
    ];

    const PERMISOS = [
        'Ventas' => [
            'ventas.crear'    => 'Crear ventas',
            'ventas.editar'   => 'Editar ventas',
            'ventas.eliminar' => 'Eliminar ventas',
            'ventas.importar' => 'Importar ventas',
            'ventas.pago'     => 'Registrar pagos',
        ],
        'Compras' => [
            'compras.crear'    => 'Crear compras',
            'compras.editar'   => 'Editar compras',
            'compras.eliminar' => 'Eliminar compras',
        ],
        'Clientes' => [
            'clientes.crear'    => 'Crear clientes',
            'clientes.editar'   => 'Editar clientes',
            'clientes.eliminar' => 'Eliminar clientes',
        ],
        'Productos' => [
            'productos.ver'    => 'Ver productos',
            'productos.crear'  => 'Crear productos',
            'productos.editar' => 'Editar productos',
            'productos.ajuste' => 'Ajustar stock',
        ],
        'Inventario ZENDY' => [
            'inventario.ver'    => 'Ver inventario',
            'inventario.ajuste' => 'Ajustar inventario',
        ],
        'Almacenes ZENDY' => [
            'almacenes.ver'    => 'Ver almacenes',
            'almacenes.entrada'=> 'Registrar entradas',
            'almacenes.salida' => 'Registrar salidas',
        ],
        'Vales' => [
            'vales.ver'    => 'Ver vales',
            'vales.crear'  => 'Crear vales',
            'vales.editar' => 'Editar vales',
            'vales.anular' => 'Anular vales',
        ],
        'Vendedores' => [
            'vendedores.crear'    => 'Crear vendedores',
            'vendedores.editar'   => 'Editar vendedores',
            'vendedores.eliminar' => 'Eliminar vendedores',
        ],
        'Caja' => [
            'caja.abrir'  => 'Abrir caja',
            'caja.cerrar' => 'Cerrar caja',
        ],
        'Movimientos' => [
            'movimientos.crear' => 'Crear movimientos',
        ],
        'Pendientes' => [
            'pendientes.ver_todos' => 'Ver todos los pendientes (sin filtro de caja)',
        ],
        'Saldos a favor' => [
            'saldos.crear'     => 'Crear saldo a favor',
            'saldos.aplicar'   => 'Aplicar saldo a favor',
            'saldos.ver_todos' => 'Ver todos los saldos (sin filtro de caja)',
        ],
        'Devoluciones' => [
            'devoluciones.ver'      => 'Ver devoluciones',
            'devoluciones.crear'    => 'Registrar devoluciones',
            'devoluciones.procesar' => 'Procesar devoluciones / anulaciones',
        ],
    ];

    const DEFAULTS = [
        'Administrador' => [
            'modulos' => [
                'dashboard','caja','ventas','pendientes','compras','productos','inventario','almacenes',
                'clientes','vendedores','saldos-favor','movimientos','devoluciones','zendy',
                'reportes','reportes-caja','admin.usuarios','admin.roles','admin.cajas','admin.series',
            ],
            'permisos' => [
                'ventas.crear','ventas.editar','ventas.eliminar','ventas.importar','ventas.pago',
                'compras.crear','compras.editar','compras.eliminar',
                'clientes.crear','clientes.editar','clientes.eliminar',
                'productos.ver','productos.crear','productos.editar','productos.ajuste',
                'inventario.ver','inventario.ajuste',
                'almacenes.ver','almacenes.entrada','almacenes.salida',
                'vales.ver','vales.crear','vales.editar','vales.anular',
                'vendedores.crear','vendedores.editar','vendedores.eliminar',
                'caja.abrir','caja.cerrar',
                'movimientos.crear',
                'pendientes.ver_todos',
                'saldos.crear','saldos.aplicar','saldos.ver_todos',
                'devoluciones.ver','devoluciones.crear','devoluciones.procesar',
            ],
        ],
        'Supervisor' => [
            'modulos' => [
                'dashboard','caja','ventas','pendientes','compras','productos','inventario','almacenes',
                'clientes','vendedores','saldos-favor','movimientos','devoluciones','zendy','reportes','reportes-caja',
            ],
            'permisos' => [
                'ventas.crear','ventas.editar','ventas.eliminar','ventas.importar','ventas.pago',
                'compras.crear','compras.editar','compras.eliminar',
                'clientes.crear','clientes.editar','clientes.eliminar',
                'productos.ver','productos.crear','productos.editar','productos.ajuste',
                'inventario.ver','inventario.ajuste',
                'almacenes.ver','almacenes.entrada','almacenes.salida',
                'vales.ver','vales.crear','vales.editar','vales.anular',
                'vendedores.crear','vendedores.editar','vendedores.eliminar',
                'caja.abrir','caja.cerrar',
                'movimientos.crear',
                'pendientes.ver_todos',
                'saldos.crear','saldos.aplicar','saldos.ver_todos',
                'devoluciones.ver','devoluciones.crear','devoluciones.procesar',
            ],
        ],
        'Cajero' => [
            'modulos' => [
                'dashboard','caja','ventas','pendientes','productos','clientes','saldos-favor','devoluciones',
            ],
            'permisos' => [
                'ventas.ver','ventas.crear','ventas.editar','ventas.anular','ventas.pago','ventas.importar',
                'clientes.crear','clientes.editar',
                'caja.abrir','caja.cerrar',
                'pendientes.ver_todos',
                'saldos.aplicar',
                'devoluciones.ver','devoluciones.crear','devoluciones.procesar',
            ],
        ],
        'Vendedor' => [
            'modulos' => [
                'dashboard','ventas','pendientes','clientes','reportes',
            ],
            'permisos' => [
                'ventas.crear',
                'clientes.crear',
            ],
        ],
    ];

    public static function allPermisoKeys(): array
    {
        $keys = [];
        foreach (self::PERMISOS as $group) {
            $keys = array_merge($keys, array_keys($group));
        }
        return $keys;
    }

    public static function allModuloKeys(): array
    {
        return array_keys(self::MODULOS);
    }
}
