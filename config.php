<?php
/**
 * Depósito Nascimento — configurações (XAMPP / MySQL).
 * Incluído pelo index.php. Edite apenas esta área.
 */
defined('DEPOSITO') or exit;

date_default_timezone_set('America/Sao_Paulo');

/* ---------- Banco de dados (padrão do XAMPP: usuário root, sem senha) ---------- */
define('DB_HOST',    getenv('DB_HOST')    ?: '127.0.0.1');
define('DB_PORTA',   (int) (getenv('DB_PORTA') ?: 3306));
define('DB_NOME',    getenv('DB_NOME')    ?: 'deposito_nascimento');   // criado automaticamente se não existir
define('DB_USUARIO', getenv('DB_USUARIO') ?: 'root');
define('DB_SENHA',   getenv('DB_SENHA') !== false ? getenv('DB_SENHA') : '');

/* ---------- Administrador (troque antes de publicar o site) ---------- */
define('ADMIN_EMAIL', getenv('ADMIN_EMAIL') ?: 'admin@exemplo.com.br');
define('ADMIN_SENHA', getenv('ADMIN_SENHA') ?: 'troque-esta-senha');

/* ---------- Comportamento ---------- */
// true = contas novas já nascem com pedidos de exemplo. Em produção, deixe false.
define('MODO_DEMO', true);
// true = mostra a mensagem técnica quando ocorre erro. Em produção, deixe false.
define('DEBUG', true);
