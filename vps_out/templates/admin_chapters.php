<?php
/**
 * admin_chapters.php — Admin Chapter Uploads & Page Sorter (PHP Version)
 */

// ── Runtime overrides for large ZIP file uploads ──────────────────────────────
// These apply under both Apache (mod_php) and Nginx (PHP-FPM), overriding
// any php.ini defaults that would otherwise cause 500 errors on big archives.
@ini_set('upload_max_filesize', '5120M');
@ini_set('post_max_size', '5120M');
@ini_set('memory_limit', '512M');
@ini_set('max_execution_time', '3600');
@ini_set('max_input_time', '3600');
set_time_limit(0); // No execution time limit for image processing loops

if (empty($manga_id)) {
    header("Location: /" . $admin_slug . "/manga");
    exit;
}

// Fetch current manga details
$manga = db_fetch("SELECT * FROM mangas WHERE id = ?", [$manga_id]);
if (!$manga) {
    header("Location: /" . $admin_slug . "/manga");
    exit;
}

$error = '';
$success = '';
$focused_chapter_id = isset($_GET['ch_id']) ? trim($_GET['ch_id']) : '';

// ── Handle Reorder API/POST ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reorder') {
    $ordered_ids = isset($_POST['ordered_ids']) ? explode(',', $_POST['ordered_ids']) : [];
    if (!empty($ordered_ids) && !empty($focused_chapter_id)) {
        try {
            $pdo->beginTransaction();
            foreach ($ordered_ids as $index => $page_id) {
                $order_index = $index + 1;
                db_query(
                    "UPDATE pages SET order_index = ? WHERE id = ? AND chapter_id = ?",
                    [$order_index, $page_id, $focused_chapter_id]
                );
            }
            $pdo->commit();
            $success = 'Pages re-ordered successfully.';
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Failed to save page order: ' . $e->getMessage();
        }
    }
}

// ── Handle Delete Chapter ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['sub_action']) && $_GET['sub_action'] === 'delete' && !empty($focused_chapter_id)) {
    try {
        $ch = db_fetch("SELECT * FROM chapters WHERE id = ?", [$focused_chapter_id]);
        if ($ch) {
            // Delete folder on disk
            $ch_dir = PAGES_DIR . '/' . $manga['slug'] . '/ch-' . $ch['number'];
            if (is_dir($ch_dir)) {
                $files = array_diff(scandir($ch_dir), array('.', '..'));
                foreach ($files as $file) {
                    unlink($ch_dir . '/' . $file);
                }
                rmdir($ch_dir);
            }
            
            // Delete database entries
            db_query("DELETE FROM chapters WHERE id = ?", [$focused_chapter_id]);
            $success = "Chapter " . $ch['number'] . " deleted successfully.";
            try {
                generate_seo_assets();
            } catch (Exception $seo_ex) {
                \MangaNexus\Logging\Logger::error("Failed to automatically regenerate SEO assets on chapter delete: " . $seo_ex->getMessage());
            }
            $focused_chapter_id = '';
        }
    } catch (PDOException $e) {
        $error = 'Failed to delete chapter: ' . $e->getMessage();
    }
}

