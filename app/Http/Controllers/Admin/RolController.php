<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rol;
use App\Support\PermisoCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RolController extends Controller
{
    public function index()
    {
        $roles = Rol::withCount('users')->orderBy('nombre')->get();
        return view('admin.roles.index', compact('roles'));
    }

    public function create()
    {
        $rol     = new Rol(['modulos' => [], 'permisos' => []]);
        $catalog = PermisoCatalog::class;
        return view('admin.roles.edit', compact('rol', 'catalog'));
    }

    public function store(Request $request)
    {
        $data = $this->validar($request, null);
        Rol::create($data);
        return redirect('/admin/roles')->with('success', 'Rol creado correctamente.');
    }

    public function edit(Rol $rol)
    {
        $catalog = PermisoCatalog::class;
        return view('admin.roles.edit', compact('rol', 'catalog'));
    }

    public function update(Request $request, Rol $rol)
    {
        $data = $this->validar($request, $rol);
        $rol->update($data);
        return redirect('/admin/roles')->with('success', 'Rol actualizado correctamente.');
    }

    public function destroy(Rol $rol)
    {
        if ($rol->users()->count() > 0) {
            return back()->with('error', 'No se puede eliminar un rol que tiene usuarios asignados.');
        }
        $rol->delete();
        return redirect('/admin/roles')->with('success', 'Rol eliminado.');
    }

    private function validar(Request $request, ?Rol $rol = null): array
    {
        $request->validate([
            'nombre'      => [
                'required', 'string', 'max:100',
                Rule::unique('roles', 'nombre')->ignore($rol?->id),
            ],
            'descripcion' => 'nullable|string|max:255',
        ]);

        return [
            'nombre'      => $request->nombre,
            'descripcion' => $request->descripcion,
            'modulos'     => $request->input('modulos', []),
            'permisos'    => $request->input('permisos', []),
        ];
    }
}
