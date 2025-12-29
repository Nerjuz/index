<?php
/**
 * All-in-one PHP File Explorer & Previewer
 */

// Enable GZIP compression
if (extension_loaded('zlib') && !ini_get('zlib.output_compression')) {
    ob_start('ob_gzhandler');
}

// --- CONFIGURATION START ---
$config = [
    'title' => 'Files Explorer',
    'password' => '', // Leave empty to disable password protection
    'allow_upload' => true,
    'allow_create' => true,
    'allow_rename' => true,
    'allow_delete' => true,
    'exclude_files' => ['index.php', 'Dockerfile', 'docker-compose.yml', 'Makefile', '.git', '.DS_Store'],
    'exclude_extensions' => ['tmp', 'bak', 'log'],
    'base_root' => __DIR__,
    'extensions' => [
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'],
        'video' => ['mp4', 'webm', 'ogg', 'mov'],
        'audio' => ['mp3', 'wav', 'ogg', 'm4a', 'flac'],
        'font' => ['ttf', 'otf', 'woff', 'woff2'],
        'text' => ['txt', 'md', 'php', 'js', 'css', 'json', 'html', 'sql', 'log'],
        'office' => ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'],
        'archive' => ['zip', 'rar', 'tar', 'gz', '7z']
    ]
];
// --- CONFIGURATION END ---

session_start();

// Logout handler
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// Login handler
$authError = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === $config['password']) {
        $_SESSION['authenticated'] = true;
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    } else {
        $authError = "Invalid password. Access denied.";
    }
}

// Authentication check
if (!empty($config['password']) && !isset($_SESSION['authenticated'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login - <?php echo $config['title']; ?></title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            body { background: radial-gradient(circle at top right, #1e293b, #0f172a); color: #f8fafc; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Inter', sans-serif; }
            .glass { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.1); }
        </style>
    </head>
    <body class="p-6">
        <div class="glass max-w-md w-full rounded-3xl p-8 shadow-2xl text-center">
            <div class="w-20 h-20 bg-blue-600 rounded-2xl flex items-center justify-center mx-auto mb-6 shadow-lg shadow-blue-900/40">
                <i class="fa-solid fa-lock text-3xl text-white"></i>
            </div>
            <h1 class="text-3xl font-black mb-2"><?php echo $config['title']; ?></h1>
            <p class="text-slate-400 mb-8 text-sm">Please enter the password to access your files.</p>
            
            <form method="POST" class="space-y-4">
                <div class="relative">
                    <input type="password" name="password" placeholder="Password" required autofocus
                           class="w-full bg-white/5 border border-white/10 rounded-xl px-5 py-3 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 transition-all">
                </div>
                <?php if ($authError): ?>
                    <p class="text-red-400 text-xs font-medium"><?php echo $authError; ?></p>
                <?php endif; ?>
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-3 rounded-xl shadow-lg shadow-blue-900/40 transition-all active:scale-95">
                    Unlock
                </button>
            </form>
            <p class="mt-8 text-[10px] text-slate-600 uppercase tracking-widest font-bold">Secure Access</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Application Logic
$baseRoot = $config['base_root'];
$excludeFiles = $config['exclude_files'];
$excludeExtensions = $config['exclude_extensions'] ?? [];

$requestedPath = $_GET['path'] ?? '';
$requestedPath = str_replace('..', '', $requestedPath);
$requestedPath = trim($requestedPath, '/');
$currentDir = $requestedPath ? $baseRoot . '/' . $requestedPath : $baseRoot;

if (!is_dir($currentDir) || strpos(realpath($currentDir), realpath($baseRoot)) !== 0) {
    $currentDir = $baseRoot;
    $requestedPath = '';
}

// Handler: File Upload
if ($config['allow_upload'] && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    if (!empty($config['password']) && !isset($_SESSION['authenticated'])) exit('Unauthorized');
    $file = $_FILES['file'];
    $targetPath = $currentDir . '/' . basename($file['name']);
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } else {
        header('Content-Type: application/json', true, 500);
        echo json_encode(['error' => 'Upload failed']);
    }
    exit;
}

// Handler: File Operations (Save/Create)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!empty($config['password']) && !isset($_SESSION['authenticated'])) exit('Unauthorized');
    header('Content-Type: application/json');
    $action = $_POST['action'];
    $fileName = str_replace(['/', '\\', '..'], '', $_POST['filename'] ?? '');

    if ($action === 'read_file' && $fileName) {
        $filePath = $currentDir . '/' . $fileName;
        if (file_exists($filePath)) {
            readfile($filePath);
        } else {
            http_response_code(404);
            echo "File not found";
        }
        exit;
    }

    if ($action === 'save_file' && $fileName) {
        $content = $_POST['content'] ?? '';
        $filePath = $currentDir . '/' . $fileName;
        if (file_put_contents($filePath, $content) !== false) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save file']);
        }
        exit;
    }

    if ($action === 'create_file' && $config['allow_create'] && $fileName) {
        $filePath = $currentDir . '/' . $fileName;
        if (!file_exists($filePath)) {
            if (file_put_contents($filePath, '') !== false) {
                echo json_encode(['success' => true]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to create file']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'File already exists']);
        }
        exit;
    }

    if ($action === 'rename_file' && $config['allow_rename'] && $fileName) {
        $newName = str_replace(['/', '\\', '..'], '', $_POST['new_name'] ?? '');
        if ($newName && $newName !== $fileName) {
            $oldPath = $currentDir . '/' . $fileName;
            $newPath = $currentDir . '/' . $newName;
            if (file_exists($oldPath) && !file_exists($newPath)) {
                if (rename($oldPath, $newPath)) {
                    echo json_encode(['success' => true]);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to rename file']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid rename request or file already exists']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid new name']);
        }
        exit;
    }

    if ($action === 'delete_file' && $config['allow_delete'] && $fileName) {
        $filePath = $currentDir . '/' . $fileName;
        if (file_exists($filePath)) {
            if (is_dir($filePath)) {
                if (deleteRecursive($filePath)) {
                    echo json_encode(['success' => true]);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to delete directory']);
                }
            } else {
                if (unlink($filePath)) {
                    echo json_encode(['success' => true]);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to delete file']);
                }
            }
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'File not found']);
        }
        exit;
    }
}

function deleteRecursive($path) {
    if (is_dir($path)) {
        $files = array_diff(scandir($path), ['.', '..']);
        foreach ($files as $file) {
            deleteRecursive($path . '/' . $file);
        }
        return rmdir($path);
    }
    return unlink($path);
}


// Handler: Download File (GET)
if (isset($_GET['action']) && $_GET['action'] === 'download' && isset($_GET['filename'])) {
    if (!empty($config['password']) && !isset($_SESSION['authenticated'])) exit('Unauthorized');
    
    $fileName = str_replace(['/', '\\', '..'], '', $_GET['filename']);
    $filePath = $currentDir . '/' . $fileName;
    
    if (file_exists($filePath) && is_file($filePath)) {
        // Clear headers and output buffers to disable GZIP/output compression for downloads
        while (ob_get_level()) ob_end_clean();
        
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        $baseName = basename($filePath);
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $baseName) . '"; filename*=UTF-8\'\'' . rawurlencode($baseName));
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    } else {
        http_response_code(404);
        die("File not found");
    }
}