// ── Handle Single Chapter Upload ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_type']) && $_POST['upload_type'] === 'single') {
    set_time_limit(0); // Ensure no timeout during image extraction loop
    $ch_num = floatval($_POST['chapter_number']);
    $ch_title = trim($_POST['chapter_title']) ?: null;

    // Check for PHP upload errors and map to human-readable messages
    $upload_error_map = [
        UPLOAD_ERR_INI_SIZE   => 'The file exceeds the server upload_max_filesize limit. Please update php.ini or contact your host.',
        UPLOAD_ERR_FORM_SIZE  => 'The file exceeds the form MAX_FILE_SIZE limit.',
        UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded. Please try again.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder on the server.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk. Check server permissions.',
        UPLOAD_ERR_EXTENSION  => 'A PHP extension blocked the file upload.',
    ];

    if (empty($ch_num) && $ch_num !== 0.0) {
        $error = 'Valid chapter number is required.';
    } else if (!isset($_FILES['zip_file']) || $_FILES['zip_file']['error'] !== UPLOAD_ERR_OK) {
        $upload_err_code = $_FILES['zip_file']['error'] ?? UPLOAD_ERR_NO_FILE;
        $error = $upload_error_map[$upload_err_code] ?? 'Valid ZIP file is required (unknown upload error code: ' . $upload_err_code . ').';
        if ($upload_err_code === UPLOAD_ERR_INI_SIZE || $upload_err_code === UPLOAD_ERR_FORM_SIZE) {
            $error .= ' Current server limit: upload_max_filesize=' . ini_get('upload_max_filesize') . ', post_max_size=' . ini_get('post_max_size') . '.';
        }
    } else if (strtolower(pathinfo($_FILES['zip_file']['name'], PATHINFO_EXTENSION)) !== 'zip') {
        $error = 'Only ZIP files are allowed.';
    } else {
        $zip_tmp = $_FILES['zip_file']['tmp_name'];
        $zip = new ZipArchive();
        
        if ($zip->open($zip_tmp) === TRUE) {
            // Filter and gather image files
            $image_entries = [];
            $valid_exts = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'bmp'];
            
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = $stat['name'];
                
                // Skip directories and system files
                if (substr($name, -1) === '/' || strpos($name, '__MACOSX') !== false || substr(basename($name), 0, 1) === '.') {
                    continue;
                }
                
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (in_array($ext, $valid_exts)) {
                    $image_entries[] = $name;
                }
            }
            
            if (empty($image_entries)) {
                $error = 'No valid images found in the uploaded ZIP file.';
            } else {
                // Natural alphabetical sorting
                usort($image_entries, 'strnatcasecmp');
                
                // Create chapter directory
                $ch_dir = PAGES_DIR . '/' . $manga['slug'] . '/ch-' . $ch_num;
                if (!is_dir($ch_dir)) {
                    mkdir($ch_dir, 0755, true);
                }
                
                try {
                    $pdo->beginTransaction();
                    
                    // Fetch or Create Chapter
                    $chapter = db_fetch(
                        "SELECT id FROM chapters WHERE manga_id = ? AND number = ?", 
                        [$manga['id'], $ch_num]
                    );
                    
                    if ($chapter) {
                        $chapter_id = $chapter['id'];
                        // Clean existing pages
                        db_query("DELETE FROM pages WHERE chapter_id = ?", [$chapter_id]);
                        // Update title
                        db_query("UPDATE chapters SET title = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?", [$ch_title, $chapter_id]);
                    } else {
                        $chapter_id = uuid();
                        db_query(
                            "INSERT INTO chapters (id, manga_id, number, title, created_at, updated_at) 
                             VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)",
                            [$chapter_id, $manga['id'], $ch_num, $ch_title]
                        );
                    }
                    
                    // Process images
                    foreach ($image_entries as $idx => $entry) {
                        $page_index = $idx + 1;
                        $webp_name = sprintf('page-%03d.webp', $page_index);
                        $dest_path = $ch_dir . '/' . $webp_name;
                        
                        // Extract file to tmp file
                        $img_stream = $zip->getStream($entry);
                        $tmp_img = tempnam(sys_get_temp_dir(), 'manga_zip_');
                        file_put_contents($tmp_img, $img_stream);
                        fclose($img_stream);
                        
                        // Optimize and encode to WebP
                        optimize_image($tmp_img, $dest_path, 80);
                        unlink($tmp_img);
                        
                        $public_url = '/uploads/pages/' . $manga['slug'] . '/ch-' . $ch_num . '/' . $webp_name;
                        
                        // Insert page records
                        db_query(
                            "INSERT INTO pages (id, chapter_id, image_url, order_index, created_at) 
                             VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)",
                            [uuid(), $chapter_id, $public_url, $page_index]
                        );
                    }
                    
                    $pdo->commit();
                    $success = "Successfully uploaded Chapter $ch_num with " . count($image_entries) . " pages.";
                    try {
                        generate_seo_assets();
                    } catch (Exception $seo_ex) {
                        \MangaNexus\Logging\Logger::error("Failed to automatically regenerate SEO assets on single chapter upload: " . $seo_ex->getMessage());
                    }
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Upload processing failed: ' . $e->getMessage();
                }
            }
            $zip->close();
        } else {
            $error = 'Unable to open ZIP archive.';
        }
    }
}

