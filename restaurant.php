<?php
/**
 * restaurant.php
 * Single-file Restaurant Management system
 * - Attractive homepage with menu (cards + search + filter)
 * - Admin login + dashboard + menu CRUD + reservations + orders
 * - Dark theme + animations
 *
 * Usage:
 * - Place in your webserver (XAMPP/WAMP) folder.
 * - Open in browser: http://localhost/restaurant/restaurant.php
 *
 * IMPORTANT: change default admin password after first login!
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// -------------------- DB CONNECTION --------------------
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'restaurant_single';

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) die("DB Conn Error: " . $conn->connect_error);

// Create DB if missing
$conn->query("CREATE DATABASE IF NOT EXISTS `{$DB_NAME}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
$conn->select_db($DB_NAME);

// -------------------- CREATE TABLES IF NOT EXISTS --------------------
// note: menu_items now has "category"
$conn->query("
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    fullname VARCHAR(255) ,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
");

$conn->query("
CREATE TABLE IF NOT EXISTS menu_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    category VARCHAR(100) DEFAULT 'Uncategorized',
    is_available TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
");

$conn->query("
CREATE TABLE IF NOT EXISTS reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    phone VARCHAR(30),
    persons INT,
    date DATE,
    time TIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
");

$conn->query("
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT,
    item_name VARCHAR(255),
    quantity INT,
    table_no VARCHAR(20),
    order_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES menu_items(id) ON DELETE SET NULL
) ENGINE=InnoDB;
");

// -------------------- CREATE DEFAULT ADMIN IF NONE --------------------
$checkAdmin = $conn->query("SELECT COUNT(*) as c FROM admins")->fetch_assoc()['c'];
if ($checkAdmin == 0) {
    $default_user = ' ';
    $default_pass = ' '; 
    $hash = password_hash($default_pass, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO admins (username,password,fullname) VALUES (?,?,?)");
    $fulln = 'Restaurant Admin';
    $stmt->bind_param("sss", $default_user, $hash, $fulln);
    $stmt->execute();
    $stmt->close();
}


// -------------------- SIMPLE ROUTING --------------------
$page = $_GET['page'] ?? 'home';
$act  = $_GET['act']  ?? '';

// -------------------- AUTH HELPERS --------------------
function is_logged() { return !empty($_SESSION['admin_id']); }
function require_login() {
    if (!is_logged()) {
        header("Location: ?page=admin&msg=loginrequired");
        exit;
    }
}
function h($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// -------------------- AUTH: LOGIN / LOGOUT --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $u = $_POST['username'] ?? '';
    $p = $_POST['password'] ?? '';
    $stmt = $conn->prepare("SELECT id, password, fullname FROM admins WHERE username = ?");
    $stmt->bind_param("s", $u);
    $stmt->execute();
    $stmt->bind_result($id, $hash, $fullname);
    if ($stmt->fetch()) {
        if (password_verify($p, $hash)) {
            $_SESSION['admin_id'] = $id;
            $_SESSION['admin_name'] = $fullname;
            $stmt->close();
            header("Location: ?page=dashboard");
            exit;
        } else {
            $error = "Invalid credentials.";
        }
    } else {
        $error = "Invalid credentials.";
    }
    $stmt->close();
}

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: ?page=admin&msg=loggedout");
    exit;
}

// -------------------- ADMIN: ADD / EDIT / DELETE MENU --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_menu'])) {
    require_login();
    $name = trim($_POST['name']);
    $desc = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $category = trim($_POST['category'] ?? 'Uncategorized');
    $avail = isset($_POST['is_available']) ? 1 : 0;
    $stmt = $conn->prepare("INSERT INTO menu_items (name, description, price, category, is_available) VALUES (?,?,?,?,?)");
    $stmt->bind_param("ssdsi", $name, $desc, $price, $category, $avail);
    $stmt->execute();
    $stmt->close();
    header("Location: ?page=dashboard&tab=menu&msg=menuadded");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_menu'])) {
    require_login();
    $id = intval($_POST['id']);
    $name = trim($_POST['name']);
    $desc = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $category = trim($_POST['category'] ?? 'Uncategorized');
    $avail = isset($_POST['is_available']) ? 1 : 0;
    $stmt = $conn->prepare("UPDATE menu_items SET name=?, description=?, price=?, category=?, is_available=? WHERE id=?");
    $stmt->bind_param("ssdssi", $name, $desc, $price, $category, $avail, $id);
    $stmt->execute();
    $stmt->close();
    header("Location: ?page=dashboard&tab=menu&msg=menuupdated");
    exit;
}

if ($act === 'delmenu' && isset($_GET['id'])) {
    require_login();
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("DELETE FROM menu_items WHERE id=?");
    $stmt->bind_param("i",$id);
    $stmt->execute();
    $stmt->close();
    header("Location: ?page=dashboard&tab=menu&msg=menudeleted");
    exit;
}

// -------------------- CUSTOMER: RESERVATION / ORDER --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reserve'])) {
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $persons = intval($_POST['persons']);
    $date = $_POST['date'];
    $time = $_POST['time'];
    $stmt = $conn->prepare("INSERT INTO reservations (name,phone,persons,date,time) VALUES (?,?,?,?,?)");
    $stmt->bind_param("ssiss", $name, $phone, $persons, $date, $time);
    $stmt->execute();
    $stmt->close();
    $ok_msg = "Reservation successful!";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $item_id = intval($_POST['item_id']);
    $qty = intval($_POST['quantity']);
    $table_no = $_POST['table_no'];
    $stmt = $conn->prepare("SELECT name FROM menu_items WHERE id = ?");
    $stmt->bind_param("i",$item_id);
    $stmt->execute();
    $stmt->bind_result($iname);
    if ($stmt->fetch()) {
        $i_name = $iname;
    } else {
        $i_name = 'Unknown Item';
    }
    $stmt->close();
    $stmt = $conn->prepare("INSERT INTO orders (item_id,item_name,quantity,table_no) VALUES (?,?,?,?)");
    $stmt->bind_param("isis", $item_id, $i_name, $qty, $table_no);
    $stmt->execute();
    $stmt->close();
    $ok_msg = "Order placed! Thank you.";
}

// -------------------- UTIL: fetch counts for dashboard --------------------
function get_count($conn, $table) {
    $res = $conn->query("SELECT COUNT(*) as c FROM {$table}");
    $r = $res->fetch_assoc();
    return intval($r['c']);
}

// SMALL HELPER: fetch distinct categories
function get_categories($conn) {
    $cats = [];
    $res = $conn->query("SELECT DISTINCT category FROM menu_items ORDER BY category ASC");
    while ($r = $res->fetch_assoc()) $cats[] = $r['category'];
    return $cats;
}

// -------------------- PAGE OUTPUT --------------------
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Restaurant — Single File</title>

<!-- ----------------- STYLES (Dark & Attractive) ----------------- -->
<style>
:root{
  --bg:#071022; --panel:#0f1724; --muted:#9ca3af; --accent:#ffb86b; --accent-2:#ff6b6b;
  --glass: rgba(255,255,255,0.03); --card-glow: rgba(255,107,107,0.08);
  --success:#10b981;
  --glass-2: rgba(255,255,255,0.02);
}
*{box-sizing:border-box}
body{
  margin:0; font-family:Inter,Segoe UI,Roboto,system-ui,Arial; background:linear-gradient(180deg,#06111b 0%, #071022 100%); color:#e6eef6;
  -webkit-font-smoothing:antialiased;
}
.container{max-width:1150px;margin:28px auto;padding:18px;}
.header{display:flex;gap:16px;align-items:center;justify-content:space-between;flex-wrap:wrap}
.brand{display:flex;gap:12px;align-items:center}
.logo{
  width:64px;height:64px;border-radius:12px;background:linear-gradient(135deg,var(--accent),var(--accent-2));
  display:flex;align-items:center;justify-content:center;font-weight:800;color:#071022;font-size:20px;
  box-shadow: 0 8px 30px rgba(255,107,107,0.08);
}
.title{font-size:20px;font-weight:700}
.small-muted{color:var(--muted);font-size:13px}
.top-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.btn{background:var(--glass);border:1px solid rgba(255,255,255,0.03);padding:8px 12px;border-radius:10px;color:var(--muted);text-decoration:none;cursor:pointer}
.btn.primary{background:linear-gradient(90deg,var(--accent),var(--accent-2));color:#071022;border:none;box-shadow:0 12px 30px rgba(255,107,107,0.06)}
.card{background:linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01)); padding:16px;border-radius:14px;box-shadow: 0 10px 30px rgba(2,6,23,0.6);border:1px solid rgba(255,255,255,0.03)}
.hero{
  margin-top:18px;border-radius:14px;padding:28px;background:linear-gradient(90deg, rgba(255,107,107,0.06), rgba(255,184,107,0.03));
  display:flex;align-items:center;gap:18px;overflow:hidden;
}
.hero .text{flex:1}
.hero h1{margin:0;font-size:32px}
.hero p{margin:6px 0;color:var(--muted)}
.hero .cta{margin-top:12px}
.hero .visual{width:320px;height:210px;border-radius:12px;background:url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="600" height="400"><rect width="100%" height="100%" fill="%2309121a"/><text x="50%" y="50%" fill="%23ffb86b" font-size="28" text-anchor="middle" font-family="Arial">Delicious</text></svg>') center/cover no-repeat; box-shadow: 0 12px 30px rgba(0,0,0,0.6);}
.searchbar{display:flex;gap:8px;margin-top:12px}
.searchbar input{flex:1;padding:10px;border-radius:10px;border:1px solid rgba(255,255,255,0.04);background:transparent;color:#e6eef6}
.filters{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}
.filters .pill{background:var(--glass-2);padding:8px 10px;border-radius:999px;border:1px solid rgba(255,255,255,0.02);color:var(--muted);cursor:pointer}
.menu-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:18px}
@media(max-width:1000px){.menu-grid{grid-template-columns:repeat(2,1fr)} .hero .visual{display:none}}
@media(max-width:640px){.menu-grid{grid-template-columns:1fr} .hero{flex-direction:column} .hero .visual{display:none}}
.menu-card{border-radius:12px;padding:14px;background:linear-gradient(180deg, rgba(255,255,255,0.015), rgba(255,255,255,0.01));border:1px solid rgba(255,255,255,0.03);display:flex;flex-direction:column;justify-content:space-between;transition:transform .18s ease, box-shadow .18s ease}
.menu-card:hover{transform:translateY(-6px);box-shadow:0 18px 48px rgba(0,0,0,0.6), 0 6px 24px var(--card-glow)}
.menu-top{display:flex;gap:12px;align-items:center}
.thumb{width:86px;height:86px;border-radius:10px;background:linear-gradient(135deg,var(--accent),var(--accent-2));display:flex;align-items:center;justify-content:center;font-weight:700;color:#071022}
.menu-title{font-weight:700}
.menu-desc{color:var(--muted);font-size:13px;margin-top:6px}
.menu-bottom{display:flex;justify-content:space-between;align-items:center;margin-top:12px}
.price{font-weight:800}
.actions{display:flex;gap:8px}
.kv{color:var(--muted);font-size:13px}
.section{margin-top:20px;display:grid;grid-template-columns:1fr 380px;gap:18px}
@media(max-width:900px){.section{grid-template-columns:1fr}}
.sidecard .list{display:flex;flex-direction:column;gap:10px;margin-top:10px}
.order-form input, .order-form select{width:100%;padding:10px;border-radius:8px;border:1px solid rgba(255,255,255,0.04);background:transparent;color:var(--muted)}
.footer{margin-top:28px;text-align:center;color:var(--muted);font-size:13px}
.notice{padding:10px;border-radius:8px;background:rgba(16,185,129,0.06);color:var(--success);border:1px solid rgba(16,185,129,0.08)}
.badge{background:#0b1220;padding:6px 10px;border-radius:999px;color:var(--muted);font-size:13px;border:1px solid rgba(255,255,255,0.02)}
.search-empty{padding:18px;border-radius:10px;background:rgba(255,255,255,0.01);color:var(--muted);text-align:center}
.topline{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
</style>

</head>
<body>

<div class="container">
  <div class="header">
    <div class="brand">
      <div class="logo">RS</div>
      <div>
        <div class="title">Restaurant System</div>
        <div class="small-muted"> • Menu • Admin dashboard </div>
      </div>
    </div>

    <div class="top-actions">
      <?php if (is_logged()): ?>
        <div class="kv">Welcome,
 <?php echo h($_SESSION['admin_name']); ?></div>
        <a class="btn" href="?page=dashboard">Dashboard</a>
        <a class="btn" href="?page=dashboard&tab=menu">Menu</a>
        <a class="btn" href="?logout=1" style="background:transparent;color:var(--muted)">Logout</a>
      <?php else: ?>
        <a class="btn" href="?page=admin">Admin Login</a>
      <?php endif; ?>
      <a class="btn primary" href="#reserve">Reserve Table</a>
    </div>
  </div>

  <!-- ------------------ HOME PAGE ------------------ -->
  <?php if ($page === 'home'): ?>

    <div class="hero card">
      <div class="text">
        <h1>Fresh flavors, made with love</h1>
        <p class="small-muted">Discover our menu — handcrafted dishes, seasonal specials, and chef recommendations.</p>

        <div class="topline">
          <div class="searchbar" style="max-width:520px;flex:1">
            <input id="searchInput" type="search" placeholder="Search food, e.g. biryani, pizza..." oninput="filterMenu()" />
            <select id="categorySelect" onchange="filterMenu()">
              <option value="Burger">Burger</option>
              <option value="Pizza">Pizza</option>
              <option value="Fried Rice">Fried Rice</option>
              <option value="Fried Momos">Fried Momos</option>
              <option value="Fries">Fries</option>


              <?php foreach (get_categories($conn) as $cat): ?>
                <option value="<?php echo h($cat); ?>"><?php echo h($cat); ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn" onclick="clearFilters()">Clear</button>
          </div>
        </div>

        <div class="filters" id="quickFilters">
          <div class="pill" onclick="quickFilter('')">All</div>
          <?php foreach (get_categories($conn) as $cat): ?>
            <div class="pill" onclick="quickFilter('<?php echo h($cat); ?>')"><?php echo h($cat); ?></div>
          <?php endforeach; ?>
        </div>

        <div class="cta">
          <a class="btn primary" href="#menu">View Menu</a>
          <a class="btn" href="#order">Quick Order</a>
        </div>
      </div>
    </div>

    <div class="section">
      <div>
        <h2 id="menu">Our Menu</h2>
        <p class="small-muted">Tap a card to add to order quickly — or use the Order form on the right.</p>

        <div class="menu-grid" id="menuGrid">
          <?php
            // fetch all menu items with limit and display cards
            $res = $conn->query("SELECT * FROM menu_items ORDER BY created_at DESC");
            $menu_items = [];
            while($mi = $res->fetch_assoc()){
              $menu_items[] = $mi;
            }
            if (count($menu_items) === 0) {
              echo '<div class="search-empty card">No menu items yet. Admins: add items in Dashboard → Menu.</div>';
            } else {
              foreach ($menu_items as $mi):
                $thumb = strtoupper(substr($mi['name'],0,1));
          ?>
            <div class="menu-card card" data-name="<?php echo h(strtolower($mi['name'])); ?>" data-cat="<?php echo h($mi['category']); ?>">
              <div class="menu-top">
                <div class="thumb"><?php echo h($thumb); ?></div>
                <div style="flex:1;margin-left:8px">
                  <div class="menu-title"><?php echo h($mi['name']); ?></div>
                  <div class="menu-desc"><?php echo h(strlen($mi['description'])>120?substr($mi['description'],0,118).'...':$mi['description']); ?></div>
                  <div class="kv" style="margin-top:6px">Category: <?php echo h($mi['category']); ?> • <?php echo $mi['is_available'] ? '<span style="color:#10b981">Available</span>' : '<span style="color:#f97316">Unavailable</span>'; ?></div>
                </div>
              </div>

              <div class="menu-bottom">
                <div class="price">₹<?php echo number_format($mi['price'],2); ?></div>
                <div class="actions">
                  <?php if ($mi['is_available']): ?>
                    <button class="btn" onclick="quickAdd(<?php echo intval($mi['id']); ?>, '<?php echo h(addslashes($mi['name'])); ?>', <?php echo number_format($mi['price'],2); ?>)">Add</button>
                  <?php else: ?>
                    <div class="kv">Not available</div>
                  <?php endif; ?>
                  <?php if (is_logged()): ?>
                    <a class="btn" href="?page=dashboard&tab=menu&edit=<?php echo intval($mi['id']); ?>">Edit</a>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endforeach; } ?>
        </div>
      </div>

      <aside>
        <div class="card sidecard">
          <?php if(!empty($ok_msg)) echo "<div class='notice'>{$ok_msg}</div><br>"; ?>
          <h3 style="margin-top:0">Place an Order</h3>
          <form method="POST" class="order-form" id="orderForm">
            <label>Menu Item
              <select name="item_id" id="itemSelect" required>
                <?php
                  // show only available items
                  $r = $conn->query("SELECT id,name,price FROM menu_items WHERE is_available=1 ORDER BY name ASC");
               while($m = $r->fetch_assoc()){
    echo '<option value="'.intval($m['id']).'">'.h($m['name']).'₹'.number_format($m['price'],2).'</option>';
                  }
                ?>
              </select>
            </label>

            <label>Quantity <input type="number" name="quantity" id="qtyInput" min="1" value="1" required></label>
            <label>Table No (or 'Pickup') <input name="table_no" id="tableInput" placeholder="e.g. 5 or Pickup"></label>
            <div style="display:flex;gap:8px;margin-top:8px">
              <button class="btn primary" name="place_order" type="submit">Place Order</button>
              <button type="button" class="btn" onclick="resetOrderForm()">Reset</button>
            </div>
          </form>

          <div style="margin-top:14px">
            <h4 style="margin:0">Quick Cart</h4>
            <div class="small-muted">Click Add on a menu card to prefill order.</div>
            <div class="list" id="quickCart" style="margin-top:8px"></div>
          </div>
        </div>

        <div class="card" style="margin-top:12px">
          <h3 style="margin-top:0">Reserve Table</h3>
          <form method="POST" id="reserve" style="display:grid;gap:8px">
            <label>Name <input name="name" required></label>
            <label>Phone <input name="phone" required></label>
            <label>No. Persons <input name="persons" type="number" min="1" value="2" required></label>
            <label>Date <input name="date" type="date" required></label>
            <label>Time <input name="time" type="time" required></label>
            <button class="btn primary" name="reserve" type="submit">Reserve</button>
          </form>
        </div>

      </aside>
    </div>

    <div class="footer">Made with ❤️ • Single-file Restaurant Management • Change default admin password after first login</div>

  <!-- ------------------ ADD to CART ------------------ -->
<style>

.add-cart-btn {
    margin-top: 10px;
    padding: 10px 20px;
    background: #ffb03b;
    color: black;
    border: none;
    border-radius: 20px;
    cursor: pointer;
    font-weight: bold;
    transition: 0.3s;
}

.add-cart-btn:hover {
    background: #ff9900;
}

/* Cart Panel */
#cart-panel {
    position: fixed;
    top: 0;
    right: -400px;
    width: 350px;
    height: 100%;
    background: #1b1b1d;
    color: white;
    box-shadow: -5px 0 15px rgba(0,0,0,0.4);
    padding: 20px;
    transition: 0.3s;
    overflow-y: auto;
    z-index: 9999;
}

