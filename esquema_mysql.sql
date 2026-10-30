-- =====================================================================
-- Depósito Nascimento — esquema do banco de dados (MySQL 5.7+ / MariaDB 10.3+)
-- Crie antes o banco:  CREATE DATABASE deposito CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- O esquema é aplicado automaticamente na primeira execução (veja db.php).
-- status do pedido: 0 recebido · 1 em separação · 2 saiu para entrega
--                   3 entregue · 4 cancelado
-- =====================================================================

CREATE TABLE config (
    chave VARCHAR(50)  NOT NULL PRIMARY KEY,
    valor VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE usuarios (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    nome      VARCHAR(100) NOT NULL,
    email     VARCHAR(150) NOT NULL UNIQUE,
    hash      VARCHAR(255) NOT NULL,
    criado_em DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE produtos (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    categoria     VARCHAR(60)   NOT NULL,
    nome          VARCHAR(100)  NOT NULL,
    descricao     VARCHAR(300)  NOT NULL DEFAULT '',
    estoque       INT           NOT NULL DEFAULT 0 CHECK (estoque >= 0),
    minimo        INT           NOT NULL DEFAULT 10 CHECK (minimo >= 0),
    preco         DECIMAL(10,2) NOT NULL CHECK (preco >= 0),
    imagem        VARCHAR(255)  NOT NULL DEFAULT '',
    ativo         TINYINT(1)    NOT NULL DEFAULT 1,
    criado_em     DATETIME      NOT NULL,
    atualizado_em DATETIME      NOT NULL,
    INDEX idx_produtos_categoria (ativo, categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE formas_pagamento (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    nome      VARCHAR(40)  NOT NULL,
    descricao VARCHAR(120) NOT NULL DEFAULT '',
    desconto  DECIMAL(5,2) NOT NULL DEFAULT 0 CHECK (desconto >= 0 AND desconto <= 50),
    parcelas  INT          NOT NULL DEFAULT 1 CHECK (parcelas >= 1 AND parcelas <= 12),
    ativo     TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- O id do pedido é o "número do pedido" (a numeração começa em 1001).
CREATE TABLE pedidos (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id     INT           NOT NULL,
    status         INT           NOT NULL DEFAULT 0 CHECK (status >= 0 AND status <= 4),
    subtotal       DECIMAL(12,2) NOT NULL,
    desconto_pct   DECIMAL(5,2)  NOT NULL DEFAULT 0,
    total          DECIMAL(12,2) NOT NULL,
    pagamento_id   INT           NULL,
    pagamento_nome VARCHAR(40)   NULL,
    criado_em      DATETIME      NOT NULL,
    atualizado_em  DATETIME      NOT NULL,
    INDEX idx_pedidos_usuario (usuario_id),
    INDEX idx_pedidos_status (status),
    FOREIGN KEY (usuario_id)   REFERENCES usuarios (id)         ON DELETE CASCADE,
    FOREIGN KEY (pagamento_id) REFERENCES formas_pagamento (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1001;

-- Itens guardam nome e preço do momento da compra (histórico preservado
-- mesmo que o produto seja alterado ou excluído).
CREATE TABLE pedido_itens (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id  INT           NOT NULL,
    produto_id INT           NULL,
    nome       VARCHAR(100)  NOT NULL,
    qtd        INT           NOT NULL CHECK (qtd > 0),
    preco      DECIMAL(10,2) NOT NULL,
    INDEX idx_itens_pedido (pedido_id),
    INDEX idx_itens_produto (produto_id),
    FOREIGN KEY (pedido_id)  REFERENCES pedidos (id)  ON DELETE CASCADE,
    FOREIGN KEY (produto_id) REFERENCES produtos (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE movimentos (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    produto_id   INT          NULL,
    produto_nome VARCHAR(100) NOT NULL,
    tipo         VARCHAR(30)  NOT NULL,
    qtd          INT          NOT NULL,
    saldo        INT          NOT NULL,
    motivo       VARCHAR(150) NOT NULL DEFAULT '',
    criado_em    DATETIME     NOT NULL,
    INDEX idx_mov_data (criado_em),
    FOREIGN KEY (produto_id) REFERENCES produtos (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE reposicoes (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    produto_id   INT           NULL,
    produto_nome VARCHAR(100)  NOT NULL,
    qtd          INT           NOT NULL CHECK (qtd > 0),
    fornecedor   VARCHAR(100)  NOT NULL,
    custo        DECIMAL(10,2) NOT NULL DEFAULT 0,
    previsao     DATE          NULL,
    status       VARCHAR(20)   NOT NULL DEFAULT 'Solicitada',
    criado_em    DATETIME      NOT NULL,
    recebida_em  DATETIME      NULL,
    INDEX idx_repos_status (status),
    FOREIGN KEY (produto_id) REFERENCES produtos (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE mensagens (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    nome      VARCHAR(100)  NOT NULL,
    email     VARCHAR(150)  NOT NULL,
    telefone  VARCHAR(20)   NOT NULL DEFAULT '',
    assunto   VARCHAR(60)   NOT NULL,
    mensagem  VARCHAR(2000) NOT NULL,
    lida      TINYINT(1)    NOT NULL DEFAULT 0,
    criado_em DATETIME      NOT NULL,
    INDEX idx_msg_lida (lida, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tentativas de login malsucedidas por IP (limite além da sessão).
CREATE TABLE tentativas_login (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    ip        VARCHAR(45) NOT NULL,
    criado_em DATETIME    NOT NULL,
    INDEX idx_tent_ip (ip, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;