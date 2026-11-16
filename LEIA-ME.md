# Depósito Nascimento

O **Depósito Nascimento** é um sistema web responsivo de e-commerce e gestão administrativa para a Casa de Construção Nascimento LTDA, integrando o catálogo online ao controle de estoque, pedidos, pagamentos, reposições e atendimento.

## 🛠️ Tecnologias Utilizadas

- **Frontend**: HTML5, CSS3, JavaScript.
- **Backend**: PHP.
- **Banco de Dados**: MySQL.
- **Git & GitHub**
- **Visual Studio Code**

## 👥 Equipe de Desenvolvimento

- Arthur Alves (Líder do Projeto, Banco de Dados)
- Gustavo Borges (Back-end)
- Luiz Nascimento (Front-end)
- Nicolas Sebastião (Back-end, Integração)

## 📂 Estrutura do Projeto

```
📦 deposito-nascimento
 ┣ 📂 banco
 ┃ ┗ 📜 esquema_mysql.sql
 ┣ 📂 img
 ┃ ┣ 🖼️ argamassa.jpg
 ┃ ┣ 🖼️ caixa-dagua.jpg
 ┃ ┣ 🖼️ cimento.jpg
 ┃ ┣ 🖼️ fio.jpg
 ┃ ┣ 🖼️ furadeira.jpg
 ┃ ┣ 🖼️ martelo.jpg
 ┃ ┣ 🖼️ tinta.jpg
 ┃ ┗ 🖼️ tubo.jpg
 ┣ 📜 admin_acoes.php
 ┣ 📜 admin_views.php
 ┣ 🎨 catalogo.css
 ┣ ⚙️ config.php
 ┣ 🗄️ db.php
 ┣ 📜 funcoes.php
 ┣ 📜 index.php
 ┣ 📝 LEIA-ME.md
 ┗ 🎨 stleys.css
```

## 📄 Licença

Este projeto está sob a licença GNU GPL v3.0.

Você pode utilizar, modificar e distribuir este projeto livremente, desde que mantenha os créditos originais e utilize a mesma licença em versões derivadas.

Para mais informações, acesse:

https://www.gnu.org/licenses/gpl-3.0.html

### O que cada arquivo faz

| Arquivo | Função |
|---|---|
| `index.php` | Página principal (loja, catálogo e login) |
| `config.php` | Configurações do banco e credenciais padrão do administrador |
| `db.php` | Conexão com o MySQL e criação automática do banco |
| `funcoes.php` | Funções auxiliares usadas pelo sistema |
| `admin_acoes.php` | Ações do painel administrativo (cadastrar, editar, excluir etc.) |
| `admin_views.php` | Telas do painel administrativo |
| `banco/esquema_mysql.sql` | Estrutura das tabelas do banco de dados |
| `img/` | Fotos dos produtos (sem o arquivo, aparece uma imagem padrão) |
| `stleys.css` / `catalogo.css` | Estilos do site e do catálogo |

## 🚀 Como Executar o Projeto

### Pré-requisitos

- [XAMPP](https://www.apachefriends.org/pt_br/index.html) (ou outro ambiente com **Apache + PHP + MySQL**)
- [Git](https://git-scm.com/) (opcional, para clonar o repositório)

### Passo a passo

**1. Baixe o projeto**

Clone o repositório (ou baixe o ZIP pelo GitHub e extraia):

```bash
git clone https://github.com/SEU-USUARIO/deposito-nascimento.git
```

**2. Coloque a pasta no diretório do servidor**

Mova a pasta `deposito-nascimento` para dentro do `htdocs` do XAMPP:

- Windows: `C:\xampp\htdocs\deposito-nascimento`
- Linux: `/opt/lampp/htdocs/deposito-nascimento`

**3. Inicie os serviços**

Abra o **XAMPP Control Panel** e clique em **Start** nos módulos **Apache** e **MySQL**.

**4. Confira as configurações do banco (opcional)**

O `config.php` já usa o padrão do XAMPP (usuário `root`, sem senha). Só altere se o seu MySQL for diferente.

**5. Acesse o site**

Abra no navegador:

```
http://localhost/deposito-nascimento
```

Na **primeira visita**, o sistema cria sozinho o banco `deposito_nascimento`, as tabelas (`banco/esquema_mysql.sql`) e cadastra produtos e formas de pagamento de exemplo. Não é necessário importar nada manualmente.

**6. Confira o banco de dados (opcional)**

Abra o phpMyAdmin: http://localhost/phpmyadmin

**7. Entre no painel do administrador**

Clique no botão **Entrar** e escolha a aba de administrador. Credenciais de teste (definidas em `config.php`):

| Campo | Valor |
|---|---|
| E-mail | `admin@exemplo.com.br` |
| Senha | `troque-esta-senha` |

> ⚠️ Troque essas credenciais no `config.php` antes de usar o sistema em produção.

## 🔄 Recomeçar do Zero

No phpMyAdmin, apague o banco `deposito_nascimento` e recarregue o site. Ele será recriado automaticamente.

## 🖼️ Fotos dos Produtos

As imagens ficam na pasta `img/` (ex.: `img/cimento.jpg`, `img/tinta.jpg`). Se o arquivo não existir, o site exibe uma imagem padrão.

## ❓ Problemas Comuns

- **Página não abre:** confirme que o Apache está ativo (verde) no XAMPP e que a pasta está dentro de `htdocs`.
- **Erro de conexão com o banco:** confirme que o MySQL está ativo e que usuário/senha no `config.php` estão corretos.
- **Porta 80 ou 3306 ocupada:** feche programas que usam essas portas (Skype, outro MySQL) ou altere a porta no XAMPP.