#cart-panel.open {
    right: 0;
}

.cart-title {
    font-size: 26px;
    margin-bottom: 20px;
    color: #ffb03b;
    font-weight: bold;
}

.cart-item {
    background: #262628;
    padding: 10px;
    margin-bottom: 10px;
    border-radius: 8px;
}

.cart-item h4 {
    margin: 0;
    color: #ffb03b;
}

.cart-total {
    margin-top: 15px;
    font-size: 20px;
    font-weight: bold;
    color: #fff;
}

.checkout-btn {
    width: 100%;
    background: #ffb03b;
    padding: 12px;
    color: black;
    border-radius: 25px;
    border: none;
    margin-top: 20px;
    font-weight: bold;
    cursor: pointer;
}

</style>

  <!-- ----------------- ADMIN LOGIN PAGE ----------------- -->
  <?php elseif ($page === 'admin'): ?>

    <div style="max-width:520px;margin:34px auto">
      <div class="card login-box">
        <h2>Admin Login</h2>
        <?php if(!empty($error)) echo '<div style="color:#f87171;margin-bottom:8px;">'.h($error).'</div>'; ?>
        <?php if(!empty($_GET['msg']) && $_GET['msg'] === 'loggedout') echo '<div class="notice" style="background:transparent;color:var(--muted);border:1px solid rgba(255,255,255,0.04)">Logged out</div>'; ?>
        <form method="POST" style="display:grid;gap:8px;margin-top:12px">
          <label>Username <input name="username" required></label>
          <label>Password <input type="password" name="password" required></label>
          <button class="btn primary" name="login">Login</button>
        </form>
        <div style="margin-top:12px;color:var(--muted)">Default admin: <b>admin</b> / <b>admin123</b></div>
      </div>
    </div>

  <!-- ----------------- DASHBOARD ----------------- -->
  <?php elseif ($page === 'dashboard'):
        require_login();
        $tab = $_GET['tab'] ?? 'overview';
  ?>

    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <div>
          <h2 style="margin:0">Dashboard</h2>
          <div class="small-muted">Overview & management</div>
        </div>
        <div class="top-actions">
          <a class="badge" href="?page=dashboard&tab=overview">Overview</a>
          <a class="badge" href="?page=dashboard&tab=menu">Menu</a>
          <a class="badge" href="?page=dashboard&tab=orders">Orders</a>
          <a class="badge" href="?page=dashboard&tab=resv">Reservations</a>
          <a class="btn" href="?logout=1" style="background:transparent;color:var(--muted)">Logout</a>
        </div>
      </div>

      <?php if($tab === 'overview'): ?>
        <div class="menu-grid" style="margin-top:16px">
          <div class="card stat">
            <div class="small-muted">Total Reservations</div>
            <div style="font-size:22px;font-weight:700"><?php echo get_count($conn,'reservations'); ?></div>
            <div class="small-muted">All time</div>
          </div>
          <div class="card stat">
            <div class="small-muted">Total Orders</div>
            <div style="font-size:22px;font-weight:700"><?php echo get_count($conn,'orders'); ?></div>
            <div class="small-muted">All time</div>
          </div>
          <div class="card stat">
            <div class="small-muted">Menu Items</div>
            <div style="font-size:22px;font-weight:700"><?php echo get_count($conn,'menu_items'); ?></div>
            <div class="small-muted">Active / total</div>
          </div>
        </div>

        <div style="display:flex;gap:16px;margin-top:16px;flex-wrap:wrap">
          <div style="flex:1" class="card">
            <h3 style="margin-top:0">Recent Reservations</h3>
            <table class="table" style="width:100%;border-collapse:collapse;margin-top:8px">
              <thead><tr><th style="text-align:left">Name</th><th>Persons</th><th>Date</th><th>Time</th></tr></thead>
              <tbody>
                <?php $res = $conn->query("SELECT * FROM reservations ORDER BY created_at DESC LIMIT 8");
                while($r = $res->fetch_assoc()){
                  echo '<tr><td>'.h($r['name']).' <div class="small-muted">'.h($r['phone']).'</div></td><td>'.intval($r['persons']).'</td><td class="small-muted">'.h($r['date']).'</td><td class="small-muted">'.h($r['time']).'</td></tr>';
                } ?>
              </tbody>
            </table>
          </div>
          <div style="width:420px" class="card">
            <h3 style="margin-top:0">Quick Add Menu</h3>
            <form method="POST" style="display:grid;gap:8px">
              <label>Name <input name="name" required></label>
              <label>Category <input name="category" placeholder="e.g. Main Course, Dessert"></label>
              <label>Description <textarea name="description" rows="3"></textarea></label>
              <label>Price <input name="price" type="number" step="0.01" value="0.00" required></label>
              <label><input type="checkbox" name="is_available" checked> Available</label>
              <button class="btn primary" name="add_menu" type="submit">Add Menu Item</button>
            </form>
          </div>
        </div>

      <?php elseif($tab === 'menu'): ?>
        <div style="margin-top:12px;">
          <h3 style="margin:0 0 8px 0">Menu Items</h3>
          <div style="display:flex;gap:12px;flex-wrap:wrap">
            <?php
              $res = $conn->query("SELECT * FROM menu_items ORDER BY created_at DESC");
              while($m = $res->fetch_assoc()):
            ?>
              <div class="card" style="width:300px">
                <div style="display:flex;justify-content:space-between;align-items:start">
                  <div>
                    <div style="font-weight:700"><?php echo h($m['name']); ?></div>
                    <div class="small-muted"><?php echo h(substr($m['description'],0,140)); ?></div>
                    <div class="kv" style="margin-top:6px">Category: <?php echo h($m['category']); ?></div>
                  </div>
                  <div style="text-align:right">
                    <div style="font-weight:700">₹<?php echo number_format($m['price'],2); ?></div>
                    <div class="small-muted"><?php echo $m['is_available'] ? 'Available' : 'Unavailable'; ?></div>
                  </div>
                </div>
                <div style="display:flex;gap:8px;margin-top:10px">
                  <a class="btn" href="?page=dashboard&tab=menu&edit=<?php echo intval($m['id']); ?>">Edit</a>
                  <a class="btn" href="?page=dashboard&tab=menu&act=del&id=<?php echo intval($m['id']); ?>" onclick="return confirm('Delete this item?')">Delete</a>
                </div>
                <?php if(isset($_GET['edit']) && intval($_GET['edit']) === intval($m['id'])): ?>
                  <hr>
                  <form method="POST" style="display:grid;gap:8px">
                    <input type="hidden" name="id" value="<?php echo intval($m['id']); ?>">
                    <label>Name <input name="name" value="<?php echo h($m['name']); ?>" required></label>
                    <label>Category <input name="category" value="<?php echo h($m['category']); ?>"></label>
                    <label>Description <textarea name="description"><?php echo h($m['description']); ?></textarea></label>
                    <label>Price <input name="price" type="number" step="0.01" value="<?php echo number_format($m['price'],2); ?>" required></label>
                    <label><input type="checkbox" name="is_available" <?php echo $m['is_available'] ? 'checked' : ''; ?>> Available</label>
                    <button class="btn primary" name="edit_menu" type="submit">Save Changes</button>
                  </form>
                <?php endif; ?>
              </div>
            <?php endwhile; ?>
          </div>

        </div>

      <?php elseif($tab === 'orders'): ?>
        <div style="margin-top:12px">
          <h3>Orders</h3>
          <table class="table" style="width:100%;border-collapse:collapse;margin-top:8px">
            <thead><tr><th>#</th><th>Item</th><th>Qty</th><th>Table</th><th>Time</th></tr></thead>
            <tbody>
            <?php $res = $conn->query("SELECT * FROM orders ORDER BY order_time DESC LIMIT 200");
              while($o = $res->fetch_assoc()){
                echo '<tr><td>'.$o['id'].'</td><td>'.h($o['item_name']).'</td><td>'.$o['quantity'].'</td><td>'.h($o['table_no']).'</td><td class="small-muted">'.$o['order_time'].'</td></tr>';
              }
            ?>
            </tbody>
          </table>
        </div>

      <?php elseif($tab === 'resv'): ?>
        <div style="margin-top:12px">
          <h3>Reservations</h3>
          <table class="table" style="width:100%;border-collapse:collapse;margin-top:8px">
            <thead><tr><th>#</th><th>Name</th><th>Phone</th><th>Persons</th><th>Date</th><th>Time</th></tr></thead>
            <tbody>
              <?php $res = $conn->query("SELECT * FROM reservations ORDER BY created_at DESC LIMIT 200");
                while($r = $res->fetch_assoc()){
                  echo '<tr><td>'.$r['id'].'</td><td>'.h($r['name']).'</td><td>'.h($r['phone']).'</td><td>'.$r['persons'].'</td><td class="small-muted">'.h($r['date']).'</td><td class="small-muted">'.h($r['time']).'</td></tr>';
                }
              ?>
            </tbody>
          </table>
        </div>

      <?php endif; ?>

    </div>

  <?php else: ?>

    <div class="card">
      <h2>Page not found</h2>
      <p class="small-muted">Use the top navigation to access features.</p>
    </div>

  <?php endif; ?>

