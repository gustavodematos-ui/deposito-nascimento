# Depósito Nascimento

O **Depósito Nascimento** é um sistema web responsivo de e-commerce e gestão administrativa para a Casa de Construção Nascimento LTDA, integrando o catálogo online ao controle de estoque, pedidos, pagamentos, reposições e atendimento

## Tecnologias Utilizadas

- **Frontend**: HTML5, CSS3, JavaScript.
- **Backend**: PHP.
- **Banco de Dados**: MySQL.

## Equipe de Desenvolvimento
- Arthur Alves (Líder do Projeto, Banco de Dados)
- Gustavo Borges (Back-end)
- Luiz Nascimento (Front-end)
- Nicolas Sebastião (Back-end, Integração)

Na primeira visita o site cria sozinho o banco `deposito_nascimento`, as tabelas
(`banco/esquema_mysql.sql`) e cadastra produtos e formas de pagamento de exemplo.
Para conferir, abra o phpMyAdmin: http://localhost/phpmyadmin

- Painel do administrador: botão **Entrar** → aba de administrador.
  Padrão de teste (em `config.php`): `admin@exemplo.com.br` / `troque-esta-senha`.
- Banco: `config.php` usa o padrão do XAMPP (usuário `root`, sem senha).
- Para recomeçar do zero: no phpMyAdmin, apague o banco `deposito_nascimento` e recarregue o site.
- Fotos dos produtos: `img/cimento.jpg`, `img/tinta.jpg` etc. Sem o arquivo, aparece uma imagem padrão.

## Arquivos
index.php · config.php · db.php · funcoes.php · admin_acoes.php · admin_views.php ·
banco/esquema_mysql.sql · stleys.css · catalogo.css
