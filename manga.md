# MangaNexus Reader (`manga.hsini.dev`)

MangaNexus is an online manga directory and viewer equipped with custom content structures, tracking systems, customizable ad placements, and multiple reading themes.

---

## 🔑 Access URLs & Credentials

### 1. Administrative Control Panel
*   **URL:** [https://manga.hsini.dev/admin191103400](https://manga.hsini.dev/admin191103400)
*   **Credentials:**
    *   **Username:** `admin`
    *   **Password:** `12345`
*   **Purpose:** Managing manga directories, uploading chapters, configuring visual theme templates, managing ad campaigns, and checking visitor telemetry.

---

## 🛠️ Technology Stack & Architecture

*   **Backend Core:** Vanilla PHP with `MangaNexus\\` namespaces.
*   **Database:** SQLite (`data/manga.db`) via PDO.
*   **Import System:** Implements automated zip extraction imports for chapters.

---

## 📁 VPS Deployment Directory Layout

*   📂 **Web Root Folder (`public_html/`)** -> Maps to `/home/hsini/domains/manga.hsini.dev/public_html/`
    *   `index.php` (Core routing file and session guard)
    *   `.htaccess` (Apache routing rules)
    *   `theme.css` (Active stylesheet)
    *   `themes/` (Visual templates: Midnight Dark, Solarized Novel, Cyberpunk)
    *   `uploads/` (Manga chapters, pages, and cover image files)
    *   `favicon.ico`, `HSINI.jfif`, `robots.txt`
*   📁 **Core Logic Folder (One level above `public_html/`)** -> Maps to `/home/hsini/domains/manga.hsini.dev/`
    *   `src/` (Namespaced security and database controllers)
    *   `templates/` (Theme render sections: header, footer, viewer)
    *   `data/` (Contains SQLite database file: `manga.db`)
    *   `import/` (Temporary staging folder for zip uploads)
    *   `config.php` (Site initialization and directory boot)
    *   `.env` (Environment variables)
    *   `composer.json` & `composer.lock`

---

## ⚙️ Initial VPS Provisioning & Directory setup

1.  Extract the deployment files into `/home/hsini/domains/manga.hsini.dev/`.
2.  Install dependencies:
    ```bash
    cd /home/hsini/domains/manga.hsini.dev/
    composer install --no-dev --optimize-autoloader
    ```
3.  Set up SQLite database file permissions (webserver needs write access to the `data/` and `import/` folders):
    ```bash
    chmod -R 775 /home/hsini/domains/manga.hsini.dev/data
    chmod -R 775 /home/hsini/domains/manga.hsini.dev/import
    chmod -R 775 /home/hsini/domains/manga.hsini.dev/public_html/uploads
    ```
