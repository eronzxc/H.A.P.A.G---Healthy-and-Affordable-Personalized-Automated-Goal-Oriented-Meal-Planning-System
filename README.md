# H.A.P.A.G. — Meal Planning & Nutrition Management System

---

## 1. Project Description

### 1.1 Introduction (Product Overview)

**H.A.P.A.G.** stands for **Healthy and Affordable Personalized Automated Goal-Oriented Meal Planning System**. It is a comprehensive web-based meal planning and nutrition management system designed to help users create personalized meal plans based on their health goals, dietary preferences, and budget constraints. The system integrates with the **Bantay Presyo** (Price Watch) API to provide real-time market pricing data, enabling users to make informed decisions about ingredient purchases and meal planning.

The application provides a modern, user-friendly interface for meal planning while leveraging a robust backend API that manages recipes, nutrition calculations, user profiles, meal plan generation, and price tracking.

### 1.2 Product Purpose (Purpose, Context & Scope)

**Purpose:**  
H.A.P.A.G. bridges the gap between nutrition science, affordable cooking, and personalized health goals by providing users with:
- Customized meal recommendations based on their health objectives (muscle building, weight loss, maintenance, performance, family nutrition)
- Real-time market price information to manage household budgets effectively
- Automatic calorie and nutritional calculations
- Meal plan generation tailored to individual dietary restrictions and preferences

**Context:**  
In regions with diverse agricultural markets (particularly the Philippines), consumers often struggle to balance nutrition, affordability, and meal planning. H.A.P.A.G. addresses this by combining a comprehensive recipe database with live market pricing data and personalized nutrition guidance.

**Scope:**  
The system includes:
- User authentication and profile management
- Recipe database with nutritional information
- Meal plan creation and management
- Price tracking integration with Bantay Presyo
- Admin tools for recipe and pricing management
- Mobile-responsive web interface
- RESTful API for data access

### 1.3 Intended Audience

- **Primary Users:** Individuals and families seeking to plan meals based on health goals and budget constraints
- **Secondary Users:** Nutritionists, dietitians, and health coaches recommending meal plans to clients
- **Tertiary Users:** Kitchen staff and meal preparers implementing the generated meal plans
- **Admin Users:** System administrators and content managers who maintain recipes and pricing data

### 1.4 Intended Use

H.A.P.A.G. is intended to be used in the following scenarios:
1. **Personal Meal Planning:** Users create customized meal plans for themselves or their household
2. **Budget Management:** Users identify affordable meal options within their weekly budget constraints
3. **Health Goal Tracking:** Users follow nutrition plans tailored to specific health objectives
4. **Dietary Accommodation:** Users exclude ingredients due to allergies, preferences, or cultural restrictions
5. **Community Nutrition:** Organizations use the system to guide community members toward healthier, more affordable eating
6. **Price Discovery:** Users compare ingredient costs across the market using real-time price data

### 1.5 User Class and Characteristics

**User Class 1: Individual Health-Conscious Users**
- Age: 18-65 years
- Technical Proficiency: Moderate to high
- Goal: Personal nutrition and health management
- Device: Desktop, tablet, or smartphone
- Frequency: Daily to weekly usage

**User Class 2: Budget-Conscious Families**
- Age: 25-60 years (household decision-makers)
- Technical Proficiency: Low to moderate
- Goal: Affordable meal planning for household
- Device: Primarily mobile and desktop
- Frequency: Weekly planning sessions

**User Class 3: Health Professionals**
- Age: 25-70 years
- Technical Proficiency: Moderate to high
- Goal: Recommend personalized meal plans to clients
- Device: Desktop and tablet
- Frequency: Daily usage in professional settings

**User Class 4: System Administrators**
- Age: 20-55 years
- Technical Proficiency: High
- Goal: Maintain system data, recipes, and pricing information
- Device: Desktop
- Frequency: Regular maintenance and updates

---

## 2. System Features and Requirements

### 2.1 Functional Requirements and Non-Functional Requirements

**Functional Requirements:**