// ── Handle Bulk Manga Upload ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_type']) && $_POST['upload_type'] === 'bulk') {
    set_time_limit(0); // Ensure no timeout during bulk image extraction loop

    $upload_error_map_bulk = [
        UPLOAD_ERR_INI_SIZE   => 'The file exceeds the server upload_max_filesize limit. Please update php.ini or contact your host.',
        UPLOAD_ERR_FORM_SIZE  => 'The file exceeds the form MAX_FILE_SIZE limit.',
        UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded. Please try again.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder on the server.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk. Check server permissions.',
        UPLOAD_ERR_EXTENSION  => 'A PHP extension blocked the file upload.',
    ];

    if (!isset($_FILES['zip_file']) || $_FILES['zip_file']['error'] !== UPLOAD_ERR_OK) {
        $upload_err_code = $_FILES['zip_file']['error'] ?? UPLOAD_ERR_NO_FILE;
        $error = $upload_error_map_bulk[$upload_err_code] ?? 'Valid bulk ZIP file is required (unknown error code: ' . $upload_err_code . ').';
        if ($upload_err_code === UPLOAD_ERR_INI_SIZE || $upload_err_code === UPLOAD_ERR_FORM_SIZE) {
            $error .= ' Current server limit: upload_max_filesize=' . ini_get('upload_max_filesize') . ', post_max_size=' . ini_get('post_max_size') . '.';
        }
    } else if (strtolower(pathinfo($_FILES['zip_file']['name'], PATHINFO_EXTENSION)) !== 'zip') {
        $error = 'Only ZIP files are allowed.';
    } else {
        $zip_tmp = $_FILES['zip_file']['tmp_name'];
        $zip = new ZipArchive();
        
        if ($zip->open($zip_tmp) === TRUE) {
            // Group files by direct parent folder inside the zip
            $folder_groups = [];
            $valid_exts = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'bmp'];
            
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = $stat['name'];
                
                if (substr($name, -1) === '/' || strpos($name, '__MACOSX') !== false || basename($name)[0] === '.') {
                    continue;
                }
                
                $parts = explode('/', $name);
                if (count($parts) < 2) continue; // Skip root files
                
                $folder_name = $parts[count($parts) - 2];
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                
                if (in_array($ext, $valid_exts)) {
                    if (!isset($folder_groups[$folder_name])) {
                        $folder_groups[$folder_name] = [];
                    }
                    $folder_groups[$folder_name][] = $name;
                }
            }
            
            if (empty($folder_groups)) {
                $error = 'No valid chapter folders containing images were detected in the ZIP.';
            } else {
                $processed_chapters_count = 0;
                
                try {
                    $pdo->beginTransaction();
                    
                    foreach ($folder_groups as $folder_name => $entries) {
                        $ch_num = parse_chapter_number($folder_name);
                        if ($ch_num === null) continue; // Skip folder if no number parsed
                        
                        // Sort entries inside folder naturally
                        usort($entries, 'strnatcasecmp');
                        
                        // Create directory
                        $ch_dir = PAGES_DIR . '/' . $manga['slug'] . '/ch-' . $ch_num;
                        if (!is_dir($ch_dir)) {
                            mkdir($ch_dir, 0755, true);
                        }
                        
                        // Create or Fetch Chapter
                        $chapter = db_fetch(
                            "SELECT id FROM chapters WHERE manga_id = ? AND number = ?", 
                            [$manga['id'], $ch_num]
                        );
                        if ($chapter) {
                            $chapter_id = $chapter['id'];
                            db_query("DELETE FROM pages WHERE chapter_id = ?", [$chapter_id]);
                        } else {
                            $chapter_id = uuid();
                            db_query(
                                "INSERT INTO chapters (id, manga_id, number, title, created_at, updated_at) 
                                 VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)",
                                [$chapter_id, $manga['id'], $ch_num, "Chapter $ch_num"]
                            );
                        }
                        
                        // Process pages
                        foreach ($entries as $idx => $entry) {
                            $page_index = $idx + 1;
                            $webp_name = sprintf('page-%03d.webp', $page_index);
                            $dest_path = $ch_dir . '/' . $webp_name;
                            
                            $img_stream = $zip->getStream($entry);
                            $tmp_img = tempnam(sys_get_temp_dir(), 'manga_bulk_');
                            file_put_contents($tmp_img, $img_stream);
                            fclose($img_stream);
                            
                            optimize_image($tmp_img, $dest_path, 80);
                            unlink($tmp_img);
                            
                            $public_url = '/uploads/pages/' . $manga['slug'] . '/ch-' . $ch_num . '/' . $webp_name;
                            
                            db_query(
                                "INSERT INTO pages (id, chapter_id, image_url, order_index, created_at) 
                                 VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)",
                                [uuid(), $chapter_id, $public_url, $page_index]
                            );
                        }
                        
                        $processed_chapters_count++;
                    }
                    
                    $pdo->commit();
                    $success = "Successfully processed and uploaded $processed_chapters_count chapters from the bulk archive.";
                    try {
                        generate_seo_assets();
                    } catch (Exception $seo_ex) {
                        \MangaNexus\Logging\Logger::error("Failed to automatically regenerate SEO assets on bulk chapter upload: " . $seo_ex->getMessage());
                    }
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Bulk processing error: ' . $e->getMessage();
                }
            }
            $zip->close();
        } else {
            $error = 'Unable to open ZIP archive.';
        }
    }
}