</div>

<!-- =======================
       MENU SECTION
========================= -->
<section id="menu" class="menu-section">
    <h2 class="section-title">Our Menu</h2>

    <!-- MAIN COURSES -->
    <div class="menu-category">
        <h3>Main Courses</h3>
        <div class="menu-grid">
          <div class="menu-card">
           <img src="https://assets.bonappetit.com/photos/5b919cb83d923e31d08fed17/4:3/w_2666,h_2000,c_limit/basically-burger-1.jpg" alt="Burger">
                <h4>Burger</h4>
                <p>Juicy beef patty, cheese & fresh veggies</p>
                <span class="price">₹199</span>
            </div>

            <div class="menu-card">
                <img src="https://www.tillamook.com/_next/image?url=https%3A%2F%2Fimages.ctfassets.net%2Fj8tkpy1gjhi5%2F5OvVmigx6VIUsyoKz1EHUs%2Fb8173b7dcfbd6da341ce11bcebfa86ea%2FSalami-pizza-hero.jpg&w=1024&q=75" alt="Pizza">
                <h4>Pizza</h4>
                <p>Wood-fired cheese pizza with toppings</p>
                <span class="price">₹299</span>
            </div>

            <div class="menu-card">
                <img src="https://images.rawpixel.com/image_png_800/cHJpdmF0ZS9sci9pbWFnZXMvd2Vic2l0ZS8yMDI0LTA4L3Jhd3BpeGVsb2ZmaWNlMl9waG90b19vZl9leHBsb2RpbmdfYmFjb25fZnJpZWRfcmljZV9faW5fc3R5bGVfb18xYjllOWMyOC00ZDFiLTRiNDQtYmNjMy0yMGQ3ZjY2NWMzMmMucG5n.png" alt="Fried Rice">
                <h4>Fried Rice</h4>
                <p>Crispy fried Rice</p>
                <span class="price">₹249</span>
            </div>

            <div class="menu-card">
                <img src="https://www.tasteofhome.com/wp-content/uploads/2025/07/Best-Lasagna_EXPS_ATBBZ25_36333_DR_07_01_2b.jpg" alt="Lasagna">
                <h4>Lasagna</h4>
                <p>Italian layered pasta with cheese</p>
                <span class="price">₹349</span>
            </div>

            <div class="menu-card">
                <img src="https://i.ytimg.com/vi/dNhgr9w7Y6g/maxresdefault.jpg" alt="Fried Momos">
                <h4>Fried Momos</h4>
                <p>Crispy golden fried momos</p>
                <span class="price">₹599</span>
            </div>

        </div>
    </div>

    <!-- SIDE DISHES -->
    <div class="menu-category">
        <h3>Side Dishes</h3>
        <div class="menu-grid">

            <div class="menu-card">
                <img src="https://www.hindustantimes.com/ht-img/img/2025/07/11/1600x900/french_fries_1752214461274_1752214461432.jpg" alt="Fries">
                <h4>Fries</h4>
                <p>Golden crispy french fries</p>
                <span class="price">₹99</span>
            </div>

            <div class="menu-card">
                <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQ6ZAAgM6GMo9CAFZ74KOIc-hkd36ADUSqiiA&s9" alt="Salad">
                <h4>Salad</h4>
                <p>Fresh and healthy vegetable salad</p>
                <span class="price">₹149</span>
            </div>

            <div class="menu-card">
                <img src="https://www.allrecipes.com/thmb/8pkbFP258H24axyBlRbGtWS-Vnk=/1500x0/filters:no_upscale():max_bytes(150000):strip_icc()/24771-basic-mashed-potatoes-mfs318-ed832ab37551471cba0997410217d4c5.jpg" alt="Mashed Potatoes">
                <h4>Mashed Potatoes</h4>
                <p>Creamy smooth mashed potatoes</p>
                <span class="price">₹129</span>
            </div>

        </div>
    </div>

    <!-- DESSERTS -->
    <div class="menu-category">
        <h3>Desserts</h3>
        <div class="menu-grid">

            <div class="menu-card">
                <img src="https://inbloombakery.com/wp-content/uploads/2022/04/chocolate-drip-cake-featured-image.jpg" alt="Cake">
                <h4>Cake</h4>
                <p>Soft chocolate birthday cake</p>
                <span class="price">₹199</span>
            </div>

            <div class="menu-card">
                <img src="https://www.simplyorganic.com/media/wysiwyg/tmp/simply-oragnic-No-Churn-Mixed-Berry-Sorbet-1080x1080-thumbnail.jpg" alt="Sorbet">
                <h4>Sorbet</h4>
                <p>Cold fruity sorbet dessert</p>
                <span class="price">₹149</span>
            </div>

            <div class="menu-card">
                <img src="https://www.nestleprofessional.in/sites/default/files/2021-08/Brownies.jpg" alt="Brownies">
                <h4>Brownies</h4>
                <p>Warm chocolate brownies</p>
                <span class="price">₹179</span>
            </div>

        </div>
    </div>

    <!-- BEVERAGES -->
    <div class="menu-category">
        <h3>Beverages</h3>
        <div class="menu-grid">

            <div class="menu-card">
                <img src="https://corkframes.com/cdn/shop/articles/Corkframes_Coffee_Guide_520x500_422ebe38-4cfa-42b5-a266-b9bfecabaf30.jpg?v=1734598727" alt="Coffee">
                <h4>Coffee</h4>
                <p>Fresh brewed hot coffee</p>
                <span class="price">₹89</span>
            </div>

            <div class="menu-card">
                <img src="https://desifreshfoods.com/wp-content/uploads/2023/04/Indian-Tea-Recipes1.jpg" alt="Tea">
                <h4>Tea</h4>
                <p>Masala chai with milk</p>
                <span class="price">₹69</span>
            </div>

            <div class="menu-card">
                <img src="https://static.vecteezy.com/system/resources/thumbnails/040/175/328/small/ai-generated-pictures-of-delicious-and-beautiful-drinks-photo.jpg" alt="Soda">
                <h4>Soda</h4>
                <p>Chilled soft drink</p>
                <span class="price">₹49</span>
            </div>

        </div>
    </div>
