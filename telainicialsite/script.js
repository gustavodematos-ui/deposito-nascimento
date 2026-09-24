// Base de dados de Produtos
const products = [
    {
        id: 1,
        name: "Cimento CP II-E-32 (50kg)",
        category: "hidraulica",
        price: 36.90,
        image: "imagens/cimento.jpg"},
    {
        id: 2,
        name: "Tinta Acrílica Fosca Branca 18L",
        category: "tintas",
        price: 249.90,
        image: "imagens/tinta.jpg"
    },
    {
        id: 3,
        name: "Furadeira de Impacto 600W 1/2\"",
        category: "ferramentas",
        price: 219.00,
        image: "imagens/furadeira.png"
    },
    {
        id: 4,
        name: "Kit Tubo e Conexões PVC 3/4\"",
        category: "hidraulica",
        price: 89.50,
        image: "imagens/kit.jpg"},
    {
        id: 5,
        name: "Piso Cerâmico Retificado 60x60cm (m²)",
        category: "pisos",
        price: 49.90,
        image: "imagens/piso.jpg"
    },
    {
        id: 6,
        name: "Disjuntor Termomagnético Monofásico 20A",
        category: "eletrica",
        price: 18.50,
        image: "imagens/disjuntor.jpg"
    },
    {
        id: 7,
        name: "Impermeabilizante Mantatec 4kg",
        category: "tintas",
        price: 115.00,
        image: "imagens/manta.jpg"
    },
    {
        id: 8,
        name: "Jogo de Chaves de Fenda e Phillips (6 Peças)",
        category: "ferramentas",
        price: 45.00,
        image: "imagens/jogo1.jpg"
    },
    {
        id: 9,
        name: "Cabo Flexível 2,5mm² (Rolo 100m)",
        category: "eletrica",
        price: 139.90,
        image: "imagens/rolo.jpg"
    },

    {
        id: 11, // Use um número que não esteja repetido
        name: "Martelo Unha 29mm com Cabo Emborrachado",
        category: "ferramentas", // Precisa ser exatamente igual ao data-category do menu
        price: 59.90,
        image: "imagens/martelo.jpg"
    },
    {
        id: 12,
        name: "Torneira Para Pia de Cozinha Bica Móvel",
        category: "hidraulica",
        price: 89.90,
        image: "imagens/torneira.jpg"
    },

    {
        id: 10,
        name: "Argamassa AC-III Externa (20kg)",
        category: "pisos",
        price: 32.00,
        image: "imagens/argamassa.jpg"
    }
];

let cart = [];
let currentCategory = 'todos';

// Elementos do DOM
const productGrid = document.getElementById('productGrid');
const searchInput = document.getElementById('searchInput');
const catButtons = document.querySelectorAll('.cat-btn');
const cartBtn = document.getElementById('cartBtn');
const cartDrawer = document.getElementById('cartDrawer');
const cartOverlay = document.getElementById('cartOverlay');
const closeCart = document.getElementById('closeCart');
const cartItemsContainer = document.getElementById('cartItems');
const cartCount = document.getElementById('cartCount');
const cartTotalPrice = document.getElementById('cartTotalPrice');
const checkoutBtn = document.getElementById('checkoutBtn');
const successModal = document.getElementById('successModal');
const closeModalBtn = document.getElementById('closeModalBtn');

// Inicialização
document.addEventListener('DOMContentLoaded', () => {
    displayProducts(products);
    setupEventListeners();
});

// Renderizar Produtos
function displayProducts(items) {
    productGrid.innerHTML = '';
    
    if(items.length === 0) {
        productGrid.innerHTML = `<p style="grid-column: 1/-1; text-align: center; color: var(--gray); padding: 40px;">Nenhum produto encontrado.</p>`;
        return;
    }

    items.forEach(product => {
        const card = document.createElement('div');
        card.className = 'product-card';
        card.innerHTML = `
            <img src="${product.image}" alt="${product.name}" class="product-image">
            <div class="product-info">
                <div>
                    <span class="product-category">${formatCategoryName(product.category)}</span>
                    <h3 class="product-name">${product.name}</h3>
                </div>
                <div class="product-footer">
                    <span class="product-price">R$ ${product.price.toFixed(2).replace('.', ',')}</span>
                    <button class="btn-add" onclick="addToCart(${product.id})">
                        <i class="fa-solid fa-plus"></i> Adicionar
                    </button>
                </div>
            </div>
        `;
        productGrid.appendChild(card);
    });
}