// ── Handle VPS Local Folder Import ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_type']) && $_POST['upload_type'] === 'vps_import') {
    set_time_limit(0); // Ensure no timeout during local copy/optimize loop
    
    $vps_folder_name = isset($_POST['vps_folder']) ? trim($_POST['vps_folder']) : '';
    // Basic sanitization to prevent directory traversal attacks
    $vps_folder_name = basename($vps_folder_name);
    $full_import_path = IMPORT_DIR . '/' . $vps_folder_name;
    
    if (empty($vps_folder_name) || !is_dir($full_import_path)) {
        $error = 'Please select a valid VPS folder to import.';
    } else {
        // Group files by direct parent folder (chapters) inside the import folder
        $valid_exts = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'bmp'];
        
        // Scan the selected folder for subdirectories (representing chapters)
        $chapter_subdirs = array_diff(scandir($full_import_path), ['.', '..']);
        $processed_chapters_count = 0;
        
        try {
            $pdo->beginTransaction();
            
            foreach ($chapter_subdirs as $sub_item) {
                $sub_item_path = $full_import_path . '/' . $sub_item;
                if (!is_dir($sub_item_path)) {
                    continue; // Skip files, we only want folders representing chapters
                }
                
                $ch_num = parse_chapter_number($sub_item);
                if ($ch_num === null) {
                    continue; // Skip folder if no chapter number could be parsed
                }
                
                // Scan the chapter folder for image files
                $files_in_ch = array_diff(scandir($sub_item_path), ['.', '..']);
                $image_files = [];
                foreach ($files_in_ch as $file) {
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    if (in_array($ext, $valid_exts)) {
                        $image_files[] = $file;
                    }
                }
                
                if (empty($image_files)) {
                    continue; // Skip empty chapters
                }
                
                // Sort naturally
                usort($image_files, 'strnatcasecmp');
                
                // Create chapter destination folder
                $ch_dir = PAGES_DIR . '/' . $manga['slug'] . '/ch-' . $ch_num;
                if (!is_dir($ch_dir)) {
                    mkdir($ch_dir, 0755, true);
                }
                
                // Retrieve or insert chapter database record
                $chapter = db_fetch(
                    "SELECT id FROM chapters WHERE manga_id = ? AND number = ?", 
                    [$manga['id'], $ch_num]
                );
                
                if ($chapter) {
                    $chapter_id = $chapter['id'];
                    db_query("DELETE FROM pages WHERE chapter_id = ?", [$chapter_id]);
                } else {
                    $chapter_id = uuid();
                    db_query(
                        "INSERT INTO chapters (id, manga_id, number, title, created_at, updated_at) 
                         VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)",
                        [$chapter_id, $manga['id'], $ch_num, "Chapter $ch_num"]
                    );
                }
                
                // Optimize and copy image files to output folder
                foreach ($image_files as $idx => $img_file) {
                    $page_index = $idx + 1;
                    $webp_name = sprintf('page-%03d.webp', $page_index);
                    $dest_path = $ch_dir . '/' . $webp_name;
                    $src_path = $sub_item_path . '/' . $img_file;
                    
                    optimize_image($src_path, $dest_path, 80);
                    
                    $public_url = '/uploads/pages/' . $manga['slug'] . '/ch-' . $ch_num . '/' . $webp_name;
                    
                    db_query(
                        "INSERT INTO pages (id, chapter_id, image_url, order_index, created_at) 
                         VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)",
                        [uuid(), $chapter_id, $public_url, $page_index]
                    );
                }
                
                $processed_chapters_count++;
            }
            
            $pdo->commit();
            $success = "Successfully imported $processed_chapters_count chapters from VPS folder: " . htmlspecialchars($vps_folder_name);
            try {
                generate_seo_assets();
            } catch (Exception $seo_ex) {
                \MangaNexus\Logging\Logger::error("Failed to automatically regenerate SEO assets on VPS chapter import: " . $seo_ex->getMessage());
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'VPS filesystem import error: ' . $e->getMessage();
        }
    }
}

