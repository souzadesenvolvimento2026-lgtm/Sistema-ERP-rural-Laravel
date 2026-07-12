<?php

namespace App\Support;

use App\Services\CotacaoSojaService;
use Illuminate\Support\Facades\DB;

class FarmContext
{
    private static array $cotacaoTicked = [];

    public function property()
    {
        $sessionPropertyId = (int) session('propriedade_id', 0);
        if ($sessionPropertyId > 0) {
            $property = DB::table('propriedades')
                ->where('id', $sessionPropertyId)
                ->where('ativo', 1)
                ->first();

            if ($property) {
                $this->tickCotacao((int) $property->id);

                return $property;
            }
        }

        if ((int) session('usuario_id', 0) > 0) {
            return null;
        }

        $property = DB::table('propriedades')
            ->where('nome', 'Fazenda teste')
            ->where('ativo', 1)
            ->orderBy('id')
            ->first()
            ?? DB::table('propriedades')->where('ativo', 1)->orderBy('id')->first();

        if ($property) {
            $this->tickCotacao((int) $property->id);
        }

        return $property;
    }

    public function propertyId(): int
    {
        $property = $this->property();
        abort_if(! $property, 403, 'Nenhuma propriedade ativa autorizada na sessão.');

        return (int) $property->id;
    }

    private function tickCotacao(int $propertyId): void
    {
        if (app()->runningUnitTests()) {
            return;
        }
        if (isset(self::$cotacaoTicked[$propertyId])) {
            return;
        }

        self::$cotacaoTicked[$propertyId] = true;
        app(CotacaoSojaService::class)->tick($propertyId);
    }
}
