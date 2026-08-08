<?php

declare(strict_types=1);

namespace MangaNexus\Database;

use Exception;
use MangaNexus\Logging\Logger;
use PDO;

class Migrator
{
    /**
     * Run all pending migrations.
     */
    public static function run(PDO $pdo): void
    {
        // 1. Ensure migrations table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS migrations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            version TEXT NOT NULL UNIQUE,
            executed_at TEXT DEFAULT CURRENT_TIMESTAMP
        );");

        // 2. Define standard migrations
        $migrations = [
            '001_init_schema' => function (PDO $pdo) {
                // site_settings table
                $pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (
                    id TEXT PRIMARY KEY DEFAULT 'global',
                    site_title TEXT NOT NULL DEFAULT 'MangaNexus',
                    site_description TEXT NOT NULL DEFAULT 'Read the latest manga series online.',
                    homepage_blog_articles TEXT,
                    homepage_schema TEXT,
                    website_mode TEXT NOT NULL DEFAULT 'general',
                    primary_manga_id TEXT,
                    homepage_categories TEXT NOT NULL DEFAULT '[]',
                    geo_config TEXT NOT NULL DEFAULT '[]',
                    admin_slug TEXT NOT NULL DEFAULT 'admin191103400',
                    admin_username TEXT NOT NULL DEFAULT 'admin',
                    admin_password TEXT NOT NULL DEFAULT '12345',
                    production_domain TEXT NOT NULL DEFAULT 'localhost',
                    current_theme TEXT NOT NULL DEFAULT 'midnight-dark',
                    site_logo TEXT,
                    site_favicon TEXT,
                    license_key TEXT,
                    license_verified_at TEXT,
                    license_verify_cache TEXT,
                    google_analytics_id TEXT,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
                );");

                // Insert default settings
                $pdo->exec("INSERT OR IGNORE INTO site_settings (id, site_title, site_description) 
                            VALUES ('global', 'MangaNexus', 'Read the latest manga series online.')");

                // mangas table
                $pdo->exec("CREATE TABLE IF NOT EXISTS mangas (
                    id TEXT PRIMARY KEY,
                    title TEXT NOT NULL,
                    slug TEXT NOT NULL UNIQUE,
                    description TEXT,
                    cover_url TEXT,
                    status TEXT NOT NULL DEFAULT 'ongoing',
                    blog_content TEXT,
                    seo_schema TEXT,
                    author TEXT DEFAULT 'Unknown',
                    meta_keywords TEXT,
                    meta_tags TEXT,
                    meta_title TEXT,
                    meta_description TEXT,
                    geo_targeting TEXT,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
                );");
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_mangas_slug ON mangas(slug);");

                // chapters table
                $pdo->exec("CREATE TABLE IF NOT EXISTS chapters (
                    id TEXT PRIMARY KEY,
                    manga_id TEXT NOT NULL,
                    number REAL NOT NULL,
                    title TEXT,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY(manga_id) REFERENCES mangas(id) ON DELETE CASCADE,
                    UNIQUE(manga_id, number)
                );");
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_chapters_manga_id ON chapters(manga_id);");

                // pages table
                $pdo->exec("CREATE TABLE IF NOT EXISTS pages (
                    id TEXT PRIMARY KEY,
                    chapter_id TEXT NOT NULL,
                    image_url TEXT NOT NULL,
                    order_index INTEGER NOT NULL,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY(chapter_id) REFERENCES chapters(id) ON DELETE CASCADE,
                    UNIQUE(chapter_id, order_index)
                );");
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_pages_chapter_id ON pages(chapter_id);");
            },
            '002_add_ad_blocks' => function (PDO $pdo) {
                // ad_blocks table
                $pdo->exec("CREATE TABLE IF NOT EXISTS ad_blocks (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    block_index INTEGER NOT NULL UNIQUE,
                    name TEXT NOT NULL,
                    code TEXT,
                    is_active INTEGER DEFAULT 0,
                    insertion_type TEXT DEFAULT 'none',
                    custom_selector TEXT,
                    selector_action TEXT DEFAULT 'before',
                    between_frequency INTEGER DEFAULT 5,
                    target_pages TEXT DEFAULT 'all',
                    target_devices TEXT DEFAULT 'all',
                    wrapper_class TEXT DEFAULT 'ad-block-wrapper',
                    wrapper_style TEXT DEFAULT 'margin: 1rem auto; text-align: center;'
                );");
            },
            '003_add_columns_to_site_settings' => function (PDO $pdo) {
                // Ensure columns exist on site_settings
                try {
                    $pdo->exec("ALTER TABLE site_settings ADD COLUMN google_analytics_id TEXT;");
                } catch (Exception $e) {
                }

                try {
                    $pdo->exec("ALTER TABLE site_settings ADD COLUMN theme_hero_images TEXT DEFAULT '{}';");
                } catch (Exception $e) {
                }

                try {
                    $pdo->exec("ALTER TABLE site_settings ADD COLUMN hero_style TEXT DEFAULT '{}';");
                } catch (Exception $e) {
                }
            },
            '004_add_external_path_to_pages' => function (PDO $pdo) {
                try {
                    $pdo->exec("ALTER TABLE pages ADD COLUMN external_path TEXT;");
                } catch (Exception $e) {
                }
            },
            '005_add_manga_style_columns' => function (PDO $pdo) {
                try {
                    $pdo->exec("ALTER TABLE mangas ADD COLUMN hero_bg_url TEXT;");
                } catch (Exception $e) {
                }

                try {
                    $pdo->exec("ALTER TABLE mangas ADD COLUMN custom_accent_color TEXT;");
                } catch (Exception $e) {
                }

                try {
                    $pdo->exec("ALTER TABLE mangas ADD COLUMN custom_secondary_color TEXT;");
                } catch (Exception $e) {
                }

                try {
                    $pdo->exec("ALTER TABLE mangas ADD COLUMN custom_hero_style TEXT DEFAULT '{}';");
                } catch (Exception $e) {
                }
            },
            '006_add_social_and_visitors' => function (PDO $pdo) {
                // 1. Create users table for general site visitors
                $pdo->exec("CREATE TABLE IF NOT EXISTS users (
                    id TEXT PRIMARY KEY,
                    email TEXT UNIQUE NOT NULL,
                    password TEXT NOT NULL,
                    username TEXT,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP
                );");

                // 2. Add social_links TEXT column to site_settings (holds JSON settings)
                try {
                    $pdo->exec("ALTER TABLE site_settings ADD COLUMN social_links TEXT DEFAULT '{\"facebook\":\"\",\"twitter\":\"\",\"linkedin\":\"\",\"tumblr\":\"\",\"pinterest\":\"\",\"youtube\":\"\",\"discord\":\"\"}';");
                } catch (Exception $e) {
                }
            },
            '007_custom_pages_and_blog_posts' => function (PDO $pdo) {
                // 1. custom_pages table
                $pdo->exec("CREATE TABLE IF NOT EXISTS custom_pages (
                    id TEXT PRIMARY KEY,
                    title TEXT NOT NULL,
                    slug TEXT UNIQUE NOT NULL,
                    content TEXT,
                    is_published INTEGER DEFAULT 1,
                    show_in_footer INTEGER DEFAULT 1,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
                );");
                
                // Seed default Privacy Policy and Contact pages
                $stmt = $pdo->prepare("INSERT OR IGNORE INTO custom_pages (id, title, slug, content, is_published, show_in_footer) VALUES (?, ?, ?, ?, ?, ?)");
                
                $stmt->execute([
                    'page-privacy',
                    'Privacy Policy',
                    'privacy-policy',
                    '<h1>Privacy Policy</h1><p>Your privacy is important to us. This page explains what information we collect and how we use it.</p>',
                    1,
                    1
                ]);

                $stmt->execute([
                    'page-contact',
                    'Contact Us',
                    'contact',
                    '<h1>Contact Us</h1><p>Have questions or feedback? Drop us a line at contact@hsini.dev.</p>',
                    1,
                    1
                ]);

                // 2. blog_posts table
                $pdo->exec("CREATE TABLE IF NOT EXISTS blog_posts (
                    id TEXT PRIMARY KEY,
                    title TEXT NOT NULL,
                    slug TEXT UNIQUE NOT NULL,
                    excerpt TEXT,
                    content TEXT,
                    thumbnail_url TEXT,
                    is_published INTEGER DEFAULT 1,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
                );");
            },
            '008_add_gemini_api_key' => function (PDO $pdo) {
                try {
                    $pdo->exec("ALTER TABLE site_settings ADD COLUMN gemini_api_key TEXT;");
                } catch (Exception $e) {
                }
            },
            '009_add_chatgpt_and_ollama_apis' => function (PDO $pdo) {
                try {
                    $pdo->exec("ALTER TABLE site_settings ADD COLUMN openai_api_key TEXT;");
                } catch (Exception $e) {}
                try {
                    $pdo->exec("ALTER TABLE site_settings ADD COLUMN openai_model TEXT DEFAULT 'gpt-4o-mini';");
                } catch (Exception $e) {}
                try {
                    $pdo->exec("ALTER TABLE site_settings ADD COLUMN ollama_api_url TEXT DEFAULT 'http://localhost:11434';");
                } catch (Exception $e) {}
                try {
                    $pdo->exec("ALTER TABLE site_settings ADD COLUMN ollama_model TEXT DEFAULT 'llama3';");
                } catch (Exception $e) {}
                try {
                    $pdo->exec("ALTER TABLE site_settings ADD COLUMN preferred_ai_provider TEXT DEFAULT 'gemini';");
                } catch (Exception $e) {}
            },
            '010_add_blog_prompt_and_schema' => function (PDO $pdo) {
                try {
                    $pdo->exec("ALTER TABLE blog_posts ADD COLUMN read_manga_url TEXT;");
                } catch (Exception $e) {}
                try {
                    $pdo->exec("ALTER TABLE blog_posts ADD COLUMN seo_schema TEXT;");
                } catch (Exception $e) {}
                try {
                    $pdo->exec("ALTER TABLE site_settings ADD COLUMN blog_ai_prompt TEXT;");
                } catch (Exception $e) {}
            },
            '011_add_custom_hero_fields' => function (PDO $pdo) {
                try {
                    $pdo->exec("ALTER TABLE site_settings ADD COLUMN custom_hero_title TEXT;");
                } catch (Exception $e) {}
                try {
                    $pdo->exec("ALTER TABLE site_settings ADD COLUMN custom_hero_desc TEXT;");
                } catch (Exception $e) {}
                try {
                    $pdo->exec("ALTER TABLE site_settings ADD COLUMN custom_hero_image TEXT;");
                } catch (Exception $e) {}
                try {
                    $pdo->exec("ALTER TABLE site_settings ADD COLUMN custom_hero_link TEXT;");
                } catch (Exception $e) {}
                try {
                    $pdo->exec("ALTER TABLE site_settings ADD COLUMN custom_hero_btn_text TEXT;");
                } catch (Exception $e) {}
            },
            '012_add_sort_order_to_mangas' => function (PDO $pdo) {
                try {
                    $pdo->exec("ALTER TABLE mangas ADD COLUMN sort_order INTEGER DEFAULT 0;");
                } catch (Exception $e) {}
            },
        ];

        // 3. Execute pending migrations
        foreach ($migrations as $version => $callback) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM migrations WHERE version = ?");
            $stmt->execute([$version]);
            if ($stmt->fetchColumn() == 0) {
                Logger::info("Running database migration: $version");

                try {
                    $pdo->beginTransaction();
                    $callback($pdo);
                    $insert = $pdo->prepare("INSERT INTO migrations (version) VALUES (?)");
                    $insert->execute([$version]);
                    $pdo->commit();
                    Logger::info("Migration $version completed successfully.");
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    Logger::error("Migration $version failed: " . $e->getMessage());

                }
            }
        }
    }
}

