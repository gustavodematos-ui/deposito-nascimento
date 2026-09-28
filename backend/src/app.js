const API_URL = "http://localhost:3000/api";

document.addEventListener("DOMContentLoaded", () => {
  const path = window.location.pathname;

  if (path.includes("cadastro.html")) initCadastro();
  if (path.includes("produtos.html")) carregarProdutos();
  if (path.includes("estoque.html")) carregarEstoque();
  if (path.includes("forma-pagamento.html")) carregarFormasPagamento();
  if (path.includes("reposicao.html")) carregarReposicao();
  if (path.includes("index.html")) carregarDashboardPedidos();
});

// 1. Cadastro de Usuário
function initCadastro() {
  const form = document.querySelector("form");
  if (!form) return;

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    const nome = document.getElementById("nome").value;
    const email = document.getElementById("email").value;
    const senha = document.getElementById("senha").value;
    const confirmarSenha = document.getElementById("confirmar-senha").value;

    if (senha !== confirmarSenha) {
      alert("As senhas não coincidem.");
      return;
    }

    try {
      const res = await fetch(`${API_URL}/auth/cadastro`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ nome, email, senha }),
      });

      if (res.ok) {
        alert("Cadastro realizado com sucesso!");
        window.location.href = "login.html";
      } else {
        const err = await res.json();
        alert(err.erro || "Falha ao cadastrar.");
      }
    } catch (error) {
      console.error(error);
    }
  });
}

// 2. Carregar Produtos
async function carregarProdutos() {
  const tbody = document.querySelector("table tbody");
  if (!tbody) return;

  try {
    const res = await fetch(`${API_URL}/produtos`);
    const produtos = await res.json();

    tbody.innerHTML = produtos
      .map(
        (p) => `
      <tr>
        <td class="td-bold">${p.codigo}</td>
        <td>${p.nome}</td>
        <td><span class="badge badge-blue">${p.categoria}</span></td>
        <td>R$ ${parseFloat(p.preco).toLocaleString("pt-BR", { minimumFractionDigits: 2 })}</td>
        <td>${p.garantia}</td>
        <td>
          <div class="actions">
            <button class="btn-primary btn-sm">Editar</button>
            <button class="btn-primary btn-sm">Excluir</button>
          </div>
        </td>
      </tr>
    `,
      )
      .join("");
  } catch (err) {
    console.error("Erro ao buscar produtos:", err);
  }
}

// 3. Carregar Estoque
async function carregarEstoque() {
  const tbody = document.querySelector("table tbody");
  if (!tbody) return;

  try {
    const res = await fetch(`${API_URL}/estoque`);
    const itens = await res.json();

    tbody.innerHTML = itens
      .map((item) => {
        const isLow = item.quantidade <= item.minimoAlerta;
        return `
        <tr>
          <td class="td-bold">${item.produto.codigo}</td>
          <td>${item.produto.nome}</td>
          <td>${item.localizacao}</td>
          <td>${item.quantidade} un.</td>
          <td>
            <span class="stock-badge ${isLow ? "low" : "high"}">
              ${isLow ? "Alerta Baixo" : "Normal"}
            </span>
          </td>
          <td><button class="btn-primary btn-sm">Ajustar Qtd</button></td>
        </tr>
      `;
      })
      .join("");
  } catch (err) {
    console.error("Erro ao buscar estoque:", err);
  }
}

// 4. Carregar Formas de Pagamento
async function carregarFormasPagamento() {
  const tbody = document.querySelector("table tbody");
  if (!tbody) return;

  try {
    const res = await fetch(`${API_URL}/formas-pagamento`);
    const formas = await res.json();

    tbody.innerHTML = formas
      .map(
        (f) => `
      <tr>
        <td class="td-bold">${f.metodo}</td>
        <td>${parseFloat(f.taxaOperacao).toFixed(2)}%</td>
        <td>${f.prazoLiberacao}</td>
        <td>
          <span class="badge ${f.ativo ? "badge-green" : "badge-red"}">
            ${f.ativo ? "Ativo" : "Inativo"}
          </span>
        </td>
        <td><button class="btn-primary btn-sm">Configurar</button></td>
      </tr>
    `,
      )
      .join("");
  } catch (err) {
    console.error("Erro ao buscar formas de pagamento:", err);
  }
}

// 5. Carregar Reposição
async function carregarReposicao() {
  const tbody = document.querySelector("table tbody");
  if (!tbody) return;

  try {
    const res = await fetch(`${API_URL}/reposicao`);
    const ordens = await res.json();

    tbody.innerHTML = ordens
      .map(
        (r) => `
      <tr>
        <td class="td-bold">${r.codigo}</td>
        <td>${r.fornecedor}</td>
        <td>${r.itens[0]?.produto?.nome || "Diversos"}</td>
        <td>${r.itens[0]?.quantidade || 0} un.</td>
        <td>R$ ${parseFloat(r.precoTotalEstimado).toLocaleString("pt-BR", { minimumFractionDigits: 2 })}</td>
        <td><span class="badge badge-orange">${r.status}</span></td>
        <td><button class="btn-primary btn-sm">Ver Detalhes</button></td>
      </tr>
    `,
      )
      .join("");
  } catch (err) {
    console.error("Erro ao buscar ordens de reposição:", err);
  }
}

// 6. Carregar Dashboard / Pedidos
async function carregarDashboardPedidos() {
  const tbody = document.querySelector("table tbody");
  if (!tbody) return;

  try {
    const res = await fetch(`${API_URL}/pedidos`);
    const pedidos = await res.json();

    tbody.innerHTML = pedidos
      .map(
        (p) => `
      <tr>
        <td>#${String(p.numero).padStart(3, "0")}</td>
        <td>${p.usuario.nome}</td>
        <td>R$ ${parseFloat(p.valorTotal).toLocaleString("pt-BR", { minimumFractionDigits: 2 })}</td>
        <td><span class="badge badge-blue">${p.status}</span></td>
        <td>
          <button class="btn-primary btn-sm">Ver detalhes</button>
          <button class="btn-primary btn-sm">Atualizar status</button>
        </td>
      </tr>
    `,
      )
      .join("");
  } catch (err) {
    console.error("Erro ao buscar pedidos:", err);
  }
}
