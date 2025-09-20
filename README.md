# PHP + MySQL Security System

## Overview

This project is a lightweight PHP + MySQL security system with both admin and client sides.  
It logs user access, detects suspicious activity, allows blocking/revoking users/sessions/IPs, and sends Telegram notifications for security events.

## Features

- **Admin Panel** (`index.php`):  
  - Password-protected (single file, Bootstrap UI)
  - View recent access logs, sessions, live sessions
  - Block/revoke users, sessions, IPs
  - Whitelist IPs (override blocks)
  - Delete individual/all logs, sessions, IPs
  - Live auto-refresh (AJAX)
  - Telegram notifications for suspicious activity and admin actions
  - Logout button

- **Client Side** (`security_client.js`):  
  - Collects page URL, referrer, user agent, user/session ID
  - Sends data to `check_access.php`
  - Redirects blocked users to `blocked.html`
  - Masked as SVG/analytics script for stealth

- **Database Tables**:  
  - `users`, `sessions`, `access_logs`, `blocked_ips`, `whitelisted_ips`, `revoked_sessions`

## Setup

1. **Clone or copy files to your server directory**  
   Place all files in `d:\xampp\htdocs\security` (or your web root).

2. **Create the database and tables**  
   Use phpMyAdmin or MySQL CLI to run:
   ```sql
   CREATE DATABASE security;
   USE security;

   CREATE TABLE users (
       user_id VARCHAR(64) PRIMARY KEY,
       revoked TINYINT(1) DEFAULT 0
   );

   CREATE TABLE sessions (
       session_id VARCHAR(128) PRIMARY KEY,
       user_id VARCHAR(64),
       created_at DATETIME DEFAULT CURRENT_TIMESTAMP
   );

   CREATE TABLE access_logs (
       id INT AUTO_INCREMENT PRIMARY KEY,
       user_id VARCHAR(64),
       session_id VARCHAR(128),
       ip VARCHAR(45),
       page TEXT,
       ua TEXT,
       suspicious TINYINT(1) DEFAULT 0,
       created_at DATETIME DEFAULT CURRENT_TIMESTAMP
   );

   CREATE TABLE blocked_ips (
       ip VARCHAR(45) PRIMARY KEY
   );

   CREATE TABLE whitelisted_ips (
       ip VARCHAR(45) PRIMARY KEY
   );

   CREATE TABLE revoked_sessions (
       session_id VARCHAR(128) PRIMARY KEY
   );
   ```

3. **Configure credentials**  
   Edit `config.php` and set your database and Telegram bot credentials.

4. **Telegram notifications**  
   - Create a bot with [BotFather](https://t.me/BotFather) and get the token.
   - Get your chat ID (search for "get chat id telegram" online).
   - Set `TELEGRAM_BOT_TOKEN` and `TELEGRAM_CHAT_ID` in `config.php`.

5. **Include client script**  
   Add to your HTML pages:
   ```html
   <script src="/security/security_client.js"></script>
   ```
   Optionally, set:
   ```html
   <script>
     window.SECURITY_USER_ID = "user123";
     window.SECURITY_SESSION_ID = "sess456";
   </script>
   ```

## Usage

- **Admin Panel:**  
  Visit `/security/index.php` in your browser.  
  Login with the password set in the file (`$admin_password`).  
  Use the panel to view logs, sessions, block/revoke/whitelist IPs, and manage security.

- **Client Side:**  
  The JS script runs silently, sending access info to the server.  
  Blocked/revoked users are redirected to `blocked.html`.

## Security Notes

- Change the admin password in `index.php`!
- Use HTTPS for production.
- Restrict access to the admin panel (e.g., via firewall or VPN).
- Telegram notifications require internet access.

## Customization

- You can adjust suspicious activity detection in `check_access.php`.
- Modify the Bootstrap theme in `index.php` and `blocked.html` for your branding.
- Extend database tables for more user/session info if needed.

## Troubleshooting

- If nothing is displayed, check database connection and table structure.
- Enable error reporting (`error_reporting(E_ALL); ini_set('display_errors', 1);`) for debugging.
- Check browser console for client-side errors.

## License

MIT License (or your preferred license).
