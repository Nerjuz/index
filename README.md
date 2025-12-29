# Single-File PHP File Manager

A modern, lightweight, and powerful file management solution contained within a single PHP file. Drop it on your server and start managing files immediately.

**[🔴 Live Demo](https://ite.lt/index/)** (Password: `password`)

## 🚀 Features

### Core Functionality
-   **Single File**: No database or complex installation required. Just `index.php`.
-   **Security**: Built-in password protection (optional).
-   **File Operations**:
    -   **Upload**: Drag & drop support for multiple files.
    -   **Create**: Create new files directly in the browser.
    -   **Edit**: Full-featured code editor (CodeMirror) for text/code files.
    -   **Rename**: Rename files and folders.
    -   **Delete**: Recursive deletion for folders and files.
    -   **Download**: Secure downloads with correct filename handling.

### Rich Previewing
-   **Images**: Dynamic zoom and pan controls.
-   **Video & Audio**: Built-in HTML5 media players.
-   **Code/Text**: Syntax highlighting for various languages (PHP, JS, CSS, HTML, SQL, etc.).
-   **Fonts**: Live preview for TTF/OTF/WOFF fonts with sample text.
-   **PDF**: Native PDF embedding.
-   **Office Docs**: Preview Word/Excel/PowerPoint files (via Google Docs Viewer).

### User Interface
-   **Modern Design**: Dark mode with glassmorphism aesthetic.
-   **Responsive**: Fully functional on mobile and desktop.
-   **Search**: Real-time client-side filtering of file lists.
-   **Navigation**: Breadcrumb navigation and easy folder traversal.

## 🛠 Installation

1.  Download `index.php`.
2.  Upload it to any directory on your PHP-enabled web server.
3.  Navigate to the file in your browser (e.g., `https://your-site.com/files/index.php`).

## ⚙️ Configuration

Open `index.php` in a text editor to find the configuration section at the top:

```php
$config = [
    'title' => 'Files Explorer',
    'password' => '', // Set a password to enable login protection
    'allow_upload' => true,
    'allow_create' => true,
    'allow_rename' => true,
    'allow_delete' => true,
    'exclude_files' => ['index.php', 'Dockerfile', ...], // Files to hide
    'exclude_extensions' => ['tmp', 'bak', 'log'],       // Extensions to hide
    // ...
];
```

### Options
-   **`password`**: Leave empty to disable authentication. Set a string to require a login.
-   **`allow_*`**: Toggle specific features on or off (`true`/`false`).
-   **`exclude_files`**: Array of filenames to hide from the list.
-   **`exclude_extensions`**: Array of file extensions to hide.

## 📋 Requirements
-   PHP 7.4 or higher.
-   Extensions: `json`, `mbstring` (standard in most installations).

## 📄 License
MIT License. Feel free to modify and adapt for your own use.
