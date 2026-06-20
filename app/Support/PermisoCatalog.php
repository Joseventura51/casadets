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
            'productos.crear'  => 'Crear productos',
            'productos.editar' => 'Editar productos',
            'productos.ajuste' => 'Ajustar stock',
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
        'Saldos a favor' => [
            'saldos.crear'   => 'Crear saldo a favor',
            'saldos.aplicar' => 'Aplicar saldo a favor',
        ],
        'Devoluciones' => [
            'devoluciones.procesar' => 'Procesar devoluciones / anulaciones',
        ],
    ];

    const DEFAULTS = [
        'Administrador' => [
            'modulos' => [
                'dashboard','caja','ventas','pendientes','compras','productos',
                'clientes','vendedores','saldos-favor','movimientos','devoluciones','zendy',
                'reportes','reportes-caja','admin.usuarios','admin.roles','admin.cajas','admin.series',
            ],
            'permisos' => [
                'ventas.crear','ventas.editar','ventas.eliminar','ventas.importar','ventas.pago',
                'compras.crear','compras.editar','compras.eliminar',
                'clientes.crear','clientes.editar','clientes.eliminar',
                'productos.crear','productos.editar','productos.ajuste',
                'vendedores.crear','vendedores.editar','vendedores.eliminar',
                'caja.abrir','caja.cerrar',
                'movimientos.crear',
                'saldos.crear','saldos.aplicar',
                'devoluciones.procesar',
            ],
        ],
        'Supervisor' => [
            'modulos' => [
                'dashboard','caja','ventas','pendientes','compras','productos',
                'clientes','vendedores','saldos-favor','movimientos','devoluciones','zendy','reportes','reportes-caja',
            ],
            'permisos' => [
                'ventas.crear','ventas.editar','ventas.eliminar','ventas.importar','ventas.pago',
                'compras.crear','compras.editar','compras.eliminar',
                'clientes.crear','clientes.editar','clientes.eliminar',
                'productos.crear','productos.editar','productos.ajuste',
                'vendedores.crear','vendedores.editar','vendedores.eliminar',
                'caja.abrir','caja.cerrar',
                'movimientos.crear',
                'saldos.crear','saldos.aplicar',
                'devoluciones.procesar',
            ],
        ],
        'Cajero' => [
            'modulos' => [
                'dashboard','caja','ventas','pendientes','productos','clientes','saldos-favor','devoluciones',
            ],
            'permisos' => [
                'ventas.crear','ventas.editar','ventas.pago','ventas.importar',
                'clientes.crear','clientes.editar',
                'caja.abrir','caja.cerrar',
                'saldos.aplicar',
                'devoluciones.procesar',
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
