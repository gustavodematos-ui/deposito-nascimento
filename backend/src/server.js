const express = require("express");
const cors = require("cors");
const bcrypt = require("bcrypt");
const jwt = require("jsonwebtoken");
const { PrismaClient } = require("@prisma/client");

const app = express();
const prisma = new PrismaClient();
const JWT_SECRET = process.env.JWT_SECRET || "secreto_deposito_nascimento";

app.use(cors());
app.use(express.json());

// Middleware de Autenticação
const autenticar = (req, res, next) => {
  const token = req.headers.authorization?.split(" ")[1];
  if (!token) return res.status(401).json({ erro: "Acesso negado." });

  try {
    const usuario = jwt.verify(token, JWT_SECRET);
    req.usuario = usuario;
    next();
  } catch (err) {
    res.status(403).json({ erro: "Token inválido." });
  }
};

// --- AUTENTICAÇÃO E USUÁRIOS ---
app.post("/api/auth/cadastro", async (req, res) => {
  try {
    const { nome, email, senha } = req.body;
    const senhaHash = await bcrypt.hash(senha, 10);

    const novoUsuario = await prisma.usuario.create({
      data: { nome, email, senha: senhaHash },
    });

    res
      .status(201)
      .json({
        id: novoUsuario.id,
        nome: novoUsuario.nome,
        email: novoUsuario.email,
      });
  } catch (error) {
    res
      .status(400)
      .json({ erro: "Erro ao cadastrar usuário ou e-mail já existente." });
  }
});

app.post("/api/auth/login", async (req, res) => {
  const { email, senha } = req.body;
  const usuario = await prisma.usuario.findUnique({ where: { email } });

  if (!usuario || !(await bcrypt.compare(senha, usuario.senha))) {
    return res.status(401).json({ erro: "Credenciais inválidas." });
  }

  const token = jwt.sign({ id: usuario.id, role: usuario.role }, JWT_SECRET, {
    expiresIn: "1d",
  });
  res.json({
    token,
    usuario: { id: usuario.id, nome: usuario.nome, role: usuario.role },
  });
});

// --- PRODUTOS ---
app.get("/api/produtos", async (req, res) => {
  const produtos = await prisma.produto.findMany({
    include: { estoque: true },
  });
  res.json(produtos);
});

app.post("/api/produtos", async (req, res) => {
  const { codigo, nome, categoria, preco, garantia, localizacao, quantidade } =
    req.body;

  const produto = await prisma.produto.create({
    data: {
      codigo,
      nome,
      categoria,
      preco,
      garantia,
      estoque: {
        create: { localizacao, quantidade: parseInt(quantidade) },
      },
    },
    include: { estoque: true },
  });

  res.status(201).json(produto);
});

// --- ESTOQUE ---
app.get("/api/estoque", async (req, res) => {
  const estoques = await prisma.estoque.findMany({
    include: { produto: true },
  });
  res.json(estoques);
});

app.patch("/api/estoque/:id", async (req, res) => {
  const { id } = req.params;
  const { quantidade } = req.body;

  const estoqueAtualizado = await prisma.estoque.update({
    where: { id },
    data: { quantidade: parseInt(quantidade) },
  });

  res.json(estoqueAtualizado);
});

// --- FORMAS DE PAGAMENTO ---
app.get("/api/formas-pagamento", async (req, res) => {
  const formas = await prisma.formaPagamento.findMany();
  res.json(formas);
});

app.post("/api/formas-pagamento", async (req, res) => {
  const { metodo, taxaOperacao, prazoLiberacao, ativo } = req.body;
  const forma = await prisma.formaPagamento.create({
    data: { metodo, taxaOperacao, prazoLiberacao, ativo },
  });
  res.status(201).json(forma);
});

// --- PEDIDOS E VENDAS ---
app.get("/api/pedidos", async (req, res) => {
  const pedidos = await prisma.pedido.findMany({
    include: {
      usuario: true,
      formaPagamento: true,
      itens: { include: { produto: true } },
    },
    orderBy: { createdAt: "desc" },
  });
  res.json(pedidos);
});

app.get("/api/pedidos/meus", autenticar, async (req, res) => {
  const pedidos = await prisma.pedido.findMany({
    where: { usuarioId: req.usuario.id },
    include: { formaPagamento: true, itens: { include: { produto: true } } },
  });
  res.json(pedidos);
});

// --- REPOSIÇÃO ---
app.get("/api/reposicao", async (req, res) => {
  const reposicoes = await prisma.reposicao.findMany({
    include: { itens: { include: { produto: true } } },
    orderBy: { createdAt: "desc" },
  });
  res.json(reposicoes);
});

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => console.log(`Servidor rodando na porta ${PORT}`));
