<?php

namespace App\Http\Controllers;

use App\Services\SuporteAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SuporteAdminController extends Controller
{
    public function index(SuporteAdminService $service): View|RedirectResponse
    {
        if (!in_array((string)session('perfil'), ['administrador_sistema', 'gerencia_sistema', 'colaborador_sistema'], true)) {
            return redirect()->route('dashboard')->with('error', 'Acesso restrito ao atendimento FarmFort.');
        }

        return view('suporte.admin.index', $service->pagina());
    }
}
