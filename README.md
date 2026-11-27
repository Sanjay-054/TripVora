# TripVora — Tourist Trip Planning & Booking (Angular + PHP OOP + MySQL)

A complete **local development starter application**, not a production booking service. Includes customer/admin authentication, one-time OTP, destination/attraction selection, day-wise suggested itinerary, optional hotels and vehicles, availability checks, pricing, demo payments, invoice and admin CRUD/overview.

## Windows XAMPP setup
1. Install PHP 8.2+, Apache, MySQL, Node.js LTS. Put the **TripVora** folder directly inside `C:\xampp\htdocs\TripVora` (keep the folder name and case consistent).
2. Start Apache and MySQL. In phpMyAdmin, **Import** `database/schema.sql`. Database name is `tripvora` (not the old `vehicle_rental`). The SQL includes demo destinations, attractions, hotels and vehicles. Demo attraction prices are illustrative, not live official fees.
3. Backend defaults to `DB_HOST=127.0.0.1`, `DB_PORT=4309`, `DB_NAME=tripvora`, `DB_USER=root`, `DB_PASS=`. If your MySQL uses a different port, set the environment variable `DB_PORT` in Apache's environment (or change the fallback in `backend/src/Core.php`). Apache/PHP must have `pdo_mysql` enabled.
4. Open a terminal inside `C:\xampp\htdocs\TripVora\frontend`. Run `npm install` and then `npm start`. Open **http://localhost:4200**. The Angular proxy routes `/api` requests to the PHP endpoint at `http://localhost/TripVora/backend/public/index.php`.
5. To create an admin, open XAMPP Shell, navigate to `C:\xampp\htdocs\TripVora\backend\scripts`, and run `php create_admin.php admin "ChooseAStrongUniquePassword123!"`. **Never run the admin script from a browser.** It refuses duplicate usernames.
6. Register a customer. In local mode (`APP_ENV=local`, `MAIL_MODE=dev`), the OTP is displayed on screen for testing. Enter it within 10 minutes, then login. OTPs cannot be reused and have five attempts. Production requires real SMTP/email integration; PHP `mail()` is only a basic optional transport and should not be relied on for production.
7. Create a trip, select attractions/hotel/vehicle, preview pricing and itinerary, book, then click **Confirm Demo Payment**. No real money is charged. Use My Trips to view invoice and print/save as PDF.

## Features
- Angular 19 frontend, responsive premium navy/gold interface; TypeScript HTTP service and session-based login.
- PHP OOP services with encapsulated DB, AuthService, TripService, abstract AccountService, pricing interface and interchangeable per-day/per-night calculators.
- PDO native prepared statements with unique named placeholders (avoids prior HY093 issue).
- Secure password hashes, OTP hashes, OTP expiry/attempts/single-use, session regeneration, CSRF protection, role checks.
- Destination, attractions, hotels, vehicles, trip booking, booking history, demo payment, printable invoice.
- Admin login, management tables, add/edit/deactivate hotels/vehicles, destinations/attractions, customer and trip visibility, business overview.

## Important development limitations
- **Not a real hotel reservation platform:** sample hotels and inventory; one room per booking; no external hotel provider connection.
- **Not a real payment integration:** demo payment action is intentionally blocked when `APP_ENV` is not `local`.
- **Not real email by default:** local development OTP is displayed on screen; configure an authenticated SMTP provider and remove OTP disclosure for production.
- Hotel availability is based on date overlap and total rooms; vehicle overlap is prevented. Database-level transaction serialization is used for booking, but high-scale deployments require additional operational safeguards.
- Itinerary is a simple distribution of selected attractions across days; real routing, opening hours, ferry availability and map integrations are future work.
- Prices and ratings in seed data are demo figures. Real attraction fees and hotel rates must be verified.
- Admin delete for hotels/vehicles deactivates them, preserving booking history. Deleting destinations/attractions referenced by bookings is restricted by foreign keys.
- Before any public deployment: configure HTTPS, real SMTP, payment provider/webhook verification, production environment variables, secrets, server access restrictions, rate limiting, security testing and backup/restore.

## Useful URLs
- Angular UI: `http://localhost:4200`
- API test: `http://localhost/TripVora/backend/public/index.php?action=destinations`
- phpMyAdmin: `http://localhost/phpmyadmin/`

## Existing RideRent data
This project creates a **new** `tripvora` database; it does not automatically migrate the earlier `vehicle_rental` database. Back up your old database before changing anything. Customer/admin accounts must be registered or migrated explicitly.
