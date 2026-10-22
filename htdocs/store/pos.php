<?php
session_start();
require_once '../auth_check.php';

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'vendeur' && $_SESSION['role'] !== 'superadmin')) {
    header("Location: ../login_page.php");
    exit();
}

require '../config/db.php';

if (!isset($_SESSION['store_id'])) { 
    header("Location: ../login_page.php"); 
    exit(); 
}

$current_store_id = $_SESSION['store_id'];

// 1. Récupération des produits en stock (AJOUT de min_threshold)
$query = "
    SELECT 
        p.id, p.name, p.variant, p.sku, p.selling_price, 
        p.unit_type, p.category_id, 
        i.quantity, i.min_threshold 
    FROM products p 
    JOIN inventory i ON p.id = i.product_id 
    WHERE i.store_id = ? AND i.quantity > 0
";
$stmt = $pdo->prepare($query);
$stmt->execute([$current_store_id]);
$available_products = $stmt->fetchAll();

// 2. Récupération des catégories (Uniquement celles qui ont du stock)
$cat_query = "
    SELECT DISTINCT c.id, c.name 
    FROM categories c
    JOIN products p ON p.category_id = c.id
    JOIN inventory i ON i.product_id = p.id
    WHERE i.store_id = ? AND i.quantity > 0
";
$stmt_cat = $pdo->prepare($cat_query);
$stmt_cat->execute([$current_store_id]);
$categories = $stmt_cat->fetchAll();