</section>
<!-- ----------------- Menu Styling & Dark Theme----------------- -->
<style>
.menu-section {
    background: #0f0f10;
    padding: 50px 20px;
}

.section-title {
    text-align: center;
    color: #ffb03b;
    font-size: 36px;
    margin-bottom: 40px;
    font-weight: bold;
}

.menu-category h3 {
    color: #ffffff;
    font-size: 28px;
    margin-bottom: 20px;
}

.menu-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 25px;
}

.menu-card {
    background: #1b1b1d;
    border-radius: 15px;
    overflow: hidden;
    padding-bottom: 20px;
    text-align: center;
    box-shadow: 0 4px 15px rgba(255, 176, 59, 0.15);
    transition: transform 0.3s, box-shadow 0.3s;
}

.menu-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 6px 20px rgba(255, 176, 59, 0.25);
}

.menu-card img {
    width: 100%;
    height: 160px;
    object-fit: cover;
}

.menu-card h4 {
    margin: 15px 0 5px;
    font-size: 22px;
    color: #ffb03b;
}

.menu-card p {
    font-size: 14px;
    color: #cccccc;
    margin: 0 15px 10px;
}

.price {
    color: #ffffff;
    font-size: 18px;
    font-weight: bold;
    background: #ffb03b;
    padding: 5px 15px;
    border-radius: 20px;
}

