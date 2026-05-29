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
        'zendy'          => ['label' => 'Zendy',                      'grupo' => 'ZENDY'],
        'reportes'       => ['label' => 'Reportes',                   'grupo' => 'General'],
        'admin.usuarios' => ['label' => 'Administración / Usuarios',  'grupo' => 'Administración'],
        'admin.roles'    => ['label' => 'Administración / Roles',     'grupo' => 'Administración'],
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
    ];

    const DEFAULTS = [
        'Administrador' => [
            'modulos' => [
                'dashboard','caja','ventas','pendientes','compras','productos',
                'clientes','vendedores','saldos-favor','movimientos','zendy',
                'reportes','admin.usuarios','admin.roles',
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
            ],
        ],
        'Supervisor' => [
            'modulos' => [
                'dashboard','caja','ventas','pendientes','compras','productos',
                'clientes','vendedores','saldos-favor','movimientos','zendy','reportes',
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
            ],
        ],
        'Cajero' => [
            'modulos' => [
                'dashboard','caja','ventas','pendientes','productos','clientes','saldos-favor',
            ],
            'permisos' => [
                'ventas.crear','ventas.pago',
                'clientes.crear','clientes.editar',
                'caja.abrir','caja.cerrar',
                'saldos.aplicar',
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
