<?php

namespace App\Http\Controllers;

use App\Domain\Access\ProfileAccess;
use App\Services\SuporteAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SuporteAdminController extends Controller
{
    public function __construct(private readonly ProfileAccess $access) {}

    public function index(SuporteAdminService $service): View|RedirectResponse
    {
        if (! $this->access->canHandleSupport((string) session('perfil'))) {
            return redirect()->route('dashboard')->with('error', 'Acesso restrito ao atendimento FarmFort.');
        }

        return view('suporte.admin.index', [
            ...$service->pagina(),
            'suporteEndpoint' => url('/pages/ajax/suporte_chat.php'),
            'profile' => (string) session('perfil', ''),
        ]);
    }
}