$username = $_SESSION['username'];
$store_name = $_SESSION['store_name'] ?? 'Boutique';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Caisse MultiPOS - Algérie</title>
<style>
    /* RESET & BASE */
    body { 
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
        margin: 0; background: #f4f7f6; height: 100vh; 
        display: flex; flex-direction: column; overflow: hidden; 
    }
    
    header { 
        background: #2c3e50; color: white; padding: 10px 20px; 
        display: flex; justify-content: space-between; align-items: center; 
        box-shadow: 0 2px 5px rgba(0,0,0,0.1); flex-shrink: 0;
    }

    /* LAYOUT RESPONSIVE */
    .pos-container {
        display: flex; flex-direction: row; gap: 15px; padding: 15px;
        height: calc(100vh - 70px); box-sizing: border-box;
    }

    .catalog-section { flex: 1.2; background: white; padding: 15px; border-radius: 8px; display: flex; flex-direction: column; overflow: hidden; }
    .scanner-section { flex: 2; background: white; padding: 15px; border-radius: 8px; display: flex; flex-direction: column; overflow: hidden; }
    .summary-section { flex: 0.8; background: #2c3e50; color: white; padding: 25px; border-radius: 8px; display: flex; flex-direction: column; justify-content: space-between; }

    /* CATALOGUE */
    .filter-bar { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 10px; margin-bottom: 10px; }
    .btn-cat { background: #ebedef; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; font-weight: bold; color: #2c3e50; white-space: nowrap; }
    .btn-cat.active { background: #2ecc71; color: white; }
    .grid-container { flex-grow: 1; overflow-y: auto; }
    .product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 12px; }
    .product-card { border: 1px solid #eee; padding: 12px; cursor: pointer; background: #fff; border-radius: 8px; text-align: center; transition: 0.2s; }
    .product-card:hover { border-color: #3498db; transform: scale(1.02); }

    /* PANIER TABLE */
    .scanner-header { display: flex; gap: 10px; margin-bottom: 15px; }
    #barcode-input { flex: 3; padding: 12px; font-size: 1.1em; border: 2px solid #3498db; border-radius: 5px; outline: none; }
    .btn-manual { flex: 1; background: #9b59b6; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; }
    
    .table-wrapper { flex-grow: 1; overflow-y: auto; margin-bottom: 10px; border: 1px solid #eee; }
    .cart-table { width: 100%; border-collapse: collapse; }
    .cart-table th { background: #f8f9fa; padding: 12px; border-bottom: 2px solid #dee2e6; color: #7f8c8d; position: sticky; top: 0; }
    .cart-table td { padding: 12px; border-bottom: 1px solid #eee; text-align: center; }

    /* BOUTONS ACTIONS */
    .btn-hold-action { width: 100%; padding: 15px; background: #f39c12 !important; color: white !important; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 1.1em; }
    #total-display { font-size: 3.5em; text-align: center; margin: 20px 0; color: #2ecc71; font-weight: bold; }
    .btn-pay { width: 100%; padding: 20px; background: #2ecc71; color: white; border: none; font-size: 1.3em; font-weight: bold; cursor: pointer; border-radius: 5px; text-transform: uppercase; }

    /* CALCULATRICE & MODALS */
    .modal-calc, .modal-numpad { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
    .calc-container, .numpad-content { background: white; padding: 20px; border-radius: 12px; width: 300px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); }
    .calc-screen, .numpad-display { width: 100%; height: 60px; font-size: 2em; text-align: right; margin-bottom: 15px; padding: 10px; border: 2px solid #eee; border-radius: 8px; background: #f9f9f9; box-sizing: border-box; font-weight: bold; }
    
    .calc-buttons, .numpad-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
    .numpad-grid { grid-template-columns: repeat(3, 1fr); }
    
    .calc-btn, .numpad-btn { padding: 18px 5px; font-weight: bold; border: 1px solid #ddd; border-radius: 8px; cursor: pointer; background: #fff; font-size: 1.2em; }
    .calc-btn.clr, .numpad-btn.clear { background: #e74c3c !important; color: white; }
    .calc-btn.op { background: #f39c12 !important; color: white; }
    .calc-btn.res, .numpad-btn.confirm { background: #27ae60 !important; color: white; }

    /* RESPONSIVE BREAKPOINT */
    @media (max-width: 1100px) {
        body { overflow-y: auto; height: auto; }
        .pos-container { flex-direction: column; height: auto; }
        header { flex-direction: column; gap: 10px; text-align: center; }
    }
</style>
</head>
<body>

<header>
    <div style="display: flex; align-items: center; gap: 15px;">
        <span>📍 Boutique : <b><?= htmlspecialchars($store_name); ?></b> | 👤 Vendeur : <b><?= htmlspecialchars($username); ?></b></span>
        <span id="digital-clock" style="background: #1a252f; padding: 4px 10px; border-radius: 5px; color: #2ecc71; font-family: monospace; font-weight: bold;">00:00:00</span>
    </div>
    <div style="display: flex; gap: 10px;">
        <button onclick="toggleCalculator()" style="background: #34495e; color: white; border: 1px solid #5d6d7e; padding: 7px 12px; border-radius: 4px; cursor: pointer; font-weight: bold;">🧮 Calculatrice</button>
        <a href="cloture.php" style="background: #e67e22; color: white; text-decoration: none; padding: 8px 15px; border-radius: 4px; font-weight: bold;">Clôture caisse 🏁</a>
        <a href="../logout.php" style="background: #e74c3c; color: white; text-decoration: none; padding: 8px 15px; border-radius: 4px; font-weight: bold;">Déconnexion 🔒</a>
    </div>
</header>

<div class="pos-container">
    <div class="catalog-section">
        <h3 style="margin: 0 0 10px 0;">🛍️ Catalogue</h3>
        <div class="filter-bar">
            <button class="btn-cat active" onclick="filtrerCat('all', this)">Tous</button>
            <?php foreach ($categories as $cat): ?>
                <button class="btn-cat" onclick="filtrerCat(<?= $cat['id'] ?>, this)"><?= htmlspecialchars($cat['name']) ?></button>
            <?php endforeach; ?>
        </div>
        <div class="grid-container">
            <div class="product-grid" id="product-list">
                <?php foreach ($available_products as $product): ?>
                    <div class="product-card" data-category="<?= $product['category_id'] ?>" onclick='addToCart(<?= json_encode($product) ?>)'>
                        <strong style="font-size: 0.95em; display: block; margin-bottom: 5px;"><?= htmlspecialchars($product['name']) ?></strong>
                        <small style="color: #7f8c8d;"><?= htmlspecialchars($product['variant'] ?? '') ?></small>
                        <div style="color: #27ae60; font-weight: bold; font-size: 1.1em; margin: 8px 0;"><?= number_format($product['selling_price'], 2) ?> DA</div>
                        <div style="font-size: 0.8em; color: #34495e; background: #f8f9fa; border-radius: 5px; padding: 5px;">Stock: <b><?= (float)$product['quantity'] ?></b></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="scanner-section">
        <div class="scanner-header">
            <input type="text" id="barcode-input" placeholder="Scannez l'article ou SKU..." autofocus>
            <button class="btn-manual" onclick="ajouterArticleManuel()">+ Article Manuel</button>
        </div>
        <div class="table-wrapper">
            <table class="cart-table" id="cart">
                <thead>
                    <tr>
                        <th style="text-align:left;">Produit</th>
                        <th>Prix Unit.</th>
                        <th>Qté</th>
                        <th>Sous-total</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <div class="hold-section">
            <button type="button" onclick="mettreEnAttente()" class="btn-hold-action">⏳ Mettre en attente (F9)</button>
            <div id="pending-orders-container" style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px;"></div>
        </div>
    </div>

    <div class="summary-section">
        <div>
            <h2 style="margin-top:0; border-bottom: 1px solid #555; padding-bottom: 10px; text-align: center;">TOTAL</h2>
            <div id="total-display">0,00 DZD</div>
        </div>
        <button onclick="validerLaVente()" class="btn-pay">Encaisser [F10]</button>
    </div>
</div>

<div id="calc-modal" class="modal-calc">
    <div class="calc-container">
        <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
            <b style="color:#333">Calculatrice</b>
            <button onclick="toggleCalculator()" style="border:none; background:none; cursor:pointer; font-size:1.5em;">&times;</button>
        </div>
        <input type="text" id="calc-display" class="calc-screen" readonly>
        <div class="calc-buttons">
            <button class="calc-btn" onclick="cInput('7')">7</button>
            <button class="calc-btn" onclick="cInput('8')">8</button>
            <button class="calc-btn" onclick="cInput('9')">9</button>
            <button class="calc-btn op" onclick="cInput('/')">÷</button>
            <button class="calc-btn" onclick="cInput('4')">4</button>
            <button class="calc-btn" onclick="cInput('5')">5</button>
            <button class="calc-btn" onclick="cInput('6')">6</button>
            <button class="calc-btn op" onclick="cInput('*')">×</button>
            <button class="calc-btn" onclick="cInput('1')">1</button>
            <button class="calc-btn" onclick="cInput('2')">2</button>
            <button class="calc-btn" onclick="cInput('3')">3</button>
            <button class="calc-btn op" onclick="cInput('-')">-</button>
            <button class="calc-btn" onclick="cInput('0')">0</button>
            <button class="calc-btn" onclick="cInput('.')">.</button>
            <button class="calc-btn clr" onclick="cClear()">C</button>
            <button class="calc-btn op" onclick="cInput('+')">+</button>
            <button class="calc-btn" style="grid-column: span 2; background: #95a5a6; color: white;" onclick="toggleCalculator()">Fermer</button>
            <button class="calc-btn res" style="grid-column: span 2;" onclick="cResult()">=</button>
        </div>
    </div>
</div>

<div id="numpad-modal" class="modal-numpad">
    <div class="numpad-content">
        <h3 style="text-align:center; margin-top:0;">Modifier Quantité</h3>
        <input type="text" id="numpad-val" class="numpad-display" readonly placeholder="0">
        <div class="numpad-grid">
            <?php for($i=1;$i<=9;$i++) echo "<button class='numpad-btn' onclick='nInput($i)'>$i</button>"; ?>
            <button class="numpad-btn clear" onclick="nClear()">C</button>
            <button class="numpad-btn" onclick="nInput(0)">0</button>
            <button class="numpad-btn confirm" onclick="nConfirm()">OK</button>
        </div>
        <button onclick="fermerNumpad()" style="width:100%; margin-top:10px; border:none; background:none; color:gray; cursor:pointer;">Annuler</button>
    </div>
</div>

<script>
let cart = [];
let paniersEnAttente = [];
let currentQtyItemId = null;

// --- INITIALISATION ---
window.onload = function() {
    loadFromLocalStorage();
    document.getElementById('barcode-input').focus();
};

document.addEventListener('click', (e) => {
    if (!e.target.closest('button') && !e.target.closest('input')) {
        document.getElementById('barcode-input').focus();
    }
});

// --- SCAN ---
document.getElementById('barcode-input').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        const sku = this.value;
        if(sku) fetchProduct(sku);
        this.value = '';
    }
});

function fetchProduct(sku) {
    fetch(`get_product.php?sku=${sku}`)
    .then(response => response.json())
    .then(product => {
        if(product.error) alert("Article inconnu !");
        else addToCart(product);
    });
}

// --- PANIER ---
function addToCart(product) {
    let productId = product.id;
    let exist = cart.find(item => item.id == productId);
    if(exist) {
        exist.qty++;
    } else {
        let price = product.selling_price ? product.selling_price : product.base_price;
        cart.push({ id: productId, name: product.name, base_price: parseFloat(price), qty: 1, is_manual: false });
    }
    renderCart();
}

function renderCart() {
    const tbody = document.querySelector('#cart tbody');
    tbody.innerHTML = '';
    let total = 0;
    cart.forEach(item => {
        let subtotal = item.base_price * item.qty;
        total += subtotal;
        tbody.innerHTML += `
            <tr>
                <td style="text-align:left;">${item.name}</td>
                <td>${formatDZD(item.base_price)}</td>
                <td><button type="button" onclick="ouvrirNumpad('${item.id}')" style="padding: 5px 15px; cursor:pointer; font-weight:bold;">${item.qty}</button></td>
                <td>${formatDZD(subtotal)}</td>
                <td><button onclick="removeItem('${item.id}')" style="border:none; background:none; cursor:pointer; font-size:1.2em;">🗑️</button></td>
            </tr>`;
    });
    document.getElementById('total-display').innerText = formatDZD(total);
}

function formatDZD(amount) { return new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2 }).format(amount) + " DZD"; }

function removeItem(id) { cart = cart.filter(item => item.id != id); renderCart(); }

function ajouterArticleManuel() {
    const nom = prompt("Nom de l'article :");
    const prix = prompt("Prix de l'article (DZD) :");
    if (nom && prix && !isNaN(prix)) {
        cart.push({ id: 'MANUAL_' + Date.now(), name: "[MANUEL] " + nom, base_price: parseFloat(prix), qty: 1, is_manual: true });
        renderCart();
    }
}

// --- FILTRES ---
function filtrerCat(catId, btn) {
    document.querySelectorAll('.btn-cat').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.product-card').forEach(card => {
        card.style.display = (catId === 'all' || card.dataset.category == catId) ? 'block' : 'none';
    });
}

// --- ATTENTE ---
function mettreEnAttente() {
    if (cart.length === 0) return;
    paniersEnAttente.push({ id: Date.now(), heure: new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}), items: [...cart], total: document.getElementById('total-display').innerText });
    cart = []; renderCart(); saveToLocalStorage(); afficherPaniersEnAttente();
}

function afficherPaniersEnAttente() {
    const container = document.getElementById('pending-orders-container');
    container.innerHTML = ''; 
    paniersEnAttente.forEach((p, index) => {
        let div = document.createElement('div');
        div.style.cssText = "background:#fff; border:1px solid #f39c12; padding:6px 12px; border-radius:20px; cursor:pointer; font-size:0.9em; box-shadow: 0 2px 4px rgba(0,0,0,0.1);";
        div.innerHTML = `<span onclick="restaurerPanier(${p.id})">👤 Client ${index + 1} (${p.heure}) - <b style="color:#e67e22;">${p.total}</b></span>`;
        container.appendChild(div);
    });
}

function restaurerPanier(id) {
    const index = paniersEnAttente.findIndex(p => p.id === id);
    if (index !== -1) { cart = paniersEnAttente[index].items; paniersEnAttente.splice(index, 1); saveToLocalStorage(); renderCart(); afficherPaniersEnAttente(); }
}

function saveToLocalStorage() { localStorage.setItem('paniers_attente', JSON.stringify(paniersEnAttente)); }
function loadFromLocalStorage() { const saved = localStorage.getItem('paniers_attente'); if (saved) { paniersEnAttente = JSON.parse(saved); afficherPaniersEnAttente(); } }

// --- VENTE & IMPRESSION (TA LOGIQUE COMPLETE) ---
function validerLaVente() {
    if(cart.length === 0) { alert("Le panier est vide !"); return; }
    
    fetch('process_sale_complex.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({cart: cart})
    })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            if (confirm("Vente enregistrée avec succès ! Voulez-vous imprimer le ticket ?")) {
                const win = window.open(`print_ticket.php?id=${data.order_id}`, '_blank', 'width=400,height=600');
                if (!win || win.closed || typeof win.closed == 'undefined') {
                    alert("L'impression a été bloquée. Veuillez autoriser les popups.");
                } else { win.focus(); }
            }
            cart = []; renderCart();
        } else { alert("Erreur lors de la vente : " + data.message); }
    })
    .catch(error => { alert("Erreur de connexion serveur."); });
}

// --- MODALS & TOOLS ---
function toggleCalculator() { const m = document.getElementById('calc-modal'); m.style.display = (m.style.display === 'flex') ? 'none' : 'flex'; }
function cInput(v) { document.getElementById('calc-display').value += v; }
function cClear() { document.getElementById('calc-display').value = ""; }
function cResult() { try { let d = document.getElementById('calc-display'); d.value = eval(d.value); } catch(e) { alert("Erreur"); cClear(); } }

function ouvrirNumpad(itemId) { currentQtyItemId = itemId; document.getElementById('numpad-val').value = ""; document.getElementById('numpad-modal').style.display = "flex"; }
function fermerNumpad() { document.getElementById('numpad-modal').style.display = "none"; }
function nInput(n) { document.getElementById('numpad-val').value += n; }
function nClear() { document.getElementById('numpad-val').value = ""; }
function nConfirm() {
    let nQty = parseInt(document.getElementById('numpad-val').value);
    if (nQty > 0) { let item = cart.find(i => i.id == currentQtyItemId); if (item) item.qty = nQty; renderCart(); }
    fermerNumpad();
}

// HORLOGE
setInterval(() => {
    const el = document.getElementById('digital-clock');
    if(el) el.innerText = new Date().toLocaleTimeString('fr-FR');
}, 1000);

// CLAVIER
document.addEventListener('keydown', (e) => {
    if(e.key === 'F10') { e.preventDefault(); validerLaVente(); }
    if(e.key === 'F9') { e.preventDefault(); mettreEnAttente(); }
});
</script>
</body>
</html>