| ID | Requirement | Description |
|---|---|---|
| FR1 | User Registration | System shall allow users to create accounts with email and password |
| FR2 | User Authentication | System shall authenticate users and maintain session management |
| FR3 | User Profile Management | System shall allow users to set health goals, dietary preferences, and household size |
| FR4 | Recipe Database | System shall maintain a searchable repository of recipes with ingredients and nutritional information |
| FR5 | Meal Plan Generation | System shall generate personalized meal plans based on user goals and preferences |
| FR6 | Price Integration | System shall integrate with Bantay Presyo API to display real-time ingredient prices |
| FR7 | Budget Tracking | System shall calculate meal plan costs and alert users when exceeding budget limits |
| FR8 | Dietary Exclusions | System shall exclude ingredients based on allergies and user preferences |
| FR9 | Nutrition Calculation | System shall automatically calculate calories, macronutrients, and micronutrients for meal plans |
| FR10 | Admin Recipe Management | System shall provide admin interface to create, update, and manage recipes |
| FR11 | Admin Price Management | System shall provide tools to manage pricing data and updates |
| FR12 | API Endpoints | System shall provide RESTful APIs for all core functionality |

**Non-Functional Requirements:**

| ID | Requirement | Description |
|---|---|---|
| NFR1 | Performance | Page load time shall not exceed 2 seconds for standard connections |
| NFR2 | Availability | System shall maintain 99.5% uptime during standard operating hours |
| NFR3 | Scalability | System shall support up to 10,000 concurrent users |
| NFR4 | Usability | Interface shall be intuitive and require minimal training |
| NFR5 | Compatibility | System shall work on Chrome, Firefox, Safari, and Edge browsers |
| NFR6 | Data Integrity | System shall maintain referential integrity across all database tables |
| NFR7 | Security | All user data shall be encrypted in transit and at rest |
| NFR8 | Maintainability | Code shall follow PSR standards and include comprehensive documentation |

### 2.2 System Features

**Core Features:**
1. **User Management**
   - Registration and login
   - Profile creation with health metrics (age, weight, height, activity level)
   - Health goal selection (muscle building, weight loss, maintenance, performance, family)
   - Allergy and exclusion management
   - Weekly budget setting

2. **Recipe Management**
   - Searchable recipe database
   - Ingredient tracking with quantities
   - Nutritional information per serving
   - Category classification (breakfast, lunch, dinner, snack)
   - Goal-based tagging

3. **Meal Planning**
   - Generate personalized meal plans
   - Adjust meal plans based on preferences
   - View nutritional summary for planned meals
   - Save and reuse meal plans
   - Export meal plans (future)

4. **Price Integration**
   - Real-time ingredient pricing from Bantay Presyo
   - Price filtering by category
   - Weekly price updates
   - Budget comparison tools

5. **Admin Dashboard**
   - Recipe CRUD operations
   - Price management and updates
   - User management
   - Analytics and reporting (future)

### 2.3 Hardware Requirements

**Minimum Hardware Requirements:**

| Component | Specification |
|---|---|
| Processor | Intel Core i3 / AMD Ryzen 3 (2.0 GHz or higher) |
| RAM | 4 GB minimum, 8 GB recommended |
| Storage | 20 GB available disk space |
| Network | 10 Mbps internet connection |

**Server Hardware:**
- Processor: Dual-core 2.0+ GHz
- RAM: 4 GB minimum, 8+ GB recommended for 100+ concurrent users
- Storage: SSD with 50+ GB capacity
- Network: 100 Mbps+ connection

### 2.4 Software Requirements

**Server-Side:**
- **Web Server:** Apache 2.4+ with mod_rewrite enabled
- **Database:** MySQL 5.7+ or MariaDB 10.2+
- **PHP:** PHP 7.4 or PHP 8.0+
- **Required PHP Extensions:** PDO, PDO MySQL, OpenSSL, JSON, cURL

**Client-Side:**
- **Browser:** Chrome 90+, Firefox 88+, Safari 14+, Edge 90+
- **JavaScript:** ES6 support required
- **CSS:** CSS3 support required

