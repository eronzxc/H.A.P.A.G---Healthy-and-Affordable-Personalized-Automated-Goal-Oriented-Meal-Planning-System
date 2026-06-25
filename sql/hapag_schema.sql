-- ============================================================
--  H.A.P.A.G. — Database Schema
--  Run this in phpMyAdmin or via: mysql -u root -p < hapag_schema.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS hapag_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE hapag_db;

-- ─────────────────────────────────────────────
--  USERS
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  first_name    VARCHAR(80)  NOT NULL,
  last_name     VARCHAR(80)  NOT NULL DEFAULT '',
  email         VARCHAR(191) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  goal          ENUM('muscle','weightloss','maintenance','performance','family') NOT NULL DEFAULT 'maintenance',
  sex           ENUM('male','female') NOT NULL DEFAULT 'male',
  age           TINYINT UNSIGNED NOT NULL DEFAULT 25,
  weight_kg     DECIMAL(5,1) NOT NULL DEFAULT 65.0,
  height_cm     DECIMAL(5,1) NOT NULL DEFAULT 165.0,
  activity      ENUM('sedentary','light','moderate','active','very_active') NOT NULL DEFAULT 'moderate',
  custom_kcal   SMALLINT UNSIGNED NULL DEFAULT NULL COMMENT 'User-set calorie target; NULL = auto-calculate',
  household     TINYINT UNSIGNED NOT NULL DEFAULT 1,
  is_admin      TINYINT(1) NOT NULL DEFAULT 0,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_email (email)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────