// Helper to get file icon and type
function getFileDetails($filePath, $isDir = null) {
    global $config;
    
    if ($isDir === null) {
        $isDir = is_dir($filePath);
    }

    if ($isDir) {
        return ['icon' => 'fa-folder text-yellow-500', 'type' => 'directory'];
    }

    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $exts = $config['extensions'];

    if (in_array($ext, $exts['image'])) {
        return ['icon' => 'fa-file-image text-purple-500', 'type' => 'image'];
    }
    if (in_array($ext, $exts['video'])) {
        return ['icon' => 'fa-file-video text-red-500', 'type' => 'video'];
    }
    if (in_array($ext, $exts['audio'])) {
        return ['icon' => 'fa-file-audio text-yellow-500', 'type' => 'audio'];
    }
    if (in_array($ext, $exts['font'])) {
        return ['icon' => 'fa-font text-indigo-500', 'type' => 'font'];
    }
    if (in_array($ext, $exts['text'])) {
        return ['icon' => 'fa-file-code text-blue-500', 'type' => 'text'];
    }
    if (in_array($ext, $exts['office'])) {
        return ['icon' => 'fa-file-word text-blue-600', 'type' => 'office'];
    }
    if (in_array($ext, $exts['archive'])) {
        return ['icon' => 'fa-file-archive text-green-500', 'type' => 'archive'];
    }
    if ($ext === 'pdf') {
        return ['icon' => 'fa-file-pdf text-red-600', 'type' => 'pdf'];
    }

    return ['icon' => 'fa-file text-gray-400', 'type' => 'other'];
}

// Scan directory
$files = scandir($currentDir, SCANDIR_SORT_NONE);
$fileList = [];
foreach ($files as $file) {
    if ($file === '.' || $file === '..') continue;
    
    $fullPath = $currentDir . '/' . $file;
    $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

    if ($requestedPath === '' && in_array($file, $excludeFiles)) continue;
    
    $isDir = is_dir($fullPath);
    if (!$isDir && in_array($ext, $excludeExtensions)) continue;
    
    $details = getFileDetails($fullPath, $isDir);
    $relPath = $requestedPath ? $requestedPath . '/' . $file : $file;

    $fileList[] = [
        'name' => $file,
        'relPath' => $relPath,
        'icon' => $details['icon'],
        'type' => $details['type'],
        'size' => $isDir ? '-' : round(filesize($fullPath) / 1024, 2) . ' KB',
        'mtime' => date("Y-m-d H:i:s", filemtime($fullPath)),
        'isPreviewable' => in_array($details['type'], ['image', 'video', 'audio', 'font', 'text', 'pdf', 'office'])
    ];
}

// Sort: Folders first, then alphabetically
usort($fileList, function($a, $b) {
    if ($a['type'] === 'directory' && $b['type'] !== 'directory') return -1;
    if ($a['type'] !== 'directory' && $b['type'] === 'directory') return 1;
    return strcasecmp($a['name'], $b['name']);
});

