# Data Capture Application

A web app for managing clients and contacts with linking functionality using PHP (MySQLi), MySQL, HTML, CSS, and JavaScript with AJAX and client-side validation.

## Features
- Create/list clients and contacts.
- Auto-generated client codes (e.g., FNB001, ITA001).
- Many-to-many client-contact linking/unlinking via AJAX.
- Tabbed forms with JavaScript validation.

## Installation
1. Clone: `git clone https://github.com/your-username/data-capture-app.git`
2. Set up a web server (e.g., XAMPP).
3. Import MySQL schema from `schema.sql`.
4. Configure `includes/db_connect.php` with MySQL credentials.
5. Access: `http://localhost/data-capture-app`.

## Usage
- `clients.php`: View clients.
- `contacts.php`: View contacts.
- Use tabs to link/unlink contacts/clients via AJAX.
- Forms validate input client-side and submit via AJAX.

## Contributing
- Fork, create branch (`feature/your-feature`), submit PR.
- Report issues via GitHub Issues.