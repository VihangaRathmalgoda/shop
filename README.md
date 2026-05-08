# 🛍️ Sri Lankan Online Clothing Store
## Complete PHP + MySQL + Bootstrap Web Application

---

## 📁 Project Structure

```
shop/
├── index.php                   ← Customer homepage
├── shop.php                    ← Product listing / search
├── product.php                 ← Product detail page
├── cart.php                    ← Cart + Checkout
├── offers.php                  ← Active offers page
├── database.sql                ← ⭐ Run this first!
│
├── includes/
│   ├── config.php              ← DB config + helpers
│   └── theme.php               ← Dynamic theming + shared HTML
│
├── admin/                      ← Admin Portal
│   ├── index.php               ← Dashboard
│   ├── login.php               ← Admin Login
│   ├── logout.php
│   ├── orders.php              ← Order management
│   ├── order_detail.php        ← Order detail + status update
│   ├── products.php            ← Product add/edit/list
│   ├── categories.php          ← Category management
│   ├── stock.php               ← Stock manager (per color+size)
│   ├── banners.php             ← Carousel banner management
│   ├── offers.php              ← Offers & promo codes
│   ├── customers.php           ← Customer list
│   ├── settings.php            ← All settings + themes + payment
│   └── includes/
│       ├── admin_header.php
│       ├── admin_sidebar.php
│       └── admin_footer.php
│
├── customer/
│   ├── login.php               ← Customer login/register
│   ├── account.php             ← Account + order tracking
│   └── logout.php
│
├── api/
│   ├── cart.php                ← Cart API (add/update/remove)
│   └── apply_promo.php         ← Promo code validation
│
└── assets/
    ├── images/
    │   ├── uploads/
    │   │   ├── products/       ← Product images
    │   │   ├── banners/        ← Carousel banner images
    │   │   ├── logos/          ← Store logo
    │   │   └── slips/          ← Payment slips
    │   └── placeholder.png
```

---

## 🚀 Setup Steps

### 1. Database Setup
```sql
-- Import the database schema:
mysql -u root -p < database.sql

-- OR paste the contents of database.sql in phpMyAdmin
```

### 2. Configure Database
Edit `includes/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'clothing_store');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

// Change this to your actual URL:
define('SITE_URL', 'http://localhost/shop');
```

### 3. Upload Permissions
Make sure the uploads directory is writable:
```bash
chmod -R 755 assets/images/uploads/
```

### 4. Admin Login
- URL: `http://yoursite.com/shop/admin/login.php`
- Username: `admin`
- Password: `Admin@1234`
- ⚠️ **Change password immediately after first login!**

---

## ✅ Features Checklist

### 🛒 Customer Portal
- [x] Mobile-first responsive design
- [x] Product listing with filters (category, price, sale, new)
- [x] Product detail with color/size selection
- [x] Image changes when color is selected
- [x] Real-time stock display (per color + size)
- [x] WhatsApp order button with pre-filled message
- [x] Portal cart + checkout
- [x] Customer registration & login
- [x] Order tracking with status progress bar
- [x] Payment slip upload
- [x] Promo code application

### 🔧 Admin Portal
- [x] Dashboard with stats
- [x] Product management (colors, sizes, images, stock)
- [x] Category management (nested)
- [x] Stock manager (bulk edit per color/size)
- [x] Order management with status dropdown
- [x] Order detail with history log
- [x] Banner/carousel management
- [x] Offers & promo code management
- [x] Customer list
- [x] General settings (name, logo, contact, WhatsApp)
- [x] Payment gateway toggles (PayHere, Koko, COD)
- [x] 7 Color themes (Default, Aurudu, Christmas, Vesak, Valentine, Eid, Black Friday)

### 💳 Payment Methods
- [x] Cash on Delivery (COD)
- [x] Bank Transfer + slip upload
- [x] PayHere gateway (toggle on/off + sandbox mode)
- [x] Koko Pay (toggle on/off + sandbox mode)

---

## 🎨 Color Themes
Admin → Settings → Color Themes

| Theme | Occasion |
|-------|----------|
| Default | Normal everyday |
| Aurudu | Sinhala & Tamil New Year |
| Christmas | Christmas season |
| Vesak | Vesak Poya |
| Valentine | Valentine's Day |
| Eid | Eid / Ramadan |
| Black Friday | Sales / promotions |

---

## 📱 WhatsApp Order Flow
1. Customer views product → selects color + size
2. Clicks **"Order via WhatsApp"** button
3. WhatsApp opens with pre-filled message:
   ```
   Hello! I want to order:
   🛍 Item: Blue Floral Dress
   📦 Code: PRD-ABC123
   🎨 Color: Blue
   📏 Size: M
   🔢 Quantity: 1
   ```
4. You reply with payment details
5. Customer sends payment slip
6. You update order status in admin panel

---

## 🔒 Security Notes
- Change default admin password immediately
- Use HTTPS in production (free SSL with Let's Encrypt)
- The DB credentials are in `includes/config.php` — keep this file protected
- Add `.htaccess` to protect the `includes/` folder:
  ```apache
  Deny from all
  ```

---

## 📞 Support
Built for Sri Lankan clothing businesses.
All currency in Rs. | WhatsApp + Portal orders | PayHere + Koko payment support