</style>


<!-- ----------------- SCRIPTS: filtering, quick-add, UX ----------------- -->
<script>
/* -------- Helpers -------- */
function qs(sel){return document.querySelector(sel)}
function qsa(sel){return Array.from(document.querySelectorAll(sel))}

/* -------- Menu Filtering -------- */
function filterMenu(){
  const q = (qs('#searchInput')?.value || '').trim().toLowerCase();
  const cat = qs('#categorySelect')?.value || '';
  qsa('#menuGrid .menu-card').forEach(card=>{
    const name = card.getAttribute('data-name') || '';
    const cardCat = card.getAttribute('data-cat') || '';
    const matchesQ = q === '' || name.indexOf(q) !== -1;
    const matchesCat = cat === '' || cardCat === cat;
    card.style.display = (matchesQ && matchesCat) ? 'flex' : 'none'
  })
}
function quickFilter(cat){
  if(cat === ''){ qs('#categorySelect').value = ''; } else { qs('#categorySelect').value = cat; }
  filterMenu();
}
function clearFilters(){
  qs('#searchInput').value = '';
  qs('#categorySelect').value = '';
  filterMenu();
}

/* -------- Quick Add to order (prefill order form) -------- */
function quickAdd(id, name, price){
  // set select value (if available)
  const sel = qs('#itemSelect');
  if(!sel) return;
  // try to find option with this id
  const opt = sel.querySelector('option[value="'+id+'"]');
  if(opt){
    sel.value = id;
    qs('#qtyInput').value = 1;
    qs('#tableInput').focus();
    // show in quick cart
    addToQuickCart(id, name, price, 1);
  } else {
    // item might be unavailable for ordering; show notification
    alert('Item not currently available to order. You can still add it from admin.');
  }
}

