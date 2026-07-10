<?php

namespace App\Http\Controllers;

use App\Services\ChatInternoService;
use App\Support\FarmContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ChatInternoController extends Controller
{
    public function heartbeat(Request $request, ChatInternoService $service): JsonResponse
    {
        $service->online($this->usuarioId(), $request->session()->getId(), (string)session('sessao_token', ''));

        return response()->json(['ok' => true]);
    }

    public function offline(ChatInternoService $service): JsonResponse
    {
        $service->offline($this->usuarioId());

        return response()->json(['ok' => true]);
    }

    public function contatos(ChatInternoService $service): JsonResponse
    {
        return response()->json($service->contatos($this->usuarioId(), $this->propriedadeId()));
    }

    public function mensagens(int $usuario, ChatInternoService $service): JsonResponse
    {
        return response()->json($service->mensagens($this->usuarioId(), $usuario, $this->propriedadeId()));
    }

    public function enviar(int $usuario, Request $request, ChatInternoService $service): JsonResponse
    {
        $dados = $request->validate([
            'mensagem' => ['nullable', 'string'],
            'anexos' => ['nullable', 'array'],
            'anexos.*' => ['file', 'max:25600', 'mimes:png,jpg,jpeg,webp,pdf,xls,xlsx,csv,xml'],
        ]);

        return response()->json($service->enviar($this->usuarioId(), $usuario, $this->propriedadeId(), $dados['mensagem'] ?? null, $request->file('anexos', [])));
    }

    public function anexo(int $anexo, ChatInternoService $service): BinaryFileResponse
    {
        return $service->baixarAnexo($anexo, $this->usuarioId());
    }

    private function usuarioId(): int
    {
        return (int)session('usuario_id');
    }

    private function propriedadeId(): int
    {
        return app(FarmContext::class)->propertyId();
    }
}
