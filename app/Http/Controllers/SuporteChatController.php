<?php

namespace App\Http\Controllers;

use App\Services\SuporteChatService;
use App\Support\FarmContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SuporteChatController extends Controller
{
    public function atual(SuporteChatService $service): JsonResponse
    {
        return response()->json($service->conversaCliente($this->usuarioId(), $this->propriedadeId()));
    }

    public function enviar(Request $request, SuporteChatService $service): JsonResponse
    {
        $dados = $request->validate([
            'mensagem' => ['nullable', 'string'],
            'anexos' => ['nullable', 'array'],
            'anexos.*' => ['file', 'max:25600', 'mimes:png,jpg,jpeg,webp,pdf,xls,xlsx,csv,xml'],
        ]);

        return response()->json($service->enviarCliente($this->usuarioId(), $this->propriedadeId(), $dados['mensagem'] ?? null, $request->file('anexos', [])));
    }

    public function conversa(int $conversa, SuporteChatService $service): JsonResponse
    {
        abort_unless($this->podeAtenderSuporte(), 403);

        return response()->json($service->payload($conversa));
    }

    public function responder(int $conversa, Request $request, SuporteChatService $service): JsonResponse
    {
        abort_unless($this->podeAtenderSuporte(), 403);

        $dados = $request->validate([
            'mensagem' => ['nullable', 'string'],
            'anexos' => ['nullable', 'array'],
            'anexos.*' => ['file', 'max:25600', 'mimes:png,jpg,jpeg,webp,pdf,xls,xlsx,csv,xml'],
        ]);

        return response()->json($service->responderAdmin($conversa, $this->usuarioId(), $dados['mensagem'] ?? null, $request->file('anexos', [])));
    }

    public function anexo(int $anexo, SuporteChatService $service): BinaryFileResponse
    {
        return $service->baixarAnexo($anexo, $this->usuarioId(), $this->podeAtenderSuporte());
    }

    private function usuarioId(): int
    {
        return (int)session('usuario_id');
    }

    private function propriedadeId(): ?int
    {
        $id = app(FarmContext::class)->propertyId();

        return $id > 0 ? $id : null;
    }

    private function podeAtenderSuporte(): bool
    {
        return in_array((string)session('perfil'), ['administrador_sistema', 'gerencia_sistema', 'colaborador_sistema'], true);
    }
}
