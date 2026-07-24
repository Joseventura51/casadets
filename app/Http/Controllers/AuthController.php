<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect('/');
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string',
            'password'   => 'required|string',
        ]);

        $identifier = $request->input('identifier');
        $remember   = $request->boolean('remember');

        $user = \App\Models\User::where('email', $identifier)
            ->orWhere('name', $identifier)
            ->first();

        if (!$user) {
            return back()
                ->withInput($request->only('identifier'))
                ->withErrors(['identifier' => 'Las credenciales no son correctas.']);
        }

        if (!$user->activo) {
            return back()
                ->withInput($request->only('identifier'))
                ->withErrors(['identifier' => 'Tu cuenta está desactivada. Contacta al administrador.']);
        }

        if (Auth::attempt(['email' => $user->email, 'password' => $request->input('password')], $remember)) {
            $request->session()->regenerate();

            // Auto-seleccionar caja principal del usuario.
            // El fallback a "primera caja del sistema" ya NO aplica:
            // un usuario sin cajas asignadas (ej. rol Vendedor) no debe heredar
            // una caja aleatoria que luego filtraría incorrectamente sus ventas.
            $cajaPrincipal = $user->cajaPrincipal();
            if ($cajaPrincipal) {
                session(['caja_id' => $cajaPrincipal->id]);
            }

            return redirect()->intended('/');
        }

        return back()
            ->withInput($request->only('identifier'))
            ->withErrors(['identifier' => 'Las credenciales no son correctas.']);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }
}
