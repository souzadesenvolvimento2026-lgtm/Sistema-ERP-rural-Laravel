/*M!999999\- enable the sandbox mode */

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;
DROP TABLE IF EXISTS `alertas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `alertas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `safra_id` int(11) NOT NULL,
  `categoria_id` int(11) NOT NULL,
  `percentual_estouro` decimal(5,2) DEFAULT NULL,
  `valor_previsto` decimal(12,2) DEFAULT NULL,
  `valor_realizado` decimal(12,2) DEFAULT NULL,
  `lido` tinyint(1) DEFAULT 0,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `safra_id` (`safra_id`),
  KEY `categoria_id` (`categoria_id`),
  CONSTRAINT `alertas_ibfk_1` FOREIGN KEY (`safra_id`) REFERENCES `safras` (`id`),
  CONSTRAINT `alertas_ibfk_2` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `anos_agricolas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `anos_agricolas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `propriedade_id` int(11) NOT NULL,
  `ano_inicio` int(11) NOT NULL,
  `descricao` varchar(20) NOT NULL,
  `data_inicio` date NOT NULL,
  `data_fim` date NOT NULL,
  `observacoes` text DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ano_agricola_prop_ano` (`propriedade_id`,`ano_inicio`),
  KEY `idx_ano_agricola_prop` (`propriedade_id`)
) ENGINE=InnoDB AUTO_INCREMENT=179 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `atividades_campo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `atividades_campo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `propriedade_id` int(11) NOT NULL,
  `safra_id` int(11) DEFAULT NULL,
  `talhao_id` int(11) DEFAULT NULL,
  `area_executada` decimal(12,2) DEFAULT NULL,
  `tipo` enum('preparo_solo','plantio','manejo','colheita','monitoramento','recomendacao','outro') DEFAULT 'manejo',
  `data_inicio` date NOT NULL,
  `data_fim` date DEFAULT NULL,
  `status` enum('planejada','em_execucao','concluida','cancelada') DEFAULT 'planejada',
  `descricao` varchar(180) NOT NULL,
  `responsavel` varchar(120) DEFAULT NULL,
  `servico` varchar(180) DEFAULT NULL,
  `produto` varchar(120) DEFAULT NULL,
  `dose` varchar(60) DEFAULT NULL,
  `custo_estimado` decimal(12,2) DEFAULT 0.00,
  `observacoes` text DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `propriedade_id` (`propriedade_id`),
  KEY `talhao_id` (`talhao_id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `idx_atividades_safra_data` (`safra_id`,`data_inicio`),
  CONSTRAINT `atividades_campo_ibfk_1` FOREIGN KEY (`propriedade_id`) REFERENCES `propriedades` (`id`),
  CONSTRAINT `atividades_campo_ibfk_2` FOREIGN KEY (`safra_id`) REFERENCES `safras` (`id`),
  CONSTRAINT `atividades_campo_ibfk_3` FOREIGN KEY (`talhao_id`) REFERENCES `talhoes` (`id`),
  CONSTRAINT `atividades_campo_ibfk_4` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=801 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `categorias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `categorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `categoria_pai_id` int(11) DEFAULT NULL,
  `nome` varchar(100) NOT NULL,
  `tipo` enum('insumo','manutencao','folha','servico','combustivel','administrativo','bancario','outros') DEFAULT 'outros',
  `cor` varchar(7) DEFAULT '#6c757d',
  `icone` varchar(40) DEFAULT 'bi-tag',
  `ativo` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_categorias_pai` (`categoria_pai_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3345 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `certificados_digitais`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `certificados_digitais` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `propriedade_id` int(11) NOT NULL,
  `produtor_id` int(11) DEFAULT NULL,
  `tipo_certificado` enum('A1','A3') NOT NULL DEFAULT 'A1',
  `ambiente` enum('homologacao','producao') NOT NULL DEFAULT 'homologacao',
  `nome_identificacao` varchar(120) NOT NULL,
  `titular` varchar(180) DEFAULT NULL,
  `cpf_cnpj` varchar(20) DEFAULT NULL,
  `numero_serie` varchar(120) DEFAULT NULL,
  `emissor` varchar(180) DEFAULT NULL,
  `validade_inicio` datetime DEFAULT NULL,
  `validade_fim` datetime DEFAULT NULL,
  `arquivo_path` varchar(255) DEFAULT NULL,
  `senha_criptografada` text DEFAULT NULL,
  `principal` tinyint(1) NOT NULL DEFAULT 1,
  `status` enum('ativo','inativo','vencido','revogado') NOT NULL DEFAULT 'ativo',
  `observacoes` text DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_certificados_prop` (`propriedade_id`),
  KEY `idx_certificados_usuario` (`usuario_id`),
  CONSTRAINT `fk_certificados_prop` FOREIGN KEY (`propriedade_id`) REFERENCES `propriedades` (`id`),
  CONSTRAINT `fk_certificados_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=757 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `chat_anexos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_anexos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mensagem_id` int(11) NOT NULL,
  `remetente_usuario_id` int(11) NOT NULL,
  `destinatario_usuario_id` int(11) NOT NULL,
  `nome_original` varchar(255) NOT NULL,
  `nome_arquivo` varchar(255) DEFAULT NULL,
  `caminho_relativo` varchar(255) DEFAULT NULL,
  `mime` varchar(120) NOT NULL,
  `tamanho_bytes` int(11) NOT NULL DEFAULT 0,
  `baixado_por` int(11) DEFAULT NULL,
  `baixado_em` datetime DEFAULT NULL,
  `expira_em` datetime NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_chat_anexos_mensagem` (`mensagem_id`),
  KEY `idx_chat_anexos_expira` (`expira_em`)
) ENGINE=InnoDB AUTO_INCREMENT=120 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `chat_mensagens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_mensagens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `remetente_usuario_id` int(11) NOT NULL,
  `destinatario_usuario_id` int(11) NOT NULL,
  `mensagem` text DEFAULT NULL,
  `lida_em` datetime DEFAULT NULL,
  `criada_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_chat_destinatario_lida` (`destinatario_usuario_id`,`lida_em`),
  KEY `idx_chat_conversa` (`remetente_usuario_id`,`destinatario_usuario_id`,`id`)
) ENGINE=InnoDB AUTO_INCREMENT=226 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `chat_usuarios_online`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_usuarios_online` (
  `usuario_id` int(11) NOT NULL,
  `sessao_id` varchar(128) DEFAULT NULL,
  `sessao_token` varchar(128) DEFAULT NULL,
  `atualizado_em` datetime NOT NULL,
  PRIMARY KEY (`usuario_id`),
  KEY `idx_chat_online_atualizado` (`atualizado_em`),
  KEY `idx_chat_online_sessao` (`sessao_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `chuvas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `chuvas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `propriedade_id` int(11) NOT NULL,
  `talhao_id` int(11) DEFAULT NULL,
  `data_chuva` date NOT NULL,
  `volume_mm` decimal(8,2) NOT NULL DEFAULT 0.00,
  `fonte` enum('manual','pluviometro','estacao','importado') DEFAULT 'manual',
  `observacoes` text DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `propriedade_id` (`propriedade_id`),
  KEY `talhao_id` (`talhao_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `chuvas_ibfk_1` FOREIGN KEY (`propriedade_id`) REFERENCES `propriedades` (`id`),
  CONSTRAINT `chuvas_ibfk_2` FOREIGN KEY (`talhao_id`) REFERENCES `talhoes` (`id`),
  CONSTRAINT `chuvas_ibfk_3` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=757 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `colheita_talhoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `colheita_talhoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `propriedade_id` int(11) NOT NULL,
  `safra_id` int(11) DEFAULT NULL,
  `talhao_id` int(11) NOT NULL,
  `mapa_colheita_id` int(11) DEFAULT NULL,
  `ticket_numero` varchar(40) DEFAULT NULL,
  `ticket_imagem` varchar(255) DEFAULT NULL,
  `ticket_ocr_texto` mediumtext DEFAULT NULL,
  `motorista` varchar(120) DEFAULT NULL,
  `veiculo_placa` varchar(80) DEFAULT NULL,
  `destino_producao` varchar(40) DEFAULT NULL,
  `local_destino` varchar(160) DEFAULT NULL,
  `data_colheita` date NOT NULL,
  `peso_bruto_kg` decimal(12,2) DEFAULT NULL,
  `tara_kg` decimal(12,2) DEFAULT NULL,
  `peso_liquido_kg` decimal(12,2) DEFAULT NULL,
  `desconto_kg` decimal(12,2) DEFAULT NULL,
  `peso_final_kg` decimal(12,2) DEFAULT NULL,
  `sacas` decimal(12,2) NOT NULL DEFAULT 0.00,
  `area_colhida` decimal(10,2) DEFAULT NULL,
  `produtividade_sc_ha` decimal(12,2) DEFAULT NULL,
  `umidade` decimal(6,2) DEFAULT NULL,
  `impureza_pct` decimal(6,2) DEFAULT NULL,
  `origem` enum('manual','arquivo') NOT NULL DEFAULT 'manual',
  `observacoes` text DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `propriedade_id` (`propriedade_id`),
  KEY `safra_id` (`safra_id`),
  KEY `talhao_id` (`talhao_id`),
  KEY `mapa_colheita_id` (`mapa_colheita_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `colheita_talhoes_ibfk_1` FOREIGN KEY (`propriedade_id`) REFERENCES `propriedades` (`id`),
  CONSTRAINT `colheita_talhoes_ibfk_2` FOREIGN KEY (`safra_id`) REFERENCES `safras` (`id`),
  CONSTRAINT `colheita_talhoes_ibfk_3` FOREIGN KEY (`talhao_id`) REFERENCES `talhoes` (`id`),
  CONSTRAINT `colheita_talhoes_ibfk_4` FOREIGN KEY (`mapa_colheita_id`) REFERENCES `mapas_colheita` (`id`),
  CONSTRAINT `colheita_talhoes_ibfk_5` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=248 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `compradores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `compradores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `propriedade_id` int(11) NOT NULL,
  `nome` varchar(150) NOT NULL,
  `documento` varchar(30) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_comprador_prop_nome` (`propriedade_id`,`nome`),
  KEY `idx_compradores_prop` (`propriedade_id`),
  CONSTRAINT `fk_compradores_prop` FOREIGN KEY (`propriedade_id`) REFERENCES `propriedades` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2356 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `contas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `propriedade_id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `tipo` enum('conta_corrente','conta_poupanca','caixa_interno','investimento') DEFAULT 'conta_corrente',
  `banco` varchar(80) DEFAULT NULL,
  `agencia` varchar(20) DEFAULT NULL,
  `numero_conta` varchar(30) DEFAULT NULL,
  `saldo_inicial` decimal(12,2) DEFAULT 0.00,
  `ativo` tinyint(1) DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `propriedade_id` (`propriedade_id`),
  CONSTRAINT `contas_ibfk_1` FOREIGN KEY (`propriedade_id`) REFERENCES `propriedades` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1213 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contrato_entregas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `contrato_entregas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contrato_id` int(11) NOT NULL,
  `data_entrega` date NOT NULL,
  `quantidade` decimal(12,3) DEFAULT 0.000,
  `unidade` varchar(30) DEFAULT 'sc',
  `valor` decimal(12,2) DEFAULT 0.00,
  `nota_fiscal_id` int(11) DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `contrato_id` (`contrato_id`),
  KEY `nota_fiscal_id` (`nota_fiscal_id`),
  CONSTRAINT `contrato_entregas_ibfk_1` FOREIGN KEY (`contrato_id`) REFERENCES `contratos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contrato_entregas_ibfk_2` FOREIGN KEY (`nota_fiscal_id`) REFERENCES `notas_fiscais` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=315 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contratos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `contratos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `propriedade_id` int(11) NOT NULL,
  `safra_id` int(11) DEFAULT NULL,
  `tipo` enum('venda','deposito','armazenagem','fixacao','compra') DEFAULT 'venda',
  `numero` varchar(80) NOT NULL,
  `contraparte` varchar(150) DEFAULT NULL,
  `produto` varchar(100) DEFAULT NULL,
  `quantidade` decimal(12,3) DEFAULT 0.000,
  `unidade` varchar(30) DEFAULT 'sc',
  `preco_unitario` decimal(12,2) DEFAULT 0.00,
  `valor_total` decimal(12,2) DEFAULT 0.00,
  `data_contrato` date NOT NULL,
  `data_vencimento` date DEFAULT NULL,
  `status` enum('aberto','parcial','entregue','cancelado') DEFAULT 'aberto',
  `observacoes` text DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `propriedade_id` (`propriedade_id`),
  KEY `safra_id` (`safra_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `contratos_ibfk_1` FOREIGN KEY (`propriedade_id`) REFERENCES `propriedades` (`id`),
  CONSTRAINT `contratos_ibfk_2` FOREIGN KEY (`safra_id`) REFERENCES `safras` (`id`),
  CONSTRAINT `contratos_ibfk_3` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=653 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `culturas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `culturas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(80) NOT NULL,
  `unidade_producao` varchar(20) DEFAULT 'sc' COMMENT 'sc=sacas, ton=toneladas',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=348 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `despesas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `despesas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `propriedade_id` int(11) NOT NULL,
  `safra_id` int(11) DEFAULT NULL,
  `talhao_id` int(11) DEFAULT NULL,
  `categoria_id` int(11) NOT NULL,
  `subcategoria_id` int(11) DEFAULT NULL,
  `conta_id` int(11) DEFAULT NULL,
  `produtor_id` int(11) DEFAULT NULL,
  `descricao` varchar(255) NOT NULL,
  `fornecedor` varchar(150) DEFAULT NULL,
  `quantidade` decimal(10,3) DEFAULT NULL,
  `unidade` varchar(30) DEFAULT NULL,
  `valor_unitario` decimal(12,2) DEFAULT NULL,
  `valor_total` decimal(12,2) NOT NULL,
  `data_lancamento` date NOT NULL,
  `data_vencimento` date DEFAULT NULL,
  `data_pagamento` date DEFAULT NULL,
  `status_pagamento` enum('pendente','pago','vencido','cancelado') DEFAULT 'pendente',
  `status_aprovacao` enum('pendente','aprovada','reprovada') NOT NULL DEFAULT 'pendente',
  `aprovado_por` int(11) DEFAULT NULL,
  `aprovado_em` datetime DEFAULT NULL,
  `motivo_reprovacao` text DEFAULT NULL,
  `forma_pagamento` enum('dinheiro','pix','boleto','cheque','transferencia','cartao') DEFAULT 'pix',
  `numero_parcelas` int(11) DEFAULT 1,
  `parcela_atual` int(11) DEFAULT 1,
  `despesa_pai_id` int(11) DEFAULT NULL COMMENT 'Refer??ncia para parcelamentos',
  `nota_fiscal` varchar(50) DEFAULT NULL,
  `comprovante` varchar(255) DEFAULT NULL COMMENT 'Caminho do arquivo',
  `observacoes` text DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `safra_id` (`safra_id`),
  KEY `talhao_id` (`talhao_id`),
  KEY `categoria_id` (`categoria_id`),
  KEY `conta_id` (`conta_id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `idx_despesas_aprovacao` (`propriedade_id`,`status_aprovacao`),
  KEY `idx_despesas_subcategoria` (`subcategoria_id`),
  CONSTRAINT `despesas_ibfk_1` FOREIGN KEY (`propriedade_id`) REFERENCES `propriedades` (`id`),
  CONSTRAINT `despesas_ibfk_2` FOREIGN KEY (`safra_id`) REFERENCES `safras` (`id`),
  CONSTRAINT `despesas_ibfk_3` FOREIGN KEY (`talhao_id`) REFERENCES `talhoes` (`id`),
  CONSTRAINT `despesas_ibfk_4` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`),
  CONSTRAINT `despesas_ibfk_5` FOREIGN KEY (`conta_id`) REFERENCES `contas` (`id`),
  CONSTRAINT `despesas_ibfk_6` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5101 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `documentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `documentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `propriedade_id` int(11) NOT NULL,
  `safra_id` int(11) DEFAULT NULL,
  `tipo` enum('nota_fiscal','contrato','receituario','boleto','comprovante','analise_solo','mapa','outro') DEFAULT 'outro',
  `titulo` varchar(180) NOT NULL,
  `numero` varchar(80) DEFAULT NULL,
  `pessoa` varchar(150) DEFAULT NULL,
  `data_documento` date DEFAULT NULL,
  `valor` decimal(12,2) DEFAULT 0.00,
  `arquivo` varchar(255) DEFAULT NULL,
  `status` enum('pendente','conferido','arquivado') DEFAULT 'pendente',
  `observacoes` text DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `propriedade_id` (`propriedade_id`),
  KEY `safra_id` (`safra_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `documentos_ibfk_1` FOREIGN KEY (`propriedade_id`) REFERENCES `propriedades` (`id`),
  CONSTRAINT `documentos_ibfk_2` FOREIGN KEY (`safra_id`) REFERENCES `safras` (`id`),
  CONSTRAINT `documentos_ibfk_3` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=447 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `financeiro_projecoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `financeiro_projecoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `propriedade_id` int(11) NOT NULL,
  `safra_id` int(11) DEFAULT NULL,
  `cultura_id` int(11) DEFAULT NULL,
  `tipo_lancamento` enum('receita','despesa') NOT NULL DEFAULT 'despesa',
  `tipo_safra` enum('principal','safrinha') NOT NULL DEFAULT 'principal',
  `ano_safra` varchar(40) NOT NULL,
  `mes_referencia` date NOT NULL,
  `categoria_id` int(11) NOT NULL,
  `subcategoria_id` int(11) DEFAULT NULL,
  `quantidade` decimal(12,3) DEFAULT NULL,
  `unidade` varchar(20) DEFAULT NULL,
  `valor_unitario` decimal(12,2) DEFAULT NULL,
  `valor_projetado` decimal(12,2) NOT NULL DEFAULT 0.00,
  `observacoes` text DEFAULT NULL,
  `recorrencia_grupo` varchar(64) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_fin_proj_prop_safra` (`propriedade_id`,`safra_id`),
  KEY `idx_fin_proj_mes` (`mes_referencia`),
  KEY `idx_fin_proj_categoria` (`categoria_id`),
  KEY `fk_fin_proj_safra` (`safra_id`),
  KEY `fk_fin_proj_usuario` (`usuario_id`),
  KEY `idx_fin_proj_subcategoria` (`subcategoria_id`),
  KEY `idx_fin_proj_cultura` (`cultura_id`),
  KEY `idx_fin_proj_tipo_lancamento` (`tipo_lancamento`),
  KEY `idx_fin_proj_recorrencia` (`recorrencia_grupo`),
  CONSTRAINT `fk_fin_proj_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`),
  CONSTRAINT `fk_fin_proj_prop` FOREIGN KEY (`propriedade_id`) REFERENCES `propriedades` (`id`),
  CONSTRAINT `fk_fin_proj_safra` FOREIGN KEY (`safra_id`) REFERENCES `safras` (`id`),
  CONSTRAINT `fk_fin_proj_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1641 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fiscal_invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fiscal_invoice_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `product_code` varchar(80) DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `unit` varchar(20) DEFAULT NULL,
  `quantity` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `unit_value` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `total_value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_fiscal_invoice_items_invoice` (`invoice_id`),
  CONSTRAINT `fk_fiscal_invoice_items_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `fiscal_invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=665 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fiscal_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fiscal_invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `propriedade_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `access_key` varchar(44) NOT NULL,
  `invoice_number` varchar(30) NOT NULL,
  `series` varchar(10) DEFAULT NULL,
  `issue_date` date NOT NULL,
  `issuer_cnpj` varchar(20) NOT NULL,
  `issuer_name` varchar(160) NOT NULL,
  `recipient_cnpj` varchar(20) DEFAULT NULL,
  `recipient_name` varchar(160) DEFAULT NULL,
  `total_value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` varchar(40) NOT NULL DEFAULT 'aguardando_aprovacao',
  `xml_file_path` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `previous_status` varchar(40) DEFAULT NULL,
  `approval_metadata` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fiscal_invoices_access_key` (`access_key`),
  KEY `idx_fiscal_invoices_propriedade` (`propriedade_id`),
  KEY `idx_fiscal_invoices_user` (`user_id`),
  KEY `idx_fiscal_invoices_status` (`status`),
  KEY `idx_fiscal_invoices_issuer_cnpj` (`issuer_cnpj`),
  KEY `idx_fiscal_invoices_issue_date` (`issue_date`),
  KEY `fk_fiscal_invoices_created_by` (`created_by`),
  CONSTRAINT `fk_fiscal_invoices_created_by` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_fiscal_invoices_propriedade` FOREIGN KEY (`propriedade_id`) REFERENCES `propriedades` (`id`),
  CONSTRAINT `fk_fiscal_invoices_user` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=775 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fiscal_order_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fiscal_order_invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `linked_by` int(11) DEFAULT NULL,
  `linked_at` datetime NOT NULL DEFAULT current_timestamp(),
  `match_status` varchar(40) DEFAULT NULL,
  `match_summary` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fiscal_order_invoice` (`order_id`,`invoice_id`),
  KEY `idx_fiscal_order_invoices_invoice` (`invoice_id`),
  KEY `fk_fiscal_order_invoices_user` (`linked_by`),
  CONSTRAINT `fk_fiscal_order_invoices_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `fiscal_invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fiscal_order_invoices_order` FOREIGN KEY (`order_id`) REFERENCES `fiscal_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fiscal_order_invoices_user` FOREIGN KEY (`linked_by`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=152 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fiscal_order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fiscal_order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_code` varchar(80) DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `unit` varchar(20) DEFAULT NULL,
  `categoria_id` int(11) DEFAULT NULL,
  `patrimonio_id` int(11) DEFAULT NULL,
  `patrimonio_uso` enum('estoque','total','parcial') NOT NULL DEFAULT 'estoque',
  `patrimonio_quantidade` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `quantity` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `unit_value` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `total_value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_fiscal_order_items_order` (`order_id`),
  KEY `idx_fiscal_order_items_categoria` (`categoria_id`),
  KEY `idx_fiscal_order_items_patrimonio` (`patrimonio_id`),
  CONSTRAINT `fk_fiscal_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `fiscal_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=919 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fiscal_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fiscal_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `propriedade_id` int(11) NOT NULL,
  `order_number` varchar(40) NOT NULL,
  `supplier_name` varchar(160) DEFAULT NULL,
  `supplier_cnpj` varchar(20) DEFAULT NULL,
  `issue_date` date NOT NULL,
  `total_value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` varchar(40) NOT NULL DEFAULT 'em_aberto',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `previous_status` varchar(40) DEFAULT NULL,
  `approval_metadata` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `order_type` varchar(20) NOT NULL DEFAULT 'entrada',
  `financial_expense_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_fiscal_orders_propriedade` (`propriedade_id`),
  KEY `idx_fiscal_orders_status` (`status`),
  KEY `idx_fiscal_orders_supplier_cnpj` (`supplier_cnpj`),
  KEY `idx_fiscal_orders_issue_date` (`issue_date`),
  KEY `fk_fiscal_orders_created_by` (`created_by`),
  KEY `fk_fiscal_orders_approved_by` (`approved_by`),
  KEY `idx_fiscal_orders_financial_expense` (`financial_expense_id`),
  CONSTRAINT `fk_fiscal_orders_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_fiscal_orders_created_by` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_fiscal_orders_propriedade` FOREIGN KEY (`propriedade_id`) REFERENCES `propriedades` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=594 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `grupo_fazenda_propriedades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `grupo_fazenda_propriedades` (
  `grupo_id` int(11) NOT NULL,
  `propriedade_id` int(11) NOT NULL,
  PRIMARY KEY (`grupo_id`,`propriedade_id`),
  KEY `propriedade_id` (`propriedade_id`),
  CONSTRAINT `grupo_fazenda_propriedades_ibfk_1` FOREIGN KEY (`grupo_id`) REFERENCES `grupos_fazendas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `grupo_fazenda_propriedades_ibfk_2` FOREIGN KEY (`propriedade_id`) REFERENCES `propriedades` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `grupos_fazendas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `grupos_fazendas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(120) NOT NULL,
  `descricao` text DEFAULT NULL,
  `aprovador_usuario_id` int(11) DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_grupos_fazendas_aprovador` (`aprovador_usuario_id`)
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `logs_auditoria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `logs_auditoria` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) DEFAULT NULL,
  `acao` varchar(100) NOT NULL,
  `tabela` varchar(60) DEFAULT NULL,
  `registro_id` int(11) DEFAULT NULL,
  `propriedade_id` int(11) DEFAULT NULL,
  `detalhes` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `idx_logs_auditoria_propriedade` (`propriedade_id`,`criado_em`),
  CONSTRAINT `logs_auditoria_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5345 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mapas_colheita`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mapas_colheita` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `propriedade_id` int(11) NOT NULL,
  `safra_id` int(11) DEFAULT NULL,
  `titulo` varchar(160) NOT NULL,
  `tipo_arquivo` varchar(20) NOT NULL,
  `arquivo` varchar(255) NOT NULL,
  `registros_importados` int(11) DEFAULT 0,
  `observacoes` text DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `propriedade_id` (`propriedade_id`),
  KEY `safra_id` (`safra_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `mapas_colheita_ibfk_1` FOREIGN KEY (`propriedade_id`) REFERENCES `propriedades` (`id`),
  CONSTRAINT `mapas_colheita_ibfk_2` FOREIGN KEY (`safra_id`) REFERENCES `safras` (`id`),
  CONSTRAINT `mapas_colheita_ibfk_3` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='LEGADO_INATIVA_EM_OBSERVACAO: sem uso operacional PHP em 2026-06-02; manter por vinculo tecnico com colheita_talhoes.mapa_colheita_id.';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `maquina_lancamentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `maquina_lancamentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `propriedade_id` int(11) NOT NULL,
  `maquina_id` int(11) NOT NULL,
  `safra_id` int(11) DEFAULT NULL,
  `talhao_id` int(11) DEFAULT NULL,
  `tipo` enum('abastecimento','manutencao_preventiva','manutencao_corretiva','troca_oleo','pecas','seguro','outro') DEFAULT 'abastecimento',
  `data_lancamento` date NOT NULL,
  `descricao` varchar(180) NOT NULL,
  `fornecedor` varchar(150) DEFAULT NULL,
  `quantidade` decimal(12,3) DEFAULT NULL,
  `unidade` varchar(30) DEFAULT NULL,
  `valor_unitario` decimal(12,2) DEFAULT 0.00,
  `valor_total` decimal(12,2) DEFAULT 0.00,
  `horimetro` decimal(12,2) DEFAULT NULL,
  `odometro` decimal(12,2) DEFAULT NULL,
  `proxima_revisao_horas` decimal(12,2) DEFAULT NULL,
  `comprovante` varchar(255) DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `propriedade_id` (`propriedade_id`),
  KEY `maquina_id` (`maquina_id`),
  KEY `safra_id` (`safra_id`),
  KEY `talhao_id` (`talhao_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `maquina_lancamentos_ibfk_1` FOREIGN KEY (`propriedade_id`) REFERENCES `propriedades` (`id`),
  CONSTRAINT `maquina_lancamentos_ibfk_2` FOREIGN KEY (`maquina_id`) REFERENCES `maquinas` (`id`),
  CONSTRAINT `maquina_lancamentos_ibfk_3` FOREIGN KEY (`safra_id`) REFERENCES `safras` (`id`),
  CONSTRAINT `maquina_lancamentos_ibfk_4` FOREIGN KEY (`talhao_id`) REFERENCES `talhoes` (`id`),
  CONSTRAINT `maquina_lancamentos_ibfk_5` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=237 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `maquinas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `maquinas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `propriedade_id` int(11) NOT NULL,
  `nome` varchar(120) NOT NULL,
  `tipo` enum('trator','colheitadeira','plantadeira','pulverizador','caminhao','implemento','outro') DEFAULT 'trator',
  `tipo_outro` varchar(120) DEFAULT NULL,
  `marca_modelo` varchar(150) DEFAULT NULL,
  `identificacao` varchar(80) DEFAULT NULL,
  `descricao_patrimonio` text DEFAULT NULL,
  `ano` int(11) DEFAULT NULL,
  `valor_aquisicao` decimal(14,2) NOT NULL DEFAULT 0.00,
  `data_aquisicao` date DEFAULT NULL,
  `fornecedor` varchar(180) DEFAULT NULL,
  `fornecedor_doc` varchar(20) DEFAULT NULL,
  `nota_fiscal_numero` varchar(40) DEFAULT NULL,
  `nota_fiscal_serie` varchar(20) DEFAULT NULL,
  `nota_fiscal_chave` varchar(44) DEFAULT NULL,
  `nota_fiscal_arquivo` varchar(255) DEFAULT NULL,
  `nf_entrada_id` int(11) DEFAULT NULL,
  `documento_id` int(11) DEFAULT NULL,
  `controla_horimetro` tinyint(1) NOT NULL DEFAULT 1,
  `controla_odometro` tinyint(1) NOT NULL DEFAULT 0,
  `horimetro_atual` decimal(12,2) DEFAULT 0.00,
  `odometro_atual` decimal(12,2) DEFAULT 0.00,
  `ativo` tinyint(1) DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `propriedade_id` (`propriedade_id`),
  CONSTRAINT `maquinas_ibfk_1` FOREIGN KEY (`propriedade_id`) REFERENCES `propriedades` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=569 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `movimentacoes_bancarias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `movimentacoes_bancarias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `propriedade_id` int(11) NOT NULL,
  `conta_id` int(11) NOT NULL,
  `data_movimento` date NOT NULL,
  `tipo` enum('entrada','saida') NOT NULL,
  `descricao` varchar(180) NOT NULL,
  `valor` decimal(12,2) NOT NULL DEFAULT 0.00,
  `origem` enum('manual','extrato','ofx','csv') DEFAULT 'manual',
  `status` enum('pendente','conciliado','ignorado') DEFAULT 'pendente',
  `referencia_tipo` varchar(40) DEFAULT NULL,
  `referencia_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `propriedade_id` (`propriedade_id`),
  KEY `conta_id` (`conta_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `movimentacoes_bancarias_ibfk_1` FOREIGN KEY (`propriedade_id`) REFERENCES `propriedades` (`id`),
  CONSTRAINT `movimentacoes_bancarias_ibfk_2` FOREIGN KEY (`conta_id`) REFERENCES `contas` (`id`),
  CONSTRAINT `movimentacoes_bancarias_ibfk_3` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=452 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `nf_entrada_itens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nf_entrada_itens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nf_entrada_id` int(11) NOT NULL,
  `produto_id` int(11) DEFAULT NULL,
  `descricao_nf` varchar(255) NOT NULL,
  `descricao_generica` varchar(180) DEFAULT NULL,
  `descricao_detalhada` text DEFAULT NULL,
  `descricao_interna` varchar(255) DEFAULT NULL,
  `descricao_uso` enum('generica','detalhada','interna') NOT NULL DEFAULT 'generica',
  `quantidade` decimal(14,4) NOT NULL DEFAULT 1.0000,
  `unidade` varchar(30) DEFAULT 'un',
  `valor_unitario` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `valor_total` decimal(14,2) NOT NULL DEFAULT 0.00,
  `desconto` decimal(14,2) NOT NULL DEFAULT 0.00,
  `frete_rateado` decimal(14,2) NOT NULL DEFAULT 0.00,
  `base_icms` decimal(14,2) NOT NULL DEFAULT 0.00,
  `valor_icms` decimal(14,2) NOT NULL DEFAULT 0.00,
  `base_pis` decimal(14,2) NOT NULL DEFAULT 0.00,
  `valor_pis` decimal(14,2) NOT NULL DEFAULT 0.00,
  `base_cofins` decimal(14,2) NOT NULL DEFAULT 0.00,
  `valor_cofins` decimal(14,2) NOT NULL DEFAULT 0.00,
  `valor_ipi` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total_liquido` decimal(14,2) NOT NULL DEFAULT 0.00,
  `centro_custo` varchar(120) DEFAULT NULL,
  `fazenda_unidade` varchar(160) DEFAULT NULL,
  `safra_id` int(11) DEFAULT NULL,
  `categoria_id` int(11) DEFAULT NULL,
  `fiscal_validado` tinyint(1) NOT NULL DEFAULT 0,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_nf_item_entrada` (`nf_entrada_id`),
  KEY `idx_nf_item_produto` (`produto_id`),
  KEY `idx_nf_item_safra` (`safra_id`),
  KEY `idx_nf_item_categoria` (`categoria_id`),
  CONSTRAINT `fk_nf_item_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`),
  CONSTRAINT `fk_nf_item_entrada` FOREIGN KEY (`nf_entrada_id`) REFERENCES `nf_entradas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_nf_item_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`),
  CONSTRAINT `fk_nf_item_safra` FOREIGN KEY (`safra_id`) REFERENCES `safras` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=169 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `nf_entrada_parcelas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nf_entrada_parcelas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nf_entrada_id` int(11) NOT NULL,
  `despesa_id` int(11) DEFAULT NULL,
  `parcela_numero` int(11) NOT NULL DEFAULT 1,
  `data_vencimento` date NOT NULL,
  `valor` decimal(14,2) NOT NULL DEFAULT 0.00,
  `forma_pagamento` enum('dinheiro','pix','boleto','cheque','transferencia','cartao') DEFAULT 'boleto',
  `conta_id` int(11) DEFAULT NULL,
  `observacoes` varchar(255) DEFAULT NULL,
  `status` enum('pendente','confirmada','cancelada') NOT NULL DEFAULT 'pendente',
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_nf_parcela_entrada` (`nf_entrada_id`),
  KEY `idx_nf_parcela_despesa` (`despesa_id`),
  KEY `idx_nf_parcela_conta` (`conta_id`),
  CONSTRAINT `fk_nf_parcela_conta` FOREIGN KEY (`conta_id`) REFERENCES `contas` (`id`),
  CONSTRAINT `fk_nf_parcela_despesa` FOREIGN KEY (`despesa_id`) REFERENCES `despesas` (`id`),
  CONSTRAINT `fk_nf_parcela_entrada` FOREIGN KEY (`nf_entrada_id`) REFERENCES `nf_entradas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=279 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `nf_entradas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nf_entradas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `propriedade_id` int(11) NOT NULL,
  `nota_fiscal_id` int(11) DEFAULT NULL,
  `numero` varchar(40) NOT NULL,
  `serie` varchar(20) DEFAULT NULL,
  `chave_acesso` varchar(44) DEFAULT NULL,
  `origem_lancamento` enum('manual','certificado') NOT NULL DEFAULT 'manual',
  `data_emissao` date NOT NULL,
  `data_entrada` date NOT NULL,
  `fornecedor` varchar(180) NOT NULL,
  `fornecedor_doc` varchar(20) DEFAULT NULL,
  `valor_total` decimal(14,2) NOT NULL DEFAULT 0.00,
  `valor_produtos` decimal(14,2) NOT NULL DEFAULT 0.00,
  `valor_frete` decimal(14,2) NOT NULL DEFAULT 0.00,
  `valor_desconto` decimal(14,2) NOT NULL DEFAULT 0.00,
  `valor_impostos` decimal(14,2) NOT NULL DEFAULT 0.00,
  `valor_financeiro_final` decimal(14,2) NOT NULL DEFAULT 0.00,
  `condicao_pagamento` varchar(80) DEFAULT NULL,
  `forma_pagamento` enum('dinheiro','pix','boleto','cheque','transferencia','cartao') DEFAULT 'boleto',
  `conta_id` int(11) DEFAULT NULL,
  `produtor_id` int(11) DEFAULT NULL,
  `centro_custo` varchar(120) DEFAULT NULL,
  `categoria_id` int(11) DEFAULT NULL,
  `safra_id` int(11) DEFAULT NULL,
  `fazenda_unidade` varchar(160) DEFAULT NULL,
  `observacoes_nota` text DEFAULT NULL,
  `observacoes_financeiras` text DEFAULT NULL,
  `status` enum('rascunho','validada','concluida','cancelada') NOT NULL DEFAULT 'rascunho',
  `financeiro_confirmado` tinyint(1) NOT NULL DEFAULT 0,
  `classificar_patrimonio` tinyint(1) NOT NULL DEFAULT 0,
  `patrimonio_id` int(11) DEFAULT NULL,
  `patrimonio_nome` varchar(120) DEFAULT NULL,
  `patrimonio_tipo` varchar(40) DEFAULT NULL,
  `patrimonio_tipo_outro` varchar(120) DEFAULT NULL,
  `patrimonio_controla_horimetro` tinyint(1) NOT NULL DEFAULT 0,
  `patrimonio_controla_odometro` tinyint(1) NOT NULL DEFAULT 0,
  `usuario_id` int(11) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_nf_entradas_prop` (`propriedade_id`),
  KEY `idx_nf_entradas_safra` (`safra_id`),
  KEY `idx_nf_entradas_categoria` (`categoria_id`),
  KEY `idx_nf_entradas_conta` (`conta_id`),
  KEY `idx_nf_entradas_nota` (`nota_fiscal_id`),
  KEY `fk_nf_ent_usuario` (`usuario_id`),
  CONSTRAINT `fk_nf_ent_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`),
  CONSTRAINT `fk_nf_ent_conta` FOREIGN KEY (`conta_id`) REFERENCES `contas` (`id`),
  CONSTRAINT `fk_nf_ent_nota` FOREIGN KEY (`nota_fiscal_id`) REFERENCES `notas_fiscais` (`id`),
  CONSTRAINT `fk_nf_ent_prop` FOREIGN KEY (`propriedade_id`) REFERENCES `propriedades` (`id`),
  CONSTRAINT `fk_nf_ent_safra` FOREIGN KEY (`safra_id`) REFERENCES `safras` (`id`),
  CONSTRAINT `fk_nf_ent_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=265 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `nota_fiscal_itens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nota_fiscal_itens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nota_fiscal_id` int(11) NOT NULL,
  `descricao` varchar(180) NOT NULL,
  `quantidade` decimal(12,3) NOT NULL DEFAULT 1.000,
  `unidade` varchar(20) DEFAULT 'un',
  `valor_unitario` decimal(12,2) NOT NULL DEFAULT 0.00,
  `valor_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `nota_fiscal_id` (`nota_fiscal_id`),
  CONSTRAINT `nota_fiscal_itens_ibfk_1` FOREIGN KEY (`nota_fiscal_id`) REFERENCES `notas_fiscais` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notas_fiscais`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notas_fiscais` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `propriedade_id` int(11) NOT NULL,
  `safra_id` int(11) DEFAULT NULL,
  `tipo` enum('emitida','recebida') NOT NULL,
  `modelo` enum('nfe','nfpe','manual') DEFAULT 'nfe',
  `numero` varchar(30) DEFAULT NULL,
  `serie` varchar(10) DEFAULT NULL,
  `chave_acesso` varchar(44) DEFAULT NULL,
  `emitente` varchar(160) DEFAULT NULL,
  `emitente_doc` varchar(20) DEFAULT NULL,
  `destinatario` varchar(160) DEFAULT NULL,
  `destinatario_doc` varchar(20) DEFAULT NULL,
  `data_emissao` date NOT NULL,
  `valor_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('rascunho','gerada','importada','cancelada') DEFAULT 'rascunho',
  `xml_arquivo` varchar(255) DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `propriedade_id` (`propriedade_id`),
  KEY `safra_id` (`safra_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `notas_fiscais_ibfk_1` FOREIGN KEY (`propriedade_id`) REFERENCES `propriedades` (`id`),
  CONSTRAINT `notas_fiscais_ibfk_2` FOREIGN KEY (`safra_id`) REFERENCES `safras` (`id`),
  CONSTRAINT `notas_fiscais_ibfk_3` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `orcamentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `orcamentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `safra_id` int(11) NOT NULL,
  `categoria_id` int(11) NOT NULL,
  `valor_previsto` decimal(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_safra_cat` (`safra_id`,`categoria_id`),
  KEY `categoria_id` (`categoria_id`),
  CONSTRAINT `orcamentos_ibfk_1` FOREIGN KEY (`safra_id`) REFERENCES `safras` (`id`),
  CONSTRAINT `orcamentos_ibfk_2` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `produto_estoque_movimentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `produto_estoque_movimentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `propriedade_id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `origem_tipo` varchar(40) NOT NULL,
  `origem_id` int(11) NOT NULL,
  `fiscal_order_id` int(11) DEFAULT NULL,
  `fiscal_order_item_id` int(11) DEFAULT NULL,
  `tipo` enum('entrada','saida','ajuste') NOT NULL DEFAULT 'entrada',
  `quantidade` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `unidade` varchar(20) DEFAULT NULL,
  `valor_unitario` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `valor_total` decimal(14,2) NOT NULL DEFAULT 0.00,
  `data_movimento` date NOT NULL,
  `observacoes` text DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_produto_estoque_pedido_item` (`fiscal_order_item_id`),
  KEY `idx_produto_estoque_produto` (`produto_id`),
  KEY `idx_produto_estoque_prop_data` (`propriedade_id`,`data_movimento`),
  KEY `fk_produto_estoque_usuario` (`usuario_id`),
  CONSTRAINT `fk_produto_estoque_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`),
  CONSTRAINT `fk_produto_estoque_prop` FOREIGN KEY (`propriedade_id`) REFERENCES `propriedades` (`id`),
  CONSTRAINT `fk_produto_estoque_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=180 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `produtores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `produtores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `propriedade_id` int(11) NOT NULL,
  `nome` varchar(150) NOT NULL,
  `documento` varchar(30) DEFAULT NULL,
  `participacao_percentual` decimal(8,4) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_produtor_prop_nome` (`propriedade_id`,`nome`),
  KEY `idx_produtores_prop` (`propriedade_id`),
  CONSTRAINT `fk_produtores_prop` FOREIGN KEY (`propriedade_id`) REFERENCES `propriedades` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=410 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `produtos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `produtos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `propriedade_id` int(11) NOT NULL,
  `codigo_interno` varchar(60) DEFAULT NULL,
  `codigo_fornecedor` varchar(80) DEFAULT NULL,
  `descricao_original_nf` varchar(255) DEFAULT NULL,
  `descricao_generica` varchar(180) NOT NULL,
  `descricao_detalhada` text DEFAULT NULL,
  `descricao_interna` varchar(255) DEFAULT NULL,
  `unidade_medida` varchar(30) DEFAULT 'un',
  `categoria_id` int(11) DEFAULT NULL,
  `grupo` varchar(100) DEFAULT NULL,
  `subgrupo` varchar(100) DEFAULT NULL,
  `marca` varchar(100) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `ncm` varchar(10) DEFAULT NULL,
  `cest` varchar(10) DEFAULT NULL,
  `cfop_entrada` varchar(10) DEFAULT NULL,
  `cst_icms` varchar(10) DEFAULT NULL,
  `csosn` varchar(10) DEFAULT NULL,
  `cst_pis` varchar(10) DEFAULT NULL,
  `cst_cofins` varchar(10) DEFAULT NULL,
  `aliquota_icms` decimal(8,4) NOT NULL DEFAULT 0.0000,
  `aliquota_pis` decimal(8,4) NOT NULL DEFAULT 0.0000,
  `aliquota_cofins` decimal(8,4) NOT NULL DEFAULT 0.0000,
  `aliquota_ipi` decimal(8,4) NOT NULL DEFAULT 0.0000,
  `origem_mercadoria` varchar(80) DEFAULT NULL,
  `tipo_item` varchar(80) DEFAULT NULL,
  `codigo_anp` varchar(20) DEFAULT NULL,
  `informacoes_fiscais` text DEFAULT NULL,
  `observacoes_fiscais` text DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_produtos_prop` (`propriedade_id`),
  KEY `idx_produtos_categoria` (`categoria_id`),
  KEY `idx_produtos_ncm` (`ncm`),
  CONSTRAINT `fk_produtos_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`),
  CONSTRAINT `fk_produtos_prop` FOREIGN KEY (`propriedade_id`) REFERENCES `propriedades` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=440 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `propriedades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `propriedades` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(150) NOT NULL,
  `municipio` varchar(100) DEFAULT NULL,
  `estado` char(2) DEFAULT NULL,
  `area_total` decimal(10,2) DEFAULT NULL COMMENT 'Hectares',
  `responsavel` varchar(100) DEFAULT NULL,
  `inscricao_estadual` varchar(50) DEFAULT NULL,
  `cnpj_cpf` varchar(20) DEFAULT NULL,
  `plano` enum('basico','avancado','premium') NOT NULL DEFAULT 'basico',
  `pecuaria_ativa` tinyint(1) NOT NULL DEFAULT 0,
  `ativo` tinyint(1) DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `latitude` decimal(11,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `kml_arquivo` varchar(255) DEFAULT NULL,
  `regiao_cotacao` varchar(160) DEFAULT NULL,
  `cotacao_soja` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cotacao_soja_atualizada_em` date DEFAULT NULL,
  `cotacao_soja_fonte` varchar(160) DEFAULT NULL,
  `cotacao_soja_auto` tinyint(1) NOT NULL DEFAULT 1,
  `cotacao_soja_ultima_busca` datetime DEFAULT NULL,
  `cotacao_soja_proxima_busca` datetime DEFAULT NULL,
  `cotacao_soja_status` varchar(40) DEFAULT NULL,
  `cotacao_soja_erro` varchar(255) DEFAULT NULL,
  `aprovador_usuario_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_propriedades_aprovador` (`aprovador_usuario_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2456 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `receitas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `receitas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `propriedade_id` int(11) NOT NULL,
  `safra_id` int(11) DEFAULT NULL,
  `categoria_id` int(11) DEFAULT NULL,
  `subcategoria_id` int(11) DEFAULT NULL,
  `conta_id` int(11) DEFAULT NULL,
  `produtor_id` int(11) DEFAULT NULL,
  `descricao` varchar(255) NOT NULL,
  `comprador` varchar(150) DEFAULT NULL,
  `quantidade` decimal(10,3) DEFAULT NULL,
  `unidade` varchar(30) DEFAULT NULL,
  `preco_unitario` decimal(12,2) DEFAULT NULL,
  `valor_total` decimal(12,2) NOT NULL,
  `data_venda` date NOT NULL,
  `data_recebimento` date DEFAULT NULL,
  `status` enum('pendente','recebido','cancelado') DEFAULT 'pendente',
  `status_aprovacao` enum('pendente','aprovada','reprovada') NOT NULL DEFAULT 'aprovada',
  `aprovado_por` int(11) DEFAULT NULL,
  `aprovado_em` datetime DEFAULT NULL,
  `motivo_reprovacao` text DEFAULT NULL,
  `comprovante` varchar(255) DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `propriedade_id` (`propriedade_id`),
  KEY `safra_id` (`safra_id`),
  KEY `conta_id` (`conta_id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `idx_receitas_categoria` (`categoria_id`),
  KEY `idx_receitas_subcategoria` (`subcategoria_id`),
  KEY `idx_receitas_aprovacao` (`propriedade_id`,`status_aprovacao`),
  CONSTRAINT `receitas_ibfk_1` FOREIGN KEY (`propriedade_id`) REFERENCES `propriedades` (`id`),
  CONSTRAINT `receitas_ibfk_2` FOREIGN KEY (`safra_id`) REFERENCES `safras` (`id`),
  CONSTRAINT `receitas_ibfk_3` FOREIGN KEY (`conta_id`) REFERENCES `contas` (`id`),
  CONSTRAINT `receitas_ibfk_4` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2222 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `safra_talhoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `safra_talhoes` (
  `safra_id` int(11) NOT NULL,
  `talhao_id` int(11) NOT NULL,
  `propriedade_id` int(11) NOT NULL,
  `colheita_finalizada_em` datetime DEFAULT NULL,
  `colheita_finalizada_por` int(11) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`safra_id`,`talhao_id`),
  KEY `idx_safra_talhoes_prop_talhao` (`propriedade_id`,`talhao_id`),
  KEY `idx_safra_talhoes_talhao` (`talhao_id`),
  KEY `idx_safra_talhoes_safra` (`safra_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `safras`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `safras` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `propriedade_id` int(11) NOT NULL,
  `cultura_id` int(11) DEFAULT NULL,
  `safra_referencia` enum('primeira','segunda','terceira') NOT NULL,
  `descricao` varchar(120) NOT NULL,
  `data_inicio` date NOT NULL,
  `data_fim` date DEFAULT NULL,
  `area_plantada` decimal(10,2) DEFAULT NULL,
  `producao_estimada` decimal(10,2) DEFAULT NULL,
  `producao_realizada` decimal(10,2) DEFAULT NULL,
  `preco_estimado` decimal(10,2) DEFAULT NULL COMMENT 'Pre??o estimado por unidade',
  `status` enum('planejamento','em_andamento','colhida','encerrada') DEFAULT 'planejamento',
  `observacoes` text DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `propriedade_id` (`propriedade_id`),
  KEY `cultura_id` (`cultura_id`),
  CONSTRAINT `safras_ibfk_1` FOREIGN KEY (`propriedade_id`) REFERENCES `propriedades` (`id`),
  CONSTRAINT `safras_ibfk_2` FOREIGN KEY (`cultura_id`) REFERENCES `culturas` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3153 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sistema_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sistema_config` (
  `chave` varchar(80) NOT NULL,
  `valor` text DEFAULT NULL,
  `atualizado_por` int(11) DEFAULT NULL,
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`chave`),
  KEY `idx_sistema_config_atualizado_por` (`atualizado_por`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `suporte_anexos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `suporte_anexos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mensagem_id` int(11) NOT NULL,
  `conversa_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `nome_original` varchar(255) NOT NULL,
  `nome_arquivo` varchar(255) DEFAULT NULL,
  `caminho_relativo` varchar(255) DEFAULT NULL,
  `mime` varchar(120) NOT NULL,
  `tamanho_bytes` int(11) NOT NULL DEFAULT 0,
  `baixado_por` int(11) DEFAULT NULL,
  `baixado_em` datetime DEFAULT NULL,
  `expira_em` datetime DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_suporte_anexos_mensagem` (`mensagem_id`),
  KEY `idx_suporte_anexos_conversa` (`conversa_id`),
  KEY `idx_suporte_anexos_expira` (`expira_em`)
) ENGINE=InnoDB AUTO_INCREMENT=121 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `suporte_conversas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `suporte_conversas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `propriedade_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) NOT NULL,
  `atendente_usuario_id` int(11) DEFAULT NULL,
  `atendimento_assumido_em` datetime DEFAULT NULL,
  `nivel_atendimento` enum('colaborador','gerencia','admin') NOT NULL DEFAULT 'colaborador',
  `assunto` varchar(160) NOT NULL DEFAULT 'D??vida do cliente',
  `status` enum('aberta','respondida','aguardando_encerramento','encerrada') NOT NULL DEFAULT 'aberta',
  `origem` enum('manual','ia') NOT NULL DEFAULT 'manual',
  `ia_status` enum('nao_aplicado','pendente','filtrado') NOT NULL DEFAULT 'nao_aplicado',
  `criada_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizada_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `encerrada_em` datetime DEFAULT NULL,
  `encerramento_solicitado_em` datetime DEFAULT NULL,
  `encerramento_solicitado_por` enum('admin','cliente','sistema') DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_suporte_conv_usuario` (`usuario_id`),
  KEY `idx_suporte_conv_prop` (`propriedade_id`),
  KEY `idx_suporte_conv_status` (`status`,`atualizada_em`),
  KEY `idx_suporte_conv_atendente` (`atendente_usuario_id`,`status`,`atualizada_em`),
  KEY `idx_suporte_conv_nivel` (`nivel_atendimento`,`status`,`atualizada_em`),
  CONSTRAINT `fk_suporte_conv_prop` FOREIGN KEY (`propriedade_id`) REFERENCES `propriedades` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_suporte_conv_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=134 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `suporte_mensagens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `suporte_mensagens` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `conversa_id` int(11) NOT NULL,
  `autor_usuario_id` int(11) DEFAULT NULL,
  `autor_tipo` enum('cliente','admin','sistema','ia') NOT NULL DEFAULT 'cliente',
  `mensagem` text NOT NULL,
  `lida_admin` tinyint(1) NOT NULL DEFAULT 0,
  `lida_cliente` tinyint(1) NOT NULL DEFAULT 0,
  `criada_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_suporte_msg_conversa` (`conversa_id`,`id`),
  KEY `idx_suporte_msg_admin` (`lida_admin`,`autor_tipo`,`id`),
  KEY `idx_suporte_msg_cliente` (`lida_cliente`,`autor_tipo`,`id`),
  KEY `fk_suporte_msg_usuario` (`autor_usuario_id`),
  CONSTRAINT `fk_suporte_msg_conversa` FOREIGN KEY (`conversa_id`) REFERENCES `suporte_conversas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_suporte_msg_usuario` FOREIGN KEY (`autor_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=352 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `suporte_mensagens_admin_lidas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `suporte_mensagens_admin_lidas` (
  `mensagem_id` bigint(20) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `lida_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`mensagem_id`,`usuario_id`),
  KEY `idx_suporte_lidas_usuario` (`usuario_id`,`lida_em`),
  CONSTRAINT `fk_suporte_lidas_mensagem` FOREIGN KEY (`mensagem_id`) REFERENCES `suporte_mensagens` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_suporte_lidas_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talhoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `talhoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `propriedade_id` int(11) NOT NULL,
  `nome` varchar(80) NOT NULL,
  `area` decimal(10,2) DEFAULT NULL COMMENT 'Hectares',
  `area_bruta` decimal(12,2) DEFAULT NULL,
  `area_excluida_ha` decimal(12,2) NOT NULL DEFAULT 0.00,
  `descricao` text DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `latitude` decimal(11,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `geometria_tipo` enum('polygon','line','point') DEFAULT NULL,
  `coordenadas_json` longtext DEFAULT NULL,
  `exclusoes_json` longtext DEFAULT NULL,
  `pivo_ativo` tinyint(1) NOT NULL DEFAULT 0,
  `pivo_lat` decimal(10,8) DEFAULT NULL,
  `pivo_lng` decimal(11,8) DEFAULT NULL,
  `pivo_raio_m` decimal(12,2) DEFAULT NULL,
  `pivo_area_ha` decimal(12,2) DEFAULT NULL,
  `kml_nome` varchar(160) DEFAULT NULL,
  `kml_arquivo` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `propriedade_id` (`propriedade_id`),
  CONSTRAINT `talhoes_ibfk_1` FOREIGN KEY (`propriedade_id`) REFERENCES `propriedades` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2194 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `transferencias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `transferencias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `propriedade_id` int(11) NOT NULL,
  `conta_origem_id` int(11) NOT NULL,
  `conta_destino_id` int(11) NOT NULL,
  `valor` decimal(12,2) NOT NULL,
  `data_transferencia` date NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `propriedade_id` (`propriedade_id`),
  KEY `conta_origem_id` (`conta_origem_id`),
  KEY `conta_destino_id` (`conta_destino_id`),
  CONSTRAINT `transferencias_ibfk_1` FOREIGN KEY (`propriedade_id`) REFERENCES `propriedades` (`id`),
  CONSTRAINT `transferencias_ibfk_2` FOREIGN KEY (`conta_origem_id`) REFERENCES `contas` (`id`),
  CONSTRAINT `transferencias_ibfk_3` FOREIGN KEY (`conta_destino_id`) REFERENCES `contas` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=208 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `usuario_grupos_fazendas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuario_grupos_fazendas` (
  `usuario_id` int(11) NOT NULL,
  `grupo_id` int(11) NOT NULL,
  PRIMARY KEY (`usuario_id`,`grupo_id`),
  KEY `grupo_id` (`grupo_id`),
  CONSTRAINT `usuario_grupos_fazendas_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `usuario_grupos_fazendas_ibfk_2` FOREIGN KEY (`grupo_id`) REFERENCES `grupos_fazendas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `usuario_propriedades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuario_propriedades` (
  `usuario_id` int(11) NOT NULL,
  `propriedade_id` int(11) NOT NULL,
  PRIMARY KEY (`usuario_id`,`propriedade_id`),
  KEY `propriedade_id` (`propriedade_id`),
  CONSTRAINT `usuario_propriedades_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `usuario_propriedades_ibfk_2` FOREIGN KEY (`propriedade_id`) REFERENCES `propriedades` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `perfil` enum('administrador','administrador_sistema','gerencia_sistema','colaborador_sistema','gestor_financeiro','gestor_propriedade','gestao','produtor','colaborador','financeiro','visualizador') DEFAULT 'visualizador',
  `ativo` tinyint(1) DEFAULT 1,
  `ultimo_acesso` timestamp NULL DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `sessao_token` varchar(128) DEFAULT NULL,
  `sessao_atualizada_em` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_usuarios_sessao_token` (`sessao_token`)
) ENGINE=InnoDB AUTO_INCREMENT=1499 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;
