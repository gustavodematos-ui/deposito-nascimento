# Depósito Nascimento — instalação no XAMPP

1. Copie a pasta `deposito` para `C:\xampp\htdocs\`.
2. No Painel do XAMPP, clique em **Start** em **Apache** e em **MySQL**.
3. Abra **http://localhost/deposito/** (o `index.php` é a página principal).

Na primeira visita o site cria sozinho o banco `deposito_nascimento`, as tabelas
(`banco/esquema_mysql.sql`) e cadastra produtos e formas de pagamento de exemplo.
Para conferir, abra o phpMyAdmin: http://localhost/phpmyadmin

- Painel do administrador: botão **Entrar** → aba de administrador.
  Padrão de teste (em `config.php`): `admin@exemplo.com.br` / `troque-esta-senha`.
- Banco: `config.php` usa o padrão do XAMPP (usuário `root`, sem senha).
- Para recomeçar do zero: no phpMyAdmin, apague o banco `deposito_nascimento` e recarregue o site.
- Se existir uma pasta `dados/` com os JSON antigos, eles são importados na primeira execução.
- Fotos dos produtos: `img/cimento.jpg`, `img/tinta.jpg` etc. Sem o arquivo, aparece uma imagem padrão.

## Arquivos
index.php · config.php · db.php · funcoes.php · admin_acoes.php · admin_views.php ·
banco/esquema_mysql.sql · stleys.css · catalogo.css
