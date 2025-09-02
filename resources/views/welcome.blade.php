<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>PHP Configuration Test</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                padding: 20px;
                background-color: #f5f5f5;
            }
            .config-box {
                background: white;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                margin-bottom: 20px;
            }
            .config-item {
                margin: 10px 0;
                padding: 8px;
                background: #f8f9fa;
                border-left: 4px solid #007bff;
            }
            pre {
                background: #2d3748;
                color: #fff;
                padding: 15px;
                border-radius: 4px;
                overflow-x: auto;
            }
        </style>
    </head>
    <body>
        <div class="config-box">
            <h1>PHP Configuration Test</h1>
            <p><strong>SAPI:</strong> {{ php_sapi_name() }}</p>
            <p><strong>Loaded Config File:</strong> {{ php_ini_loaded_file() }}</p>
            
            <div class="config-item">
                <strong>post_max_size:</strong> {{ ini_get('post_max_size') }}
            </div>
            
            <div class="config-item">
                <strong>upload_max_filesize:</strong> {{ ini_get('upload_max_filesize') }}
            </div>
            
            <div class="config-item">
                <strong>memory_limit:</strong> {{ ini_get('memory_limit') }}
            </div>
            
            <div class="config-item">
                <strong>max_execution_time:</strong> {{ ini_get('max_execution_time') }}
            </div>
            
            <div class="config-item">
                <strong>max_input_vars:</strong> {{ ini_get('max_input_vars') }}
            </div>
        </div>
        
        <div class="config-box">
            <h2>Raw var_dump Output:</h2>
            <pre><?php 
var_dump([
    'post_max_size' => ini_get('post_max_size'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
    'sapi' => php_sapi_name(),
    'loaded_ini' => php_ini_loaded_file(),
    'content_length_from_server' => $_SERVER['CONTENT_LENGTH'] ?? 'not set'
]);
?></pre>
        </div>

        <div class="config-box">
            <h2>File Size Check (411MB file):</h2>
            <?php
            $fileSize = 411773819; // 411MB in bytes
            $postMaxBytes = ini_get('post_max_size');
            
            // Convert post_max_size to bytes
            $postMaxBytes = str_replace(['k', 'K'], '', $postMaxBytes);
            if (strpos($postMaxBytes, 'M') !== false) {
                $postMaxBytes = intval($postMaxBytes) * 1048576;
            } elseif (strpos($postMaxBytes, 'G') !== false) {
                $postMaxBytes = intval($postMaxBytes) * 1073741824;
            }
            
            echo "<div class='config-item'>";
            echo "<strong>Your file size:</strong> " . number_format($fileSize) . " bytes (" . round($fileSize/1048576, 2) . " MB)<br>";
            echo "<strong>post_max_size in bytes:</strong> " . number_format($postMaxBytes) . " bytes (" . round($postMaxBytes/1048576, 2) . " MB)<br>";
            
            if ($fileSize > $postMaxBytes) {
                echo "<span style='color: red; font-weight: bold;'>❌ FILE TOO LARGE</span>";
            } else {
                echo "<span style='color: green; font-weight: bold;'>✅ FILE SIZE OK</span>";
            }
            echo "</div>";
            ?>
        </div>
    </body>
</html>