<?php

namespace App\Http\Controllers;

use App\Services\AuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PropertyContextController extends Controller
{
    public function __construct(private readonly AuthenticationService $authentication) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'propriedade_id' => ['required', 'integer'],
        ]);

        $userId = (int) $request->session()->get('usuario_id', 0);
        $profile = (string) $request->session()->get('perfil', '');
        $property = $this->authentication->selectableProperty(
            $userId,
            $profile,
            (int) $data['propriedade_id'],
        );

        if (! $property) {
            return back()->withErrors('Você não tem acesso a esta propriedade ativa.');
        }

        $request->session()->put('propriedade_id', (int) $property->id);
        $request->session()->put('propriedade_nome', (string) $property->nome);

        return back()->with('success', 'Propriedade selecionada com sucesso.');
    }
}
