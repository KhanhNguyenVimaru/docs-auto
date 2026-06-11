# DocFormatter - DOCX Style Reformatter & Validator

[![PHP Version](https://img.shields.io/badge/php-%5E8.3-blue.svg)](https://www.php.net/)
[![Laravel Version](https://img.shields.io/badge/laravel-%5E13.0-red.svg)](https://laravel.com)
[![Vite](https://img.shields.io/badge/vite-%5E5.0-purple.svg)](https://vitejs.dev)
[![Docker](https://img.shields.io/badge/docker-supported-blue.svg)](https://www.docker.com/)

DocFormatter is a web application built on **Laravel** (Backend) and **Vite/Tailwind CSS v4** (Frontend) designed to automate the process of formatting, validating, and exporting Word documents (`.docx`) according to standard layout guidelines.

*For the Vietnamese version, please see [readme.vn.md](file:///c:/Users/admin/docs-gpt/readme.vn.md).*

---

## Key Features

* **Auto Formatting**:
  * Automatically sets page margins (top, bottom, left, right) based on selected templates.
  * Prepends tab characters (`\t`) for paragraph first-line indents (excluding heading paragraphs).
  * Automatically aligns line spacing (Line Height) and paragraph spacing after (`spaceAfter`) dynamically.
  * Generates sequential title numbering (headings levels 1 to 4) if enabled.
* **Smart Image Processing**:
  * Dynamically computes the printable page width based on template margins.
  * Automatically resizes (scales) oversized images to fit within the printable margins while maintaining their original aspect ratio.
  * Center-aligns images that are narrower than the printable page width.
  * Automatically prevents tab characters (`\t`) from being prepended to paragraphs containing images.
* **Optimized Bullet Lists**:
  * Integrates deep into the DOCX engine numbering structure to set bullet symbol (dot marker) sizes to **8pt** (16 half-points) for cleaner document design.
  * Enforces tight spacing between bullet items by setting space before/after to `0` and line height to `1.0` (single spacing).
* **Document Quality Validator**:
  * Scans uploaded documents and reports layout non-compliance (incorrect fonts, spacing, alignment, margins, image bounds, and list styles).
* **A4 Live Preview**:
  * Renders formatting results on an A4-proportioned container on the web page, allowing users to verify styling before downloading.

---

## Installation & Getting Started

### Method 1: Using Docker & Docker Compose (Recommended)

Make sure you have Docker and Docker Compose installed on your system. Run the following command at the project root:

```bash
docker-compose up -d --build
```

Once the containers are up:
* Open your browser and navigate to: **[http://localhost:8000](http://localhost:8000)**
* All database migrations, initial seeding, and storage links are automatically set up by the container entrypoint.
* To stop the services: `docker-compose down`

---

### Method 2: Manual Installation (Local Environment)

#### Prerequisites
* PHP >= 8.3 (with `gd`, `zip`, `pdo_mysql`, `mbstring`, `xml`, and `bcmath` extensions installed).
* Composer.
* Node.js >= 18 & NPM.
* A blank MySQL database.

#### Setup Steps:

1. **Install Backend Dependencies**:
   ```bash
   composer install
   ```

2. **Configure Mappings & Keys**:
   Copy `.env.example` to `.env` and fill in your MySQL database credentials, then generate the application key:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Run Migrations & Seeds**:
   ```bash
   php artisan migrate --seed
   php artisan storage:link
   ```

4. **Install Frontend Dependencies & Bundle Assets**:
   ```bash
   npm install
   npm run build
   ```

5. **Start Servers (Development Mode)**:
   Launch the concurrent server (runs Artisan host and Vite development server simultaneously with hot-reload support):
   ```bash
   npm run dev
   ```
   *Access the application at **[http://localhost:8000](http://localhost:8000)***

---

## Core File Directory

* [DocumentProcessingService.php](file:///c:/Users/admin/docs-gpt/app/Services/DocumentProcessingService.php): Primary engine handling DOCX element traversal, image sizing, list formatting, and style validation.
* [DocumentFormatHelper.php](file:///c:/Users/admin/docs-gpt/app/Helpers/DocumentFormatHelper.php): Helper functions for units conversion and PhpWord styling objects generator.
* [Numbering.php](file:///c:/Users/admin/docs-gpt/vendor/phpoffice/phpword/src/PhpWord/Writer/Word2007/Part/Numbering.php): Custom XML serialization writer overriding bullet size properties to 8pt.
* [index.blade.php](file:///c:/Users/admin/docs-gpt/resources/views/index.blade.php): Modern Single Page application view styled with Tailwind CSS v4 and DaisyUI.
