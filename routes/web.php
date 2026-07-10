<?php

use App\Http\Controllers\CompraPedidoController;
use App\Http\Controllers\AdminPainelController;
use App\Http\Controllers\AuthSessionController;
use App\Http\Controllers\AgendaFinanceiraController;
use App\Http\Controllers\AnaliseDespesasController;
use App\Http\Controllers\AtividadeCampoController;
use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\CertificadoDigitalController;
use App\Http\Controllers\ChatInternoController;
use App\Http\Controllers\ChuvaController;
use App\Http\Controllers\ComparativoSafrasController;
use App\Http\Controllers\ContaBancariaController;
use App\Http\Controllers\ContratoController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\FinanceiroLancamentoController;
use App\Http\Controllers\FinanceiroPainelController;
use App\Http\Controllers\GrupoFazendaController;
use App\Http\Controllers\LivroCaixaController;
use App\Http\Controllers\LegacyAjaxController;
use App\Http\Controllers\MigrationModuleController;
use App\Http\Controllers\MovimentacaoBancariaController;
use App\Http\Controllers\ProdutoController;
use App\Http\Controllers\PatrimonioController;
use App\Http\Controllers\SafraController;
use App\Http\Controllers\SystemUnlockController;
use App\Http\Controllers\SuporteAdminController;
use App\Http\Controllers\SuporteChatController;
use App\Http\Controllers\TalhaoController;
use App\Http\Controllers\ColheitaController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\PropriedadeController;
use App\Http\Controllers\EntradaNfController;
use App\Http\Controllers\NotaFiscalController;
use App\Http\Controllers\FiscalConsolidadoController;
use App\Http\Controllers\PlanejamentoFinanceiroController;
use App\Http\Controllers\ProdutorController;
use App\Http\Controllers\RelatorioController;
use App\Http\Controllers\RelatorioLancamentosController;
use App\Http\Controllers\ReceitaFinanceiraController;
use App\Http\Controllers\DespesaFinanceiraController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::get('/login', [AuthSessionController::class, 'create'])->name('login');
Route::post('/login', [AuthSessionController::class, 'store'])->name('login.store');
Route::post('/logout', [AuthSessionController::class, 'destroy'])->name('logout');
Route::get('/login.php', fn () => redirect()->route('login'));
Route::post('/login.php', [AuthSessionController::class, 'store']);
Route::get('/index.php', fn (Request $request) => redirect()->route($request->boolean('admin') ? 'admin.index' : 'dashboard'));

