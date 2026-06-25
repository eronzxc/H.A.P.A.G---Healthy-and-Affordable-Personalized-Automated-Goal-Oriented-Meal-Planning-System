<?php
// ============================================================
//  H.A.P.A.G. — Main Landing Page (index.php)
//  Drop this in htdocs/hapag/ alongside hapag-styles.css
// ============================================================

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$user        = current_user();
$logged_in   = is_logged_in();
$flash_error = '';
$flash_ok    = '';

// ── Compute macros for logged-in hero card ────────────────────
$user_macros = [];
if ($logged_in && $user) {
    $user_macros = calc_calories(
        $user['goal']        ?? 'maintenance',
        $user['sex']         ?? 'male',
        (int)   ($user['age']        ?? 25),
        (float) ($user['weight_kg']  ?? 65),
        (float) ($user['height_cm']  ?? 165),
        $user['activity']    ?? 'moderate',
        !empty($user['custom_kcal']) ? (int)$user['custom_kcal'] : null
    );
}

// ── Filipino nutrition tip of the day (rotates daily) ─────────
$nutrition_tips = [
    "Malunggay (moringa) leaves contain more Vitamin C than oranges and more calcium than milk. Throw a handful into your tinola or sinigang for a free nutrition boost!",
    "Bangus (milkfish) is one of the richest sources of Omega-3 among locally available fish — great for your heart and brain, and usually affordable at the palengke.",
    "Kangkong (water spinach) is packed with iron, Vitamin A, and folate. Sautéed quickly with garlic, it's one of the most nutritious and cheapest vegetable sides you can make.",
    "Kamote (sweet potato) has a lower glycemic index than white rice — it keeps you fuller longer and helps stabilize blood sugar. Great swap for weight loss goals!",
    "Ampalaya (bitter gourd) contains natural compounds that help regulate blood sugar, making it especially beneficial for people managing diabetes or pre-diabetes.",
    "Gabi (taro) is a high-fiber carbohydrate with a lower GI than regular potatoes — a smart, filling option that's easy to find at any wet market.",
    "Sinigang's tamarind broth is rich in Vitamin C and antioxidants. It makes lean pork or fish taste deeply satisfying without adding much fat to your meal.",
    "Brown rice has 3× more fiber than white rice and keeps you full significantly longer. Try substituting for 1–2 meals per week as a start.",
    "Tokwa (firm tofu) gives you about 8g of protein per 100g — at a fraction of the cost of meat. Pan-fry it until golden for the best texture.",
    "Sayote (chayote) is extremely low in calories (~19 kcal per 100g) and high in folate — one of the best bulking vegetables for weight loss plans.",
    "Sitaw (string beans) are high in folate, Vitamin K, and fiber. They're one of the most budget-friendly and nutritious vegetables at any palengke.",
    "Coconut milk (gata) contains medium-chain triglycerides (MCTs) — fats the body metabolizes quickly for energy rather than storing. Use in moderation for flavor.",
    "Sardines in tomato sauce are a budget protein powerhouse — high in Omega-3, calcium, and Vitamin D. Keep a few cans on hand for quick, nutritious meals.",
    "Dahon ng saging (banana leaves) used in cooking are not eaten, but wrapping food in them during steaming or grilling adds a subtle aroma and reduces the need for oil.",
    "Fermented foods like bagoong and patis contain beneficial probiotics. Use them as flavor enhancers — not main protein sources — due to their high sodium content.",
];
$tip_of_day = $nutrition_tips[date('z') % count($nutrition_tips)];

// ── Goal label map ────────────────────────────────────────────
$goal_labels = [
    'muscle'      => 'Muscle Gain',
    'weightloss'  => 'Weight Loss',
    'maintenance' => 'Maintenance',
    'performance' => 'Performance',
    'family'      => 'Family Nutrition',
];

// ── Inline form POST handler (non-AJAX fallback) ─────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = clean(post('action'));

    if ($action === 'register') {
        $first_name  = clean(post('first_name'));
        $last_name   = clean(post('last_name'));
        $email       = strtolower(trim(post('email')));
        $password    = post('password');
        $goal        = clean(post('goal', 'maintenance'));
        $household   = max(1, (int) post('household', 1));
        // Custom calorie target — null means auto-calculate from profile
        $raw_kcal    = post('custom_kcal', '');
        $custom_kcal = ($raw_kcal !== '' && (int)$raw_kcal >= 1000 && (int)$raw_kcal <= 5000)
                       ? (int) $raw_kcal : null;
        // Profile data for accurate calorie calculation
        $sex         = in_array(post('sex'), ['male','female']) ? post('sex') : 'male';
        $age         = max(10, min(100, (int) post('age', 25)));
        $weight_kg   = max(30, min(300, (float) post('weight_kg', 65)));
        $height_cm   = max(100, min(250, (float) post('height_cm', 165)));
        $valid_act   = ['sedentary','light','moderate','active','very_active'];
        $activity    = in_array(post('activity'), $valid_act) ? post('activity') : 'moderate';
        // User preferences
        $allergies            = trim(post('allergies', '')) ?: null;
        $excluded_ingredients = trim(post('excluded_ingredients', '')) ?: null;
        $raw_budget           = post('max_weekly_budget', '');
        $max_weekly_budget    = ($raw_budget !== '' && (float)$raw_budget > 0) ? (float)$raw_budget : null;

        $errors = [];
        if (strlen($first_name) < 2)  $errors[] = 'First name too short.';
        if (!valid_email($email))      $errors[] = 'Invalid email address.';
        if (strlen($password) < 6)    $errors[] = 'Password must be at least 6 characters.';

        if (empty($errors)) {
            try {
                $pdo  = db();
                $dup  = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                $dup->execute([$email]);
                if ($dup->fetch()) {
                    $errors[] = 'Email already registered. Try signing in.';
                } else {
                    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                    $pdo->prepare(
                        'INSERT INTO users (first_name,last_name,email,password_hash,goal,household,custom_kcal,sex,age,weight_kg,height_cm,activity)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
                    )->execute([$first_name, $last_name, $email, $hash, $goal, $household, $custom_kcal, $sex, $age, $weight_kg, $height_cm, $activity]);
                    $uid = (int) $pdo->lastInsertId();
                    $pdo->prepare('INSERT INTO user_preferences (user_id, allergies, excluded_ingredients, max_weekly_budget) VALUES (?,?,?,?)')->execute([$uid, $allergies, $excluded_ingredients, $max_weekly_budget]);

                    $row = $pdo->prepare('SELECT * FROM users WHERE id = ?');
                    $row->execute([$uid]);
                    login_user($row->fetch());
                    $user      = current_user();
                    $logged_in = true;
                    $flash_ok  = "Welcome, {$first_name}! Your account is ready.";
                }
            } catch (PDOException $e) {
                $errors[] = 'Server error. Please try again.';
                error_log('[HAPAG index register] ' . $e->getMessage());
            }
        }
        if ($errors) $flash_error = implode(' ', $errors);
    }

    if ($action === 'login') {
        $email    = strtolower(trim(post('email')));
        $password = post('password');
        try {
            $pdo  = db();
            $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
            $stmt->execute([$email]);
            $row = $stmt->fetch();
            if ($row && password_verify($password, $row['password_hash'])) {
                login_user($row);
                $user      = current_user();
                $logged_in = true;
                $flash_ok  = "Welcome back, {$row['first_name']}!";
            } else {
                $flash_error = 'Incorrect email or password.';
            }
        } catch (PDOException $e) {
            $flash_error = 'Server error. Please try again.';
        }
    }
}

// ── Load live Bantay Presyo prices for the price section ──────
$live_prices = [];
try {
    $pdo   = db();
    $prows = $pdo->query(
        'SELECT item_name, category, price_min, price_max, unit, updated_at
         FROM food_prices ORDER BY category, item_name'
    )->fetchAll();
    foreach ($prows as $pr) {
        $live_prices[$pr['category']][] = $pr;
    }
} catch (PDOException $e) {
    // Fail silently — page still works without prices
}