// ── Handle VPS Absolute Directory Mapping ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_type']) && $_POST['upload_type'] === 'vps_map') {
    set_time_limit(0); // Ensure no timeout during mapping scans
    
    $absolute_path = isset($_POST['vps_absolute_path']) ? trim($_POST['vps_absolute_path']) : '';
    // Normalize path separators based on OS
    $absolute_path = rtrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $absolute_path), DIRECTORY_SEPARATOR);
    
    $real_abs_path = realpath($absolute_path);
    if (empty($absolute_path) || !$real_abs_path || !is_dir($real_abs_path)) {
        $error = 'Please enter a valid absolute VPS directory path.';
    } else {
        // Security check: blacklist sensitive system directories
        $blacklisted_prefixes = [
            '/etc', '/boot', '/dev', '/proc', '/sys', '/root', '/bin', '/sbin', 
            '/lib', '/lib64', '/var/log', '/var/run', '/var/mail', 
            'C:\\Windows', 'C:\\Program Files', 'C:\\Program Files (x86)', 'C:\\System Volume Information'
        ];
        
        $is_safe = true;
        $normalized_real_path = strtolower(str_replace('\\', '/', $real_abs_path));
        
        foreach ($blacklisted_prefixes as $prefix) {
            $normalized_prefix = strtolower(str_replace('\\', '/', $prefix));
            if (str_starts_with($normalized_real_path, $normalized_prefix)) {
                $is_safe = false;
                break;
            }
        }
        
        if (!$is_safe) {
            $error = 'Access to this system directory is restricted for security reasons.';
        } else {
            $absolute_path = $real_abs_path; // Use fully resolved safe path
            $valid_exts = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'bmp'];
            
            // Scan the selected folder for subdirectories (representing chapters)
            $chapter_subdirs = array_diff(scandir($absolute_path), ['.', '..']);
            $processed_chapters_count = 0;
            
            try {
                $pdo->beginTransaction();
                
                foreach ($chapter_subdirs as $sub_item) {
                    $sub_item_path = $absolute_path . DIRECTORY_SEPARATOR . $sub_item;
                    if (!is_dir($sub_item_path)) {
                        continue; // Skip files
                    }
                    
                    $ch_num = parse_chapter_number($sub_item);
                    if ($ch_num === null) {
                        continue; // Skip folder if no chapter number parsed
                    }
                    
                    // Scan chapter folder for images
                    $files_in_ch = array_diff(scandir($sub_item_path), ['.', '..']);
                    $image_files = [];
                    foreach ($files_in_ch as $file) {
                        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                        if (in_array($ext, $valid_exts)) {
                            $image_files[] = $file;
                        }
                    }
                    
                    if (empty($image_files)) {
                        continue; // Skip empty chapters
                    }
                    
                    // Sort naturally
                    usort($image_files, 'strnatcasecmp');
                    
                    // Retrieve or insert chapter database record
                    $chapter = db_fetch(
                        "SELECT id FROM chapters WHERE manga_id = ? AND number = ?", 
                        [$manga['id'], $ch_num]
                    );
                    
                    if ($chapter) {
                        $chapter_id = $chapter['id'];
                        db_query("DELETE FROM pages WHERE chapter_id = ?", [$chapter_id]);
                    } else {
                        $chapter_id = uuid();
                        db_query(
                            "INSERT INTO chapters (id, manga_id, number, title, created_at, updated_at) 
                             VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)",
                            [$chapter_id, $manga['id'], $ch_num, "Chapter $ch_num"]
                        );
                    }
                    
                    // Map the image files without copying
                    foreach ($image_files as $idx => $img_file) {
                        $page_index = $idx + 1;
                        $page_id = uuid();
                        $src_path = $sub_item_path . DIRECTORY_SEPARATOR . $img_file;
                        $public_url = '/external-media/' . $page_id;
                        
                        db_query(
                            "INSERT INTO pages (id, chapter_id, image_url, order_index, external_path, created_at) 
                             VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)",
                            [$page_id, $chapter_id, $public_url, $page_index, $src_path]
                        );
                    }
                    
                    $processed_chapters_count++;
                }
                
                $pdo->commit();
                $success = "Successfully mapped $processed_chapters_count chapters from absolute path: " . htmlspecialchars($absolute_path);
                try {
                    generate_seo_assets();
                } catch (Exception $seo_ex) {
                    \MangaNexus\Logging\Logger::error("Failed to automatically regenerate SEO assets on VPS chapter mapping: " . $seo_ex->getMessage());
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'VPS filesystem mapping error: ' . $e->getMessage();
            }
        }
    }
}

// Fetch all chapters of this manga
$chapters = db_fetch_all("SELECT * FROM chapters WHERE manga_id = ? ORDER BY number DESC", [$manga['id']]);

// Scan the IMPORT_DIR directory for folders that can be imported
$vps_folders = [];
if (is_dir(IMPORT_DIR)) {
    $dirs = array_diff(scandir(IMPORT_DIR), ['.', '..']);
    foreach ($dirs as $dir) {
        if (is_dir(IMPORT_DIR . '/' . $dir)) {
            $vps_folders[] = $dir;
        }
    }
}
natcasesort($vps_folders);