Route::middleware('farmfort.auth')->group(function () {
Route::get('/admin', [AdminPainelController::class, 'index'])->name('admin.index');
Route::get('/dashboard', [MigrationModuleController::class, 'dashboard'])->name('dashboard');
Route::get('/logout.php', [AuthSessionController::class, 'destroy']);
Route::post('/sistema/liberar-edicao', [SystemUnlockController::class, 'store'])->name('system.unlock.store');
Route::get('/suporte', [SuporteAdminController::class, 'index'])->name('suporte.admin.index');
Route::get('/suporte/chat', [SuporteChatController::class, 'atual'])->name('suporte.chat.atual');
Route::post('/suporte/chat/mensagens', [SuporteChatController::class, 'enviar'])->name('suporte.chat.enviar');
Route::get('/suporte/chat/anexos/{anexo}', [SuporteChatController::class, 'anexo'])->name('suporte.chat.anexo');
Route::get('/suporte/chat/{conversa}', [SuporteChatController::class, 'conversa'])->name('suporte.chat.conversa');
Route::post('/suporte/chat/{conversa}/responder', [SuporteChatController::class, 'responder'])->name('suporte.chat.responder');
Route::get('/chat-interno/contatos', [ChatInternoController::class, 'contatos'])->name('chat-interno.contatos');
Route::post('/chat-interno/heartbeat', [ChatInternoController::class, 'heartbeat'])->name('chat-interno.heartbeat');
Route::post('/chat-interno/offline', [ChatInternoController::class, 'offline'])->name('chat-interno.offline');
Route::get('/chat-interno/anexos/{anexo}', [ChatInternoController::class, 'anexo'])->name('chat-interno.anexo');
Route::get('/chat-interno/{usuario}/mensagens', [ChatInternoController::class, 'mensagens'])->name('chat-interno.mensagens');
Route::post('/chat-interno/{usuario}/mensagens', [ChatInternoController::class, 'enviar'])->name('chat-interno.enviar');
Route::match(['get', 'post'], '/pages/ajax/chat_interno.php', [LegacyAjaxController::class, 'chatInterno'])->name('legacy.ajax.chat-interno');
Route::get('/pages/ajax/chat_anexo.php', [LegacyAjaxController::class, 'chatAnexo'])->name('legacy.ajax.chat-anexo');
Route::match(['get', 'post'], '/pages/ajax/suporte_chat.php', [LegacyAjaxController::class, 'suporteChat'])->name('legacy.ajax.suporte-chat');
Route::get('/pages/ajax/suporte_anexo.php', [LegacyAjaxController::class, 'suporteAnexo'])->name('legacy.ajax.suporte-anexo');
Route::get('/modulos/{module}', [MigrationModuleController::class, 'module'])->name('modules.show');
Route::get('/financeiro', [FinanceiroPainelController::class, 'index'])->name('financeiro.index');
Route::get('/financeiro/lancamentos/novo', [FinanceiroLancamentoController::class, 'create'])->name('financeiro.lancamentos.create');
Route::post('/financeiro/lancamentos', [FinanceiroLancamentoController::class, 'store'])->name('financeiro.lancamentos.store');
Route::get('/financeiro/contas', [ContaBancariaController::class, 'index'])->name('financeiro.contas.index');
Route::post('/financeiro/contas', [ContaBancariaController::class, 'store'])->name('financeiro.contas.store');
Route::post('/financeiro/contas/transferencias', [ContaBancariaController::class, 'transfer'])->name('financeiro.contas.transfer');
Route::get('/financeiro/contas/{conta}/editar', [ContaBancariaController::class, 'edit'])->name('financeiro.contas.edit');
Route::put('/financeiro/contas/{conta}', [ContaBancariaController::class, 'update'])->name('financeiro.contas.update');
Route::post('/financeiro/contas/{conta}/alternar-status', [ContaBancariaController::class, 'toggleStatus'])->name('financeiro.contas.toggle-status');
Route::get('/financeiro/movimentacoes', [MovimentacaoBancariaController::class, 'index'])->name('financeiro.movimentacoes.index');
Route::post('/financeiro/movimentacoes', [MovimentacaoBancariaController::class, 'store'])->name('financeiro.movimentacoes.store');
Route::post('/financeiro/movimentacoes/{movimentacao}/conciliar', [MovimentacaoBancariaController::class, 'conciliar'])->name('financeiro.movimentacoes.conciliar');
Route::post('/financeiro/movimentacoes/{movimentacao}/ignorar', [MovimentacaoBancariaController::class, 'ignorar'])->name('financeiro.movimentacoes.ignorar');
Route::get('/financeiro/agenda', [AgendaFinanceiraController::class, 'index'])->name('financeiro.agenda.index');
Route::post('/financeiro/agenda/pagar', [AgendaFinanceiraController::class, 'pagarDespesa'])->name('financeiro.agenda.pagar');
Route::post('/financeiro/agenda/receber', [AgendaFinanceiraController::class, 'receberReceita'])->name('financeiro.agenda.receber');
Route::get('/financeiro/analise-despesas', [AnaliseDespesasController::class, 'index'])->name('financeiro.analise-despesas.index');
Route::get('/financeiro/despesas', [DespesaFinanceiraController::class, 'index'])->name('financeiro.despesas.index');
Route::post('/financeiro/despesas/aprovar-lote', [DespesaFinanceiraController::class, 'approveBatch'])->name('financeiro.despesas.approve-batch');
Route::get('/financeiro/despesas/{despesa}/editar', [DespesaFinanceiraController::class, 'edit'])->name('financeiro.despesas.edit');
Route::get('/financeiro/despesas/{despesa}/duplicar', [DespesaFinanceiraController::class, 'duplicate'])->name('financeiro.despesas.duplicate');
Route::put('/financeiro/despesas/{despesa}', [DespesaFinanceiraController::class, 'update'])->name('financeiro.despesas.update');
Route::post('/financeiro/despesas/{despesa}/aprovar', [DespesaFinanceiraController::class, 'approve'])->name('financeiro.despesas.approve');
Route::post('/financeiro/despesas/{despesa}/reprovar', [DespesaFinanceiraController::class, 'reject'])->name('financeiro.despesas.reject');
Route::post('/financeiro/despesas/{despesa}/pagar', [DespesaFinanceiraController::class, 'pay'])->name('financeiro.despesas.pay');
Route::post('/financeiro/despesas/{despesa}/cancelar', [DespesaFinanceiraController::class, 'cancel'])->name('financeiro.despesas.cancel');
Route::get('/financeiro/receitas', [ReceitaFinanceiraController::class, 'index'])->name('financeiro.receitas.index');
Route::post('/financeiro/receitas/compradores', [ReceitaFinanceiraController::class, 'storeBuyer'])->name('financeiro.receitas.compradores.store');
Route::post('/financeiro/receitas/aprovar-lote', [ReceitaFinanceiraController::class, 'approveBatch'])->name('financeiro.receitas.approve-batch');
Route::get('/financeiro/receitas/{receita}/editar', [ReceitaFinanceiraController::class, 'edit'])->name('financeiro.receitas.edit');
Route::get('/financeiro/receitas/{receita}/duplicar', [ReceitaFinanceiraController::class, 'duplicate'])->name('financeiro.receitas.duplicate');
Route::put('/financeiro/receitas/{receita}', [ReceitaFinanceiraController::class, 'update'])->name('financeiro.receitas.update');
Route::post('/financeiro/receitas/{receita}/aprovar', [ReceitaFinanceiraController::class, 'approve'])->name('financeiro.receitas.approve');
Route::post('/financeiro/receitas/{receita}/reprovar', [ReceitaFinanceiraController::class, 'reject'])->name('financeiro.receitas.reject');
Route::post('/financeiro/receitas/{receita}/receber', [ReceitaFinanceiraController::class, 'receive'])->name('financeiro.receitas.receive');
Route::post('/financeiro/receitas/{receita}/cancelar', [ReceitaFinanceiraController::class, 'cancel'])->name('financeiro.receitas.cancel');
Route::get('/financeiro/categorias', [CategoriaController::class, 'index'])->name('financeiro.categorias.index');
Route::post('/financeiro/categorias', [CategoriaController::class, 'store'])->name('financeiro.categorias.store');
Route::put('/financeiro/categorias/{categoria}', [CategoriaController::class, 'update'])->name('financeiro.categorias.update');
Route::delete('/financeiro/categorias/{categoria}', [CategoriaController::class, 'destroy'])->name('financeiro.categorias.destroy');
Route::get('/financeiro/planejamento', [PlanejamentoFinanceiroController::class, 'planejamentoSafra'])->name('financeiro.planejamento.index');
Route::get('/financeiro/livro-caixa', [LivroCaixaController::class, 'index'])->name('financeiro.livro-caixa.index');
Route::get('/financeiro/livro-caixa/exportar', [LivroCaixaController::class, 'exportar'])->name('financeiro.livro-caixa.exportar');
Route::get('/financeiro/relatorio-lancamentos', [RelatorioLancamentosController::class, 'index'])->name('financeiro.relatorio-lancamentos.index');
Route::get('/financeiro/relatorio-lancamentos/exportar', [RelatorioLancamentosController::class, 'exportar'])->name('financeiro.relatorio-lancamentos.exportar');
Route::get('/produtos', [ProdutoController::class, 'index'])->name('produtos.index');
Route::get('/produtos/novo', [ProdutoController::class, 'create'])->name('produtos.create');
Route::post('/produtos', [ProdutoController::class, 'store'])->name('produtos.store');
Route::get('/produtos/{produto}/editar', [ProdutoController::class, 'edit'])->name('produtos.edit');
Route::put('/produtos/{produto}', [ProdutoController::class, 'update'])->name('produtos.update');
Route::post('/produtos/{produto}/alternar-status', [ProdutoController::class, 'toggleStatus'])->name('produtos.toggle-status');
Route::get('/patrimonio', [PatrimonioController::class, 'index'])->name('patrimonio.index');
Route::get('/patrimonio/novo', [PatrimonioController::class, 'create'])->name('patrimonio.create');
Route::post('/patrimonio', [PatrimonioController::class, 'store'])->name('patrimonio.store');
Route::get('/patrimonio/{patrimonio}/editar', [PatrimonioController::class, 'edit'])->name('patrimonio.edit');
Route::put('/patrimonio/{patrimonio}', [PatrimonioController::class, 'update'])->name('patrimonio.update');
Route::post('/patrimonio/{patrimonio}/valor', [PatrimonioController::class, 'updateValue'])->name('patrimonio.update-value');
Route::post('/patrimonio/{patrimonio}/alternar-status', [PatrimonioController::class, 'toggleStatus'])->name('patrimonio.toggle-status');
Route::post('/patrimonio/{patrimonio}/lancamentos', [PatrimonioController::class, 'storeLancamento'])->name('patrimonio.lancamentos.store');
Route::get('/patrimonio/{patrimonio}', [PatrimonioController::class, 'show'])->name('patrimonio.show');
Route::get('/safras', [SafraController::class, 'index'])->name('safras.index');
Route::get('/safras/novo', [SafraController::class, 'create'])->name('safras.create');
Route::post('/safras', [SafraController::class, 'store'])->name('safras.store');
Route::get('/safras/{safra}/editar', [SafraController::class, 'edit'])->name('safras.edit');
Route::put('/safras/{safra}', [SafraController::class, 'update'])->name('safras.update');
Route::post('/safras/{safra}/status', [SafraController::class, 'status'])->name('safras.status');
Route::delete('/safras/{safra}', [SafraController::class, 'destroy'])->name('safras.destroy');
Route::get('/talhoes', [TalhaoController::class, 'index'])->name('talhoes.index');
Route::get('/talhoes/mapa', [TalhaoController::class, 'mapa'])->name('talhoes.mapa');
  Route::post('/talhoes/mapa', [TalhaoController::class, 'storePoligono'])->name('talhoes.mapa.store');
  Route::get('/talhoes/exportar/kml', [TalhaoController::class, 'exportarKml'])->name('talhoes.exportar-kml');
  Route::post('/talhoes/importar-geo', [TalhaoController::class, 'importarGeo'])->name('talhoes.importar-geo');
  Route::post('/talhoes/unificar', [TalhaoController::class, 'unificar'])->name('talhoes.unificar');
  Route::get('/talhoes/{talhao}/exportar', [TalhaoController::class, 'exportarTalhao'])->name('talhoes.exportar-talhao');
  Route::post('/talhoes/{talhao}/mapa/dados', [TalhaoController::class, 'atualizarDadosMapa'])->name('talhoes.mapa.dados');
  Route::post('/talhoes/{talhao}/mapa/exclusoes', [TalhaoController::class, 'salvarExclusao'])->name('talhoes.mapa.exclusoes.store');
  Route::delete('/talhoes/{talhao}/mapa/exclusoes', [TalhaoController::class, 'limparExclusoes'])->name('talhoes.mapa.exclusoes.clear');
  Route::post('/talhoes/{talhao}/mapa/pivo', [TalhaoController::class, 'salvarPivo'])->name('talhoes.mapa.pivo.store');
  Route::delete('/talhoes/{talhao}/mapa/pivo', [TalhaoController::class, 'removerPivo'])->name('talhoes.mapa.pivo.destroy');
  Route::post('/talhoes/mapa/pivo', [TalhaoController::class, 'criarPivo'])->name('talhoes.mapa.pivo.create');
Route::get('/talhoes/chuva', [ChuvaController::class, 'index'])->name('talhoes.chuva.index');
Route::post('/talhoes/chuva', [ChuvaController::class, 'store'])->name('talhoes.chuva.store');
Route::get('/talhoes/atividades', [AtividadeCampoController::class, 'index'])->name('talhoes.atividades.index');
Route::post('/talhoes/atividades', [AtividadeCampoController::class, 'store'])->name('talhoes.atividades.store');
Route::post('/talhoes/atividades/{atividade}/status', [AtividadeCampoController::class, 'status'])->name('talhoes.atividades.status');
Route::get('/talhoes/novo', [TalhaoController::class, 'create'])->name('talhoes.create');
Route::post('/talhoes', [TalhaoController::class, 'store'])->name('talhoes.store');
Route::get('/talhoes/{talhao}/editar', [TalhaoController::class, 'edit'])->name('talhoes.edit');
Route::put('/talhoes/{talhao}', [TalhaoController::class, 'update'])->name('talhoes.update');
Route::post('/talhoes/{talhao}/alternar-status', [TalhaoController::class, 'toggleStatus'])->name('talhoes.toggle-status');
Route::get('/colheita', [ColheitaController::class, 'index'])->name('colheita.index');
Route::get('/colheita/novo', [ColheitaController::class, 'create'])->name('colheita.create');
Route::post('/colheita', [ColheitaController::class, 'store'])->name('colheita.store');
Route::get('/colheita/{colheita}/editar', [ColheitaController::class, 'edit'])->name('colheita.edit');
Route::put('/colheita/{colheita}', [ColheitaController::class, 'update'])->name('colheita.update');
Route::delete('/colheita/{colheita}', [ColheitaController::class, 'destroy'])->name('colheita.destroy');
Route::post('/colheita/talhoes/finalizar', [ColheitaController::class, 'finalizarTalhao'])->name('colheita.talhoes.finalizar');
Route::post('/colheita/talhoes/reabrir', [ColheitaController::class, 'reabrirTalhao'])->name('colheita.talhoes.reabrir');
Route::get('/usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
Route::get('/usuarios/novo', [UsuarioController::class, 'create'])->name('usuarios.create');
Route::post('/usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
Route::get('/usuarios/{usuario}/editar', [UsuarioController::class, 'edit'])->name('usuarios.edit');
Route::put('/usuarios/{usuario}', [UsuarioController::class, 'update'])->name('usuarios.update');
Route::post('/usuarios/{usuario}/alternar-status', [UsuarioController::class, 'toggleStatus'])->name('usuarios.toggle-status');
Route::get('/propriedades', [PropriedadeController::class, 'index'])->name('propriedades.index');
Route::get('/propriedades/novo', [PropriedadeController::class, 'create'])->name('propriedades.create');
Route::post('/propriedades', [PropriedadeController::class, 'store'])->name('propriedades.store');
Route::get('/propriedades/{propriedade}/editar', [PropriedadeController::class, 'edit'])->name('propriedades.edit');
Route::put('/propriedades/{propriedade}', [PropriedadeController::class, 'update'])->name('propriedades.update');
Route::post('/propriedades/{propriedade}/alternar-status', [PropriedadeController::class, 'toggleStatus'])->name('propriedades.toggle-status');
Route::get('/propriedades/grupos', [GrupoFazendaController::class, 'index'])->name('propriedades.grupos.index');
Route::post('/propriedades/grupos', [GrupoFazendaController::class, 'store'])->name('propriedades.grupos.store');
Route::put('/propriedades/grupos/{grupo}', [GrupoFazendaController::class, 'update'])->name('propriedades.grupos.update');
Route::delete('/propriedades/grupos/{grupo}', [GrupoFazendaController::class, 'destroy'])->name('propriedades.grupos.destroy');
Route::get('/estoque-producao', [ContratoController::class, 'index'])->name('estoque-producao.index');
Route::get('/estoque-producao/contratos', [ContratoController::class, 'index'])->name('estoque-producao.contratos.index');
Route::post('/estoque-producao/contratos', [ContratoController::class, 'store'])->name('estoque-producao.contratos.store');
Route::post('/estoque-producao/contratos/entregas', [ContratoController::class, 'entrega'])->name('estoque-producao.contratos.entrega');
Route::get('/fiscal', [FiscalConsolidadoController::class, 'index'])->name('fiscal.index');
Route::get('/fiscal/entrada-nf', [EntradaNfController::class, 'index'])->name('fiscal.entrada-nf.index');
Route::get('/fiscal/entrada-nf/novo', [EntradaNfController::class, 'create'])->name('fiscal.entrada-nf.create');
Route::post('/fiscal/entrada-nf', [EntradaNfController::class, 'store'])->name('fiscal.entrada-nf.store');
Route::get('/fiscal/entrada-nf/{entrada}', [EntradaNfController::class, 'show'])->name('fiscal.entrada-nf.show');
Route::post('/fiscal/entrada-nf/{entrada}/itens', [EntradaNfController::class, 'storeItem'])->name('fiscal.entrada-nf.itens.store');
Route::post('/fiscal/entrada-nf/{entrada}/parcelas/gerar', [EntradaNfController::class, 'gerarParcelas'])->name('fiscal.entrada-nf.parcelas.gerar');
Route::post('/fiscal/entrada-nf/{entrada}/concluir', [EntradaNfController::class, 'concluir'])->name('fiscal.entrada-nf.concluir');
Route::get('/fiscal/consolidado', [FiscalConsolidadoController::class, 'index'])->name('fiscal.consolidado.index');
Route::get('/fiscal/notas', [NotaFiscalController::class, 'index'])->name('fiscal.notas.index');
Route::get('/fiscal/notas/importar', [NotaFiscalController::class, 'create'])->name('fiscal.notas.create');
Route::post('/fiscal/notas/importar', [NotaFiscalController::class, 'store'])->name('fiscal.notas.store');
Route::post('/fiscal/notas/importar/confirmar', [NotaFiscalController::class, 'confirm'])->name('fiscal.notas.confirm');
Route::post('/fiscal/notas/importar/cancelar', [NotaFiscalController::class, 'cancelPreview'])->name('fiscal.notas.preview.cancel');
Route::get('/fiscal/notas/{nota}', [NotaFiscalController::class, 'show'])->name('fiscal.notas.show');
Route::get('/fiscal/notas/{nota}/xml', [NotaFiscalController::class, 'xml'])->name('fiscal.notas.xml');
Route::post('/fiscal/notas/{nota}/aprovar', [NotaFiscalController::class, 'approve'])->name('fiscal.notas.approve');
Route::get('/fiscal/certificados', [CertificadoDigitalController::class, 'index'])->name('fiscal.certificados.index');
Route::post('/fiscal/certificados', [CertificadoDigitalController::class, 'store'])->name('fiscal.certificados.store');
Route::post('/fiscal/certificados/{certificado}/principal', [CertificadoDigitalController::class, 'principal'])->name('fiscal.certificados.principal');
Route::post('/fiscal/certificados/{certificado}/desativar', [CertificadoDigitalController::class, 'desativar'])->name('fiscal.certificados.desativar');
Route::get('/fiscal/produtores', [ProdutorController::class, 'index'])->name('fiscal.produtores.index');
Route::post('/fiscal/produtores', [ProdutorController::class, 'store'])->name('fiscal.produtores.store');
Route::put('/fiscal/produtores/{produtor}', [ProdutorController::class, 'update'])->name('fiscal.produtores.update');
Route::post('/fiscal/produtores/{produtor}/toggle', [ProdutorController::class, 'toggle'])->name('fiscal.produtores.toggle');
Route::get('/fiscal/documentos', [DocumentoController::class, 'index'])->name('fiscal.documentos.index');
Route::post('/fiscal/documentos', [DocumentoController::class, 'store'])->name('fiscal.documentos.store');
Route::get('/fiscal/documentos/{documento}/arquivo', [DocumentoController::class, 'arquivo'])->name('fiscal.documentos.arquivo');
Route::post('/fiscal/documentos/{documento}/status', [DocumentoController::class, 'status'])->name('fiscal.documentos.status');
Route::post('/fiscal/documentos/{documento}/conferir', [DocumentoController::class, 'conferir'])->name('fiscal.documentos.conferir');
Route::get('/relatorios', [RelatorioController::class, 'index'])->name('relatorios.index');
Route::get('/relatorios/dre', [RelatorioController::class, 'dre'])->name('relatorios.dre');
Route::get('/relatorios/fluxo-caixa', [RelatorioController::class, 'fluxoCaixa'])->name('relatorios.fluxo-caixa');
Route::get('/relatorios/orcado-realizado', [RelatorioController::class, 'orcadoRealizado'])->name('relatorios.orcado-realizado');
Route::get('/relatorios/categorias', [RelatorioController::class, 'categorias'])->name('relatorios.categorias');
Route::get('/relatorios/safra', [RelatorioController::class, 'safra'])->name('relatorios.safra');
Route::get('/relatorios/talhao', [RelatorioController::class, 'talhao'])->name('relatorios.talhao');
Route::get('/relatorios/kpis', [RelatorioController::class, 'kpis'])->name('relatorios.kpis');
Route::get('/relatorios/comparativo-safras', [ComparativoSafrasController::class, 'index'])->name('relatorios.comparativo-safras.index');
Route::get('/relatorios/comparativo-safras/exportar', [ComparativoSafrasController::class, 'exportar'])->name('relatorios.comparativo-safras.exportar');
Route::get('/auditoria', [AuditoriaController::class, 'index'])->name('auditoria.index');
Route::get('/auditoria/exportar', [AuditoriaController::class, 'export'])->name('auditoria.exportar');
Route::get('/orcamento', [PlanejamentoFinanceiroController::class, 'index'])->name('orcamento.index');
Route::get('/orcamento/novo', [PlanejamentoFinanceiroController::class, 'create'])->name('orcamento.create');
Route::post('/orcamento', [PlanejamentoFinanceiroController::class, 'store'])->name('orcamento.store');
Route::post('/orcamento/projecoes/lote', [PlanejamentoFinanceiroController::class, 'atualizarPlanejamentoEmLote'])->name('orcamento.projecoes.lote');
Route::post('/orcamento/recorrente', [PlanejamentoFinanceiroController::class, 'recorrente'])->name('orcamento.recorrente');
Route::post('/orcamento/base-safra', [PlanejamentoFinanceiroController::class, 'atualizarBaseSafra'])->name('orcamento.base-safra');
Route::post('/orcamento/categorias', [PlanejamentoFinanceiroController::class, 'criarCategoriaPlanejamento'])->name('orcamento.categorias.store');
Route::post('/orcamento/culturas', [PlanejamentoFinanceiroController::class, 'criarCulturaPlanejamento'])->name('orcamento.culturas.store');
Route::post('/orcamento/safras-retroativas', [PlanejamentoFinanceiroController::class, 'criarSafraRetroativa'])->name('orcamento.safras-retroativas.store');
Route::post('/orcamento/anos-agricolas', [PlanejamentoFinanceiroController::class, 'salvarAnoAgricola'])->name('orcamento.anos-agricolas.store');
Route::post('/orcamento/atividades-planejadas', [PlanejamentoFinanceiroController::class, 'criarAtividadePlanejada'])->name('orcamento.atividades-planejadas.store');
Route::delete('/orcamento/atividades-planejadas/{atividade}', [PlanejamentoFinanceiroController::class, 'excluirAtividadePlanejada'])->name('orcamento.atividades-planejadas.destroy');
Route::post('/orcamento/despesas-planejadas', [PlanejamentoFinanceiroController::class, 'adicionarDespesaPlanejada'])->name('orcamento.despesas-planejadas.store');
Route::post('/orcamento/insumos-planejados', [PlanejamentoFinanceiroController::class, 'adicionarInsumoPlanejado'])->name('orcamento.insumos-planejados.store');
Route::post('/orcamento/copiar-safra-anterior', [PlanejamentoFinanceiroController::class, 'copiarSafraAnterior'])->name('orcamento.copiar-safra-anterior');
Route::get('/orcamento/{projecao}/editar', [PlanejamentoFinanceiroController::class, 'edit'])->name('orcamento.edit');
Route::put('/orcamento/{projecao}', [PlanejamentoFinanceiroController::class, 'update'])->name('orcamento.update');
Route::delete('/orcamento/{projecao}', [PlanejamentoFinanceiroController::class, 'destroy'])->name('orcamento.destroy');

Route::prefix('compras')->name('compras.')->group(function () {
    Route::get('/', [CompraPedidoController::class, 'index'])->name('index');
    Route::get('/pedidos', [CompraPedidoController::class, 'index'])->name('pedidos.index');
    Route::get('/pedidos/novo', [CompraPedidoController::class, 'create'])->name('pedidos.create');
    Route::post('/pedidos', [CompraPedidoController::class, 'store'])->name('pedidos.store');
    Route::get('/pedidos/{pedido}/editar', [CompraPedidoController::class, 'edit'])->name('pedidos.edit');
    Route::put('/pedidos/{pedido}', [CompraPedidoController::class, 'update'])->name('pedidos.update');
    Route::get('/pedidos/{pedido}', [CompraPedidoController::class, 'show'])->name('pedidos.show');
    Route::post('/pedidos/{pedido}/notas', [CompraPedidoController::class, 'linkInvoice'])->name('pedidos.notas.link');
    Route::post('/pedidos/{pedido}/notas/importar', [CompraPedidoController::class, 'importInvoice'])->name('pedidos.notas.import');
    Route::post('/pedidos/{pedido}/notas/confirmar', [CompraPedidoController::class, 'confirmInvoiceLink'])->name('pedidos.notas.confirm');
    Route::post('/pedidos/{pedido}/notas/cancelar-preview', [CompraPedidoController::class, 'cancelInvoicePreview'])->name('pedidos.notas.preview.cancel');
    Route::delete('/pedidos/{pedido}/notas/{nota}', [CompraPedidoController::class, 'unlinkInvoice'])->name('pedidos.notas.unlink');
    Route::post('/pedidos/{pedido}/aprovar', [CompraPedidoController::class, 'approve'])->name('pedidos.approve');
});

Route::get('/pages/{legacy}', function (Request $request, string $legacy) {
    $map = [
        'agenda_financeira.php' => '/financeiro/agenda',
        'analise_categorias.php' => '/relatorios/categorias',
        'atividades.php' => '/talhoes/atividades',
        'auditoria.php' => '/auditoria',
        'categorias.php' => '/financeiro/categorias',
        'certificados_digitais.php' => '/fiscal/certificados',
        'chuva.php' => '/talhoes/chuva',
        'colheita.php' => '/colheita',
        'comparativo_safras.php' => '/relatorios/comparativo-safras',
        'contas.php' => '/financeiro/contas',
        'contratos.php' => '/estoque-producao/contratos',
        'despesas.php' => '/financeiro/despesas',
        'documentos.php' => '/fiscal/documentos',
        'dre.php' => '/relatorios/dre',
        'entrada_nf.php' => '/fiscal/entrada-nf',
        'financeiro.php' => '/financeiro',
        'financeiro_analise_despesas.php' => '/financeiro/analise-despesas',
        'financeiro_planejamento.php' => '/financeiro/planejamento',
        'fiscal.php' => '/fiscal',
        'fluxo_caixa.php' => '/relatorios/fluxo-caixa',
        'grupos_fazendas.php' => '/propriedades/grupos',
        'kpis.php' => '/relatorios/kpis',
        'lancamentos_export.php' => '/financeiro/relatorio-lancamentos/exportar',
        'livro_caixa.php' => '/financeiro/livro-caixa',
        'mapa_talhoes.php' => '/talhoes/mapa',
        'maquinas.php' => '/patrimonio',
        'movimentacoes_bancarias.php' => '/financeiro/movimentacoes',
        'notas_fiscais.php' => '/fiscal/notas',
        'orcado_realizado.php' => '/relatorios/orcado-realizado',
        'orcamento.php' => '/orcamento',
        'pedidos_fiscais.php' => '/compras/pedidos',
        'produtores.php' => '/fiscal/produtores',
        'produtos.php' => '/produtos',
        'propriedades.php' => '/propriedades',
        'receitas.php' => '/financeiro/receitas',
        'relatorio_categoria.php' => '/relatorios/categorias',
        'relatorio_safra.php' => '/relatorios/safra',
        'relatorio_talhao.php' => '/relatorios/talhao',
        'safras.php' => '/safras',
        'suporte_admin.php' => '/suporte',
        'system_unlock.php' => '/dashboard',
        'talhoes.php' => '/talhoes',
        'usuarios.php' => '/usuarios',
    ];

    abort_unless(isset($map[$legacy]), 404);

    $query = collect($request->query())
        ->except(['mod', 'admin', 'admin_global'])
        ->all();

    if ($legacy === 'livro_caixa.php' && in_array((string)($query['export'] ?? ''), ['pdf', 'xls'], true)) {
        $query['formato'] = $query['export'];
        unset($query['export']);
        $suffix = $query ? '?'.http_build_query($query) : '';

        return redirect()->to('/financeiro/livro-caixa/exportar'.$suffix);
    }

    $suffix = $query ? '?'.http_build_query($query) : '';

    return redirect()->to($map[$legacy].$suffix);
})->where('legacy', '[A-Za-z0-9_-]+\.php')->name('legacy.pages.redirect');
});