$parentPath = '';
if ($requestedPath) {
    $parts = explode('/', $requestedPath);
    array_pop($parts);
    $parentPath = implode('/', $parts);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $config['title']; ?></title>
    <!-- Tailwind CSS for layout -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- CodeMirror for syntax highlighting -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/theme/monokai.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/xml/xml.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/javascript/javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/css/css.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/htmlmixed/htmlmixed.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/clike/clike.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/php/php.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/sql/sql.min.js"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: radial-gradient(circle at top right, #1e293b, #0f172a);
            color: #f8fafc;
            min-height: 100vh;
        }
        .glass {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .file-card:hover {
            transform: translateY(-2px);
            background: rgba(255, 255, 255, 0.05);
        }
        .modal {
            transition: opacity 0.3s ease;
        }
        .modal-content {
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        .CodeMirror {
            height: 100%;
            font-family: 'Fira Code', monospace;
            font-size: 14px;
            background: transparent !important;
        }
        .CodeMirror-gutters {
            background: rgba(30, 41, 59, 0.5) !important;
            border-right: 1px solid rgba(255, 255, 255, 0.1) !important;
        }
    </style>
</head>
<body class="p-4 md:p-8">

    <div class="max-w-6xl mx-auto">
        <header class="mb-12">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-4xl font-black tracking-tight text-white mb-2"><?php echo $config['title']; ?></h1>
                    <nav class="flex items-center space-x-2 text-sm text-slate-400">
                        <a href="?path=" class="hover:text-blue-400 transition-colors">Root</a>
                        <?php 
                        if ($requestedPath) {
                            $pathParts = explode('/', $requestedPath);
                            $runningPath = '';
                            foreach ($pathParts as $part) {
                                $runningPath .= ($runningPath ? '/' : '') . $part;
                                echo '<span class="text-slate-600">/</span>';
                                echo '<a href="?path='.urlencode($runningPath).'" class="hover:text-blue-400 transition-colors">'.htmlspecialchars($part).'</a>';
                            }
                        }
                        ?>
                        <span class="ml-4 px-2 py-0.5 rounded-full bg-white/5 text-xs">
                            <?php echo count($fileList); ?> items
                        </span>
                    </nav>
                </div>
                <?php if (isset($_SESSION['authenticated']) || empty($config['password'])): ?>
                <div class="flex flex-col md:flex-row items-center gap-4">
                    <!-- Search Input -->
                    <div class="relative w-full md:w-64 group">
                        <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                        <input type="text" id="searchInput" placeholder="Filter by name..." 
                               oninput="filterFiles(this.value)"
                               class="w-full glass bg-white/5 border border-white/10 rounded-xl pl-10 pr-10 py-2 text-base md:text-xs text-white placeholder-slate-500 focus:outline-none focus:border-blue-500/50 transition-all">
                        <button id="clearSearchBtn" onclick="clearSearch()" class="hidden absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-white transition-colors p-1">
                            <i class="fa-solid fa-xmark text-xs"></i>
                        </button>
                    </div>

                    <div class="flex items-center space-x-3">
                        <?php if ($config['allow_create']): ?>
                        <button onclick="promptNewFile()" class="glass px-4 md:px-5 py-2.5 rounded-xl text-xs font-bold uppercase tracking-widest text-slate-400 hover:text-white hover:bg-white/10 transition-all flex items-center whitespace-nowrap" title="New File">
                            <i class="fa-solid fa-file-circle-plus md:mr-2 text-blue-400"></i> 
                            <span class="hidden md:inline">New File</span>
                        </button>
                        <?php endif; ?>
                        
                        <?php if ($config['allow_upload']): ?>
                        <button onclick="document.getElementById('fileInput').click()" class="glass px-4 md:px-5 py-2.5 rounded-xl text-xs font-bold uppercase tracking-widest text-slate-400 hover:text-white hover:bg-white/10 transition-all flex items-center whitespace-nowrap" title="Upload">
                            <i class="fa-solid fa-cloud-arrow-up md:mr-2 text-green-400"></i> 
                            <span class="hidden md:inline">Upload</span>
                        </button>
                        <input type="file" id="fileInput" class="hidden" multiple onchange="handleFileSelect(event)">
                        <?php endif; ?>

                        <?php if (!empty($config['password'])): ?>
                        <a href="?logout=1" class="glass px-4 md:px-6 py-2.5 rounded-xl text-xs font-bold uppercase tracking-widest text-slate-400 hover:text-white hover:bg-red-500/20 hover:border-red-500/50 transition-all flex items-center whitespace-nowrap" title="Logout">
                            <i class="fa-solid fa-right-from-bracket md:mr-2"></i> 
                            <span class="hidden md:inline">Logout</span>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </header>

        <!-- Drop Zone Overlay -->
        <div id="dropZone" class="fixed inset-0 z-[100] bg-blue-600/20 backdrop-blur-sm border-4 border-dashed border-blue-500 rounded-3xl m-4 pointer-events-none opacity-0 transition-opacity flex items-center justify-center">
            <div class="text-center p-12 bg-slate-900/80 rounded-3xl shadow-2xl">
                <i class="fa-solid fa-cloud-arrow-up text-7xl text-blue-400 mb-6 animate-bounce"></i>
                <h2 class="text-3xl font-black text-white mb-2">Drop files to upload</h2>
                <p class="text-slate-400">Release to start uploading to the current folder</p>
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-6">
            <?php if ($requestedPath !== ''): ?>
            <a href="?path=<?php echo urlencode($parentPath); ?>" 
               class="glass group relative aspect-square rounded-3xl flex flex-col items-center justify-center transition-all duration-300 hover:scale-105 active:scale-95 border-dashed border-2 border-white/10 hover:border-blue-500/50">
                <i class="fa-solid fa-arrow-up text-3xl text-slate-500 group-hover:text-blue-400 transition-colors mb-2"></i>
                <span class="text-xs font-semibold text-slate-400 group-hover:text-blue-400">Go Back</span>
            </a>
            <?php endif; ?>

            <?php foreach ($fileList as $file): ?>
                <?php if ($file['type'] === 'directory'): ?>
                    <a href="?path=<?php echo urlencode($file['relPath']); ?>" 
                       data-name="<?php echo htmlspecialchars(strtolower($file['name'])); ?>"
                       class="file-item glass group relative aspect-square rounded-3xl flex flex-col items-center justify-center transition-all duration-300 hover:scale-105 active:scale-95 hover:bg-white/5 border border-white/10 hover:border-yellow-500/50">
                        <i class="fa-solid <?php echo $file['icon']; ?> text-5xl mb-4 group-hover:scale-110 transition-transform"></i>
                        <span class="px-4 text-sm font-medium text-slate-200 truncate w-full text-center">
                            <?php echo htmlspecialchars($file['name']); ?>
                        </span>
                        <div class="absolute bottom-3 right-3 opacity-0 group-hover:opacity-100 transition-opacity">
                            <i class="fa-solid fa-chevron-right text-xs text-yellow-500"></i>
                        </div>
                    </a>
                <?php else: ?>
                    <div onclick="<?php echo $file['isPreviewable'] ? "openPreview('".addslashes($file['relPath'])."', '".$file['type']."')" : "downloadFile('".addslashes($file['relPath'])."')"; ?>"
                         data-name="<?php echo htmlspecialchars(strtolower($file['name'])); ?>"
                         class="file-item glass group relative aspect-square rounded-3xl overflow-hidden transition-all duration-300 cursor-pointer hover:scale-105 hover:shadow-2xl hover:shadow-blue-500/20 active:scale-95 border border-white/10 hover:border-blue-500/50">
                        
                        <!-- Thumbnail/Icon Area -->
                        <div class="absolute inset-0 flex items-center justify-center bg-slate-900/50">
                            <?php if ($file['type'] === 'image'): ?>
                                <img src="<?php echo htmlspecialchars($file['relPath']); ?>" 
                                     class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110" 
                                     alt="<?php echo htmlspecialchars($file['name']); ?>">
                                <div class="absolute inset-0 bg-black/20 group-hover:bg-transparent transition-colors"></div>
                            <?php elseif ($file['type'] === 'video'): ?>
                                <i class="fa-solid <?php echo $file['icon']; ?> text-5xl text-red-500/80 group-hover:scale-110 transition-transform"></i>
                                <div class="absolute top-2 right-2 bg-black/60 rounded-lg px-2 py-1 text-[10px] font-bold text-white flex items-center">
                                    <i class="fa-solid fa-play mr-1"></i> VIDEO
                                </div>
                            <?php else: ?>
                                <i class="fa-solid <?php echo $file['icon']; ?> text-5xl opacity-80 group-hover:scale-110 transition-transform"></i>
                            <?php endif; ?>
                        </div>

                        <!-- Info Overlay -->
                        <div class="absolute inset-x-0 bottom-0 p-4 bg-gradient-to-t from-black/90 via-black/40 to-transparent translate-y-2 group-hover:translate-y-0 transition-transform duration-300">
                            <p class="text-xs font-semibold text-white truncate mb-1">
                                <?php echo htmlspecialchars($file['name']); ?>
                            </p>
                            <div class="flex justify-between items-center opacity-0 group-hover:opacity-100 transition-opacity duration-300 delay-100">
                                <span class="text-[10px] text-slate-400"><?php echo $file['size']; ?></span>
                                <span class="text-[10px] <?php echo $file['isPreviewable'] ? 'text-blue-400' : 'text-green-400'; ?> font-bold uppercase tracking-wider">
                                    <?php echo $file['isPreviewable'] ? 'Preview' : 'Download'; ?>
                                </span>
                            </div>
                        </div>

                        <!-- Special Type Badge (PDF/Office) -->
                        <?php if ($file['type'] === 'pdf' || $file['type'] === 'office'): ?>
                            <div class="absolute top-2 left-2 bg-black/60 rounded-lg px-2 py-1 text-[10px] font-bold text-white uppercase">
                                <?php echo $file['type']; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>

            <?php if (empty($fileList)): ?>
                <div class="col-span-full py-24 text-center">
                    <div class="mb-4 text-slate-700">
                        <i class="fa-solid fa-folder-open text-8xl"></i>
                    </div>
                    <p class="text-slate-500 text-lg font-medium italic">This gallery is empty</p>
                </div>
            <?php endif; ?>

            <!-- No Results Found (Search) -->
            <div id="noResults" class="hidden col-span-full py-24 text-center">
                <div class="mb-4 text-slate-700">
                    <i class="fa-solid fa-magnifying-glass text-8xl"></i>
                </div>
                <p class="text-slate-500 text-lg font-medium italic">No matches found for your search</p>
            </div>
        </div>
    </div>

    <!-- Modal Backdrop -->
    <div id="modalBackdrop" class="modal fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 hidden opacity-0 pointer-events-none">
        <!-- Navigation Arrows -->
        <button onclick="prevPreview()" class="fixed left-4 md:left-8 top-1/2 -translate-y-1/2 z-[60] text-white/50 hover:text-white text-4xl p-4 transition-all hover:scale-110 active:scale-95">
            <i class="fa-solid fa-chevron-left"></i>
        </button>
        <button onclick="nextPreview()" class="fixed right-4 md:right-8 top-1/2 -translate-y-1/2 z-[60] text-white/50 hover:text-white text-4xl p-4 transition-all hover:scale-110 active:scale-95">
            <i class="fa-solid fa-chevron-right"></i>
        </button>

        <div class="modal-content relative max-w-5xl w-full glass rounded-3xl overflow-hidden animate-in fade-in zoom-in duration-300">
            <div class="p-6 border-b border-white/10 flex justify-between items-center bg-slate-800/50">
                <div class="flex flex-col min-w-0 flex-1">
                    <h3 id="modalTitle" class="text-xl font-semibold text-white truncate">Preview</h3>
                    <p id="modalSubtitle" class="text-[10px] text-slate-500 uppercase tracking-widest font-bold truncate"></p>
                </div>
                <div class="flex items-center space-x-3 flex-shrink-0 ml-4">
                    <button id="editBtn" onclick="toggleEdit()" class="hidden glass px-3 md:px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-widest text-slate-400 hover:text-white hover:bg-white/10 transition-all flex items-center" title="Edit">
                        <i class="fa-solid fa-pen-to-square md:mr-2"></i> <span class="hidden md:inline">Edit</span>
                    </button>
                    <button id="renameBtn" onclick="promptRename()" class="hidden glass px-3 md:px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-widest text-slate-400 hover:text-white hover:bg-white/10 transition-all flex items-center" title="Rename">
                        <i class="fa-solid fa-i-cursor md:mr-2"></i> <span class="hidden md:inline">Rename</span>
                    </button>
                    <button id="deleteBtn" onclick="confirmDelete()" class="hidden glass px-3 md:px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-widest text-slate-400 hover:bg-red-500/20 hover:text-red-400 transition-all flex items-center" title="Delete">
                        <i class="fa-solid fa-trash-can md:mr-2"></i> <span class="hidden md:inline">Delete</span>
                    </button>
                    <button id="cancelBtn" onclick="cancelEdit()" class="hidden glass px-3 md:px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-widest text-slate-400 hover:bg-red-500/20 hover:text-red-400 transition-all flex items-center" title="Cancel">
                        <i class="fa-solid fa-xmark md:mr-2"></i> <span class="hidden md:inline">Cancel</span>
                    </button>
                    <button id="saveBtn" onclick="saveFile()" class="hidden bg-blue-600 hover:bg-blue-500 text-white px-4 md:px-5 py-2 rounded-xl text-xs font-bold uppercase tracking-widest transition-all active:scale-95 flex items-center shadow-lg shadow-blue-900/40" title="Save">
                        <i class="fa-solid fa-floppy-disk md:mr-2"></i> <span class="hidden md:inline">Save</span>
                    </button>
                    <a id="downloadBtn" href="#" download class="text-slate-400 hover:text-blue-400 text-xl p-2 transition-colors" title="Download File">
                        <i class="fa-solid fa-download"></i>
                    </a>
                    <button onclick="closePreview()" class="text-slate-400 hover:text-white text-2xl p-2 transition-colors">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>
            <div id="modalBody" class="p-0 bg-slate-900 flex items-center justify-center transition-all" style="min-height: 480px; max-height: 80vh;">
                <!-- Content injected here -->
            </div>
        </div>
    </div>

    <!-- Custom Dialog Modal -->
    <div id="customDialog" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 hidden opacity-0 pointer-events-none transition-opacity duration-300">
        <div class="modal-content glass max-w-sm w-full rounded-3xl overflow-hidden shadow-2xl scale-95 transition-transform duration-300">
            <div class="p-8 text-center">
                <div id="dialogIcon" class="w-16 h-16 bg-blue-600/20 rounded-2xl flex items-center justify-center mx-auto mb-6">
                    <i class="fa-solid fa-circle-question text-2xl text-blue-400"></i>
                </div>
                <h3 id="dialogTitle" class="text-xl font-black text-white mb-2">Confirm Action</h3>
                <p id="dialogMessage" class="text-slate-400 text-sm mb-6">Are you sure you want to proceed?</p>
                
                <div id="dialogInputContainer" class="hidden mb-6">
                    <input type="text" id="dialogInput" 
                           class="w-full bg-white/5 border border-white/10 rounded-xl px-5 py-3 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 transition-all text-center font-medium">
                </div>

                <div class="flex space-x-3">
                    <button id="dialogCancelBtn" class="flex-1 glass py-3 rounded-xl text-xs font-bold uppercase tracking-widest text-slate-400 hover:text-white transition-all flex items-center justify-center">
                        <i class="fa-solid fa-xmark md:mr-2"></i> <span class="hidden md:inline">Cancel</span>
                    </button>
                    <button id="dialogConfirmBtn" class="flex-1 bg-blue-600 hover:bg-blue-500 text-white font-bold py-3 rounded-xl shadow-lg shadow-blue-900/40 transition-all active:scale-95 flex items-center justify-center">
                        <i class="fa-solid fa-check md:mr-2"></i> <span class="hidden md:inline">Confirm</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const backdrop = document.getElementById('modalBackdrop');
        const modalBody = document.getElementById('modalBody');
        const modalTitle = document.getElementById('modalTitle');
        const modalSubtitle = document.getElementById('modalSubtitle');
        const downloadBtn = document.getElementById('downloadBtn');
        const editBtn = document.getElementById('editBtn');
        const renameBtn = document.getElementById('renameBtn');
        const deleteBtn = document.getElementById('deleteBtn');
        const cancelBtn = document.getElementById('cancelBtn');
        const saveBtn = document.getElementById('saveBtn');
        const dropZone = document.getElementById('dropZone');
        
        // Custom Dialog Elements
        const customDialog = document.getElementById('customDialog');
        const dialogTitle = document.getElementById('dialogTitle');
        const dialogMessage = document.getElementById('dialogMessage');
        const dialogInputContainer = document.getElementById('dialogInputContainer');
        const dialogInput = document.getElementById('dialogInput');
        const dialogCancelBtn = document.getElementById('dialogCancelBtn');
        const dialogConfirmBtn = document.getElementById('dialogConfirmBtn');

        const config = <?php echo json_encode($config); ?>;
        const previewableFiles = <?php 
            echo json_encode(array_values(array_filter($fileList, function($f) { return $f['isPreviewable']; }))); 
        ?>;
        
        async function showDialog(title, message, options = {}) {
            const { type = 'confirm', defaultValue = '', placeholder = '' } = options;
            
            return new Promise((resolve) => {
                dialogTitle.textContent = title;
                dialogMessage.textContent = message;
                
                if (type === 'prompt') {
                    dialogInputContainer.classList.remove('hidden');
                    dialogInput.value = defaultValue;
                    dialogInput.placeholder = placeholder;
                    setTimeout(() => dialogInput.focus(), 100);
                } else {
                    dialogInputContainer.classList.add('hidden');
                }
                
                if (type === 'alert') {
                    dialogCancelBtn.classList.add('hidden');
                } else {
                    dialogCancelBtn.classList.remove('hidden');
                }

                customDialog.classList.remove('hidden');
                setTimeout(() => {
                    customDialog.classList.replace('opacity-0', 'opacity-100');
                    customDialog.classList.replace('pointer-events-none', 'pointer-events-auto');
                    customDialog.querySelector('.modal-content').classList.replace('scale-95', 'scale-100');
                }, 10);

                const cleanup = (value) => {
                    customDialog.classList.replace('opacity-100', 'opacity-0');
                    customDialog.classList.replace('pointer-events-auto', 'pointer-events-none');
                    customDialog.querySelector('.modal-content').classList.replace('scale-100', 'scale-95');
                    
                    setTimeout(() => {
                        customDialog.classList.add('hidden');
                        dialogConfirmBtn.removeEventListener('click', onConfirm);
                        dialogCancelBtn.removeEventListener('click', onCancel);
                        document.removeEventListener('keydown', onKeydown);
                        resolve(value);
                    }, 300);
                };

                const onConfirm = () => cleanup(type === 'prompt' ? dialogInput.value : true);
                const onCancel = () => cleanup(type === 'prompt' ? null : false);
                const onKeydown = (e) => {
                    if (e.key === 'Enter') onConfirm();
                    if (e.key === 'Escape') onCancel();
                };

                dialogConfirmBtn.addEventListener('click', onConfirm);
                dialogCancelBtn.addEventListener('click', onCancel);
                document.addEventListener('keydown', onKeydown);
            });
        }

        let initialContent = '';
        let currentIndex = -1;
        let isEditing = false;
        let currentFileName = '';
        let currentFileType = '';
        let editorInstance = null;

        function getModeFromExtension(filename) {
            const ext = filename.split('.').pop().toLowerCase();
            const modeMap = {
                'js': 'javascript',
                'json': 'application/json',
                'css': 'css',
                'html': 'htmlmixed',
                'php': 'application/x-httpd-php',
                'sql': 'text/x-sql',
                'xml': 'xml',
                'java': 'text/x-java',
                'c': 'text/x-csrc',
                'cpp': 'text/x-c++src',
                'cs': 'text/x-csharp'
            };
            return modeMap[ext] || 'null';
        }

        function filterFiles(query) {
            const items = document.querySelectorAll('.file-item');
            const noResults = document.getElementById('noResults');
            const clearBtn = document.getElementById('clearSearchBtn');
            const goBackBtn = document.querySelector('a[href*="?path="]:not(.file-item)');
            const q = query.toLowerCase().trim();
            let visibleCount = 0;

            items.forEach(item => {
                const name = item.getAttribute('data-name');
                if (!q || name.includes(q)) {
                    item.classList.remove('hidden');
                    visibleCount++;
                } else {
                    item.classList.add('hidden');
                }
            });

            if (noResults) {
                noResults.classList.toggle('hidden', visibleCount > 0 || !q);
            }

            if (clearBtn) {
                clearBtn.classList.toggle('hidden', !q);
            }

            if (goBackBtn) {
                goBackBtn.classList.toggle('hidden', !!q);
            }
        }

        function clearSearch() {
            const input = document.getElementById('searchInput');
            input.value = '';
            filterFiles('');
            input.focus();
        }

        function openPreview(filename, type) {
            currentIndex = previewableFiles.findIndex(f => f.relPath === filename);
            isEditing = false;
            renderPreview(filename, type);
            
            backdrop.classList.remove('hidden');
            setTimeout(() => {
                backdrop.classList.add('opacity-100');
                backdrop.style.pointerEvents = 'auto';
            }, 10);
        }

        function renderPreview(filename, type) {
            currentFileName = filename;
            currentFileType = type;
            modalTitle.innerText = filename.split('/').pop();
            modalSubtitle.innerText = type;
            
            // Construct download URL
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('action', 'download');
            urlParams.set('filename', filename.split('/').pop());
            // Use javascript:void(0) to prevent navigation, we handle click manually now or via updated onclick
            downloadBtn.href = 'javascript:void(0)';
            downloadBtn.onclick = (e) => {
                e.preventDefault();
                handleDownload('?' + urlParams.toString(), filename.split('/').pop());
            };
            
            // Reset modal body state
            modalBody.classList.add('flex', 'items-center', 'justify-center');
            modalBody.classList.remove('overflow-auto', 'block');
            modalBody.scrollTop = 0;
            modalBody.scrollLeft = 0;
            
            // Toggle buttons
            editBtn.classList.toggle('hidden', type !== 'text');
            renameBtn.classList.toggle('hidden', !config.allow_rename);
            deleteBtn.classList.toggle('hidden', !config.allow_delete);
            cancelBtn.classList.add('hidden');
            saveBtn.classList.add('hidden');
            
            // Show/Hide Nav Arrows
            document.querySelectorAll('.modal button[onclick*="Preview"]').forEach(btn => btn.classList.remove('hidden'));
            
            modalBody.innerHTML = '<div class="flex items-center space-x-2 text-blue-400"><i class="fa-solid fa-spinner fa-spin text-3xl"></i><span>Loading...</span></div>';

            if (type === 'image') {
                modalBody.innerHTML = '';
                modalBody.classList.remove('flex', 'items-center', 'justify-center');
                modalBody.classList.add('block', 'overflow-auto');
                
                // Wrapper for centering
                const wrapper = document.createElement('div');
                wrapper.className = 'min-w-full min-h-full flex items-center justify-center p-4';
                modalBody.appendChild(wrapper);

                const img = new Image();
                img.src = filename;
                // Add max-w/max-h to ensure it fits initially
                img.className = 'max-w-full max-h-[80vh] object-contain animate-in fade-in duration-500 cursor-zoom-in transition-transform duration-300';
                
                let zoomLevel = 0; // 0: fit
                let zoomScales = [0]; 
                let isDragging = false;
                let startX, startY, scrollLeft, scrollTop;
                let moved = false;

                img.onload = () => { 
                    wrapper.appendChild(img);

                    // Dynamic Zoom Scales Calculation
                    // Calculate the scale needed to fit the image
                    const containerWidth = modalBody.clientWidth;
                    const containerHeight = modalBody.clientHeight;
                    // Use natural width/height for ratio
                    const fitScale = Math.min(containerWidth / img.naturalWidth, containerHeight / img.naturalHeight);

                    // Zoom Steps: Fit -> 2x View -> 4x View
                    // We use fitScale as base. 
                    // fitScale * 1 = Actual View Size (Fit)
                    zoomScales = [0, fitScale * 2, fitScale * 4];
                    
                    const handleMouseDown = (e) => {
                        if (zoomLevel === 0) return;
                        isDragging = true;
                        moved = false;
                        startX = e.clientX;
                        startY = e.clientY;
                        scrollLeft = modalBody.scrollLeft;
                        scrollTop = modalBody.scrollTop;
                        img.classList.replace('cursor-grab', 'cursor-grabbing');
                        e.preventDefault();
                    };

                    const handleMouseMove = (e) => {
                        if (!isDragging) return;
                        const dx = e.clientX - startX;
                        const dy = e.clientY - startY;
                        if (Math.abs(dx) > 5 || Math.abs(dy) > 5) moved = true;
                        modalBody.scrollLeft = scrollLeft - dx;
                        modalBody.scrollTop = scrollTop - dy;
                    };

                    const handleMouseUp = () => {
                        if (!isDragging) return;
                        isDragging = false;
                        img.classList.replace('cursor-grabbing', 'cursor-grab');
                    };

                    img.addEventListener('mousedown', handleMouseDown);
                    window.addEventListener('mousemove', handleMouseMove);
                    window.addEventListener('mouseup', handleMouseUp);

                    img.addEventListener('click', (e) => {
                        if (moved) return;

                        const rect = img.getBoundingClientRect();
                        // Calculate click position relative to the image
                        const perX = (e.clientX - rect.left) / rect.width;
                        const perY = (e.clientY - rect.top) / rect.height;
                        
                        zoomLevel = (zoomLevel + 1) % zoomScales.length;
                        const scale = zoomScales[zoomLevel];

                        if (zoomLevel === 0) {
                            // Reset to Fit
                            img.style.width = '';
                            img.style.height = '';
                            img.classList.add('max-w-full', 'max-h-[80vh]');
                            img.classList.remove('max-w-none', 'max-h-none');
                            img.classList.replace('cursor-grab', 'cursor-zoom-in');
                            img.classList.remove('cursor-zoom-out');
                            // Centering handled by wrapper flex
                        } else {
                            // Zoom In
                            const newWidth = img.naturalWidth * scale; 
                            const newHeight = img.naturalHeight * scale;
                            
                            img.style.width = newWidth + 'px';
                            img.style.height = newHeight + 'px';
                            img.classList.remove('max-w-full', 'max-h-[80vh]');
                            img.classList.add('max-w-none', 'max-h-none');
                            img.classList.replace('cursor-zoom-in', 'cursor-grab');
                            img.classList.add('cursor-grab');
                            
                            // Scroll to center the click point
                            // We need to wait for layout update or just set scroll (sync layout)
                            // wrapper grows, modalBody scrolls
                            modalBody.scrollLeft = (newWidth * perX) - (modalBody.clientWidth / 2);
                            modalBody.scrollTop = (newHeight * perY) - (modalBody.clientHeight / 2);
                        }
                        
                        if (zoomLevel === 0) {
                             img.title = 'Click to zoom';
                        } else {
                             // Show approx multiplier relative to fit
                             const mult = Math.round(scale / fitScale);
                             img.title = `Zoom ${mult}x View - Draggable`;
                        }
                    });
                };
            }
            else if (type === 'video') {
                modalBody.innerHTML = `<video controls autoplay class="max-w-full max-h-[80vh]"><source src="${filename}">Your browser does not support the video tag.</video>`;
            } else if (type === 'audio') {
                modalBody.innerHTML = `
                    <div class="flex flex-col items-center justify-center w-full h-full p-12">
                        <div class="w-32 h-32 bg-yellow-500/20 rounded-full flex items-center justify-center mb-8 animate-pulse">
                            <i class="fa-solid fa-music text-6xl text-yellow-500"></i>
                        </div>
                        <h3 class="text-xl text-white font-medium mb-8">${filename.split('/').pop()}</h3>
                        <audio controls autoplay class="w-full max-w-md">
                            <source src="${filename}">
                            Your browser does not support the audio tag.
                        </audio>
                    </div>`;
            } else if (type === 'font') {
                const fontName = 'PreviewFont_' + Date.now();
                const style = document.createElement('style');
                style.id = 'font-preview-style';
                style.innerHTML = `
                    @font-face {
                        font-family: "${fontName}";
                        src: url("${filename}");
                    }
                `;
                document.head.appendChild(style);
                
                modalBody.innerHTML = '';
                modalBody.classList.remove('flex', 'items-center', 'justify-center');
                modalBody.classList.add('block', 'overflow-auto');
                
                modalBody.innerHTML = `
                    <div class="w-full min-h-full p-8 md:p-12 text-center flex flex-col items-center gap-8" style="font-family: '${fontName}';">
                        <div class="space-y-4">
                            <h1 class="text-8xl md:text-9xl text-white">Aa</h1>
                        </div>
                        
                        <div class="space-y-6 max-w-3xl w-full">
                            <p class="text-4xl md:text-5xl text-slate-200 break-words leading-tight">
                                The quick brown fox jumps over the lazy dog
                            </p>
                            <p class="text-2xl md:text-3xl text-slate-400">
                                ABCDEFGHIJKLMNOPQRSTUVWXYZ ĄČĘĖĮŠŲŪŽ<br>
                                abcdefghijklmnopqrstuvwxyz ąčęėįšųūž
                            </p>
                            <p class="text-2xl text-slate-500 tracking-widest">
                                0123456789
                            </p>
                            <p class="text-xl text-slate-600 tracking-widest">
                                !@#$%^&*()_+-=[]{}|;':",./<>?
                            </p>
                        </div>
                        
                        <div class="mt-8 pt-8 border-t border-white/10 text-xs font-mono text-slate-600">
                            ${filename.split('/').pop()}
                        </div>
                    </div>
                `;
            } else if (type === 'text') {
                const formData = new FormData();
                formData.append('action', 'read_file');
                formData.append('filename', filename.split('/').pop());

                fetch('', { method: 'POST', body: formData })
                    .then(response => {
                        if (!response.ok) throw new Error('Failed to load file');
                        return response.text();
                    })
                    .then(text => {
                        modalBody.innerHTML = '';
                        modalBody.classList.remove('flex', 'items-center', 'justify-center');
                        modalBody.classList.add('block');
                        
                        const container = document.createElement('div');
                        container.className = 'w-full h-[80vh] text-left';
                        modalBody.appendChild(container);

                        editorInstance = CodeMirror(container, {
                            value: text,
                            mode: getModeFromExtension(filename),
                            theme: 'monokai',
                            lineNumbers: true,
                            readOnly: true,
                            viewportMargin: Infinity
                        });
                        setTimeout(() => editorInstance.refresh(), 10);
                    })
                    .catch(e => {
                        modalBody.innerHTML = `<div class="p-8 text-red-500">Error loading file: ${e.message}</div>`;
                    });
            } else if (type === 'pdf') {
                modalBody.innerHTML = `<iframe src="${filename}" class="w-full h-[80vh] border-0"></iframe>`;
            } else if (type === 'office') {
                const fullUrl = encodeURIComponent(window.location.origin + '/' + filename);
                modalBody.innerHTML = `<div class="w-full text-center p-4"><p class="text-slate-400 mb-4 text-sm">Office preview requires public access.</p><iframe src="https://docs.google.com/gview?url=${fullUrl}&embedded=true" class="w-full h-[70vh] border-0 rounded-xl bg-white"></iframe></div>`;
            }
        }

        function toggleEdit() {
            if (isEditing) return;
            isEditing = true;
            
            // If checking from read-only mode, we already have an editor instance
            if (editorInstance) {
                editorInstance.setOption('readOnly', false);
                initialContent = editorInstance.getValue();
                editorInstance.focus();
            }

            editBtn.classList.add('hidden');
            renameBtn.classList.add('hidden');
            deleteBtn.classList.add('hidden');
            cancelBtn.classList.remove('hidden');
            saveBtn.classList.remove('hidden');

            // Hide Nav Arrows when editing
            document.querySelectorAll('.modal button[onclick*="Preview"]').forEach(btn => btn.classList.add('hidden'));
        }

        function hasChanges() {
            return editorInstance && editorInstance.getValue() !== initialContent;
        }

        async function cancelEdit() {
            if (!isEditing) return;
            if (!hasChanges() || await showDialog('Discard Changes', 'Are you sure you want to discard your unsaved edits?', { type: 'confirm' })) {
                isEditing = false;
                
                // Revert content and set to read-only
                if (editorInstance) {
                    editorInstance.setValue(initialContent);
                    editorInstance.setOption('readOnly', true);
                }
                
                // Restore buttons
                editBtn.classList.remove('hidden');
                if (config.allow_rename) renameBtn.classList.remove('hidden');
                if (config.allow_delete) deleteBtn.classList.remove('hidden');
                cancelBtn.classList.add('hidden');
                saveBtn.classList.add('hidden');
                document.querySelectorAll('.modal button[onclick*="Preview"]').forEach(btn => btn.classList.remove('hidden'));
            }
        }

        async function saveFile() {
            const content = editorInstance.getValue();
            const formData = new FormData();
            formData.append('action', 'save_file');
            formData.append('filename', currentFileName.split('/').pop());
            formData.append('content', content);

            saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i> Saving...';
            saveBtn.disabled = true;

            try {
                const response = await fetch('', { method: 'POST', body: formData });
                const result = await response.json();
                if (result.success) {
                    saveBtn.innerHTML = '<i class="fa-solid fa-check mr-2"></i> Saved!';
                    saveBtn.classList.replace('bg-blue-600', 'bg-green-600');
                    setTimeout(() => {
                        isEditing = false;
                        if (editorInstance) editorInstance.setOption('readOnly', true);
                        initialContent = content; // Update initial content to the saved version
                        
                        saveBtn.innerHTML = '<i class="fa-solid fa-floppy-disk mr-2"></i> Save';
                        saveBtn.classList.replace('bg-green-600', 'bg-blue-600');
                        saveBtn.disabled = false;
                        
                        // Restore buttons
                        editBtn.classList.remove('hidden');
                        if (config.allow_rename) renameBtn.classList.remove('hidden');
                        if (config.allow_delete) deleteBtn.classList.remove('hidden');
                        cancelBtn.classList.add('hidden');
                        saveBtn.classList.add('hidden');
                        document.querySelectorAll('.modal button[onclick*="Preview"]').forEach(btn => btn.classList.remove('hidden'));
                        
                    }, 1000);
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (e) {
                alert('Upload failed: ' + e);
            }
        }

        async function promptRename() {
            const currentName = currentFileName.split('/').pop();
            const newName = await showDialog('Rename File', 'Enter a new name for this file:', { 
                type: 'prompt', 
                defaultValue: currentName,
                placeholder: 'e.g., new_filename.txt'
            });
            if (!newName || newName === currentName) return;

            const formData = new FormData();
            formData.append('action', 'rename_file');
            formData.append('filename', currentName);
            formData.append('new_name', newName);

            try {
                const response = await fetch('', { method: 'POST', body: formData });
                const result = await response.json();
                if (result.success) {
                    window.location.reload();
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (e) {
                alert('Rename failed');
            }
        }

        async function confirmDelete() {
            const currentName = currentFileName.split('/').pop();
            const confirmed = await showDialog('Confirm Delete', `Are you sure you want to permanently delete "${currentName}"?`, { type: 'confirm' });
            
            if (!confirmed) return;

            const formData = new FormData();
            formData.append('action', 'delete_file');
            formData.append('filename', currentName);

            try {
                const response = await fetch('', { method: 'POST', body: formData });
                const result = await response.json();
                if (result.success) {
                    window.location.reload();
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (e) {
                alert('Delete failed');
            }
        }

        async function promptNewFile() {
            const name = await showDialog('New File', 'Enter a name for the new file:', {
                type: 'prompt',
                placeholder: 'e.g., notes.txt'
            });
            if (!name) return;

            const formData = new FormData();
            formData.append('action', 'create_file');
            formData.append('filename', name);

            try {
                const response = await fetch('', { method: 'POST', body: formData });
                const result = await response.json();
                if (result.success) {
                    window.location.reload();
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (e) {
                alert('Request failed');
            }
        }

        // Drag & Drop / Upload Logic
        function handleFileSelect(e) {
            uploadFiles(e.target.files);
        }

        window.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.replace('opacity-0', 'opacity-100');
            dropZone.classList.remove('pointer-events-none');
        });

        window.addEventListener('dragleave', (e) => {
            if (e.relatedTarget === null) {
                dropZone.classList.replace('opacity-100', 'opacity-0');
                dropZone.classList.add('pointer-events-none');
            }
        });

        window.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.replace('opacity-100', 'opacity-0');
            dropZone.classList.add('pointer-events-none');
            if (e.dataTransfer.files.length > 0) {
                uploadFiles(e.dataTransfer.files);
            }
        });

        async function uploadFiles(files) {
            let errors = [];
            for (let file of files) {
                const formData = new FormData();
                formData.append('file', file);
                
                // Show a simple loading state (visual feedback)
                dropZone.classList.replace('opacity-0', 'opacity-100');
                dropZone.querySelector('h2').innerText = `Uploading ${file.name}...`;

                try {
                    const response = await fetch('', { method: 'POST', body: formData });
                    if (!response.ok) {
                        const result = await response.json().catch(() => ({}));
                        throw new Error(result.error || `Server responded with ${response.status}`);
                    }
                } catch (e) {
                    console.error('Failed to upload', file.name, e);
                    errors.push(`${file.name}: ${e.message}`);
                }
            }
            
            dropZone.classList.replace('opacity-100', 'opacity-0');
            dropZone.classList.add('pointer-events-none');
            
            if (errors.length > 0) {
                await showDialog('Upload Failed', 'The following files could not be uploaded:\n' + errors.join('\n'), { type: 'alert' });
            }
            
            window.location.reload();
        }

        function nextPreview() {
            if (previewableFiles.length <= 1 || isEditing) return;
            currentIndex = (currentIndex + 1) % previewableFiles.length;
            const file = previewableFiles[currentIndex];
            renderPreview(file.relPath, file.type);
        }

        function prevPreview() {
            if (previewableFiles.length <= 1 || isEditing) return;
            currentIndex = (currentIndex - 1 + previewableFiles.length) % previewableFiles.length;
            const file = previewableFiles[currentIndex];
            renderPreview(file.relPath, file.type);
        }

        async function downloadFile(filename) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('action', 'download');
            urlParams.set('filename', filename.split('/').pop());
            const url = '?' + urlParams.toString();
            await handleDownload(url, filename.split('/').pop());
        }

        async function handleDownload(url, filename) {
            try {
                const response = await fetch(url);
                if (!response.ok) throw new Error('Download failed');
                const blob = await response.blob();
                const objectUrl = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = objectUrl;
                a.download = filename; // Force the filename
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(objectUrl);
                document.body.removeChild(a);
            } catch (e) {
                alert('Download failed: ' + e.message);
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        async function closePreview() {
            if (isEditing && hasChanges() && !await showDialog('Unsaved Changes', 'You have unsaved changes. Do you want to discard them and close?', { type: 'confirm' })) return;
            
            // Cleanup font styles
            const fontStyle = document.getElementById('font-preview-style');
            if (fontStyle) fontStyle.remove();

            backdrop.classList.remove('opacity-100');
            backdrop.style.pointerEvents = 'none';
            setTimeout(() => {
                backdrop.classList.add('hidden');
                modalBody.innerHTML = '';
                currentIndex = -1;
                isEditing = false;
            }, 300);
        }

        document.addEventListener('keydown', (e) => {
            if (backdrop.classList.contains('hidden')) return;
            if (e.key === 'Escape') closePreview();
            if (!isEditing) {
                if (e.key === 'ArrowRight') nextPreview();
                if (e.key === 'ArrowLeft') prevPreview();
            }
        });

        backdrop.addEventListener('click', (e) => {
            if (e.target === backdrop) closePreview();
        });
    </script>
</body>
</html>