function formatCategoryName(cat) {
    const map = {
        ferramentas: 'Ferramentas',
        eletrica: 'Elétrica',
        hidraulica: 'Hidráulica',
        pisos: 'Pisos e Revestimentos',
        tintas: 'Tintas'
    };
    return map[cat] || cat;
}

// Configurar Ouvintes de Eventos
function setupEventListeners() {
    // Busca em tempo real
    searchInput.addEventListener('input', (e) => {
        const term = e.target.value.toLowerCase();
        filterProducts(term, currentCategory);
    });

    // Filtros por Categoria
    catButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            catButtons.forEach(b => b.classList.remove('active'));
            e.target.classList.add('active');
            currentCategory = e.target.getAttribute('data-category');
            filterProducts(searchInput.value.toLowerCase(), currentCategory);
        });
    });

    // Abrir/Fechar Carrinho
    cartBtn.addEventListener('click', toggleCart);
    closeCart.addEventListener('click', toggleCart);
    cartOverlay.addEventListener('click', toggleCart);

    // Finalizar Pedido
    checkoutBtn.addEventListener('click', () => {
        toggleCart();
        successModal.classList.add('open');
        cart = [];
        updateCartUI();
    });

    closeModalBtn.addEventListener('click', () => {
        successModal.classList.remove('open');
    });
}

// Filtragem cruzada (Busca + Categoria)
function filterProducts(searchTerm, category) {
    const filtered = products.filter(product => {
        const matchesSearch = product.name.toLowerCase().includes(searchTerm);
        const matchesCategory = category === 'todos' || product.category === category;
        return matchesSearch && matchesCategory;
    });
    displayProducts(filtered);
}

// Gerenciamento do Carrinho
function toggleCart() {
    cartDrawer.classList.toggle('open');
    cartOverlay.classList.toggle('open');
}

function addToCart(productId) {
    const product = products.find(p => p.id === productId);
    const existingItem = cart.find(item => item.id === productId);

    if (existingItem) {
        existingItem.quantity += 1;
    } else {
        cart.push({ ...product, quantity: 1 });
    }

    updateCartUI();
}

function updateQuantity(productId, change) {
    const item = cart.find(i => i.id === productId);
    if(item) {
        item.quantity += change;
        if(item.quantity <= 0) {
            cart = cart.filter(i => i.id !== productId);
        }
    }
    updateCartUI();
}

function updateCartUI() {
    cartItemsContainer.innerHTML = '';
    
    if (cart.length === 0) {
        cartItemsContainer.innerHTML = `<p class="empty-cart-msg">Seu carrinho está vazio.</p>`;
        cartCount.textContent = '0';
        cartTotalPrice.textContent = 'R$ 0,00';
        checkoutBtn.disabled = true;
        return;
    }

    let totalItems = 0;
    let totalPrice = 0;

    cart.forEach(item => {
        totalItems += item.quantity;
        totalPrice += item.price * item.quantity;

        const cartItemEl = document.createElement('div');
        cartItemEl.className = 'cart-item';
        cartItemEl.innerHTML = `
            <div class="cart-item-info">
                <h4>${item.name}</h4>
                <p>R$ ${(item.price * item.quantity).toFixed(2).replace('.', ',')}</p>
            </div>
            <div class="cart-item-actions">
                <button onclick="updateQuantity(${item.id}, -1)">-</button>
                <span>${item.quantity}</span>
                <button onclick="updateQuantity(${item.id}, 1)">+</button>
            </div>
        `;
        cartItemsContainer.appendChild(cartItemEl);
    });

    cartCount.textContent = totalItems;
    cartTotalPrice.textContent = `R$ ${totalPrice.toFixed(2).replace('.', ',')}`;
    checkoutBtn.disabled = false;
}

// Função para abrir e fechar as perguntas do FAQ
function toggleFaq(element) {
    // Fecha todos os outros itens abertos
    const allItems = document.querySelectorAll('.faq-item');
    allItems.forEach(item => {
        if (item !== element) {
            item.classList.remove('active');
        }
    });

    // Alterna o item clicado
    element.classList.toggle('active');
}