// Fetch pages if a chapter is selected
$pages = [];
$focused_ch = null;
if (!empty($focused_chapter_id)) {
    $focused_ch = db_fetch("SELECT * FROM chapters WHERE id = ?", [$focused_chapter_id]);
    if ($focused_ch) {
        $pages = db_fetch_all("SELECT * FROM pages WHERE chapter_id = ? ORDER BY order_index ASC", [$focused_chapter_id]);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Chapters - <?php echo htmlspecialchars($manga['title']); ?></title>
  <link rel="stylesheet" href="/theme.css">
  <link rel="stylesheet" href="/themes/theme-<?php echo htmlspecialchars($theme); ?>.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
</head>
<body class="theme-<?php echo htmlspecialchars($theme); ?> admin-layout">

  <!-- Sidebar -->
  <aside class="admin-sidebar">
    <div class="sidebar-brand">
      <div class="logo-box-mini">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      </div>
      <span>MangaNexus Panel</span>
    </div>
    <nav class="sidebar-nav">
      <a href="/<?php echo htmlspecialchars($admin_slug); ?>/" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>
        Dashboard
      </a>
      <a href="/<?php echo htmlspecialchars($admin_slug); ?>/manga" class="nav-item active">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
        Manga CRUD
      </a>
      <a href="/<?php echo htmlspecialchars($admin_slug); ?>/ads" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 18H3a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h9M12 6l7-4v20l-7-4M19 12h3M19 8h2M19 16h2"/></svg>
        Ads Manager
      </a>
      <a href="/<?php echo htmlspecialchars($admin_slug); ?>/settings" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
        Site Settings
      </a>
      <a href="/<?php echo htmlspecialchars($admin_slug); ?>/visitors" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        Registered Visitors
      </a>
      <a href="/<?php echo htmlspecialchars($admin_slug); ?>/single-manga" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20M4 19.5A2.5 2.5 0 0 0 6.5 22H20M4 19.5V2.5A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5z"/></svg>
        Micro-Niche Studio
      </a>
      <a href="/<?php echo htmlspecialchars($admin_slug); ?>/pages" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        Pages Manager
      </a>
      <a href="/<?php echo htmlspecialchars($admin_slug); ?>/blog" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><path d="M16 8h2"/><path d="M16 12h2"/><path d="M16 16h2"/><path d="M6 8h6v8H6z"/></svg>
        Blog Posts
      </a>
      <a href="/" target="_blank" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>
        View Website
      </a>
      <a href="/<?php echo htmlspecialchars($admin_slug); ?>/logout" class="nav-item logout-link">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        Logout
      </a>
    </nav>
  </aside>

  <!-- Main content wrapper -->
  <main class="admin-main">
    <header class="admin-topbar">
      <h2>Chapter Management — <?php echo htmlspecialchars($manga['title']); ?></h2>
      <a href="/<?php echo htmlspecialchars($admin_slug); ?>/manga" class="btn btn-secondary btn-sm">Back to Manga List</a>
    </header>

    <div class="admin-content-box">
      <!-- Alerts -->
      <?php if (!empty($error)): ?>
        <div class="error-banner"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>
      <?php if (!empty($success)): ?>
        <div class="success-banner"><?php echo htmlspecialchars($success); ?></div>
      <?php endif; ?>

      <!-- ── SECTION: PAGE SORTER ── -->
      <?php if ($focused_ch): ?>
        <div class="chapter-sorter-card">
          <div class="card-header">
            <h3>Organize Chapter <?php echo $focused_ch['number']; ?> Pages</h3>
            <a href="/<?php echo htmlspecialchars($admin_slug); ?>/chapters/<?php echo $manga['id']; ?>" class="btn btn-secondary btn-sm">Close Sorter</a>
          </div>

          <p class="sorter-info">Drag and drop the cards to re-order the pages. The updates are saved in real-time or via the save button.</p>

          <div class="page-sorter-grid" id="sorter-grid">
            <?php foreach ($pages as $p): ?>
              <div class="sorter-card" draggable="true" data-id="<?php echo $p['id']; ?>">
                <div class="sorter-card-image">
                  <img src="<?php echo htmlspecialchars($p['image_url']); ?>" alt="">
                </div>
                <div class="sorter-card-number"><?php echo $p['order_index']; ?></div>
              </div>
            <?php endforeach; ?>
          </div>

          <form action="" method="POST" id="reorder-form" style="margin-top: 2rem;">
            <?php echo \MangaNexus\Security\Csrf::getField(); ?>
            <input type="hidden" name="action" value="reorder">
            <input type="hidden" name="ordered_ids" id="ordered-ids-input" value="">
            <button type="button" class="btn btn-primary" onclick="submitNewOrder()">Save Page Order</button>
          </form>
        </div>
      <?php endif; ?>

      <!-- ── UPLOADER SECTIONS ── -->
      <div class="uploaders-row">
        <!-- Single Chapter ZIP Upload -->
        <div class="uploader-col">
          <div class="crud-card">
            <div class="card-header">
              <h3>Upload Single Chapter</h3>
            </div>
            <form action="" method="POST" enctype="multipart/form-data">
              <?php echo \MangaNexus\Security\Csrf::getField(); ?>
              <input type="hidden" name="upload_type" value="single">
              
              <div class="form-group">
                <label for="chapter_number" class="form-label">Chapter Number *</label>
                <input type="number" step="any" name="chapter_number" id="chapter_number" class="form-input" placeholder="e.g. 5" required>
              </div>

              <div class="form-group">
                <label for="chapter_title" class="form-label">Chapter Title</label>
                <input type="text" name="chapter_title" id="chapter_title" class="form-input" placeholder="e.g. The Awakening">
              </div>

              <div class="form-group">
                <label for="zip_file" class="form-label">Chapter Page ZIP *</label>
                <input type="file" name="zip_file" id="zip_file" class="form-input" accept=".zip" required>
              </div>

              <button type="submit" class="btn btn-primary">Process ZIP</button>
            </form>
          </div>
        </div>

        <!-- Bulk Multi-Chapter Import -->
        <div class="uploader-col">
          <div class="crud-card">
            <div class="card-header">
              <h3>Bulk Multi-Chapter Import</h3>
            </div>
            <p class="bulk-desc">Upload a ZIP folder containing multiple folders (one folder per chapter, e.g. "Ch 1", "Ch 2"). Images will be extracted and sorted inside each chapter automatically.</p>
            <form action="" method="POST" enctype="multipart/form-data">
              <?php echo \MangaNexus\Security\Csrf::getField(); ?>
              <input type="hidden" name="upload_type" value="bulk">

              <div class="form-group">
                <label for="bulk_zip" class="form-label">Full Manga ZIP Package *</label>
                <input type="file" name="zip_file" id="bulk_zip" class="form-input" accept=".zip" required>
              </div>

              <button type="submit" class="btn btn-primary">Import Full Archive</button>
            </form>
          </div>
        </div>

        <!-- VPS Local Folder Import -->
        <div class="uploader-col">
          <div class="crud-card" style="height: 100%;">
            <div class="card-header">
              <h3>Import from VPS Filesystem</h3>
            </div>
            <p class="bulk-desc">Scan and import chapters from folders manually uploaded to the server's <code>/import/</code> directory (FTP/SFTP/rsync). This prevents 500 errors on massive uploads.</p>
            <form action="" method="POST">
              <?php echo \MangaNexus\Security\Csrf::getField(); ?>
              <input type="hidden" name="upload_type" value="vps_import">

              <div class="form-group">
                <label for="vps_folder" class="form-label">Select VPS Folder *</label>
                <?php if (empty($vps_folders)): ?>
                  <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); padding: 0.75rem 1rem; border-radius: 0.5rem; color: #f87171; font-size: 0.75rem; line-height: 1.4;">
                    No folders found in <code>/import/</code> on the server. Please upload folders first via SFTP/FTP.
                  </div>
                <?php else: ?>
                  <div class="dropdown-wrapper" style="width: 100%;">
                    <select name="vps_folder" id="vps_folder" class="form-input dropdown-select" style="width: 100%; height: 2.75rem; text-align: left; padding: 0 1rem; background-color: var(--theme-input-bg); border: 1px solid var(--theme-border); border-radius: 0.75rem; color: var(--theme-text); font-weight: 700; outline: none; cursor: pointer;" required>
                      <option value="" disabled selected>-- Select VPS Folder --</option>
                      <?php foreach ($vps_folders as $folder): ?>
                        <option value="<?php echo htmlspecialchars($folder); ?>"><?php echo htmlspecialchars($folder); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                <?php endif; ?>
              </div>

              <button type="submit" class="btn btn-primary" <?php echo empty($vps_folders) ? 'disabled' : ''; ?>>Start VPS Sync</button>
            </form>
          </div>
        </div>

        <!-- VPS Absolute Mapped Directory -->
        <div class="uploader-col">
          <div class="crud-card" style="height: 100%;">
            <div class="card-header">
              <h3>Map External VPS Path</h3>
            </div>
            <p class="bulk-desc">Zero-Duplication Engine. Provide any absolute directory on the VPS (e.g. <code>/var/manga_storage/my-manga</code>) to register all chapters directly without copying files.</p>
            <form action="" method="POST">
              <?php echo \MangaNexus\Security\Csrf::getField(); ?>
              <input type="hidden" name="upload_type" value="vps_map">

              <div class="form-group">
                <label for="vps_absolute_path" class="form-label">Absolute VPS Folder Path *</label>
                <input type="text" name="vps_absolute_path" id="vps_absolute_path" class="form-input" placeholder="e.g. /var/manga_storage/one-piece" required>
              </div>

              <button type="submit" class="btn btn-primary">Map VPS Path</button>
            </form>
          </div>
        </div>
      </div>


      <!-- ── CHAPTER LISTING ── -->
      <div class="chapters-list-card crud-card" style="margin-top: 2rem;">
        <div class="card-header">
          <h3>Published Chapters List</h3>
        </div>

        <?php if (empty($chapters)): ?>
          <div class="empty-state">
            <p>No chapters have been uploaded for this manga yet.</p>
          </div>
        <?php else: ?>
          <div class="table-container">
            <table class="manga-table">
              <thead>
                <tr>
                  <th>Chapter Number</th>
                  <th>Chapter Title</th>
                  <th>Published Date</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($chapters as $ch): ?>
                  <tr>
                    <td style="font-weight:700; color:var(--theme-text);">Chapter <?php echo $ch['number']; ?></td>
                    <td><?php echo htmlspecialchars($ch['title'] ?: '—'); ?></td>
                    <td style="font-size:0.75rem; color:var(--theme-text-muted);"><?php echo format_manga_date($ch['created_at']); ?></td>
                    <td class="manga-td-actions">
                      <a href="/<?php echo htmlspecialchars($admin_slug); ?>/chapters/<?php echo $manga['id']; ?>?ch_id=<?php echo $ch['id']; ?>" class="action-btn edit-btn">Sort Pages</a>
                      <form action="/<?php echo htmlspecialchars($admin_slug); ?>/chapters/<?php echo $manga['id']; ?>?ch_id=<?php echo $ch['id']; ?>&sub_action=delete" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this chapter and all its pages?');">
                        <?php echo \MangaNexus\Security\Csrf::getField(); ?>
                        <button type="submit" class="action-btn delete-btn" style="background:var(--theme-card); border:1px solid rgba(239, 68, 68, 0.2); cursor:pointer; font:inherit; color:#f87171;">Delete</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

    </div>

    <!-- Non-Removable Credits Footer -->
    <?php require_once BASE_PATH . '/templates/footer.php'; ?>
  </main>

  <!-- Drag and drop sorter script -->
  <script>
    const grid = document.getElementById('sorter-grid');
    if (grid) {
      let dragSrcEl = null;

      function handleDragStart(e) {
        this.style.opacity = '0.4';
        dragSrcEl = this;
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/html', this.innerHTML);
        e.dataTransfer.setData('text/plain', this.dataset.id);
      }

      function handleDragOver(e) {
        if (e.preventDefault) {
          e.preventDefault();
        }
        e.dataTransfer.dropEffect = 'move';
        return false;
      }

      function handleDragEnter(e) {
        this.classList.add('over');
      }

      function handleDragLeave(e) {
        this.classList.remove('over');
      }

      function handleDrop(e) {
        if (e.stopPropagation) {
          e.stopPropagation();
        }

        if (dragSrcEl !== this) {
          // Swap HTML and data ids
          const tempHTML = this.innerHTML;
          const tempId = this.dataset.id;

          this.innerHTML = dragSrcEl.innerHTML;
          this.dataset.id = dragSrcEl.dataset.id;

          dragSrcEl.innerHTML = tempHTML;
          dragSrcEl.dataset.id = tempId;

          // Recalculate page index label visually
          reindexLabels();
        }
        return false;
      }

      function handleDragEnd(e) {
        this.style.opacity = '1';
        const cards = grid.querySelectorAll('.sorter-card');
        cards.forEach(card => card.classList.remove('over'));
      }

      function reindexLabels() {
        const cards = grid.querySelectorAll('.sorter-card');
        cards.forEach((card, index) => {
          card.querySelector('.sorter-card-number').innerText = index + 1;
        });
      }

      const cards = grid.querySelectorAll('.sorter-card');
      cards.forEach(card => {
        card.addEventListener('dragstart', handleDragStart, false);
        card.addEventListener('dragenter', handleDragEnter, false);
        card.addEventListener('dragover', handleDragOver, false);
        card.addEventListener('dragleave', handleDragLeave, false);
        card.addEventListener('drop', handleDrop, false);
        card.addEventListener('dragend', handleDragEnd, false);
      });
    }

    function submitNewOrder() {
      const grid = document.getElementById('sorter-grid');
      if (!grid) return;
      
      const cards = grid.querySelectorAll('.sorter-card');
      const ids = [];
      cards.forEach(c => ids.push(c.dataset.id));
      
      document.getElementById('ordered-ids-input').value = ids.join(',');
      document.getElementById('reorder-form').submit();
    }
  </script>
</body>
</html>

<!-- CSS for chapter styles -->
<style>
.uploaders-row {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1.5rem;
}

@media(min-width: 768px) {
  .uploaders-row {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media(min-width: 1200px) {
  .uploaders-row {
    grid-template-columns: repeat(4, 1fr);
  }
}

.uploader-col {
  display: flex;
  flex-direction: column;
}

.bulk-desc {
  font-size: 0.75rem;
  color: var(--theme-text-muted);
  line-height: 1.5;
  margin-bottom: 1.5rem;
}

/* Page Sorter style */
.chapter-sorter-card {
  background-color: #090b11;
  border: 1px solid var(--theme-border);
  border-radius: 1.5rem;
  padding: 2rem;
  margin-bottom: 2rem;
}

.sorter-info {
  font-size: 0.8125rem;
  color: var(--theme-text-muted);
  margin-bottom: 1.5rem;
}

.page-sorter-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
}

@media(min-width: 576px) {
  .page-sorter-grid {
    grid-template-columns: repeat(4, 1fr);
  }
}

@media(min-width: 768px) {
  .page-sorter-grid {
    grid-template-columns: repeat(6, 1fr);
  }
}

@media(min-width: 1200px) {
  .page-sorter-grid {
    grid-template-columns: repeat(8, 1fr);
  }
}

.sorter-card {
  aspect-ratio: 3/4.5;
  background-color: #121214;
  border: 1px solid var(--theme-border);
  border-radius: 0.75rem;
  overflow: hidden;
  position: relative;
  cursor: grab;
  user-select: none;
  transition: border-color 0.2s ease;
}

.sorter-card.over {
  border-color: var(--theme-primary);
  border-width: 2px;
}

.sorter-card-image {
  width: 100%;
  height: 100%;
}

.sorter-card-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  pointer-events: none;
}

.sorter-card-number {
  position: absolute;
  bottom: 0.35rem;
  right: 0.35rem;
  background-color: rgba(0,0,0,0.7);
  padding: 0.15rem 0.4rem;
  border-radius: 0.25rem;
  font-size: 0.625rem;
  font-weight: 700;
  color: #fff;
  pointer-events: none;
}
</style>