**Development Stack:**
- **Version Control:** Git
- **Package Management:** Composer (PHP dependencies)
- **Testing:** PHPUnit (future)

### 2.5 Performance Requirements

| Metric | Target |
|---|---|
| Page Load Time | < 2 seconds (full page) |
| API Response Time | < 500 ms (95th percentile) |
| Database Query Time | < 200 ms |
| Maximum Concurrent Users | 10,000+ |
| Data Processing | Meal plan generation: < 5 seconds |
| Cache Hit Rate | > 80% for recipe queries |
| Session Timeout | 30 minutes inactivity |
| File Upload Limit | 5 MB per file (images) |

### 2.6 Safety Requirements

1. **Data Integrity**
   - Transactional consistency for financial calculations
   - Regular database backups (daily minimum)
   - Disaster recovery plan with RTO ≤ 4 hours

2. **Error Handling**
   - Graceful error messages without exposing system details
   - Comprehensive logging of errors and exceptions
   - Automated alerts for critical errors

3. **Accessibility**
   - WCAG 2.1 AA compliance for accessibility
   - Keyboard navigation support
   - Screen reader compatibility

4. **Input Validation**
   - Server-side validation of all user inputs
   - Prevention of SQL injection attacks
   - XSS (Cross-Site Scripting) prevention

### 2.7 Security Requirements

1. **Authentication & Authorization**
   - Secure password hashing (bcrypt/Argon2)
   - Session-based authentication with secure cookies
   - Role-based access control (user, admin)
   - Account lockout after failed login attempts

2. **Data Protection**
   - HTTPS/TLS 1.2+ for all communications
   - Encrypted storage of sensitive data
   - Password reset via secure token
   - No storage of plain-text passwords

3. **API Security**
   - Input sanitization for all API endpoints
   - SQL injection prevention (prepared statements)
   - CSRF token protection for state-changing operations
   - Rate limiting on authentication endpoints (5 attempts/minute)

4. **Privacy**
   - Compliance with local data protection regulations
   - User consent for data collection
   - Data retention policies (minimum viable duration)
   - User right to data export and deletion (future)

5. **Code Security**
   - Regular security code reviews
   - Dependency vulnerability scanning
   - OWASP Top 10 compliance
   - Security headers (CSP, X-Frame-Options, X-Content-Type-Options)

### 2.8 Software Quality Attributes

1. **Reliability**
   - Mean Time Between Failures (MTBF): > 720 hours
   - Mean Time To Recovery (MTTR): < 30 minutes
   - Critical bug fix response: < 24 hours

2. **Maintainability**
   - Code maintainability index: > 70
   - Cyclomatic complexity: < 10 per function
   - Test coverage: > 70%
   - Code documentation: Inline comments and README per module

3. **Usability**
   - System Usability Scale (SUS) score: > 70
   - User task completion rate: > 95%
   - Average time to complete core task: < 3 minutes

4. **Portability**
   - Cross-browser compatibility: Chrome, Firefox, Safari, Edge
   - Cross-platform support: Windows, Linux, macOS
   - Mobile responsive design (Bootstrap/responsive framework)

5. **Testability**
   - Unit test coverage for business logic
   - Integration test coverage for API endpoints
   - Automated regression testing
   - Manual testing procedures documented

6. **Efficiency**
   - Database query optimization with indexing
   - Caching strategy for frequent queries
   - Minified CSS and JavaScript
   - Lazy loading for images

---

## 📁 Project Structure

```
htdocs/hapag/
├── index.php              ← Main landing page (PHP-wired)
├── hapag-styles.css       ← Frontend stylesheet (copy from upload)
│
├── config/
│   └── db.php             ← Database credentials
│
├── includes/
│   ├── auth.php           ← Session / login helpers
│   └── helpers.php        ← Utilities (json_response, calc_calories…)
│
├── api/
│   ├── register.php       ← POST /api/register.php
│   ├── login.php          ← POST /api/login.php
│   ├── logout.php         ← GET  /api/logout.php
│   ├── prices.php         ← GET/POST/DELETE /api/prices.php
│   ├── recipes.php        ← GET  /api/recipes.php
│   └── meal_plan.php      ← GET/POST/DELETE /api/meal_plan.php
│
├── admin/
│   └── prices.php         ← Admin price management UI
│
└── sql/
    └── hapag_schema.sql   ← Full DB schema + seed data
```