// ── User count for social proof ───────────────────────────────
$total_users = 0;
try {
    $total_users = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>H.A.P.A.G. — Healthy Filipino Meal Planning That Actually Fits Your Budget</title>
  <meta name="description" content="H.A.P.A.G. generates personalized 7-day Filipino meal plans based on your fitness goals — with real-time grocery prices from DA Bantay Presyo so you always know what to spend." />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
  <link rel="stylesheet" href="hapag-styles.css" />
  <style>
    /* ── Flash messages ── */
    .flash { padding: 14px 20px; border-radius: 8px; font-size: .9rem; font-weight: 500;
             margin: 0 0 18px; text-align: center; }
    .flash.ok  { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
    .flash.err { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
    /* ── Logged-in nav badge ── */
    .user-badge { display: flex; align-items: center; gap: 8px; font-size: .85rem;
                  color: var(--green-main, #2d6a4f); }
    .user-badge span { background: #d1fae5; border-radius: 20px; padding: 3px 12px;
                       font-weight: 600; }
    /* ── Tab toggle for login/register ── */
    .form-tabs { display: flex; gap: 0; margin-bottom: 24px; border-radius: 10px;
                 overflow: hidden; border: 1px solid #d0d5dd; }
    .form-tab  { flex: 1; padding: 11px; text-align: center; cursor: pointer;
                 font-size: .9rem; font-weight: 600; background: #f0f4ef; color: #555;
                 border: none; transition: background .2s, color .2s; }
    .form-tab.active { background: #2d6a4f; color: #fff; }
    .form-panel { display: none; }
    .form-panel.active { display: block; }
    /* ── Live price badge ── */
    .live-dot { display: inline-block; width: 8px; height: 8px; background: #22c55e;
                border-radius: 50%; margin-right: 5px; animation: pulse 2s infinite; }
    @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }
    @keyframes shake { 0%,100%{transform:translateX(0)} 20%{transform:translateX(-6px)}
      40%{transform:translateX(6px)} 60%{transform:translateX(-4px)} 80%{transform:translateX(4px)} }

    /* ── Plan success banner ── */
    @keyframes successSlideIn {
      from { opacity: 0; transform: translateY(-10px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    .plan-success-banner {
      animation: successSlideIn .35s ease forwards;
      background: linear-gradient(145deg, #1a3c2e 0%, #1e4d38 60%, #163328 100%);
      border: 1px solid rgba(134,211,154,.25);
      border-radius: 16px;
      padding: 20px 22px 18px;
      box-shadow: 0 8px 32px rgba(0,0,0,.22), 0 0 0 1px rgba(134,211,154,.08) inset;
      text-align: left;
    }
    .plan-success-top {
      display: flex;
      align-items: center;
      gap: 14px;
      margin-bottom: 16px;
    }
    .plan-success-icon {
      width: 42px; height: 42px;
      border-radius: 50%;
      background: linear-gradient(135deg, #22c55e, #16a34a);
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
      font-size: 1.15rem;
      box-shadow: 0 3px 12px rgba(34,197,94,.35);
    }
    .plan-success-title {
      font-family: var(--font-display, 'Playfair Display', serif);
      font-size: 1.15rem;
      font-weight: 700;
      color: #fff;
      line-height: 1.2;
    }
    .plan-success-sub {
      font-size: .8rem;
      color: #86d39a;
      margin-top: 3px;
      font-weight: 500;
      letter-spacing: .01em;
    }
    .plan-success-divider {
      height: 1px;
      background: linear-gradient(90deg, rgba(134,211,154,.25), transparent);
      margin-bottom: 16px;
    }
    .plan-success-meta {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      flex-wrap: wrap;
    }
    .plan-success-week {
      font-size: .75rem;
      color: rgba(255,255,255,.45);
      letter-spacing: .04em;
      text-transform: uppercase;
      font-weight: 600;
    }
    .btn-generate-again {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      background: rgba(255,255,255,.08);
      color: #d1fae5;
      border: 1px solid rgba(134,211,154,.35);
      border-radius: 9px;
      padding: 9px 18px;
      font-size: .83rem;
      font-weight: 600;
      cursor: pointer;
      letter-spacing: .01em;
      transition: background .2s, border-color .2s, color .2s;
      font-family: var(--font-body, 'Inter', sans-serif);
    }
    .btn-generate-again:hover {
      background: rgba(255,255,255,.15);
      border-color: rgba(134,211,154,.6);
      color: #fff;
    }
    .btn-generate-again svg {
      width: 14px; height: 14px;
      stroke: currentColor;
      fill: none;
      stroke-width: 2.2;
      stroke-linecap: round;
      stroke-linejoin: round;
      flex-shrink: 0;
    }

    /* ── Food wallpaper hero (Option 1: dark + blur) ── */
    .hero {
      position: relative;
      overflow: hidden;
    }
    .hero::before {
      content: '';
      position: absolute;
      inset: -30px;
      background: url('HAPAGmainwallpaper.jpg') center / cover no-repeat;
      filter: blur(7px);
      transform: scale(1.06);
      z-index: 0;
    }
    .hero::after {
      content: '';
      position: absolute;
      inset: 0;
      background: rgba(10, 30, 18, 0.80);
      z-index: 1;
    }
    .hero-bg-shape,
    .hero-bg-shape-2 { display: none; }
    .hero-inner       { position: relative; z-index: 2; }

    /* Text colour overrides for dark hero */
    .hero-badge {
      background: rgba(255,255,255,.10);
      border: 1px solid rgba(255,255,255,.22);
      color: #d1fae5;
    }
    .hero-badge .dot { background: #4ade80; }
    .hero-headline            { color: #fff; }
    .hero-headline em         { color: #86efac; }
    .hero-headline .highlight { color: #fff; }
    .hero-sub        { color: rgba(255,255,255,.78); }
    .hero-actions .btn-outline {
      border-color: rgba(255,255,255,.50);
      color: #fff;
    }
    .hero-actions .btn-outline:hover {
      background: rgba(255,255,255,.10);
    }
    .hero-proof-text        { color: rgba(255,255,255,.75); }
    .hero-proof-text strong { color: #fff; }
  </style>
</head>
<body>

<!-- Scroll progress bar -->
<div id="scroll-progress"></div>
<button id="back-to-top" aria-label="Back to top" onclick="window.scrollTo({top:0,behavior:'smooth'})">↑</button>

<!-- ── NAV ── -->
<nav class="site-nav" id="site-nav">
  <div class="nav-inner">
    <a href="#" class="nav-logo">
      <div class="nav-logo-icon">🌿</div>
      <div class="nav-logo-text">H<span>.</span>A<span>.</span>P<span>.</span>A<span>.</span>G<span>.</span></div>
    </a>
    <div class="nav-links">
      <a href="#features">Browse</a>
      <a href="#how-it-works">How It Works</a>
      <a href="#bantay">Prices</a>
      <a href="#testimonials">Reviews</a>
      <a href="#faq">FAQ</a>
    </div>
    <div class="nav-cta">
      <?php if ($logged_in): ?>
        <span class="user-badge">
          👤 <span><?= htmlspecialchars($user['first_name']) ?></span>
        </span>
        <?php if (is_admin()): ?>
          <a href="/HAPAGV11/admin/prices.php" class="btn btn-outline btn-sm">Admin</a>
        <?php endif; ?>
        <a href="/HAPAGV11/api/logout.php" class="nav-login">Sign Out</a>
        <a href="#register" class="btn btn-primary btn-sm">My Meal Plan</a>
      <?php else: ?>
        <a href="#register" class="nav-login">Sign In</a>
        <a href="#register" class="btn btn-primary btn-sm">Get My Meal Plan</a>
      <?php endif; ?>
    </div>
    <button class="nav-hamburger" aria-label="Open menu" id="hamburger">
      <span></span><span></span><span></span>
    </button>
  </div>
</nav>

<!-- Mobile Menu -->
<div class="mobile-menu" id="mobile-menu" style="display:none;">
  <a href="#features"     onclick="closeMobileMenu()">Browse</a>
  <a href="#how-it-works" onclick="closeMobileMenu()">How It Works</a>
  <a href="#bantay"       onclick="closeMobileMenu()">Prices</a>
  <a href="#testimonials" onclick="closeMobileMenu()">Reviews</a>
  <a href="#faq"          onclick="closeMobileMenu()">FAQ</a>
  <a href="#register" class="btn btn-primary" style="text-align:center;margin-top:.5rem;" onclick="closeMobileMenu()">Get My Meal Plan</a>
</div>


<!-- ── HERO ── -->
<section class="hero">
  <div class="hero-bg-shape"></div>
  <div class="hero-bg-shape-2"></div>
  <div class="hero-inner">
    <div class="hero-content">
      <div class="hero-badge animate-on-load">
        <span class="dot"></span>
        Now with live DA Bantay Presyo pricing
      </div>
      <h1 class="hero-headline animate-on-load delay-1">
        Eat <em>Healthy Filipino</em> Food —
        Without Blowing Your <span class="highlight">Budget</span>
      </h1>
      <p class="hero-sub animate-on-load delay-2">
        H.A.P.A.G. builds your personalized 7-day meal plan using real Filipino recipes — chicken adobo, grilled bangus, fish sinigang — balanced to your fitness goals and priced against live market data so you always know what to spend.
      </p>
      <div class="hero-actions animate-on-load delay-3">
        <a href="#register" class="btn btn-primary btn-lg">Build My Meal Plan &rarr;</a>
        <a href="#how-it-works" class="btn btn-outline btn-lg">See How It Works</a>
      </div>

      <!-- Social proof — shown when users > 1 -->
      <?php if ($total_users > 1): ?>
      <div class="hero-social-proof animate-on-load delay-4">
        <div class="hero-avatars">
          <div class="avatar a1">MR</div>
          <div class="avatar a2">LA</div>
          <div class="avatar a3">JP</div>
        </div>
        <div class="hero-proof-text">
          <strong>
            <?= $total_users >= 1000
              ? 'Join ' . number_format($total_users / 1000, 1) . 'k Filipinos'
              : "Join {$total_users} Filipinos" ?>
          </strong>
          eating smarter, spending less
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Hero visual card -->
    <div class="hero-visual animate-on-load delay-2">

      <?php if ($logged_in && $user && !empty($user_macros)): ?>
      <!-- ── LOGGED-IN: Personalized Stats Card ── -->
      <?php
        $u_goal      = $goal_labels[$user['goal'] ?? 'maintenance'] ?? 'Maintenance';
        $u_kcal      = $user_macros['kcal'];
        $u_protein   = $user_macros['protein'];
        $u_carbs     = $user_macros['carbs'];
        $u_fat       = $user_macros['fat'];
        $u_household = (int)($user['household'] ?? 1);
        $hh_label    = match(true) {
          $u_household >= 5 => 'Large Family (5+)',
          $u_household >= 3 => 'Small Family (3–4)',
          $u_household == 2 => 'Couple (2)',
          default           => 'Just Me',
        };
        $activity_labels = [
          'sedentary'  => 'Sedentary',
          'light'      => 'Lightly Active',
          'moderate'   => 'Moderately Active',
          'active'     => 'Active',
          'very_active'=> 'Very Active',
        ];
        $u_activity = $activity_labels[$user['activity'] ?? 'moderate'] ?? 'Moderate';
      ?>
      <div class="hero-float-badge">👤 <?= htmlspecialchars($user['first_name']) ?>'s Profile</div>
      <div class="hero-card" style="overflow:hidden;">

        <!-- Card header: goal + kcal -->
        <div class="hero-card-header" style="padding-bottom:14px;">
          <div class="week-label" style="margin-bottom:6px;">Your Nutrition Targets · <?= date('F Y') ?></div>
          <h4 style="margin-bottom:10px;"><?= $u_goal ?> · <?= number_format($u_kcal) ?> kcal/day</h4>
          <div class="hero-card-macro">
            <div class="macro-pill">🥩 <?= $u_protein ?>g Protein</div>
            <div class="macro-pill">🍚 <?= $u_carbs ?>g Carbs</div>
            <div class="macro-pill">🥑 <?= $u_fat ?>g Fat</div>
          </div>
        </div>

        <!-- Stats rows -->
        <div style="padding:0 20px;">
          <div style="display:flex;flex-direction:column;gap:0;">

            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #f0f5f2;">
              <span style="font-size:.78rem;color:#888;display:flex;align-items:center;gap:6px;">🎯 <span>Goal</span></span>
              <span style="font-size:.83rem;font-weight:600;color:#1a1a1a;"><?= $u_goal ?></span>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #f0f5f2;">
              <span style="font-size:.78rem;color:#888;display:flex;align-items:center;gap:6px;">🔥 <span>Daily Calories</span></span>
              <span style="font-size:.83rem;font-weight:600;color:#2d6a4f;"><?= number_format($u_kcal) ?> kcal</span>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #f0f5f2;">
              <span style="font-size:.78rem;color:#888;display:flex;align-items:center;gap:6px;">👨‍👩‍👧 <span>Household</span></span>
              <span style="font-size:.83rem;font-weight:600;color:#1a1a1a;"><?= $hh_label ?></span>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;">
              <span style="font-size:.78rem;color:#888;display:flex;align-items:center;gap:6px;">🏃 <span>Activity Level</span></span>
              <span style="font-size:.83rem;font-weight:600;color:#1a1a1a;"><?= $u_activity ?></span>
            </div>

          </div>
        </div>

        <!-- Tip of the Day -->
        <div style="margin:12px 16px 14px;background:linear-gradient(135deg,#f0fdf4,#ecfdf5);border:1px solid #bbf7d0;border-radius:12px;padding:12px 14px;">
          <div style="font-size:.65rem;font-weight:700;color:#16a34a;letter-spacing:.07em;text-transform:uppercase;margin-bottom:5px;">💡 Filipino Nutrition Tip</div>
          <p style="font-size:.74rem;color:#374151;line-height:1.5;margin:0;"><?= htmlspecialchars($tip_of_day) ?></p>
        </div>

        <!-- CTA button -->
        <div style="padding:0 16px 18px;">
          <a href="#register" style="
            display:block;text-align:center;
            background:linear-gradient(135deg,#2d6a4f,#1e4d38);
            color:#fff;font-weight:700;font-size:.88rem;
            border-radius:10px;padding:13px;
            text-decoration:none;letter-spacing:.01em;
            box-shadow:0 4px 14px rgba(45,106,79,.35);
            transition:transform .15s,box-shadow .15s;
          " onmouseover="this.style.transform='translateY(-1px)';this.style.boxShadow='0 6px 18px rgba(45,106,79,.45)'"
             onmouseout="this.style.transform='';this.style.boxShadow='0 4px 14px rgba(45,106,79,.35)'">
            🍽 Generate My Meal Plan &rarr;
          </a>
        </div>

      </div>

      <?php else: ?>
      <!-- ── GUEST: Static preview card ── -->
      <div class="hero-float-badge">✓ This week's plan ready</div>
      <div class="hero-card">
        <div class="hero-card-header">
          <div class="week-label">Your Weekly Plan · <?= date('F Y') ?></div>
          <h4>Muscle Gain · 2,400 kcal/day</h4>
          <div class="hero-card-macro">
            <div class="macro-pill">🥩 185g Protein</div>
            <div class="macro-pill">🍚 280g Carbs</div>
            <div class="macro-pill">🥑 70g Fat</div>
          </div>
        </div>
        <div class="hero-card-meals">
          <div class="meal-day"><div class="meal-day-label">MON</div><div class="meal-day-food"><div class="food-name">Chicken Adobo + Kanin</div><div class="food-desc">635 kcal · 48g protein</div></div><div class="meal-day-price">₱85</div></div>
          <div class="meal-day"><div class="meal-day-label">TUE</div><div class="meal-day-food"><div class="food-name">Grilled Bangus + Salad</div><div class="food-desc">520 kcal · 42g protein</div></div><div class="meal-day-price">₱72</div></div>
          <div class="meal-day"><div class="meal-day-label">WED</div><div class="meal-day-food"><div class="food-name">Fish Sinigang + Kangkong</div><div class="food-desc">490 kcal · 38g protein</div></div><div class="meal-day-price">₱68</div></div>
          <div class="meal-day"><div class="meal-day-label">THU</div><div class="meal-day-food"><div class="food-name">Tinolang Manok + Malunggay</div><div class="food-desc">580 kcal · 44g protein</div></div><div class="meal-day-price">₱78</div></div>
          <div class="meal-day"><div class="meal-day-label">FRI</div><div class="meal-day-food"><div class="food-name">Pinakbet + Brown Rice</div><div class="food-desc">420 kcal · 22g protein</div></div><div class="meal-day-price">₱55</div></div>
        </div>
        <div class="hero-card-footer">
          <div class="footer-label">Estimated weekly grocery cost</div>
          <div class="footer-total">₱1,240 total</div>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </div>
</section>


<!-- ── TRUST BAR ── -->
<div class="trust-bar">
  <div class="trust-bar-inner">
    <div class="trust-item"><span class="trust-icon">🏛️</span>Pricing from <strong>&nbsp;DA Bantay Presyo</strong></div>
    <div class="trust-divider"></div>
    <div class="trust-item"><span class="trust-icon">🧪</span><strong>FNRI-based</strong>&nbsp; nutritional guidelines</div>
    <div class="trust-divider"></div>
    <div class="trust-item"><span class="trust-icon">🌿</span>100% <strong>&nbsp;authentic Filipino</strong>&nbsp; recipes</div>
    <div class="trust-divider"></div>
    <div class="trust-item"><span class="trust-icon">📱</span>Works on any <strong>&nbsp;web browser</strong></div>
    <div class="trust-divider"></div>
    <div class="trust-item"><span class="trust-icon">🔒</span><strong>Free</strong>&nbsp; to get started</div>
    <div class="trust-divider"></div>
    <!-- Duplicate for loop -->
    <div class="trust-item"><span class="trust-icon">🏛️</span>Pricing from <strong>&nbsp;DA Bantay Presyo</strong></div>
    <div class="trust-divider"></div>
    <div class="trust-item"><span class="trust-icon">🧪</span><strong>FNRI-based</strong>&nbsp; nutritional guidelines</div>
    <div class="trust-divider"></div>
    <div class="trust-item"><span class="trust-icon">🌿</span>100% <strong>&nbsp;authentic Filipino</strong>&nbsp; recipes</div>
    <div class="trust-divider"></div>
    <div class="trust-item"><span class="trust-icon">📱</span>Works on any <strong>&nbsp;web browser</strong></div>
    <div class="trust-divider"></div>
    <div class="trust-item"><span class="trust-icon">🔒</span><strong>Free</strong>&nbsp; to get started</div>
  </div>
</div>


<!-- ── BANTAY PRESYO SECTION (Live from DB) ── -->
<section class="bantay-section" id="bantay">
  <div class="bantay-inner">
    <div class="bantay-content">
      <span class="section-label">Real Prices, Real Savings</span>
      <h2>Meal Plans That Know What's Cheap at the Market Today</h2>
      <p>Rising food prices are one of the biggest barriers to eating healthy in the Philippines. H.A.P.A.G. integrates live price data from the Department of Agriculture's Bantay Presyo monitoring system — so every grocery list reflects what things actually cost right now, not last month's estimates.</p>
      <div class="bantay-points">
        <div class="bantay-point"><div class="bantay-point-icon">📊</div><div class="bantay-point-body"><h4>Live Market Price Integration</h4><p>Grocery costs are pulled from official DA Bantay Presyo data and updated regularly — no guesswork.</p></div></div>
        <div class="bantay-point"><div class="bantay-point-icon">🛒</div><div class="bantay-point-body"><h4>Budget-Aware Meal Suggestions</h4><p>If bangus is expensive this week, the system recommends equally nutritious, more affordable alternatives.</p></div></div>
        <div class="bantay-point"><div class="bantay-point-icon">📋</div><div class="bantay-point-body"><h4>Categorized Grocery Lists</h4><p>Your shopping list is sorted by market section — proteins, vegetables, condiments — with cost estimates per item.</p></div></div>
      </div>
    </div>

    <!-- Live Price Cards from DB -->
    <div class="bantay-visual">
      <?php
      $show_cats = [
        'fish'      => '🐟 Protein: Fish',
        'meat'      => '🥩 Protein: Meat',
        'vegetable' => '🥬 Vegetables',
      ];
      foreach ($show_cats as $cat => $label):
        if (empty($live_prices[$cat])) continue;
      ?>
      <div class="price-card">
        <div class="price-card-header">
          <div class="price-card-title"><?= $label ?></div>
          <div class="price-update">
            <span class="live-dot"></span>Live · <?= date('M j') ?>
          </div>
        </div>
        <div class="price-items">
          <?php foreach (array_slice($live_prices[$cat], 0, 3) as $pi): ?>
          <div class="price-row">
            <div class="price-item-name"><?= htmlspecialchars($pi['item_name']) ?> <small style="color:#999">(<?= htmlspecialchars($pi['unit']) ?>)</small></div>
            <div class="price-item-val">₱<?= number_format($pi['price_min']) ?>–₱<?= number_format($pi['price_max']) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

  </div>
</section>


<!-- ── FEATURES ── -->
<section class="features-section" id="features">
  <div class="features-header">
    <span class="section-label">What You Get</span>
    <h2>Everything You Need to Eat Right, Spend Less</h2>
    <p class="lead text-center" style="margin:0 auto;">Not just another calorie counter. H.A.P.A.G. is a full end-to-end planning system built around how Filipinos actually eat.</p>
  </div>
  <div class="features-grid">
    <div class="feature-card"><div class="feature-icon">🎯</div><h3>Personalized to Your Goal</h3><p>Tell us if you're building muscle, losing weight, or eating for a condition — and we'll generate a weekly plan designed around your exact macronutrient targets, not a generic template.</p><div class="feature-result">You get a 7-day plan tailored to you, ready in minutes</div></div>
    <div class="feature-card gold"><div class="feature-icon">💰</div><h3>Real-Time Grocery Budgeting</h3><p>Every recipe includes a cost estimate based on live DA Bantay Presyo data. You'll know exactly how much the week's groceries will cost before you step into the market.</p><div class="feature-result" style="color:var(--gold-dark);">You know your weekly food spend before shopping</div></div>
    <div class="feature-card terracotta"><div class="feature-icon">🍛</div><h3>Filipino Recipes You'll Love</h3><p>From adobo and sinigang to tinola and pinakbet — all recipes use traditional cooking methods and ingredients you can find at any palengke.</p><div class="feature-result" style="color:var(--warning);">You enjoy food that feels like home, not a diet</div></div>
    <div class="feature-card"><div class="feature-icon">🔄</div><h3>Smart Substitutions</h3><p>Don't like vegetables? Prefer tofu? Allergic to shellfish? The system adapts your plan using smart ingredient substitutions that maintain your nutritional targets.</p><div class="feature-result">Your preferences are respected, not ignored</div></div>
    <div class="feature-card gold"><div class="feature-icon">👨‍👩‍👧</div><h3>Scales for Your Household</h3><p>Planning for yourself, a couple, or a family? H.A.P.A.G. automatically adjusts serving sizes and ingredient quantities — and recalculates the grocery list to match.</p><div class="feature-result" style="color:var(--gold-dark);">One plan, right portions for everyone at the table</div></div>
    <div class="feature-card terracotta"><div class="feature-icon">📊</div><h3>Macro &amp; Nutrition Breakdown</h3><p>Every meal comes with a full nutritional breakdown: calories, protein, carbs, fat, and key micronutrients. Track your progress as a registered user and see your consistency over time.</p><div class="feature-result" style="color:var(--warning);">You see exactly what you're eating and why it works</div></div>
  </div>
</section>


<!-- ── HOW IT WORKS ── -->
<section class="how-section" id="how-it-works">
  <div class="container">
    <div class="how-header">
      <span class="section-label white">Simple by Design</span>
      <h2>From Goals to Grocery List in 4 Steps</h2>
      <p>No complicated setups. No confusing dashboards. H.A.P.A.G. asks what it needs and delivers what you want.</p>
    </div>
    <div class="how-steps">
      <div class="how-step"><div class="step-num">1</div><h4>Tell Us About You</h4><p>Share your fitness goal, health conditions, household size, and food preferences. It takes under 3 minutes.</p></div>
      <div class="how-step"><div class="step-num">2</div><h4>We Build Your Plan</h4><p>Our system calculates your macros, selects balanced Filipino recipes, and checks real-time grocery prices.</p></div>
      <div class="how-step"><div class="step-num">3</div><h4>Review Your Meals</h4><p>See your full 7-day menu with recipes, nutritional breakdowns, and cooking instructions for each dish.</p></div>
      <div class="how-step"><div class="step-num">4</div><h4>Shop and Cook</h4><p>Download your categorized grocery list with estimated costs and head to the market knowing exactly what to buy.</p></div>
    </div>
  </div>
</section>


<!-- ── BENEFITS ── -->
<section class="benefits-section">
  <div class="benefits-header">
    <span class="section-label">Why It Works</span>
    <h2>Real Results, Not Just Good Intentions</h2>
    <p class="lead text-center" style="margin:0 auto;">H.A.P.A.G. is built on FNRI nutritional science and designed around the food culture Filipinos already love.</p>
  </div>
  <div class="benefits-grid">
    <div class="benefit-card"><div class="benefit-stat">₱<span>850</span></div><div style="font-family:var(--font-display);font-size:1.1rem;font-weight:600;color:var(--green-main);margin-bottom:.5rem;">Average weekly spend</div><h3>Healthy Eating on a Filipino Budget</h3><p>By aligning menus with what's currently affordable at the market, users spend significantly less than cooking without a plan — while still hitting their macro targets.</p></div>
    <div class="benefit-card"><div class="benefit-stat">7<span>-day</span></div><div style="font-family:var(--font-display);font-size:1.1rem;font-weight:600;color:var(--green-main);margin-bottom:.5rem;">Complete plans, always fresh</div><h3>No More "What's for Dinner?" Stress</h3><p>A full week of meals — breakfast, lunch, and dinner — planned, nutritionally verified, and costed before Monday even starts.</p></div>
    <div class="benefit-card"><div class="benefit-stat">100<span>%</span></div><div style="font-family:var(--font-display);font-size:1.1rem;font-weight:600;color:var(--green-main);margin-bottom:.5rem;">Filipino recipes, every meal</div><h3>Food That Feels Like Home</h3><p>Every recipe uses ingredients from your local palengke, prepared using cooking methods your family already knows.</p></div>
  </div>
</section>


<!-- ── TESTIMONIALS ── -->
<section class="testimonials-section" id="testimonials">
  <div class="testimonials-header">
    <span class="section-label">Real Stories</span>
    <h2>What Filipinos Are Saying About H.A.P.A.G.</h2>
    <p class="lead text-center" style="margin:0 auto;">From students to athletes to full families — here's how H.A.P.A.G. is helping people eat better without the stress.</p>
  </div>
  <div class="testimonials-grid" id="testimonials-grid">
    <!-- Injected by JS below -->
  </div>
</section>


<!-- ── ABOUT ── -->
<section class="about-snippet" id="about">
  <div class="about-inner">
    <div class="about-content">
      <span class="section-label">Our Story</span>
      <h2>Why We Built H.A.P.A.G.</h2>
      <p><em>Hapag</em> is the Filipino word for the dining table — the place where family gathers, where food becomes connection. We chose that name intentionally.</p>
      <p>We started this project after noticing a frustrating gap: most nutrition apps assume you have access to a Western supermarket and a generous grocery budget. For millions of Filipinos trying to eat healthier, neither is true.</p>
      <p>H.A.P.A.G. was built to change that — combining proper nutritional science with the food you already love, at prices you can actually afford.</p>
      <div class="values-list">
        <div class="value-item"><div class="value-body"><h4>🌿 Accessibility First</h4><p>Healthy eating should be within reach of every Filipino household, not just those with high incomes or specialty stores nearby.</p></div></div>
        <div class="value-item gold"><div class="value-body"><h4>🍛 Culture is Nutrition</h4><p>Traditional Filipino dishes are nutritionally rich — malunggay, bangus, kamote — and H.A.P.A.G. honors that instead of replacing it.</p></div></div>
        <div class="value-item terra"><div class="value-body"><h4>📊 Science You Can Trust</h4><p>Every plan is grounded in FNRI nutritional guidelines so you can be confident your meals are genuinely balanced, not just marketed that way.</p></div></div>
      </div>
    </div>
    <div class="about-stats">
      <div class="about-stat-card featured"><div class="stat-number">DA Bantay Presyo</div><div class="stat-label">Our grocery prices come directly from the Department of Agriculture's official price monitoring system — updated regularly to reflect real market conditions across the Philippines.</div></div>
      <div class="about-stat-card"><div class="stat-number" data-count="100" data-suffix="+">100+</div><div class="stat-label">Filipino recipes in our database</div></div>
      <div class="about-stat-card"><div class="stat-number" data-count="4">4</div><div class="stat-label">User types supported, from solo to family</div></div>
      <div class="about-stat-card"><div class="stat-number" data-count="7">7</div><div class="stat-label">Days fully planned, from breakfast to dinner</div></div>
      <div class="about-stat-card"><div class="stat-number" data-prefix="₱" data-count="0">₱0</div><div class="stat-label">Cost to get started and generate your first plan</div></div>
    </div>
  </div>
</section>


<!-- ── FAQ ── -->
<section class="faq-section" id="faq">
  <div class="faq-header">
    <span class="section-label">Quick Answers</span>
    <h2>Questions We Hear Often</h2>
    <p class="lead text-center" style="margin:0 auto;">Honest answers about what H.A.P.A.G. is, how it works, and what to expect.</p>
  </div>
  <div class="faq-list">
    <div class="faq-item open"><button class="faq-question" onclick="toggleFaq(this)">Is H.A.P.A.G. really free to use? <span class="faq-icon">+</span></button><div class="faq-answer"><p>Yes. You can register for free and generate your first personalized meal plan at no cost. We may offer premium features in the future, but the core planning and grocery list functionality will always have a free tier.</p></div></div>
    <div class="faq-item"><button class="faq-question" onclick="toggleFaq(this)">Where does the grocery pricing data come from? <span class="faq-icon">+</span></button><div class="faq-answer"><p>All grocery prices are sourced from the Department of Agriculture's official Bantay Presyo (Price Watch) monitoring system. Admins update the data regularly to ensure accuracy.</p></div></div>
    <div class="faq-item"><button class="faq-question" onclick="toggleFaq(this)">What if I don't like certain vegetables or foods? <span class="faq-icon">+</span></button><div class="faq-answer"><p>H.A.P.A.G. has a built-in substitution system. When you set up your profile, you can flag ingredients or food categories you want to avoid.</p></div></div>
    <div class="faq-item"><button class="faq-question" onclick="toggleFaq(this)">Can it plan meals for my whole family, not just myself? <span class="faq-icon">+</span></button><div class="faq-answer"><p>Yes. During setup, you specify your household size — solo, couple, or family. The system automatically scales all recipe serving sizes and recalculates the grocery list to match.</p></div></div>
    <div class="faq-item"><button class="faq-question" onclick="toggleFaq(this)">I have a health condition. Can H.A.P.A.G. still work for me? <span class="faq-icon">+</span></button><div class="faq-answer"><p>H.A.P.A.G. accepts health conditions as part of your input profile and adjusts macro targets accordingly. We are a meal planning tool, not a medical service — always consult a licensed nutritionist for serious health concerns.</p></div></div>
    <div class="faq-item"><button class="faq-question" onclick="toggleFaq(this)">Do I need to download an app? <span class="faq-icon">+</span></button><div class="faq-answer"><p>No app required. H.A.P.A.G. is fully web-based and works in any modern browser on your phone, tablet, or computer.</p></div></div>
  </div>
</section>


<!-- ── REGISTER / LOGIN SECTION ── -->
<section class="cta-section" id="register">
  <div class="cta-inner">
    <span class="section-label white">Get Started Today</span>
    <h2>Your Personalized Filipino Meal Plan Is One Step Away</h2>
    <p>Join thousands of Filipinos eating smarter. It's free, it takes 3 minutes, and your week of healthy meals will be ready before you finish your morning coffee.</p>

    <?php if ($logged_in): ?>
    <!-- Already logged in -->
    <div class="reg-form" style="text-align:center;">
      <h3>Welcome back, <?= htmlspecialchars($user['first_name']) ?>! 👋</h3>
      <p class="form-sub">Your account is active. Generate your meal plan for this week.</p>

      <!-- Primary generate button (hidden after success) -->
      <button class="btn-submit" id="gen-plan-btn" style="margin-top:24px;"
        onclick="generateMealPlan()">
        🍽 Generate This Week's Meal Plan →
      </button>

      <!-- Success banner + regenerate (hidden until plan is ready) -->
      <div id="plan-success-bar" style="display:none;margin-top:20px;">
        <div class="plan-success-banner">

          <!-- Top row: icon + title -->
          <div class="plan-success-top">
            <div class="plan-success-icon">✓</div>
            <div>
              <div class="plan-success-title">Your Meal Plan is Ready</div>
              <div class="plan-success-sub">↓ &nbsp;Scroll down to see your full week</div>
            </div>
          </div>

          <!-- Divider -->
          <div class="plan-success-divider"></div>

          <!-- Bottom row: week label + Generate Again -->
          <div class="plan-success-meta">
            <span class="plan-success-week">Week of <?= date('Y-m-d') ?></span>
            <button class="btn-generate-again" onclick="generateMealPlan()">
              <svg viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
              Generate Again
            </button>
          </div>

        </div>
      </div>

      <!-- Plan rendered here -->
      <div id="plan-result" style="margin-top:20px;text-align:left;"></div>

      <div style="margin-top:16px;">
        <a href="/HAPAGV11/api/logout.php" style="color:#a8d5b5;font-size:.85rem;">Sign Out</a>
      </div>
    </div>

    <?php else: ?>
    <!-- Auth tabs -->
    <div class="reg-form">
      <?php if ($flash_error): ?><div class="flash err"><?= htmlspecialchars($flash_error) ?></div><?php endif; ?>
      <?php if ($flash_ok):    ?><div class="flash ok"><?= htmlspecialchars($flash_ok) ?></div><?php endif; ?>

      <div class="form-tabs">
        <button class="form-tab active" onclick="switchTab('register',this)">Create Account</button>
        <button class="form-tab" onclick="switchTab('login',this)">Sign In</button>
      </div>

      <!-- Register Panel -->
      <div class="form-panel active" id="tab-register">
        <h3>Create Your Free Account</h3>
        <p class="form-sub">No credit card required · Takes about 3 minutes</p>
        <form method="POST" id="register-form">
          <input type="hidden" name="action" value="register" />
          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="first-name">First Name</label>
              <input class="form-input" type="text" id="first-name" name="first_name" placeholder="Maria" required />
            </div>
            <div class="form-group">
              <label class="form-label" for="last-name">Last Name</label>
              <input class="form-input" type="text" id="last-name" name="last_name" placeholder="Santos" />
            </div>
          </div>
          <div class="form-group">
            <label class="form-label" for="email">Email Address</label>
            <input class="form-input" type="email" id="email" name="email" placeholder="maria@email.com" required />
          </div>
          <div class="form-group">
            <label class="form-label" for="password">Password</label>
            <input class="form-input" type="password" id="password" name="password" placeholder="At least 6 characters" required />
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="goal">My Main Goal</label>
              <select class="form-input form-select" id="goal" name="goal" required>
                <option value="" disabled selected>Choose a goal…</option>
                <option value="muscle">Build Muscle</option>
                <option value="weightloss">Lose Weight</option>
                <option value="maintenance">Maintain Weight</option>
                <option value="performance">Improve Performance</option>
                <option value="family">Family Nutrition</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label" for="household">Household Size</label>
              <select class="form-input form-select" id="household" name="household">
                <option value="" disabled selected>Serving…</option>
                <option value="1">Just Me</option>
                <option value="2">Couple (2)</option>
                <option value="3">Small Family (3–4)</option>
                <option value="5">Large Family (5+)</option>
              </select>
            </div>
          </div>
          <!-- Profile fields for accurate calorie calculation -->
          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="sex">Biological Sex</label>
              <select class="form-input form-select" id="sex" name="sex" required>
                <option value="male" selected>Male</option>
                <option value="female">Female</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label" for="age">Age</label>
              <input class="form-input" type="number" id="age" name="age" min="10" max="100" placeholder="25" required />
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="weight_kg">Weight (kg)</label>
              <input class="form-input" type="number" id="weight_kg" name="weight_kg" min="30" max="300" step="0.1" placeholder="65.0" required />
            </div>
            <div class="form-group">
              <label class="form-label" for="height_cm">Height (cm)</label>
              <input class="form-input" type="number" id="height_cm" name="height_cm" min="100" max="250" step="0.1" placeholder="165.0" required />
            </div>
          </div>
          <div class="form-group">
            <label class="form-label" for="activity">Activity Level</label>
            <select class="form-input form-select" id="activity" name="activity" required>
              <option value="sedentary">Sedentary (desk job, little exercise)</option>
              <option value="light">Light (1–3 days/week exercise)</option>
              <option value="moderate" selected>Moderate (3–5 days/week)</option>
              <option value="active">Active (6–7 days/week)</option>
              <option value="very_active">Very Active (athlete / physical job)</option>
            </select>
          </div>
          <!-- Calorie target (optional override) -->
          <div class="form-group" style="margin-top:4px;">
            <label class="form-label" for="custom_kcal" style="display:flex;justify-content:space-between;align-items:center;">
              <span>Daily Calorie Target <span style="color:#aaa;font-weight:400;">(optional override)</span></span>
              <span id="kcal-hint" style="font-size:.72rem;color:#52a97a;font-weight:500;"></span>
            </label>
            <div style="display:flex;align-items:center;gap:8px;">
              <input class="form-input" type="number" id="custom_kcal" name="custom_kcal"
                min="1000" max="5000" step="50"
                placeholder="e.g. 1700 — leave blank to auto-calculate"
                style="flex:1;" />
              <span style="font-size:.8rem;color:#aaa;white-space:nowrap;">kcal/day</span>
            </div>
            <div style="font-size:.72rem;color:#aaa;margin-top:4px;">Leave blank and we'll compute it from your profile above. You can change this anytime.</div>
          </div>

          <!-- User preferences -->
          <div class="form-group" style="margin-top:4px;">
            <label class="form-label" for="allergies">Allergies <span style="color:#aaa;font-weight:400;">(optional)</span></label>
            <input class="form-input" type="text" id="allergies" name="allergies"
              placeholder="e.g. shrimp, peanuts, shellfish" />
            <div style="font-size:.72rem;color:#aaa;margin-top:4px;">Separate multiple items with a comma.</div>
          </div>
          <div class="form-group" style="margin-top:4px;">
            <label class="form-label" for="excluded_ingredients">Ingredients to Avoid <span style="color:#aaa;font-weight:400;">(optional)</span></label>
            <input class="form-input" type="text" id="excluded_ingredients" name="excluded_ingredients"
              placeholder="e.g. ampalaya, pork, gata" />
            <div style="font-size:.72rem;color:#aaa;margin-top:4px;">Foods you dislike or want excluded from your plan.</div>
          </div>
          <div class="form-group" style="margin-top:4px;">
            <label class="form-label" for="max_weekly_budget">Max Weekly Grocery Budget <span style="color:#aaa;font-weight:400;">(optional)</span></label>
            <div style="display:flex;align-items:center;gap:8px;">
              <span style="font-size:.9rem;color:#555;">₱</span>
              <input class="form-input" type="number" id="max_weekly_budget" name="max_weekly_budget"
                min="100" max="99999" step="50" placeholder="e.g. 1500" style="flex:1;" />
              <span style="font-size:.8rem;color:#aaa;white-space:nowrap;">/ week</span>
            </div>
            <div style="font-size:.72rem;color:#aaa;margin-top:4px;">We'll keep your meal plan within this budget.</div>
          </div>

          <div class="form-check">
            <input type="checkbox" id="terms" name="terms" required />
            <label for="terms">I agree to the <a href="#" onclick="openModal('termsModal');return false;">Terms of Service</a> and <a href="#" onclick="openModal('privacyModal');return false;">Privacy Policy</a>. I understand this is a planning tool and not a substitute for professional medical advice.</label>
          </div>
          <button type="submit" class="btn-submit" id="submit-btn">Build My Free Meal Plan →</button>
          <div class="form-note">🔒 Your data is private and never sold. Free forever for core features.</div>
        </form>
      </div>

      <!-- Login Panel -->
      <div class="form-panel" id="tab-login">
        <h3>Welcome Back</h3>
        <p class="form-sub">Sign in to access your meal plans</p>
        <form method="POST" id="login-form">
          <input type="hidden" name="action" value="login" />
          <div class="form-group">
            <label class="form-label" for="login-email">Email Address</label>
            <input class="form-input" type="email" id="login-email" name="email" placeholder="maria@email.com" required />
          </div>
          <div class="form-group">
            <label class="form-label" for="login-password">Password</label>
            <input class="form-input" type="password" id="login-password" name="password" placeholder="Your password" required />
          </div>
          <button type="submit" class="btn-submit">Sign In →</button>
        </form>
      </div>
    </div>
    <?php endif; ?>

  </div>
</section>


<!-- ── FOOTER ── -->
<footer class="site-footer">
  <div class="footer-inner">
    <div class="footer-top">
      <div class="footer-brand">
        <div class="nav-logo">
          <div class="nav-logo-icon">🌿</div>
          <div class="nav-logo-text">H<span>.</span>A<span>.</span>P<span>.</span>A<span>.</span>G<span>.</span></div>
        </div>
        <p>Healthy and Affordable Personalized Automated Goal-Oriented Meal Planning System. Built for Filipinos, grounded in nutritional science, powered by real market prices.</p>
        <div class="footer-socials">
          <a href="#" class="footer-social-link" aria-label="Facebook"><svg viewBox="0 0 24 24" fill="none"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
          <a href="#" class="footer-social-link" aria-label="Instagram"><svg viewBox="0 0 24 24" fill="none"><rect x="2" y="2" width="20" height="20" rx="5" ry="5" stroke-linecap="round" stroke-linejoin="round"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z" stroke-linecap="round" stroke-linejoin="round"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
          <a href="#" class="footer-social-link" aria-label="Email"><svg viewBox="0 0 24 24" fill="none"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" stroke-linecap="round" stroke-linejoin="round"/><polyline points="22,6 12,13 2,6" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
        </div>
      </div>

      <!-- ── PRODUCT COLUMN — links now open the tabbed modal ── -->
      <div class="footer-col"><h5>Product</h5><ul>
        <li><a href="#" onclick="openProductModal('meal-plans');return false;">Meal Plans</a></li>
        <li><a href="#" onclick="openProductModal('recipe-library');return false;">Recipe Library</a></li>
        <li><a href="#" onclick="openProductModal('nutrition-tracker');return false;">Nutrition Tracker</a></li>
        <li><a href="#" onclick="openProductModal('budget-planner');return false;">Budget Planner</a></li>
      </ul></div>

      <div class="footer-col"><h5>Resources</h5><ul><li><a href="https://www.da.gov.ph" target="_blank" rel="noopener">Blog</a></li><li><a href="https://www.fnri.dost.gov.ph" target="_blank" rel="noopener">Filipino Cuisine Guide</a></li><li><a href="#bantay">Palengke Price Index</a></li><li><a href="#faq">FAQs</a></li></ul></div>
      <div class="footer-col"><h5>Company</h5><ul><li><a href="#about">About H.A.P.A.G.</a></li><li><a href="#" onclick="openModal('privacyModal');return false;">Privacy Policy</a></li><li><a href="#" onclick="openModal('termsModal');return false;">Terms of Service</a></li><li><a href="mailto:hapag@hapag.ph">Contact Us</a></li></ul></div>
    </div>
    <div class="footer-bottom">
      <div class="footer-copy">
        © <?= date('Y') ?> H.A.P.A.G. All rights reserved. Pricing data sourced from DA Bantay Presyo. &nbsp;·&nbsp;
        <a href="#" onclick="openModal('privacyModal');return false;">Privacy Policy</a> &nbsp;·&nbsp;
        <a href="#" onclick="openModal('termsModal');return false;">Terms of Service</a>
      </div>
      <div class="footer-da-badge">🏛️ Partnered with DA Bantay Presyo Data</div>
    </div>
  </div>
</footer>


<!-- ── PRIVACY POLICY MODAL ── -->
<div id="privacyModal" class="hapag-modal-overlay" onclick="if(event.target===this)closeModal('privacyModal')" style="display:none;">
  <div class="hapag-modal-box">
    <button class="hapag-modal-close" onclick="closeModal('privacyModal')" aria-label="Close">&times;</button>
    <h3 class="hapag-modal-title">Privacy Policy</h3>
    <p class="hapag-modal-date">Effective: January 1, 2026</p>
    <div class="hapag-modal-body">
      <h4>1. Information We Collect</h4>
      <p>H.A.P.A.G. collects information you provide during registration, including your name, email address, health goals, household size, and dietary preferences. This information is used solely to generate personalized meal plans tailored to your needs.</p>
      <h4>2. How We Use Your Data</h4>
      <p>Your data is used to generate weekly meal plans, track nutritional goals, and improve your experience on the platform. We do not sell, trade, or share your personal information with third parties for marketing purposes. Grocery pricing data is sourced from the Department of Agriculture's DA Bantay Presyo system and is not linked to your personal profile.</p>
      <h4>3. Data Security &amp; Your Rights</h4>
      <p>We implement standard security practices to protect your data. You may request access to, correction of, or deletion of your personal information at any time by contacting us at <a href="mailto:hapag@hapag.ph">hapag@hapag.ph</a>. H.A.P.A.G. is a planning tool and does not store sensitive medical or financial records. This policy may be updated periodically; continued use of the platform constitutes acceptance of any changes.</p>
    </div>
  </div>
</div>

<!-- ── TERMS OF SERVICE MODAL ── -->
<div id="termsModal" class="hapag-modal-overlay" onclick="if(event.target===this)closeModal('termsModal')" style="display:none;">
  <div class="hapag-modal-box">
    <button class="hapag-modal-close" onclick="closeModal('termsModal')" aria-label="Close">&times;</button>
    <h3 class="hapag-modal-title">Terms of Service</h3>
    <p class="hapag-modal-date">Effective: January 1, 2026</p>
    <div class="hapag-modal-body">
      <h4>1. Acceptance of Terms</h4>
      <p>By accessing or using H.A.P.A.G. (Healthy and Affordable Personalized Automated Goal-Oriented Meal Planning System), you agree to these Terms of Service. If you do not agree, please do not use the platform. H.A.P.A.G. is intended for informational and planning purposes only and is designed for use by residents of the Philippines.</p>
      <h4>2. Use of the Platform</h4>
      <p>H.A.P.A.G. is a meal planning tool grounded in nutritional science and real market pricing data. Meal plans and nutritional information provided are generated algorithmically and should not be treated as professional medical or dietary advice. Users with specific health conditions are encouraged to consult a licensed nutritionist or physician before making significant dietary changes.</p>
      <h4>3. Limitation of Liability</h4>
      <p>H.A.P.A.G. and its developers are not liable for any health outcomes, financial decisions, or other consequences resulting from the use of meal plans generated by this platform. Grocery prices displayed are sourced from DA Bantay Presyo and may vary from actual market prices. We reserve the right to modify or discontinue features at any time. Continued use after any changes constitutes your acceptance of the updated terms.</p>
    </div>
  </div>
</div>


<!-- ══════════════════════════════════════════════════════════
     PRODUCT MODAL — 4-tab feature showcase
     Opens when footer Product links are clicked
════════════════════════════════════════════════════════════ -->
<div id="productModal" class="hapag-modal-overlay"
     onclick="if(event.target===this)closeModal('productModal')"
     style="display:none;">
  <div class="hapag-modal-box" style="max-width:680px;padding:2rem 2rem 2.5rem;">
    <button class="hapag-modal-close" onclick="closeModal('productModal')" aria-label="Close">&times;</button>
    <h3 class="hapag-modal-title">What H.A.P.A.G. Offers</h3>
    <p class="hapag-modal-date">Everything you need to eat healthy on a Filipino budget</p>

    <!-- ── Product Tab Buttons ── -->
    <div style="display:flex;gap:0;border-radius:10px;overflow:hidden;border:1px solid rgba(255,255,255,.12);margin-bottom:24px;">
      <button class="prod-tab active" onclick="switchProductTab('meal-plans',this)">🍽 Meal Plans</button>
      <button class="prod-tab" onclick="switchProductTab('recipe-library',this)">📖 Recipes</button>
      <button class="prod-tab" onclick="switchProductTab('nutrition-tracker',this)">📊 Nutrition</button>
      <button class="prod-tab" onclick="switchProductTab('budget-planner',this)">💰 Budget</button>
    </div>

    <!-- ── Panel: Meal Plans ── -->
    <div id="prod-panel-meal-plans" class="prod-panel">
      <div class="prod-hero-icon">🍽️</div>
      <h4 class="prod-panel-title">Personalized 7-Day Meal Plans</h4>
      <p class="prod-panel-lead">Tell us your goal — build muscle, lose weight, or maintain — and we generate a complete week of Filipino meals tailored to your exact macronutrient needs.</p>
      <div class="prod-feature-list">
        <div class="prod-feature-item">
          <span class="prod-feature-icon">🎯</span>
          <div><strong>Goal-based macros</strong> — Protein, carbs, and fat targets are calculated from your profile (age, weight, activity level), not generic templates.</div>
        </div>
        <div class="prod-feature-item">
          <span class="prod-feature-icon">📅</span>
          <div><strong>Full week coverage</strong> — Breakfast, lunch, and dinner for all 7 days. No gaps, no guessing, no repeat meals.</div>
        </div>
        <div class="prod-feature-item">
          <span class="prod-feature-icon">👨‍👩‍👧</span>
          <div><strong>Household scaling</strong> — Planning for yourself or a family of five? Serving sizes and ingredient quantities adjust automatically.</div>
        </div>
        <div class="prod-feature-item">
          <span class="prod-feature-icon">🔄</span>
          <div><strong>Regenerate anytime</strong> — Not happy with this week's plan? Regenerate with one click or tweak your calorie target inline.</div>
        </div>
      </div>
      <a href="#register" onclick="closeModal('productModal')" class="prod-cta-btn">Build My Meal Plan →</a>
    </div>

    <!-- ── Panel: Recipe Library ── -->
    <div id="prod-panel-recipe-library" class="prod-panel" style="display:none;">
      <div class="prod-hero-icon">📖</div>
      <h4 class="prod-panel-title">100+ Authentic Filipino Recipes</h4>
      <p class="prod-panel-lead">Every dish in our system uses real Filipino ingredients you can find at any palengke — cooked the way your lola would recognize.</p>
      <div class="prod-feature-list">
        <div class="prod-feature-item">
          <span class="prod-feature-icon">🍛</span>
          <div><strong>Classic Filipino dishes</strong> — Adobo, sinigang, tinola, pinakbet, nilaga, kare-kare, and more — all nutritionally verified and portioned to your goals.</div>
        </div>
        <div class="prod-feature-item">
          <span class="prod-feature-icon">🥬</span>
          <div><strong>Palengke-sourced ingredients</strong> — No specialty stores needed. Every recipe is built around what's available at your local wet market.</div>
        </div>
        <div class="prod-feature-item">
          <span class="prod-feature-icon">🔄</span>
          <div><strong>Smart substitutions</strong> — Don't like ampalaya? Prefer tofu over pork? The library adapts to your taste without sacrificing your macro targets.</div>
        </div>
        <div class="prod-feature-item">
          <span class="prod-feature-icon">📋</span>
          <div><strong>Step-by-step instructions</strong> — Each recipe includes prep time, cooking steps, and serving suggestions written for home cooks, not professional chefs.</div>
        </div>
      </div>
      <a href="#register" onclick="closeModal('productModal')" class="prod-cta-btn">Explore the Recipe Library →</a>
    </div>

    <!-- ── Panel: Nutrition Tracker ── -->
    <div id="prod-panel-nutrition-tracker" class="prod-panel" style="display:none;">
      <div class="prod-hero-icon">📊</div>
      <h4 class="prod-panel-title">Full Macro & Nutrition Breakdown</h4>
      <p class="prod-panel-lead">Every meal comes with a complete nutritional profile — calories, protein, carbs, fat, and key micronutrients — so you always know what you're eating and why it works.</p>
      <div class="prod-feature-list">
        <div class="prod-feature-item">
          <span class="prod-feature-icon">🧪</span>
          <div><strong>FNRI-based calculations</strong> — All nutritional targets are grounded in the Food and Nutrition Research Institute's Philippine dietary reference intakes, not Western standards.</div>
        </div>
        <div class="prod-feature-item">
          <span class="prod-feature-icon">⚖️</span>
          <div><strong>Per-meal breakdown</strong> — See calories, protein, carbs, and fat for every single dish — breakfast, lunch, and dinner — with daily totals per day of the week.</div>
        </div>
        <div class="prod-feature-item">
          <span class="prod-feature-icon">🏃</span>
          <div><strong>Activity-adjusted targets</strong> — Your daily calorie and macro targets shift based on your activity level — sedentary, moderate, or very active — automatically.</div>
        </div>
        <div class="prod-feature-item">
          <span class="prod-feature-icon">✏️</span>
          <div><strong>Inline calorie editor</strong> — After generating your plan, you can tweak your daily calorie target directly in the meal plan view and regenerate instantly.</div>
        </div>
      </div>
      <a href="#register" onclick="closeModal('productModal')" class="prod-cta-btn">Start Tracking My Nutrition →</a>
    </div>

    <!-- ── Panel: Budget Planner ── -->
    <div id="prod-panel-budget-planner" class="prod-panel" style="display:none;">
      <div class="prod-hero-icon">💰</div>
      <h4 class="prod-panel-title">Real-Time Grocery Budget Planner</h4>
      <p class="prod-panel-lead">H.A.P.A.G. connects your meal plan directly to live DA Bantay Presyo market prices — so you see the real cost of your week's groceries before you leave the house.</p>
      <div class="prod-feature-list">
        <div class="prod-feature-item">
          <span class="prod-feature-icon">🏛️</span>
          <div><strong>DA Bantay Presyo integration</strong> — Grocery prices are sourced directly from the Department of Agriculture's official price monitoring system, updated regularly.</div>
        </div>
        <div class="prod-feature-item">
          <span class="prod-feature-icon">🛒</span>
          <div><strong>Per-meal cost estimates</strong> — Each dish shows its estimated market cost so you can see where your budget goes — not just a weekly lump sum.</div>
        </div>
        <div class="prod-feature-item">
          <span class="prod-feature-icon">📋</span>
          <div><strong>Categorized grocery list</strong> — Your shopping list is sorted by market section (proteins, vegetables, condiments) with price ranges for every ingredient.</div>
        </div>
        <div class="prod-feature-item">
          <span class="prod-feature-icon">🔁</span>
          <div><strong>Budget-aware substitutions</strong> — If bangus is expensive this week, the system recommends equally nutritious, more affordable alternatives automatically.</div>
        </div>
      </div>
      <a href="#register" onclick="closeModal('productModal')" class="prod-cta-btn">Plan My Grocery Budget →</a>
    </div>

  </div>
</div>


<style>
/* ── Shared modal styles (Privacy, Terms, Product) ── */
.hapag-modal-overlay {
  position: fixed; inset: 0; z-index: 9999;
  background: rgba(0,0,0,.72);
  display: flex; align-items: center; justify-content: center;
  padding: 1rem;
  animation: hapagFadeIn .18s ease;
}
@keyframes hapagFadeIn { from { opacity:0; } to { opacity:1; } }
.hapag-modal-box {
  background: #1a1f1a;
  border: 1px solid rgba(255,255,255,.1);
  border-radius: 16px;
  max-width: 600px; width: 100%;
  max-height: 80vh; overflow-y: auto;
  padding: 2rem 2rem 2.5rem;
  position: relative;
  box-shadow: 0 24px 64px rgba(0,0,0,.6);
}
.hapag-modal-close {
  position: absolute; top: 1rem; right: 1.2rem;
  background: none; border: none; cursor: pointer;
  font-size: 1.6rem; line-height: 1;
  color: rgba(255,255,255,.5);
  transition: color .15s;
}
.hapag-modal-close:hover { color: #fff; }
.hapag-modal-title {
  font-family: var(--font-display, 'Playfair Display', serif);
  font-size: 1.5rem; color: #fff;
  margin: 0 0 .25rem;
}
.hapag-modal-date {
  font-size: .8rem; color: rgba(255,255,255,.4);
  margin: 0 0 1.5rem;
}
.hapag-modal-body h4 {
  color: var(--gold-dark, #b45309);
  font-size: .85rem; font-weight: 700;
  margin: 1.25rem 0 .4rem;
  text-transform: uppercase; letter-spacing: .04em;
}
.hapag-modal-body p {
  color: rgba(255,255,255,.75);
  font-size: .9rem; line-height: 1.7;
  margin: 0 0 .5rem;
}
.hapag-modal-body a { color: var(--gold-dark, #b45309); }

/* ── Product modal tab buttons ── */
.prod-tab {
  flex: 1;
  padding: 10px 6px;
  border: none;
  cursor: pointer;
  font-size: .78rem;
  font-weight: 600;
  font-family: var(--font-body, 'Inter', sans-serif);
  background: rgba(255,255,255,.06);
  color: rgba(255,255,255,.45);
  transition: background .2s, color .2s;
  letter-spacing: .01em;
  white-space: nowrap;
}
.prod-tab:hover  { background: rgba(255,255,255,.1); color: rgba(255,255,255,.75); }
.prod-tab.active { background: #2d6a4f; color: #fff; }

/* ── Product panel content ── */
.prod-panel { animation: hapagFadeIn .22s ease; }

.prod-hero-icon {
  font-size: 2.2rem;
  margin-bottom: 10px;
  display: block;
}
.prod-panel-title {
  font-family: var(--font-display, 'Playfair Display', serif);
  font-size: 1.25rem;
  font-weight: 700;
  color: #fff;
  margin: 0 0 10px;
}
.prod-panel-lead {
  font-size: .9rem;
  color: rgba(255,255,255,.6);
  line-height: 1.65;
  margin: 0 0 20px;
}
.prod-feature-list {
  display: flex;
  flex-direction: column;
  gap: 14px;
  margin-bottom: 24px;
}
.prod-feature-item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  font-size: .875rem;
  color: rgba(255,255,255,.7);
  line-height: 1.6;
}
.prod-feature-icon {
  font-size: 1.1rem;
  flex-shrink: 0;
  margin-top: 1px;
}
.prod-feature-item strong {
  color: #d1fae5;
  font-weight: 600;
}
.prod-cta-btn {
  display: inline-block;
  background: #2d6a4f;
  color: #fff;
  border-radius: 10px;
  padding: 11px 22px;
  font-size: .88rem;
  font-weight: 700;
  text-decoration: none;
  font-family: var(--font-body, 'Inter', sans-serif);
  transition: background .2s, transform .15s;
  letter-spacing: .01em;
}
.prod-cta-btn:hover {
  background: #22543d;
  transform: translateY(-1px);
  color: #fff;
}

@keyframes shake {
  0%,100%{transform:translateX(0)} 20%{transform:translateX(-6px)}
  40%{transform:translateX(6px)} 60%{transform:translateX(-4px)} 80%{transform:translateX(4px)}
}
</style>


<script>
/* ═══════════════════════════════════════════════════════
   H.A.P.A.G. — Frontend JS
═══════════════════════════════════════════════════════ */

/* ── Modal open / close ── */
function openModal(id) {
  var el = document.getElementById(id);
  if (el) { el.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
}
function closeModal(id) {
  var el = document.getElementById(id);
  if (el) { el.style.display = 'none'; document.body.style.overflow = ''; }
}
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeModal('privacyModal');
    closeModal('termsModal');
    closeModal('productModal');
  }
});

/* ── Product modal: open to specific tab ── */
function openProductModal(tabKey) {
  // Reset all tabs + panels, then activate the requested one
  document.querySelectorAll('.prod-tab').forEach(function(btn) {
    btn.classList.remove('active');
  });
  document.querySelectorAll('.prod-panel').forEach(function(panel) {
    panel.style.display = 'none';
  });

  // Activate the right tab button (match by onclick attribute fragment)
  document.querySelectorAll('.prod-tab').forEach(function(btn) {
    if (btn.getAttribute('onclick') && btn.getAttribute('onclick').indexOf("'" + tabKey + "'") !== -1) {
      btn.classList.add('active');
    }
  });

  var targetPanel = document.getElementById('prod-panel-' + tabKey);
  if (targetPanel) targetPanel.style.display = 'block';

  openModal('productModal');
}

/* ── Product modal: switch tabs from inside ── */
function switchProductTab(tabKey, clickedBtn) {
  document.querySelectorAll('.prod-tab').forEach(function(btn) {
    btn.classList.remove('active');
  });
  document.querySelectorAll('.prod-panel').forEach(function(panel) {
    panel.style.display = 'none';
  });
  clickedBtn.classList.add('active');
  var panel = document.getElementById('prod-panel-' + tabKey);
  if (panel) panel.style.display = 'block';
}

/* ── Auto-suggest calorie target on goal change ── */
(function() {
  const goalDefaults = {
    muscle:      2700,
    weightloss:  1700,
    maintenance: 2200,
    performance: 2800,
    family:      2000,
  };
  const goalLabels = {
    muscle:      'Suggested for muscle gain',
    weightloss:  'Suggested for weight loss',
    maintenance: 'Suggested for maintenance',
    performance: 'Suggested for performance',
    family:      'Suggested for family',
  };
  function updateKcalHint() {
    const goalEl  = document.getElementById('goal');
    const kcalEl  = document.getElementById('custom_kcal');
    const hintEl  = document.getElementById('kcal-hint');
    if (!goalEl || !kcalEl || !hintEl) return;
    const goal = goalEl.value;
    if (goal && goalDefaults[goal]) {
      hintEl.textContent = goalLabels[goal] + ': ~' + goalDefaults[goal].toLocaleString() + ' kcal';
      if (!kcalEl.value) kcalEl.placeholder = 'e.g. ' + goalDefaults[goal] + ' — or leave blank to auto-calculate';
    } else {
      hintEl.textContent = '';
    }
  }
  document.addEventListener('DOMContentLoaded', function() {
    const goalEl = document.getElementById('goal');
    if (goalEl) {
      goalEl.addEventListener('change', updateKcalHint);
      updateKcalHint();
    }
  });
})();

/* ── Tab switching (register / login) ── */
function switchTab(tab, btn) {
  document.querySelectorAll('.form-tab').forEach(b => b.classList.remove('active'));
  document.querySelectorAll('.form-panel').forEach(p => p.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('tab-' + tab).classList.add('active');
}

/* ── AJAX: Generate meal plan ── */
function generateMealPlan(customKcal) {
  const btn        = document.getElementById('gen-plan-btn');
  const successBar = document.getElementById('plan-success-bar');
  const result     = document.getElementById('plan-result');

  if (!customKcal) {
    const editor = document.getElementById('hapag-kcal-input');
    if (editor) customKcal = parseInt(editor.value, 10) || null;
  }

  btn.style.display = 'block';
  btn.textContent   = '⏳ Generating your plan…';
  btn.disabled      = true;
  successBar.style.display = 'none';
  result.innerHTML  = '';

  const body = customKcal ? JSON.stringify({ custom_kcal: customKcal }) : null;

  fetch('/HAPAGV11/api/meal_plan.php', {
    method: 'POST',
    headers: body ? { 'Content-Type': 'application/json' } : {},
    body: body,
  })
    .then(r => r.json())
    .then(data => {
      btn.disabled = false;
      if (data.status === 'success') {
        btn.style.display        = 'none';
        successBar.style.display = 'block';
        renderPlanResult(data.data, result);
        setTimeout(() => result.scrollIntoView({ behavior: 'smooth', block: 'start' }), 120);
      } else {
        btn.textContent = '🍽 Generate This Week\'s Meal Plan →';
        result.innerHTML = '<p style="color:#dc2626;">' + (data.message || 'Error generating plan.') + '</p>';
      }
    })
    .catch(() => {
      btn.disabled    = false;
      btn.textContent = '🍽 Generate This Week\'s Meal Plan →';
      result.innerHTML = '<p style="color:#dc2626;">Network error. Please try again.</p>';
    });
}

function renderPlanResult(plan, container) {
  if (!plan || !plan.days) return;
  const dayLabels  = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
  const mealTypes  = ['breakfast','lunch','dinner'];
  const mealIcons  = { breakfast:'🌅', lunch:'☀️', dinner:'🌙' };
  const mealLabels = { breakfast:'Breakfast', lunch:'Lunch', dinner:'Dinner' };

  window.hapagDayMacros = plan.days.map(day => {
    let protein = 0, carbs = 0, fat = 0;
    mealTypes.forEach(mealType => {
      const meal = day.meals[mealType] || day.meals[mealType.charAt(0).toUpperCase() + mealType.slice(1)];
      if (!meal) return;
      const base  = parseFloat(meal.calories) || 1;
      const scale = (parseFloat(meal.scaled_kcal) || base) / base;
      protein += (parseFloat(meal.protein_g) || 0) * scale;
      carbs   += (parseFloat(meal.carbs_g)   || 0) * scale;
      fat     += (parseFloat(meal.fat_g)      || 0) * scale;
    });
    return { protein: Math.round(protein), carbs: Math.round(carbs), fat: Math.round(fat) };
  });

  const pillStyle = 'background:rgba(255,255,255,.15);color:#d1fae5;border-radius:20px;padding:3px 11px;font-size:.74rem;font-weight:600;';
  const d0 = window.hapagDayMacros[0] || (plan.macros || {});
  let macroHTML = '<div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:10px;">';
  macroHTML += `<span id="hapag-pill-protein" style="${pillStyle}">🥩 ${d0.protein||0}g Protein</span>`;
  macroHTML += `<span id="hapag-pill-carbs"   style="${pillStyle}">🍚 ${d0.carbs||0}g Carbs</span>`;
  macroHTML += `<span id="hapag-pill-fat"     style="${pillStyle}">🥑 ${d0.fat||0}g Fat</span>`;
  macroHTML += '</div>';

  function dayKcal(day) {
    if (day.daily_kcal && day.daily_kcal > 0) return day.daily_kcal;
    if (!day.meals) return 0;
    return mealTypes.reduce((sum, t) => {
      const m = day.meals[t] || day.meals[t.charAt(0).toUpperCase()+t.slice(1)];
      return sum + (m ? parseFloat(m.scaled_kcal || m.calories || 0) : 0);
    }, 0);
  }

  let tabsHTML = '<div style="display:flex;overflow-x:auto;gap:0;border-bottom:2px solid #e8f5ee;scrollbar-width:none;" id="hapag-day-tabs">';
  plan.days.forEach((day, i) => {
    const kcal = dayKcal(day);
    const isActive = i === 0;
    tabsHTML += `<button
      onclick="hapagSwitchDay(${i})"
      id="hapag-tab-${i}"
      style="
        flex:1;min-width:52px;padding:10px 6px 9px;border:none;cursor:pointer;
        background:${isActive ? '#fff' : '#f5faf7'};
        border-bottom:${isActive ? '3px solid #2d6a4f' : '3px solid transparent'};
        color:${isActive ? '#2d6a4f' : '#888'};
        font-weight:${isActive ? '700' : '500'};
        font-size:.72rem;letter-spacing:.04em;text-transform:uppercase;
        transition:all .18s;line-height:1.3;
        font-family:var(--font-body,'Inter',sans-serif);
      ">
      ${dayLabels[i] || day.day_name}
      ${kcal > 0 ? `<div style="font-size:.65rem;font-weight:500;color:${isActive?'#52a97a':'#bbb'};margin-top:2px;">${Math.round(kcal)} kcal</div>` : ''}
    </button>`;
  });
  tabsHTML += '</div>';

  let panelsHTML = '<div id="hapag-day-panels">';
  plan.days.forEach((day, i) => {
    panelsHTML += `<div id="hapag-panel-${i}" style="display:${i===0?'block':'none'};">`;

    if (!day.meals) { panelsHTML += '<p style="padding:20px;color:#999;font-size:.85rem;">No meals for this day.</p>'; panelsHTML += '</div>'; return; }

    mealTypes.forEach((mealType, mi) => {
      const meal = day.meals[mealType] || day.meals[mealType.charAt(0).toUpperCase() + mealType.slice(1)];
      if (!meal) return;

      const rawCost  = parseFloat(meal.estimated_cost);
      const hasCost  = rawCost >= 10 && rawCost <= 2000;
      const protein  = meal.protein_g ? meal.protein_g + 'g protein' : '';
      const rowId    = 'ing-row-' + i + '-' + mi;
      const hasIngs  = meal.ingredients && meal.ingredients.length > 0;

      panelsHTML += `<div style="border-bottom:1px solid #f0f5f2;">`;
      panelsHTML += `<div style="display:flex;align-items:center;padding:14px 20px;gap:14px;">`;
      panelsHTML += `<div style="
        width:40px;height:40px;border-radius:10px;
        background:${mealType==='breakfast'?'#fff7ed':mealType==='lunch'?'#f0fdf4':'#f5f3ff'};
        display:flex;flex-direction:column;align-items:center;justify-content:center;
        flex-shrink:0;border:1px solid ${mealType==='breakfast'?'#fed7aa':mealType==='lunch'?'#bbf7d0':'#ddd6fe'};
      ">
        <span style="font-size:.95rem;line-height:1;">${mealIcons[mealType]}</span>
        <span style="font-size:.52rem;font-weight:700;color:#aaa;letter-spacing:.04em;text-transform:uppercase;margin-top:1px;">${mealLabels[mealType].slice(0,5)}</span>
      </div>`;

      panelsHTML += `<div style="flex:1;min-width:0;">`;
      panelsHTML += `<div style="font-weight:600;font-size:.88rem;color:#1a1a1a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${meal.name}</div>`;
      panelsHTML += `<div style="font-size:.73rem;color:#999;margin-top:3px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;">`;
      panelsHTML += `<span>🔥 ${Math.round(meal.scaled_kcal || meal.calories || 0)} kcal</span>`;
      if (protein) panelsHTML += `<span style="color:#ccc;">·</span><span>${protein}</span>`;
      if (hasIngs)  panelsHTML += `<button onclick="toggleIngs('${rowId}',this)" style="background:none;border:1px solid #d0e8d8;border-radius:10px;padding:1px 8px;font-size:.68rem;color:#2d6a4f;cursor:pointer;font-family:inherit;">ingredients ▾</button>`;
      panelsHTML += `</div></div>`;

      if (hasCost) {
        panelsHTML += `<div style="background:#f0faf4;border:1px solid #c6e8d3;border-radius:8px;padding:4px 10px;text-align:center;flex-shrink:0;">
          <div style="font-weight:700;color:#2d6a4f;font-size:.88rem;">₱${rawCost.toFixed(0)}</div>
          <div style="font-size:.6rem;color:#7dbf97;text-transform:uppercase;letter-spacing:.04em;">est.</div>
        </div>`;
      }

      panelsHTML += `</div>`;

      if (hasIngs) {
        panelsHTML += `<div id="${rowId}" style="display:none;background:#f8fdf9;border-top:1px solid #eaf4ee;padding:12px 20px 14px 74px;">`;
        panelsHTML += `<div style="font-size:.68rem;font-weight:700;color:#2d6a4f;letter-spacing:.07em;text-transform:uppercase;margin-bottom:8px;">🛒 Bantay Presyo Price Guide</div>`;
        panelsHTML += `<table style="width:100%;border-collapse:collapse;font-size:.76rem;">`;
        panelsHTML += `<thead><tr style="color:#bbb;">`;
        panelsHTML += `<th style="padding:3px 0;font-weight:600;text-align:left;">Ingredient</th>`;
        panelsHTML += `<th style="padding:3px 0;font-weight:600;text-align:center;">Qty</th>`;
        panelsHTML += `<th style="padding:3px 0;font-weight:600;text-align:right;">Price Range</th>`;
        panelsHTML += `</tr></thead><tbody>`;
        meal.ingredients.forEach(ing => {
          const hp = ing.price_min !== undefined && ing.price_max !== undefined;
          const pc = hp ? '₱'+ing.price_min.toFixed(0)+'–₱'+ing.price_max.toFixed(0)+' <span style="color:#ccc;font-size:.68rem;">/ '+ing.price_unit+'</span>' : '<span style="color:#ccc;">—</span>';
          panelsHTML += `<tr style="border-top:1px solid #edf6f0;">
            <td style="padding:5px 0;color:#1a1a1a;">${ing.name}</td>
            <td style="padding:5px 0;color:#777;text-align:center;">${ing.quantity} ${ing.unit}</td>
            <td style="padding:5px 0;color:#2d6a4f;font-weight:600;text-align:right;">${pc}</td>
          </tr>`;
        });
        panelsHTML += `</tbody></table>`;
        panelsHTML += `<div style="margin-top:8px;font-size:.68rem;color:#bbb;">💡 Prices from DA Bantay Presyo. Actual cost may vary per market.</div>`;
        panelsHTML += `</div>`;
      }

      panelsHTML += `</div>`;
    });

    const dayTotal = plan.days[i]?.daily_cost;
    if (dayTotal && parseFloat(dayTotal) > 0) {
      panelsHTML += `<div style="display:flex;justify-content:flex-end;align-items:center;padding:10px 20px;background:#fffbeb;border-top:1px dashed #fde68a;gap:8px;">
        <span style="font-size:.75rem;color:#92400e;">Day's estimated cost:</span>
        <span style="font-size:.88rem;font-weight:700;color:#92400e;">₱${parseFloat(dayTotal).toFixed(0)}</span>
      </div>`;
    }

    panelsHTML += `</div>`;
  });
  panelsHTML += '</div>';

  const totalCost = parseFloat(plan.total_cost);
  let footerHTML = '';
  if (totalCost > 0 && totalCost < 10000) {
    footerHTML = `<div style="display:flex;justify-content:space-between;align-items:center;padding:14px 20px;background:linear-gradient(135deg,#fffbeb,#fef9c3);border-top:2px solid #fde68a;">
      <div>
        <div style="font-size:.7rem;color:#92400e;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">Weekly Grocery Budget</div>
        <div style="font-size:.75rem;color:#b45309;margin-top:2px;">All 7 days · estimated market cost</div>
      </div>
      <div style="text-align:right;">
        <div style="font-size:1.2rem;font-weight:800;color:#92400e;">₱${totalCost.toFixed(0)}</div>
        <div style="font-size:.68rem;color:#d97706;margin-top:1px;">DA Bantay Presyo basis</div>
      </div>
    </div>`;
  }

  let html = `<div style="background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 28px rgba(0,0,0,.10);margin-top:20px;">`;

  const currentKcal = plan.macros?.kcal || parseInt((plan.goal_label||'').match(/[\d,]+/)?.[0]?.replace(',','')) || 2000;
  const goalName    = (plan.goal_label||'Your Meal Plan').replace(/·.*/, '').trim();

  html += `<div style="background:linear-gradient(145deg,#1a3c2e,#2d6a4f);padding:18px 20px 16px;color:#fff;">`;
  html += `<div style="font-size:.65rem;letter-spacing:.1em;color:rgba(255,255,255,.5);text-transform:uppercase;margin-bottom:6px;">Your Weekly Plan · Week of ${plan.week_start}</div>`;
  html += `<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">`;
  html += `<span style="font-size:1.05rem;font-weight:700;font-family:var(--font-display,'Playfair Display',serif);">${goalName} ·</span>`;
  html += `<div id="hapag-kcal-wrapper" style="display:flex;align-items:center;gap:6px;">`;
  html += `<div id="hapag-kcal-display" style="display:flex;align-items:center;gap:5px;cursor:pointer;" onclick="hapagEditKcal()" title="Click to edit calorie target">`;
  html += `<span style="font-size:1.05rem;font-weight:700;" id="hapag-kcal-shown">${currentKcal.toLocaleString()}</span>`;
  html += `<span style="font-size:1.05rem;font-weight:400;opacity:.7;">kcal/day</span>`;
  html += `<span style="font-size:.75rem;opacity:.5;margin-left:2px;border:1px solid rgba(255,255,255,.3);border-radius:4px;padding:1px 5px;font-weight:500;">✏️ edit</span>`;
  html += `</div>`;
  html += `<div id="hapag-kcal-edit" style="display:none;align-items:center;gap:6px;">`;
  html += `<input id="hapag-kcal-input" type="number" min="1000" max="5000" step="50" value="${currentKcal}"
    style="
      width:90px;padding:4px 8px;border-radius:7px;border:none;
      background:rgba(255,255,255,.15);color:#fff;font-size:.95rem;font-weight:700;
      outline:none;text-align:center;font-family:inherit;
      border:1.5px solid rgba(255,255,255,.4);
    "
    onkeydown="if(event.key==='Enter')hapagApplyKcal();if(event.key==='Escape')hapagCancelKcal();"
  />`;
  html += `<button onclick="hapagApplyKcal()" style="
    background:#22c55e;color:#fff;border:none;border-radius:7px;
    padding:5px 11px;font-size:.78rem;font-weight:700;cursor:pointer;
    font-family:inherit;white-space:nowrap;
  ">Apply & Regenerate</button>`;
  html += `<button onclick="hapagCancelKcal()" style="
    background:rgba(255,255,255,.12);color:rgba(255,255,255,.7);border:none;
    border-radius:7px;padding:5px 9px;font-size:.78rem;cursor:pointer;font-family:inherit;
  ">✕</button>`;
  html += `</div>`;
  html += `</div></div>`;
  html += macroHTML;
  html += `</div>`;

  html += tabsHTML;
  html += panelsHTML;
  html += footerHTML;
  html += `</div>`;

  container.innerHTML = html;

  updateHeroCard(plan);
}

/* ── Update hero preview card with real plan data ── */
function updateHeroCard(plan) {
  const headlineEl = document.querySelector('.hero-card h4');
  if (headlineEl && plan.goal_label) headlineEl.textContent = plan.goal_label;

  const macros  = plan.macros || {};
  const pillEls = document.querySelectorAll('.hero-card .macro-pill');
  if (pillEls.length >= 3 && macros.protein) {
    pillEls[0].textContent = '🥩 ' + macros.protein + 'g Protein';
    pillEls[1].textContent = '🍚 ' + macros.carbs   + 'g Carbs';
    pillEls[2].textContent = '🥑 ' + macros.fat     + 'g Fat';
  }

  const weekEl = document.querySelector('.hero-card .week-label');
  if (weekEl && plan.week_start) weekEl.textContent = 'Your Weekly Plan · Week of ' + plan.week_start;

  const mealRows  = document.querySelectorAll('.hero-card .meal-day');
  const dayLabels = ['MON','TUE','WED','THU','FRI','SAT','SUN'];
  if (mealRows.length && plan.days) {
    plan.days.slice(0, mealRows.length).forEach((day, i) => {
      const el   = mealRows[i];
      if (!el) return;
      const meal = day.meals['lunch'] || day.meals['dinner'] || day.meals['breakfast'];
      if (!meal) return;
      const labelEl = el.querySelector('.meal-day-label');
      const nameEl  = el.querySelector('.food-name');
      const descEl  = el.querySelector('.food-desc');
      const priceEl = el.querySelector('.meal-day-price');
      if (labelEl) labelEl.textContent = dayLabels[i];
      if (nameEl)  nameEl.textContent  = meal.name;
      if (descEl)  descEl.textContent  =
        Math.round(meal.scaled_kcal || meal.calories || 0) + ' kcal · ' +
        (meal.protein_g || 0) + 'g protein';
      if (priceEl) priceEl.textContent = meal.estimated_cost > 0
        ? '₱' + Math.round(meal.estimated_cost) : '';
    });
  }

  const footerTotalEl = document.querySelector('.hero-card .footer-total');
  if (footerTotalEl && plan.total_cost) footerTotalEl.textContent = '₱' + Math.round(plan.total_cost) + ' total';

  const floatBadge = document.querySelector('.hero-float-badge');
  if (floatBadge) floatBadge.textContent = '✓ This week\'s plan ready';
}

/* ── Day tab switcher ── */
function hapagSwitchDay(idx) {
  document.querySelectorAll('[id^="hapag-panel-"]').forEach((p, i) => {
    p.style.display = i === idx ? 'block' : 'none';
  });
  document.querySelectorAll('[id^="hapag-tab-"]').forEach((t, i) => {
    const active = i === idx;
    t.style.borderBottom   = active ? '3px solid #2d6a4f' : '3px solid transparent';
    t.style.color          = active ? '#2d6a4f' : '#888';
    t.style.fontWeight     = active ? '700' : '500';
    t.style.background     = active ? '#fff' : '#f5faf7';
    const sub = t.querySelector('div');
    if (sub) sub.style.color = active ? '#52a97a' : '#bbb';
  });

  if (window.hapagDayMacros && window.hapagDayMacros[idx]) {
    const dm = window.hapagDayMacros[idx];
    const pp = document.getElementById('hapag-pill-protein');
    const pc = document.getElementById('hapag-pill-carbs');
    const pf = document.getElementById('hapag-pill-fat');
    if (pp) pp.textContent = '🥩 ' + dm.protein + 'g Protein';
    if (pc) pc.textContent = '🍚 ' + dm.carbs   + 'g Carbs';
    if (pf) pf.textContent = '🥑 ' + dm.fat     + 'g Fat';
  }
}

/* ── Inline calorie editor ── */
function hapagEditKcal() {
  document.getElementById('hapag-kcal-display').style.display = 'none';
  const ed = document.getElementById('hapag-kcal-edit');
  ed.style.display = 'flex';
  setTimeout(() => document.getElementById('hapag-kcal-input')?.focus(), 50);
}
function hapagCancelKcal() {
  document.getElementById('hapag-kcal-edit').style.display = 'none';
  document.getElementById('hapag-kcal-display').style.display = 'flex';
}
function hapagApplyKcal() {
  const input = document.getElementById('hapag-kcal-input');
  const val   = parseInt(input?.value, 10);
  if (!val || val < 1000 || val > 5000) {
    input.style.borderColor = '#f87171';
    input.style.animation   = 'shake .3s';
    setTimeout(() => { input.style.borderColor = 'rgba(255,255,255,.4)'; input.style.animation = ''; }, 600);
    return;
  }
  const shown = document.getElementById('hapag-kcal-shown');
  if (shown) shown.textContent = val.toLocaleString();
  hapagCancelKcal();
  generateMealPlan(val);
}

/* ── Toggle ingredient breakdown ── */
function toggleIngs(rowId, btn) {
  const row = document.getElementById(rowId);
  if (!row) return;
  const isOpen = row.style.display !== 'none';
  row.style.display = isOpen ? 'none' : 'block';
  btn.textContent   = isOpen ? 'See ingredients ▾' : 'Hide ingredients ▴';
  btn.style.background = isOpen ? 'none' : '#e8f5ee';
}

/* ── Testimonials data ── */
const REVIEWS = [
  { initials:'MR', avatarClass:'avatar-green', rating:5, name:'Marco Reyes', title:'Gym enthusiast, Quezon City', text:"I've been trying to build muscle for two years and always felt like healthy food meant expensive food. H.A.P.A.G. showed me a chicken adobo and malunggay plan that hit my protein target for under ₱900 a week. Sobrang shocked." },
  { initials:'LA', avatarClass:'avatar-gold',  rating:5, name:'Lorraine Alcantara', title:'Mom of three, Cavite', text:"As a working mom, I used to spend way too much time figuring out what to cook for a family of five. Now I just log in on Sunday, get the week's plan, and do one market trip. Sobrang helpful." },
  { initials:'JP', avatarClass:'avatar-terra', rating:5, name:'Jana Pascual', title:'College student, Cebu City', text:"I'm a college student trying to lose weight on a very tight budget. H.A.P.A.G. gave me real Filipino food — sinigang, pinakbet — that fit my calorie limit and my allowance." },
];

function renderTestimonials() {
  const grid = document.getElementById('testimonials-grid');
  if (!grid) return;
  grid.innerHTML = REVIEWS.map(r => {
    const stars = [...'★'.repeat(r.rating) + '☆'.repeat(5-r.rating)]
      .map(s => `<span class="star" ${s==='☆'?'style="opacity:.3"':''}>${s}</span>`).join('');
    return `<div class="testimonial-card">
      <div class="star-row">${stars}</div>
      <div class="testimonial-quote">${r.text}</div>
      <div class="testimonial-author">
        <div class="author-avatar ${r.avatarClass}">${r.initials}</div>
        <div><div class="author-name">${r.name}</div><div class="author-title">${r.title}</div></div>
      </div></div>`;
  }).join('');
}

/* ── Sticky nav shadow & scroll progress ── */
const nav         = document.getElementById('site-nav');
const progressBar = document.getElementById('scroll-progress');
const backToTop   = document.getElementById('back-to-top');

window.addEventListener('scroll', () => {
  const scrolled = window.scrollY;
  nav.classList.toggle('scrolled', scrolled > 20);
  progressBar.style.width = (scrolled / (document.documentElement.scrollHeight - window.innerHeight) * 100) + '%';
  backToTop.classList.toggle('visible', scrolled > 400);
}, { passive: true });

/* ── Mobile menu ── */
const hamburger  = document.getElementById('hamburger');
const mobileMenu = document.getElementById('mobile-menu');
let menuOpen = false;
hamburger.addEventListener('click', () => {
  menuOpen = !menuOpen;
  mobileMenu.style.display = menuOpen ? 'flex' : 'none';
  hamburger.setAttribute('aria-expanded', menuOpen);
  const bars = hamburger.querySelectorAll('span');
  if (menuOpen) {
    bars[0].style.transform = 'translateY(7px) rotate(45deg)';
    bars[1].style.opacity = '0';
    bars[2].style.transform = 'translateY(-7px) rotate(-45deg)';
  } else {
    bars[0].style.transform = bars[2].style.transform = '';
    bars[1].style.opacity = '';
  }
});
function closeMobileMenu() {
  menuOpen = false;
  mobileMenu.style.display = 'none';
  hamburger.querySelectorAll('span').forEach(b => { b.style.transform = ''; b.style.opacity = ''; });
}

/* ── FAQ ── */
function toggleFaq(btn) {
  const item = btn.closest('.faq-item');
  const isOpen = item.classList.contains('open');
  document.querySelectorAll('.faq-item.open').forEach(el => el.classList.remove('open'));
  if (!isOpen) item.classList.add('open');
}

/* ── Smooth scroll ── */
document.querySelectorAll('a[href^="#"]').forEach(link => {
  link.addEventListener('click', function(e) {
    const target = document.querySelector(this.getAttribute('href'));
    if (target) { e.preventDefault(); target.scrollIntoView({ behavior:'smooth', block:'start' }); closeMobileMenu(); }
  });
});

/* ── Scroll-triggered animations ── */
const scrollObs = new IntersectionObserver(entries => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      e.target.style.opacity = '1';
      e.target.style.transform = 'translateY(0)';
      scrollObs.unobserve(e.target);
    }
  });
}, { threshold: 0.08 });

document.querySelectorAll('.feature-card, .benefit-card, .about-stat-card').forEach((el, i) => {
  el.style.opacity = '0';
  el.style.transform = 'translateY(24px)';
  el.style.transition = `opacity .5s ease ${(i%3)*.1}s, transform .5s ease ${(i%3)*.1}s`;
  scrollObs.observe(el);
});

/* ── Animated counters ── */
function animateCounter(el, target, prefix='', suffix='') {
  const end = parseFloat(target);
  if (isNaN(end)) return;
  const start = performance.now();
  (function step(now) {
    const p = Math.min((now-start)/1600, 1);
    const e = 1 - Math.pow(1-p, 3);
    el.textContent = prefix + Math.round(e*end) + suffix;
    if (p < 1) requestAnimationFrame(step);
  })(start);
}
document.querySelectorAll('[data-count]').forEach(el => {
  new IntersectionObserver(entries => {
    entries.forEach(e => {
      if (e.isIntersecting) animateCounter(e.target, e.target.dataset.count, e.target.dataset.prefix||'', e.target.dataset.suffix||'');
    });
  }, { threshold: 0.5 }).observe(el);
});

/* ── Init ── */
renderTestimonials();
</script>

</body>
</html>