--  USER PREFERENCES (allergies, exclusions)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS user_preferences (
  id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id              INT UNSIGNED NOT NULL,
  excluded_ingredients TEXT         DEFAULT NULL COMMENT 'Comma-separated ingredient names',
  allergies            TEXT         DEFAULT NULL COMMENT 'Free-text allergy notes',
  max_weekly_budget    DECIMAL(8,2) DEFAULT NULL COMMENT 'User budget cap in PHP',
  updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────
--  RECIPES
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS recipes (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(191) NOT NULL,
  category      ENUM('breakfast','lunch','dinner','snack') NOT NULL DEFAULT 'lunch',
  description   TEXT         DEFAULT NULL,
  calories      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  protein_g     DECIMAL(5,1) NOT NULL DEFAULT 0,
  carbs_g       DECIMAL(5,1) NOT NULL DEFAULT 0,
  fat_g         DECIMAL(5,1) NOT NULL DEFAULT 0,
  cook_time_min TINYINT UNSIGNED NOT NULL DEFAULT 30,
  servings      TINYINT UNSIGNED NOT NULL DEFAULT 1,
  instructions  TEXT         DEFAULT NULL,
  tags          VARCHAR(255) DEFAULT NULL COMMENT 'e.g. high-protein,low-carb,family-friendly',
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_category (category)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────
--  RECIPE INGREDIENTS
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS recipe_ingredients (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  recipe_id     INT UNSIGNED NOT NULL,
  ingredient_name VARCHAR(191) NOT NULL,
  quantity      DECIMAL(7,2) NOT NULL DEFAULT 1,
  unit          VARCHAR(30)  NOT NULL DEFAULT 'pc',
  FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
  INDEX idx_recipe (recipe_id)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────
--  FOOD PRICES  (Bantay Presyo data)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS food_prices (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_name     VARCHAR(191) NOT NULL,
  category      ENUM('fish','meat','vegetable','grain','condiment','fruit','dairy','other') NOT NULL DEFAULT 'other',
  price_min     DECIMAL(8,2) NOT NULL DEFAULT 0,
  price_max     DECIMAL(8,2) NOT NULL DEFAULT 0,
  unit          VARCHAR(40)  NOT NULL DEFAULT '1 kg',
  source        VARCHAR(100) DEFAULT 'DA Bantay Presyo',
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_category (category)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────
--  MEAL PLANS  (one plan = one week per user)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS meal_plans (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NOT NULL,
  week_start    DATE         NOT NULL COMMENT 'Monday of the plan week',
  total_cost    DECIMAL(8,2) DEFAULT NULL,
  status        ENUM('draft','active','archived') NOT NULL DEFAULT 'active',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_week (user_id, week_start)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────
--  MEAL PLAN DAYS  (7 rows per plan × 3 meals)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS meal_plan_days (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plan_id       INT UNSIGNED NOT NULL,
  day_index     TINYINT UNSIGNED NOT NULL COMMENT '0=Mon … 6=Sun',
  meal_type     ENUM('breakfast','lunch','dinner') NOT NULL,
  recipe_id     INT UNSIGNED NOT NULL,
  servings      TINYINT UNSIGNED NOT NULL DEFAULT 1,
  estimated_cost DECIMAL(7,2) DEFAULT NULL,
  FOREIGN KEY (plan_id)    REFERENCES meal_plans(id) ON DELETE CASCADE,
  FOREIGN KEY (recipe_id)  REFERENCES recipes(id),
  INDEX idx_plan (plan_id)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────
--  SESSIONS  (server-side session store – optional)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS user_sessions (
  session_token VARCHAR(64)  PRIMARY KEY,
  user_id       INT UNSIGNED NOT NULL,
  expires_at    DATETIME     NOT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────
--  SEED: food_prices  (Bantay Presyo sample data)
-- ─────────────────────────────────────────────
INSERT INTO food_prices (item_name, category, price_min, price_max, unit) VALUES
  ('Bangus (medium, 1pc)',    'fish',      95,  120, '1 pc'),
  ('Tilapia',                 'fish',     140,  160, '1 kg'),
  ('Galunggong',              'fish',     180,  200, '1 kg'),
  ('Sardinas (canned)',       'fish',      22,   30, '1 can'),
  ('Chicken breast',          'meat',     230,  260, '1 kg'),
  ('Chicken thigh/leg',       'meat',     175,  210, '1 kg'),
  ('Pork liempo',             'meat',     290,  330, '1 kg'),
  ('Pork kasim',              'meat',     265,  300, '1 kg'),
  ('Ground pork',             'meat',     280,  310, '1 kg'),
  ('Beef',                    'meat',     380,  440, '1 kg'),
  ('Egg (medium)',             'other',    8,    10, '1 pc'),
  ('Tofu (firm)',              'other',    25,   35, '1 block'),
  ('White rice',               'grain',   54,   62, '1 kg'),
  ('Brown rice',               'grain',   70,   85, '1 kg'),
  ('Malunggay (bundle)',        'vegetable',15,  20, '1 bundle'),
  ('Kangkong (bundle)',         'vegetable',20,  25, '1 bundle'),
  ('Sitaw (bundle)',            'vegetable',20,  28, '1 bundle'),
  ('Ampalaya',                  'vegetable',45,  60, '500 g'),
  ('Kalabasa (slice)',           'vegetable',20,  30, '500 g'),
  ('Kamote (sweet potato)',     'vegetable',35,  50, '500 g'),
  ('Eggplant (talong)',          'vegetable',30,  45, '500 g'),
  ('Tomato',                    'vegetable',30,  50, '500 g'),
  ('Onion (sibuyas)',            'vegetable',55,  80, '500 g'),
  ('Garlic (bawang)',            'vegetable',45,  65, '250 g'),
  ('Ginger (luya)',              'vegetable',25,  40, '100 g'),
  ('Tamarind (sampalok)',        'vegetable',15,  25, '100 g'),
  ('Banana saba',               'fruit',   30,   45, '500 g'),
  ('Calamansi',                 'fruit',   25,   40, '10 pcs'),
  ('Soy sauce (toyo)',          'condiment',25,  35, '250 ml'),
  ('Vinegar (suka)',            'condiment',18,  25, '250 ml'),
  ('Cooking oil',               'condiment',70,  90, '500 ml'),
  ('Patis (fish sauce)',        'condiment',28,  38, '250 ml'),
  ('Bagoong',                   'condiment',35,  55, '250 g');

-- ─────────────────────────────────────────────
--  SEED: recipes
-- ─────────────────────────────────────────────
INSERT INTO recipes (name, category, calories, protein_g, carbs_g, fat_g, cook_time_min, servings, instructions, tags) VALUES
('Chicken Adobo + Kanin',        'lunch',   635, 48.0, 65.0, 14.0, 40, 1,
 'Marinate chicken in soy sauce, vinegar, garlic for 30 min. Pan-fry until golden, add marinade, simmer 20 min. Serve with steamed white rice.',
 'high-protein,classic'),
('Grilled Bangus + Side Salad',  'lunch',   520, 42.0, 30.0, 22.0, 25, 1,
 'Score bangus, rub with garlic, salt, and calamansi. Grill 8 min per side. Serve with sliced tomatoes and onions.',
 'high-protein,low-carb'),
('Fish Sinigang + Kangkong',     'lunch',   490, 38.0, 40.0, 10.0, 35, 1,
 'Boil water with tamarind mix, add fish and vegetables. Season with patis. Serve hot.',
 'low-fat,sour'),
('Tinolang Manok + Malunggay',   'dinner',  580, 44.0, 45.0, 12.0, 50, 1,
 'Sauté garlic, ginger, onion. Add chicken and sauté. Add water, simmer 30 min. Add chayote, then malunggay. Season with patis.',
 'high-protein,anti-inflammatory'),
('Pinakbet + Brown Rice',        'dinner',  420, 22.0, 55.0, 10.0, 35, 1,
 'Sauté garlic and onion. Add pork, bagoong. Add vegetables (squash, ampalaya, sitaw, eggplant). Cover and steam-cook. Serve with brown rice.',
 'vegetable-rich,family-friendly'),
('Arroz Caldo',                  'breakfast', 380, 28.0, 48.0,  8.0, 40, 1,
 'Sauté ginger, garlic, onion. Add chicken and rice. Add water and simmer until porridge consistency. Top with fried garlic and boiled egg.',
 'comfort,high-protein'),
('Tortang Talong',               'breakfast', 290, 18.0, 12.0, 18.0, 20, 1,
 'Grill eggplant until soft, peel skin. Dip in beaten egg mixed with pork. Pan-fry until golden.',
 'budget-friendly,quick'),
('Ginisang Monggo',              'dinner',  450, 24.0, 60.0,  8.0, 45, 1,
 'Soak mung beans overnight. Sauté garlic, onion, tomato. Add mung beans with water, simmer until soft. Add malunggay leaves.',
 'budget-friendly,vegetable-rich'),
('Beef Kaldereta',               'dinner',  640, 42.0, 35.0, 32.0, 90, 1,
 'Brown beef. Sauté garlic, onion, tomato. Add beef, potatoes, carrots, bell pepper. Add tomato sauce, liver spread. Simmer 60 min.',
 'special-occasion,hearty'),
('Banana Saba con Hielo',        'breakfast', 220,  3.0, 52.0,  1.0, 15, 1,
 'Boil saba bananas until tender. Serve with crushed ice and sugar syrup.',
 'budget-friendly,carb-rich');

-- Ingredients for Chicken Adobo (recipe id = 1)
INSERT INTO recipe_ingredients (recipe_id, ingredient_name, quantity, unit) VALUES
(1, 'Chicken thigh/leg', 0.5, 'kg'),
(1, 'Soy sauce (toyo)',  60,  'ml'),
(1, 'Vinegar (suka)',    60,  'ml'),
(1, 'Garlic (bawang)',   20,  'g'),
(1, 'White rice',        0.2, 'kg'),
(1, 'Cooking oil',       15,  'ml');

-- Ingredients for Grilled Bangus (recipe id = 2)
INSERT INTO recipe_ingredients (recipe_id, ingredient_name, quantity, unit) VALUES
(2, 'Bangus (medium, 1pc)', 1,  'pc'),
(2, 'Garlic (bawang)',      15, 'g'),
(2, 'Calamansi',            4,  'pcs'),
(2, 'Tomato',               100,'g'),
(2, 'Onion (sibuyas)',      50, 'g');

-- ─────────────────────────────────────────────
--  SEED: default admin user
--  Password: Admin@1234  (change immediately!)
-- ─────────────────────────────────────────────
INSERT INTO users (first_name, last_name, email, password_hash, goal, household, is_admin) VALUES
('Admin', 'HAPAG', 'admin@hapag.local',
 '$2y$12$Q5vUVN6LB5u.ZGpThCd5DObO2N5kMp7gJlbC3.vO4j3K5Bz3KhRMO',
 'maintenance', 1, 1);
-- ^ hash of "Admin@1234" via password_hash($p, PASSWORD_BCRYPT, ['cost'=>12])

SELECT 'H.A.P.A.G. schema installed successfully.' AS status;
