# Como publicar o Skinão Motos na Hostinger

Guia passo a passo para o **plano de hospedagem compartilhada da Hostinger**
(Single, Premium ou Business — todos servem). O painel da Hostinger é o **hPanel**.

> **Este site precisa de PHP + MySQL.** Não funciona no Netlify/Vercel.
> Qualquer plano de hospedagem da Hostinger atende.

**Você vai precisar de 2 arquivos** (estão na raiz do projeto no seu computador):

- `deploy-skinao.zip` (~1,6 MB) — o sistema completo
- `uploads-fotos.zip` (~166 MB) — as fotos das motos e clientes

---

## Passo 0 — Conferir a versão do PHP

1. Entre no **hPanel** → seu site → **Avançado → Configuração PHP**.
2. Selecione **PHP 8.2** ou **8.3** e salve.

## Passo 1 — Criar o banco de dados

1. No hPanel: **Bancos de Dados → Gerenciamento**.
2. Em *Criar um Novo Banco de Dados MySQL*, preencha:
   - **Nome do banco**: `skinao` (a Hostinger adiciona um prefixo, ex.: `u123456789_skinao`)
   - **Usuário**: `skinao` (vira `u123456789_skinao`)
   - **Senha**: crie uma senha forte e **anote**
3. Clique em **Criar**. O usuário já sai vinculado ao banco com todas as permissões.

> ⚠️ **Anote o nome COMPLETO** (com o prefixo `u..._`) do banco e do usuário — vai usar no Passo 4.

## Passo 2 — Importar as tabelas e os dados

1. Ainda em **Bancos de Dados → Gerenciamento**, clique em **Entrar no phpMyAdmin** ao lado do banco criado.
2. No phpMyAdmin, selecione o banco na coluna esquerda e abra a aba **Importar**.
3. Importe **nesta ordem** (um de cada vez):
   1. `sql/schema.mysql.sql` — cria as tabelas
   2. `sql/dados-producao.sql` — carrega as 33 motos, fotos e clientes

   *(esses dois arquivos estão dentro do `deploy-skinao.zip`, na pasta `sql/` — extraia antes no seu computador, ou importe depois de extrair no servidor)*

> As tabelas de **Contratos** e **RH** não estão nos arquivos de propósito:
> o sistema cria sozinho no primeiro acesso.

## Passo 3 — Enviar os arquivos

1. No hPanel: **Arquivos → Gerenciador de Arquivos** → entre na pasta **`public_html`**.
2. **Apague** o que estiver lá (a página padrão da Hostinger, ex.: `default.php`).
3. Clique em **Upload** e envie o `deploy-skinao.zip`.
4. Clique com o botão direito no zip → **Extract** (extrair) → para `public_html`.
5. Repita com o `uploads-fotos.zip` (é grande — aguarde o upload completar).
6. Apague os dois `.zip` depois de extrair.

> ⚠️ **Publique na raiz** (`public_html`), não numa subpasta — o site usa caminhos
> absolutos para as imagens (`/uploads/motos/...`).

## Passo 4 — Conectar o site ao banco

1. No Gerenciador de Arquivos, entre em `public_html/includes/`.
2. Renomeie `config.local.php.example` para **`config.local.php`**.
3. Clique com o botão direito → **Edit** e preencha com os dados do Passo 1:

```php
define('DB_DRIVER', 'mysql');
define('DB_HOST', 'localhost');
define('DB_NAME', 'u123456789_skinao');   // nome COMPLETO do banco
define('DB_USER', 'u123456789_skinao');   // usuário COMPLETO
define('DB_PASS', 'sua-senha-do-banco');
```

4. Salve. **Abra seu domínio — o site já deve estar no ar com as motos.**

## Passo 5 — Trocar a senha do administrador ⚠️

O acesso vem com a senha de testes (`admin` / `skinao2026`). **Troque agora:**

1. No Gerenciador de Arquivos, renomeie `scripts/.htaccess` para `.htaccess-off`.
2. Acesse `https://seudominio.com.br/scripts/create-admin.php` e defina a nova senha.
3. **Apague a pasta `scripts/` inteira.**

## Passo 6 — Permissões das pastas de fotos

No Gerenciador de Arquivos, confirme permissão **755** em:

- `uploads/motos` · `uploads/clientes` · `uploads/rh`

(botão direito → *Permissions*). Sem isso o envio de fotos pelo painel falha.

## Passo 7 — HTTPS

Na Hostinger o **SSL é gratuito e instala sozinho** (Segurança → SSL).

1. Confirme que `https://seudominio.com.br` abre com o cadeado.
2. Ative **Forçar HTTPS** no próprio hPanel (Segurança → SSL → Forçar HTTPS) —
   é o jeito mais simples. *(Alternativa: descomentar o bloco `FORÇAR HTTPS` no
   `.htaccess` da raiz.)*

---

## Acessos

- **Site:** `https://seudominio.com.br`
- **Painel administrativo:** `https://seudominio.com.br/admin/`

## Segurança já configurada

Os `.htaccess` incluídos bloqueiam: acesso web a `includes/`, `sql/`, `data/` e
`scripts/`; download de `.sql`, `.sqlite`, `.log`, `.md`; listagem de pastas; e
execução de PHP dentro de `uploads/`.

## Atualizações futuras

Ao enviar uma nova versão do site, **não sobrescreva** `includes/config.local.php`
(é ele que guarda a senha do banco). Todo o resto pode ser substituído.

## Alternativa: publicar e atualizar via GitHub

A Hostinger consegue puxar o código direto do repositório (bom para atualizações):

1. No hPanel: **Avançado → GIT**.
2. Em *Criar um novo repositório*, cole a URL do repositório
   (ex.: `https://github.com/seu-usuario/skinao-motos.git`), branch `main`,
   diretório de instalação em branco (= `public_html`).
3. Clique em **Criar** — a Hostinger clona o código. Para atualizar depois,
   basta dar *push* no GitHub e clicar em **Implantar** (ou ativar o auto-deploy
   com o webhook que o hPanel mostra).

> ⚠️ O repositório **não contém** (de propósito): as fotos (`uploads/`), os dados
> (`sql/dados-producao.sql`) e as credenciais (`config.local.php`). Esses três
> continuam sendo enviados **uma vez** manualmente, como descrito nos Passos 2–4.
> O deploy via Git não apaga esses arquivos nas atualizações.
>
> Se o repositório for **privado** (recomendado), a Hostinger pede para adicionar
> a chave SSH dela no GitHub (o hPanel mostra a chave em Avançado → GIT; cole em
> GitHub → Settings → Deploy keys do repositório) e use a URL SSH
> (`git@github.com:seu-usuario/skinao-motos.git`).

## Se algo der errado

| Sintoma | Causa provável |
|---|---|
| Página em branco | Erro de PHP — hPanel → Avançado → Logs de erro |
| "Access denied for user" | Nome/usuário/senha errados no `config.local.php` — lembre do prefixo `u..._` |
| Site abre sem motos | `dados-producao.sql` não foi importado no phpMyAdmin |
| Fotos não aparecem | `uploads-fotos.zip` não extraído em `public_html`, ou site em subpasta |
| Não consigo enviar fotos pelo painel | Permissão das pastas `uploads/` diferente de 755 |
| Página padrão da Hostinger aparece | O `default.php` da Hostinger não foi apagado do `public_html` |