/* Quick cart UI */
function addToQuickCart(id, name, price, qty){
  const cart = qs('#quickCart');
  const key = 'item-'+id;
  // if exists, update qty
  let el = qs('#' + key);
  if(el){
    const qel = el.querySelector('.q');
    qel.textContent = parseInt(qel.textContent)+qty;
  } else {
    const div = document.createElement('div');
    div.id = key;
    div.style.display = 'flex';
    div.style.justifyContent = 'space-between';
    div.style.alignItems = 'center';
    div.style.gap = '8px';
    div.innerHTML = `<div><strong>${escapeHtml(name)}</strong><div class="small-muted">₹${parseFloat(price).toFixed(2)}</div></div>
                     <div style="display:flex;gap:6px;align-items:center">
                       <div class="small-muted q">${qty}</div>
                       <button class="btn" onclick="fillOrder(${id})">Order</button>
                       <button class="btn" onclick="removeFromQuickCart('${key}')">Remove</button>
                     </div>`;
    cart.appendChild(div);
  }
}

/* fill order fields from quick cart item */
function fillOrder(id){
  const sel = qs('#itemSelect');
  if(sel.querySelector('option[value="'+id+'"]')){
    sel.value = id;
    qs('#qtyInput').value = 1;
    qs('#tableInput').focus();
  } else {
    alert('This item is unavailable for ordering.');
  }
}

/* remove quick cart item */
function removeFromQuickCart(key){
  const el = qs('#'+key);
  if(el) el.remove();
}

/* reset order form */
function resetOrderForm(){
  const sel = qs('#itemSelect');
  if(sel) sel.selectedIndex = 0;
  if(qs('#qtyInput')) qs('#qtyInput').value = 1;
  if(qs('#tableInput')) qs('#tableInput').value = '';
  qs('#quickCart').innerHTML = '';
}

/* ESCAPE helper */
function escapeHtml(str) {
  return String(str).replace(/[&<>"'\/]/g, function (s) {
    const entityMap = { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': '&quot;', "'": '&#39;', "/": '&#x2F;' };
    return entityMap[s];
  });
}

/* init: attach enter key search behavior */
document.addEventListener('DOMContentLoaded', function(){
  const input = qs('#searchInput');
  if(input) input.addEventListener('keydown', function(e){ if(e.key === 'Escape'){ clearFilters(); } });
});
</script>

</body>
</html>




