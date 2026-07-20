# Skinão Motos 🏍️

Site e sistema administrativo da **Skinão Motos** (HELIO ARINI MOTOS LTDA — Itápolis/SP):
vitrine pública de motos + painel completo de gestão da loja.

**Stack:** PHP 8 + PDO · MySQL (produção) / SQLite (desenvolvimento) · sem frameworks e sem dependências externas.

## Funcionalidades

| Módulo | O que faz |
|---|---|
| **Vitrine pública** | Catálogo com busca, filtros, galeria por moto, contato via WhatsApp, tema claro/escuro |
| **Painel admin** | CRUD de motos com fotos, controle financeiro (compra/venda/gastos/lucro) e captura completa da venda |
| **Dashboard** | 15 KPIs, 9 gráficos (Chart.js), aging de estoque e central de pendências/alertas |
| **Contratos** | Geração automática de contrato de compra e venda a partir da venda (preview A4, PDF, Word, numeração `CTR-ANO-000001`) |
| **RH** | Funcionários com foto, férias, documentos, histórico salarial e dashboard próprio |
| **Excel** | Exportação `.xlsx` do fechamento mensal, gerada sem bibliotecas externas |

## Estrutura

```
├── index.php          # vitrine pública
├── admin/             # painel administrativo (login, motos, contratos, rh, exportar)
├── includes/          # config, banco (PDO + migrações idempotentes), helpers, módulos
├── assets/            # css, js, imagens e vídeo da identidade visual
├── sql/               # schema MySQL para produção
├── scripts/           # utilitários: criar admin, exportar dados, empacotar deploy
├── uploads/           # fotos das motos/clientes e documentos do RH (fora do git)
└── data/              # banco SQLite de desenvolvimento (fora do git)
```

## Rodar localmente

Só precisa de PHP 8+ (com `pdo_sqlite`, `gd` e `fileinfo`):

```bash
php -S localhost:4173 -t .
```

Abra `http://localhost:4173`. O banco SQLite é criado sozinho em `data/dev.sqlite`
com o schema completo — as tabelas e colunas novas são criadas automaticamente
(migrações idempotentes em `includes/db.php`).

Para criar o usuário do painel: `php scripts/create-admin.php`.
Painel em `http://localhost:4173/admin/`.

## Publicar na Hostinger

O guia completo, passo a passo pelo hPanel, está em **[DEPLOY.md](DEPLOY.md)**.

Resumo: os pacotes de produção são gerados por `scripts/empacotar-deploy.ps1`
(gera `deploy-skinao.zip` + `uploads-fotos.zip`) e os dados atuais são exportados
por `php scripts/exportar-mysql.php` (gera `sql/dados-producao.sql`).

> ⚠️ **Três coisas nunca entram neste repositório** (estão no `.gitignore`):
> o banco local (`data/*.sqlite`), o export de dados (`sql/dados-producao.sql`)
> e as credenciais (`includes/config.local.php`) — todos contêm dados reais
> de clientes ou senhas. Os uploads (fotos) também ficam fora pelo tamanho.