/**
 * Global website domain name migrator.
 * Updates the database tables, site settings, scans and replaces occurrences across all text project files,
 * and regenerates sitemap and robots.txt.
 */
function migrate_project_domain($old_domain, $new_domain) {
    global $pdo;
    
    $settings = \get_settings();
    
    // Normalize domains
    $old_domain = trim(preg_replace('/^https?:\/\//i', '', $old_domain), '/ ');
    $new_domain = trim(preg_replace('/^https?:\/\//i', '', $new_domain), '/ ');
    
    if (empty($old_domain) || empty($new_domain)) {
        return ['success' => false, 'message' => 'Both old and new domains must be non-empty.'];
    }
    if ($old_domain === $new_domain) {
        return ['success' => false, 'message' => 'Old and new domains are identical. No changes needed.'];
    }
    
    $db_changes = 0;
    $files_updated = 0;
    $errors = [];
    
    // 1. Database Schema migrations
    try {
        $pdo->beginTransaction();
        
        // Get all database tables
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            if ($table === 'migrations') continue;
            
            // Get columns information
            $cols = $pdo->query("PRAGMA table_info([$table])")->fetchAll();
            foreach ($cols as $col) {
                $col_name = $col['name'];
                $type = strtolower($col['type']);
                
                // Skip columns with explicit integer, boolean, or float representations to avoid errors
                if (str_contains($type, 'int') || str_contains($type, 'float') || str_contains($type, 'bool') || str_contains($type, 'numeric')) {
                    continue;
                }
                
                // Replace matching domain occurrences inside rows
                $stmt = $pdo->prepare("UPDATE [$table] SET [$col_name] = REPLACE([$col_name], ?, ?) WHERE [$col_name] LIKE ?");
                $stmt->execute([$old_domain, $new_domain, "%{$old_domain}%"]);
                $db_changes += $stmt->rowCount();
            }
        }
        
        // Explicitly set production_domain in site_settings
        $stmt_settings = $pdo->prepare("UPDATE site_settings SET production_domain = ? WHERE id = 'global'");
        $stmt_settings->execute([$new_domain]);
        
        $pdo->commit();
    } catch (\Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        \MangaNexus\Logging\Logger::error("Database domain migration failed: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database update failed: ' . $e->getMessage()];
    }
    
    // 2. Scan and replace project text files
    try {
        $dir_iterator = new \RecursiveDirectoryIterator(\BASE_PATH, \RecursiveDirectoryIterator::SKIP_DOTS);
        $iterator = new \RecursiveIteratorIterator($dir_iterator, \RecursiveIteratorIterator::SELF_FIRST);
        
        foreach ($iterator as $fileinfo) {
            if ($fileinfo->isFile()) {
                $filepath = $fileinfo->getRealPath();
                
                // Exclude system logs, vendor dependencies, and cover/page uploads
                if (str_contains($filepath, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) ||
                    str_contains($filepath, DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR) ||
                    str_contains($filepath, DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR) ||
                    str_contains($filepath, DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR) ||
                    str_contains($filepath, DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR)) {
                    continue;
                }
                
                // Skip sitemap.xml and robots.txt themselves as we regenerate them
                $filename = $fileinfo->getFilename();
                if ($filename === 'sitemap.xml' || $filename === 'robots.txt') {
                    continue;
                }
                
                $ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
                if (in_array($ext, ['php', 'css', 'js', 'json', 'htaccess', 'txt', 'xml', 'md', 'html'])) {
                    try {
                        $content = file_get_contents($filepath);
                        if (str_contains($content, $old_domain)) {
                            $new_content = str_replace($old_domain, $new_domain, $content);
                            file_put_contents($filepath, $new_content);
                            $files_updated++;
                        }
                    } catch (\Exception $file_ex) {
                        $errors[] = "File: " . $filename . " (" . $file_ex->getMessage() . ")";
                    }
                }
            }
        }
    } catch (\Exception $e) {
        \MangaNexus\Logging\Logger::error("File scanning during domain migration failed: " . $e->getMessage());
        return ['success' => false, 'message' => 'File scanning failed: ' . $e->getMessage()];
    }
    
    // 3. Regenerate SEO assets sitemap.xml and robots.txt
    try {
        \generate_seo_assets();
    } catch (\Exception $seo_ex) {
        $errors[] = "Asset Regeneration: " . $seo_ex->getMessage();
    }
    
    \MangaNexus\Logging\Logger::info("Domain migration completed: '$old_domain' -> '$new_domain'. DB rows: $db_changes. Code files: $files_updated.");
    
    $msg = "🎉 Domain migration successfully completed from <strong>" . htmlspecialchars($old_domain) . "</strong> to <strong>" . htmlspecialchars($new_domain) . "</strong>! " .
           "Updated <strong>{$db_changes}</strong> database fields and <strong>{$files_updated}</strong> source code files.";
    
    if (!empty($errors)) {
        $msg .= " (Note: " . count($errors) . " minor non-critical issues occurred during updates: " . implode(', ', $errors) . ")";
    }
    
    return ['success' => true, 'message' => $msg];
}
