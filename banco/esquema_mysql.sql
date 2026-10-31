-- =====================================================================
-- Depósito Nascimento — esquema do banco (MySQL 5.7+ / MariaDB 10.4+ / XAMPP)
-- Aplicado automaticamente na primeira execução (veja db.php), mas também
-- pode ser importado manualmente no phpMyAdmin (crie antes o banco
-- "deposito_nascimento" com collation utf8mb4_unicode_ci).
--
-- status do pedido: 0 recebido · 1 em separação · 2 saiu para entrega
--                   3 entregue · 4 cancelado
-- A tabela "config" é criada por último: ela indica que o esquema terminou.
-- =====================================================================

CREATE TABLE IF NOT EXISTS usuarios (
    id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome      VARCHAR(100) NOT NULL,
    email     VARCHAR(150) NOT NULL,
    hash      VARCHAR(255) NOT NULL,
    criado_em DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuarios_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS produtos (
    id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    categoria     VARCHAR(60)   NOT NULL,
    nome          VARCHAR(100)  NOT NULL,
    descricao     VARCHAR(300)  NOT NULL DEFAULT '',
    estoque       INT UNSIGNED  NOT NULL DEFAULT 0,
    minimo        INT UNSIGNED  NOT NULL DEFAULT 10,
    preco         DECIMAL(10,2) NOT NULL,
    imagem        VARCHAR(255)  NOT NULL DEFAULT '',
    ativo         TINYINT(1)    NOT NULL DEFAULT 1,
    criado_em     DATETIME      NOT NULL,
    atualizado_em DATETIME      NOT NULL,
    PRIMARY KEY (id),
    KEY idx_produtos_ativo_categoria (ativo, categoria),
    CONSTRAINT ck_produtos_preco CHECK (preco >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS formas_pagamento (
    id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome      VARCHAR(40)  NOT NULL,
    descricao VARCHAR(120) NOT NULL DEFAULT '',
    desconto  DECIMAL(5,2) NOT NULL DEFAULT 0,
    parcelas  TINYINT UNSIGNED NOT NULL DEFAULT 1,
    ativo     TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_pagamento_ativo (ativo),
    CONSTRAINT ck_pagamento_desconto CHECK (desconto >= 0 AND desconto <= 50),
    CONSTRAINT ck_pagamento_parcelas CHECK (parcelas >= 1 AND parcelas <= 12)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- O id do pedido é o "número do pedido" (a numeração começa em 1001).
CREATE TABLE IF NOT EXISTS pedidos (
    id             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    usuario_id     INT UNSIGNED  NOT NULL,
    status         TINYINT UNSIGNED NOT NULL DEFAULT 0,
    subtotal       DECIMAL(12,2) NOT NULL,
    desconto_pct   DECIMAL(5,2)  NOT NULL DEFAULT 0,
    total          DECIMAL(12,2) NOT NULL,
    pagamento_id   INT UNSIGNED  NULL,
    pagamento_nome VARCHAR(40)   NULL,
    criado_em      DATETIME      NOT NULL,
    atualizado_em  DATETIME      NOT NULL,
    PRIMARY KEY (id),
    KEY idx_pedidos_usuario_status (usuario_id, status),
    KEY idx_pedidos_status (status),
    CONSTRAINT ck_pedidos_status CHECK (status <= 4),
    CONSTRAINT fk_pedidos_usuario   FOREIGN KEY (usuario_id)   REFERENCES usuarios (id)         ON DELETE CASCADE,
    CONSTRAINT fk_pedidos_pagamento FOREIGN KEY (pagamento_id) REFERENCES formas_pagamento (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=1001;

-- Os itens guardam nome e preço do momento da compra: o histórico é
-- preservado mesmo que o produto seja alterado ou excluído.
CREATE TABLE IF NOT EXISTS pedido_itens (
    id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    pedido_id  INT UNSIGNED  NOT NULL,
    produto_id INT UNSIGNED  NULL,
    nome       VARCHAR(100)  NOT NULL,
    qtd        INT UNSIGNED  NOT NULL,
    preco      DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_itens_pedido (pedido_id),
    KEY idx_itens_produto (produto_id, pedido_id),
    CONSTRAINT ck_itens_qtd CHECK (qtd > 0),
    CONSTRAINT fk_itens_pedido  FOREIGN KEY (pedido_id)  REFERENCES pedidos (id)  ON DELETE CASCADE,
    CONSTRAINT fk_itens_produto FOREIGN KEY (produto_id) REFERENCES produtos (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS movimentos (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    produto_id   INT UNSIGNED NULL,
    produto_nome VARCHAR(100) NOT NULL,
    tipo         VARCHAR(30)  NOT NULL,
    qtd          INT          NOT NULL,
    saldo        INT          NOT NULL,
    motivo       VARCHAR(150) NOT NULL DEFAULT '',
    criado_em    DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_mov_produto_data (produto_id, criado_em),
    CONSTRAINT fk_mov_produto FOREIGN KEY (produto_id) REFERENCES produtos (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reposicoes (
    id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    produto_id   INT UNSIGNED  NULL,
    produto_nome VARCHAR(100)  NOT NULL,
    qtd          INT UNSIGNED  NOT NULL,
    fornecedor   VARCHAR(100)  NOT NULL,
    custo        DECIMAL(10,2) NOT NULL DEFAULT 0,
    previsao     DATE          NULL,
    status       ENUM('Solicitada','Recebida','Cancelada') NOT NULL DEFAULT 'Solicitada',
    criado_em    DATETIME      NOT NULL,
    recebida_em  DATETIME      NULL,
    PRIMARY KEY (id),
    KEY idx_repos_status_produto (status, produto_id),
    CONSTRAINT ck_repos_qtd CHECK (qtd > 0),
    CONSTRAINT fk_repos_produto FOREIGN KEY (produto_id) REFERENCES produtos (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mensagens (
    id        INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    nome      VARCHAR(100)  NOT NULL,
    email     VARCHAR(150)  NOT NULL,
    telefone  VARCHAR(20)   NOT NULL DEFAULT '',
    assunto   VARCHAR(60)   NOT NULL,
    mensagem  TEXT          NOT NULL,
    lida      TINYINT(1)    NOT NULL DEFAULT 0,
    criado_em DATETIME      NOT NULL,
    PRIMARY KEY (id),
    KEY idx_msg_lida_data (lida, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tentativas de login malsucedidas por IP (limite de força bruta).
CREATE TABLE IF NOT EXISTS tentativas_login (
    id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip        VARCHAR(45)  NOT NULL,
    criado_em DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_tent_ip_data (ip, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Unidades reservadas por pedidos ainda não entregues (status 0, 1 e 2).
-- Disponível para venda = produtos.estoque - reservado.
CREATE OR REPLACE VIEW vw_reservado AS
SELECT i.produto_id AS produto_id, CAST(SUM(i.qtd) AS UNSIGNED) AS reservado
FROM pedido_itens i
INNER JOIN pedidos p ON p.id = i.pedido_id
WHERE p.status < 3 AND i.produto_id IS NOT NULL
GROUP BY i.produto_id;

CREATE TABLE IF NOT EXISTS config (
    chave VARCHAR(50)  NOT NULL,
    valor VARCHAR(255) NOT NULL,
    PRIMARY KEY (chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