---

## 🚀 Quick Start

### 1 — Install XAMPP
Download from https://www.apachefriends.org and install.
Start **Apache** and **MySQL** in the XAMPP Control Panel.

### 2 — Copy the project
Place the `hapag/` folder inside:
```
C:\xampp\htdocs\hapag\        (Windows)
/opt/lampp/htdocs/hapag/      (Linux/Mac)
```

### 3 — Import the database
1. Open **phpMyAdmin**: http://localhost/phpmyadmin
2. Click **Import** → choose `sql/hapag_schema.sql` → click **Go**

   _Or via terminal:_
   ```bash
   mysql -u root -p < hapag_schema.sql
   ```

### 4 — Configure DB credentials
Edit `config/db.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');       // ← your MySQL root password (blank by default in XAMPP)
define('DB_NAME', 'hapag_db');
```

### 5 — Open the app
Visit: **http://localhost/hapag/**

---

## 🔑 Default Admin Account
| Field    | Value            |
|----------|------------------|
| Email    | admin@hapag.local|
| Password | Admin@1234       |

> ⚠️ Change this password immediately after first login!

---

## 🌐 API Reference

### Auth
| Method | Endpoint              | Description              |
|--------|-----------------------|--------------------------|
| POST   | `/api/register.php`   | Create account + auto-login |
| POST   | `/api/login.php`      | Sign in                  |
| GET    | `/api/logout.php`     | Sign out                 |

### Content
| Method | Endpoint                | Description              |
|--------|-------------------------|--------------------------|
| GET    | `/api/recipes.php`      | All recipes              |
| GET    | `/api/recipes.php?id=N` | Single recipe + ingredients |
| GET    | `/api/prices.php`       | All Bantay Presyo prices |
| GET    | `/api/prices.php?category=fish` | Filter by category |

### Meal Plans (requires login)
| Method | Endpoint                | Description              |
|--------|-------------------------|--------------------------|
| GET    | `/api/meal_plan.php`    | Current user's active plan |
| POST   | `/api/meal_plan.php`    | Generate new 7-day plan  |
| DELETE | `/api/meal_plan.php?id=N` | Archive a plan         |

### Admin (requires admin login)
| Method | Endpoint                | Description              |
|--------|-------------------------|--------------------------|
| POST   | `/api/prices.php`       | Add or update a price    |
| DELETE | `/api/prices.php?id=N`  | Delete a price entry     |
| GET    | `/admin/prices.php`     | Price manager UI         |

---

## 🗄️ Database Tables

| Table               | Purpose                                 |
|---------------------|-----------------------------------------|
| `users`             | Registered accounts                     |
| `user_preferences`  | Allergies, exclusions, budget cap        |
| `recipes`           | Filipino recipe library                  |
| `recipe_ingredients`| Per-recipe ingredient list               |
| `food_prices`       | Bantay Presyo price data                 |
| `meal_plans`        | Weekly plan headers                      |
| `meal_plan_days`    | 7×3 meal assignments per plan           |
| `user_sessions`     | Server-side session store (optional)     |

---

## 🔐 Security Notes (before going live)
- Set a strong `DB_PASS` in `config/db.php`
- Change the default admin password
- Set `'secure' => true` in `auth.php` cookie params when using HTTPS
- Add `.htaccess` to block direct access to `config/` and `includes/`
- Rate-limit `/api/register.php` and `/api/login.php`

---

## 📦 PHP Requirements
- PHP 8.0+
- PDO + PDO_MySQL extension
- `password_hash()` / `password_verify()` (built-in since PHP 5.5)

XAMPP ships with all of these by default.
