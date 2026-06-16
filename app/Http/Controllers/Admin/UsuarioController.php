<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Caja;
use App\Models\Rol;
use App\Models\User;
use App\Models\Vendedor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UsuarioController extends Controller
{
    public function index()
    {
        $usuarios = User::with(['rol', 'cajasPermitidas'])->orderBy('name')->get();
        return view('admin.usuarios.index', compact('usuarios'));
    }

    public function create()
    {
        $roles      = Rol::orderBy('nombre')->get();
        $vendedores = Vendedor::where('activo', true)->orderBy('nombre')->get();
        $cajas      = Caja::where('activa', true)->orderBy('empresa')->orderBy('codigo')->get();
        return view('admin.usuarios.create', compact('roles', 'vendedores', 'cajas'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:255',
            'email'        => 'required|email|unique:users,email',
            'password'     => ['required', Password::min(6)],
            'rol_id'       => 'required|exists:roles,id',
            'vendedores'   => 'nullable|array',
            'vendedores.*' => 'exists:vendedores,id',
            'cajas'        => 'nullable|array',
            'cajas.*'      => 'exists:cajas,id',
            'caja_principal' => 'nullable|integer|exists:cajas,id',
        ]);

        // Mutuamente exclusivo: Caja OR Vendedor
        if (!empty($data['cajas']) && !empty($data['vendedores'])) {
            return back()->withInput()->with('error', 'Un usuario solo puede tener cajas O vendedores, no ambos.');
        }

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'rol_id'   => $data['rol_id'],
            'activo'   => $request->boolean('activo', true),
        ]);

        if (!empty($data['cajas'])) {
            $cajaPrincipal = (int) ($data['caja_principal'] ?? 0);
            $pivotData = collect($data['cajas'])->mapWithKeys(fn ($id) => [
                $id => ['principal' => ($id == $cajaPrincipal)]
            ])->toArray();
            $user->cajasPermitidas()->sync($pivotData);
            // Si tiene cajas, no se guardan vendedores
        } elseif (!empty($data['vendedores'])) {
            $user->vendedores()->sync($data['vendedores']);
        }

        return redirect('/admin/usuarios')->with('success', "Usuario {$user->name} creado correctamente.");
    }

    public function edit(User $usuario)
    {
        $roles      = Rol::orderBy('nombre')->get();
        $vendedores = Vendedor::where('activo', true)->orderBy('nombre')->get();
        $cajas      = Caja::where('activa', true)->orderBy('empresa')->orderBy('codigo')->get();
        $usuario->load('vendedores', 'rol', 'cajasPermitidas');
        return view('admin.usuarios.edit', compact('usuario', 'roles', 'vendedores', 'cajas'));
    }

    public function update(Request $request, User $usuario)
    {
        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'email'          => ['required', 'email', Rule::unique('users')->ignore($usuario->id)],
            'password'       => ['nullable', Password::min(6)],
            'rol_id'         => 'required|exists:roles,id',
            'vendedores'     => 'nullable|array',
            'vendedores.*'   => 'exists:vendedores,id',
            'cajas'          => 'nullable|array',
            'cajas.*'        => 'exists:cajas,id',
            'caja_principal' => 'nullable|integer|exists:cajas,id',
        ]);

        // Mutuamente exclusivo: Caja OR Vendedor
        if (!empty($data['cajas']) && !empty($data['vendedores'])) {
            return back()->withInput()->with('error', 'Un usuario solo puede tener cajas O vendedores, no ambos.');
        }

        $usuario->name   = $data['name'];
        $usuario->email  = $data['email'];
        $usuario->rol_id = $data['rol_id'];
        $usuario->activo = $request->boolean('activo');

        if (!empty($data['password'])) {
            $usuario->password = Hash::make($data['password']);
        }

        $usuario->save();

        if (!empty($data['cajas'])) {
            $cajasData = [];
            $cajaPrincipal = (int) ($data['caja_principal'] ?? 0);
            foreach ($data['cajas'] as $id) {
                $cajasData[$id] = ['principal' => ($id == $cajaPrincipal)];
            }
            $usuario->cajasPermitidas()->sync($cajasData);
            $usuario->vendedores()->sync([]); // elimina vendedores si tiene cajas
        } elseif (!empty($data['vendedores'])) {
            $usuario->vendedores()->sync($data['vendedores']);
            $usuario->cajasPermitidas()->sync([]); // elimina cajas si tiene vendedores
        } else {
            // Vacío ambos: limpia ambos
            $usuario->vendedores()->sync([]);
            $usuario->cajasPermitidas()->sync([]);
        }

        return redirect('/admin/usuarios')->with('success', "Usuario {$usuario->name} actualizado.");
    }

    public function toggleActivo(User $usuario)
    {
        if ($usuario->id === auth()->id()) {
            return back()->with('error', 'No puedes desactivar tu propia cuenta.');
        }
        $usuario->activo = !$usuario->activo;
        $usuario->save();
        $msg = $usuario->activo ? 'activado' : 'desactivado';
        return back()->with('success', "Usuario {$usuario->name} {$msg}.");
    }
}
