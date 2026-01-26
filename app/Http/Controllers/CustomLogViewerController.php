<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Crypt;

class CustomLogViewerController extends Controller
{
    protected $maxFileSize = 50 * 1024 * 1024; // 50MB en bytes
    protected $linesPerPage = 1000; // Líneas por página
    protected $maxLinesToRead = 10000; // Máximo de líneas a leer de una vez

    /**
     * Muestra el visor de logs con soporte para archivos grandes
     */
    public function index(Request $request)
    {
        // Manejar acciones de descarga, limpieza y eliminación
        if ($request->has('dl')) {
            return $this->download($request);
        }
        if ($request->has('clean')) {
            return $this->clean($request);
        }
        if ($request->has('del')) {
            return $this->delete($request);
        }
        if ($request->has('delall')) {
            return $this->deleteAll($request);
        }

        $folder = $request->get('f');
        $currentFile = $request->get('l');
        $page = (int) $request->get('page', 1);
        $page = max(1, $page); // Asegurar que page sea al menos 1

        $storage_path = storage_path('logs');
        $pattern = '/^' . preg_quote(config('logviewer.pattern', 'laravel-'), '/') . '/';
        $files = [];
        $folders = [];

        if ($folder) {
            $storage_path = storage_path('logs/' . Crypt::decrypt($folder));
        }

        try {
            $allFiles = File::allFiles($storage_path);
            foreach ($allFiles as $file) {
                if (preg_match($pattern, $file->getFilename())) {
                    $files[] = $file->getFilename();
                }
            }
            rsort($files);
        } catch (\Exception $e) {
            $files = [];
        }

        $current_file = null;
        $current_folder = $folder ? Crypt::decrypt($folder) : null;
        $logs = null;
        $standardFormat = true;
        $fileSize = 0;
        $totalLines = 0;
        $totalPages = 1;
        $isLargeFile = false;

        if ($currentFile) {
            try {
                $current_file = Crypt::decrypt($currentFile);
                $filePath = $storage_path . '/' . $current_file;
                
                if (file_exists($filePath)) {
                    $fileSize = filesize($filePath);
                    $isLargeFile = $fileSize > $this->maxFileSize;

                    if ($isLargeFile) {
                        // Para archivos grandes, leer desde el final
                        $logs = $this->readLargeLogFile($filePath, $page);
                        // Para archivos muy grandes, estimar líneas en lugar de contarlas todas
                        if ($fileSize > 100 * 1024 * 1024) { // > 100MB
                            // Estimar basado en tamaño promedio de línea
                            $estimatedLines = (int)($fileSize / 200); // Asumiendo ~200 bytes por línea
                            $totalLines = $estimatedLines;
                        } else {
                            $totalLines = $this->countLinesInFile($filePath);
                        }
                        $totalPages = max(1, ceil($totalLines / $this->linesPerPage));
                    } else {
                        // Para archivos pequeños, usar el método original del paquete
                        $logs = $this->readSmallLogFile($filePath);
                    }
                }
            } catch (\Exception $e) {
                $logs = null;
            }
        }

        // Obtener estructura de carpetas
        $structure = [];
        try {
            $directories = File::directories(storage_path('logs'));
            foreach ($directories as $directory) {
                $folders[] = basename($directory);
            }
        } catch (\Exception $e) {
            $folders = [];
        }

        return view('vendor.laravel-log-viewer.log', compact(
            'files',
            'folders',
            'current_file',
            'current_folder',
            'logs',
            'standardFormat',
            'fileSize',
            'isLargeFile',
            'page',
            'totalPages',
            'totalLines',
            'storage_path',
            'structure'
        ));
    }

    /**
     * Lee un archivo de log grande de forma eficiente
     */
    protected function readLargeLogFile($filePath, $page = 1)
    {
        $logs = [];
        
        // Para archivos grandes, siempre leemos desde el final
        // La página 1 muestra las últimas líneas, página 2 las anteriores, etc.
        $linesToRead = $this->linesPerPage;
        
        // Leer más líneas de las necesarias para asegurar que tenemos suficientes
        $lines = $this->readLastLines($filePath, $linesToRead * $page, $this->maxLinesToRead * 2);
        
        if (empty($lines)) {
            return [];
        }

        // Si estamos en una página mayor a 1, necesitamos tomar líneas anteriores
        if ($page > 1) {
            $startIndex = max(0, count($lines) - ($page * $this->linesPerPage));
            $lines = array_slice($lines, $startIndex, $this->linesPerPage);
        } else {
            // Página 1: últimas líneas
            $lines = array_slice($lines, -$this->linesPerPage);
        }

        // Procesar las líneas en formato estándar
        $key = 0;
        foreach (array_reverse($lines) as $line) {
            $log = $this->parseLogLine($line, $key);
            if ($log) {
                $logs[] = $log;
                $key++;
            }
        }

        return array_reverse($logs); // Revertir para mostrar más recientes primero
    }

    /**
     * Lee las últimas N líneas de un archivo de forma eficiente
     */
    protected function readLastLines($filePath, $numLines, $maxBytes = 5242880)
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return [];
        }

        $fileSize = filesize($filePath);
        
        // Leer desde el final del archivo
        // Empezar leyendo un chunk grande desde el final
        $chunkSize = min($maxBytes, $fileSize);
        $position = max(0, $fileSize - $chunkSize);
        
        fseek($handle, $position);
        
        // Leer el chunk
        $data = fread($handle, $chunkSize);
        
        // Si no leímos suficiente, leer más chunks hacia atrás
        $lineCount = substr_count($data, "\n");
        $iterations = 0;
        $maxIterations = 10; // Limitar iteraciones para evitar loops infinitos
        
        while ($lineCount < $numLines && $position > 0 && $iterations < $maxIterations) {
            $additionalBytes = min($maxBytes, $position);
            $newPosition = max(0, $position - $additionalBytes);
            
            fseek($handle, $newPosition);
            $additionalData = fread($handle, $position - $newPosition);
            
            $data = $additionalData . $data;
            $lineCount = substr_count($data, "\n");
            $position = $newPosition;
            $iterations++;
        }
        
        fclose($handle);

        // Dividir en líneas y filtrar vacías
        $lines = explode("\n", $data);
        
        // Mantener solo las últimas N líneas
        if (count($lines) > $numLines) {
            $lines = array_slice($lines, -$numLines);
        }

        return $lines;
    }

    /**
     * Cuenta el número total de líneas en un archivo
     */
    protected function countLinesInFile($filePath)
    {
        $count = 0;
        $handle = fopen($filePath, 'r');
        
        if (!$handle) {
            return 0;
        }

        // Leer en chunks para archivos grandes
        while (!feof($handle)) {
            $chunk = fread($handle, 8192);
            $count += substr_count($chunk, "\n");
        }
        
        fclose($handle);
        return $count;
    }

    /**
     * Lee un archivo pequeño usando el método tradicional
     */
    protected function readSmallLogFile($filePath)
    {
        // Usar la clase del paquete si está disponible
        if (class_exists('\Rap2hpoutre\LaravelLogViewer\LaravelLogViewer')) {
            $logViewer = new \Rap2hpoutre\LaravelLogViewer\LaravelLogViewer();
            return $logViewer->all($filePath);
        }

        // Fallback: leer y parsear manualmente
        $content = file_get_contents($filePath);
        $logs = [];
        $lines = explode("\n", $content);
        $key = 0;

        foreach ($lines as $line) {
            $log = $this->parseLogLine($line, $key);
            if ($log) {
                $logs[] = $log;
                $key++;
            }
        }

        return $logs;
    }

    /**
     * Parsea una línea de log en el formato estándar
     * Soporta múltiples formatos de logs de Laravel
     */
    protected function parseLogLine($line, $key)
    {
        if (empty(trim($line))) {
            return null;
        }

        // Formato Laravel estándar: [YYYY-MM-DD HH:MM:SS] local.LEVEL: message
        // Ejemplo: [2024-01-15 10:30:45] local.ERROR: Exception message
        if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s+(\w+)\.(\w+):\s+(.*)$/s', $line, $matches)) {
            $level = strtolower($matches[3]);
            return [
                'level' => strtoupper($level),
                'level_class' => $this->getLevelClass($level),
                'level_img' => $this->getLevelImg($level),
                'date' => $matches[1],
                'context' => $matches[2],
                'text' => trim($matches[4]),
                'stack' => null,
                'in_file' => null
            ];
        }

        // Formato alternativo: [YYYY-MM-DD HH:MM:SS] LEVEL: message
        if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s+(\w+):\s+(.*)$/s', $line, $matches)) {
            $level = strtolower($matches[2]);
            return [
                'level' => strtoupper($level),
                'level_class' => $this->getLevelClass($level),
                'level_img' => $this->getLevelImg($level),
                'date' => $matches[1],
                'context' => '',
                'text' => trim($matches[3]),
                'stack' => null,
                'in_file' => null
            ];
        }

        // Formato con stack trace (multilínea)
        // Buscar si la línea contiene información de stack trace
        if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s+(\w+)\.(\w+):\s+(.*?)(?:\n|$)(.*)$/s', $line, $matches)) {
            $level = strtolower($matches[3]);
            $stack = !empty(trim($matches[5])) ? trim($matches[5]) : null;
            return [
                'level' => strtoupper($level),
                'level_class' => $this->getLevelClass($level),
                'level_img' => $this->getLevelImg($level),
                'date' => $matches[1],
                'context' => $matches[2],
                'text' => trim($matches[4]),
                'stack' => $stack,
                'in_file' => null
            ];
        }

        // Si no coincide con ningún formato conocido, devolver como texto plano con nivel INFO
        return [
            'level' => 'INFO',
            'level_class' => 'info',
            'level_img' => 'info-circle',
            'date' => date('Y-m-d H:i:s'),
            'context' => '',
            'text' => trim($line),
            'stack' => null,
            'in_file' => null
        ];
    }

    /**
     * Obtiene la clase CSS para el nivel de log
     */
    protected function getLevelClass($level)
    {
        $level = strtolower($level);
        $classes = [
            'emergency' => 'danger',
            'alert' => 'danger',
            'critical' => 'danger',
            'error' => 'danger',
            'warning' => 'warning',
            'notice' => 'info',
            'info' => 'info',
            'debug' => 'info'
        ];
        return $classes[$level] ?? 'info';
    }

    /**
     * Obtiene el icono para el nivel de log
     */
    protected function getLevelImg($level)
    {
        $level = strtolower($level);
        $icons = [
            'emergency' => 'exclamation-triangle',
            'alert' => 'exclamation-triangle',
            'critical' => 'exclamation-triangle',
            'error' => 'exclamation-circle',
            'warning' => 'exclamation-triangle',
            'notice' => 'info-circle',
            'info' => 'info-circle',
            'debug' => 'info-circle'
        ];
        return $icons[$level] ?? 'info-circle';
    }

    /**
     * Descarga un archivo de log
     */
    public function download(Request $request)
    {
        $file = $request->get('dl');
        $folder = $request->get('f');
        
        if ($file) {
            $filePath = storage_path('logs');
            if ($folder) {
                $filePath .= '/' . Crypt::decrypt($folder);
            }
            $filePath .= '/' . Crypt::decrypt($file);
            
            if (file_exists($filePath)) {
                return response()->download($filePath);
            }
        }
        
        abort(404);
    }

    /**
     * Limpia un archivo de log
     */
    public function clean(Request $request)
    {
        $file = $request->get('clean');
        $folder = $request->get('f');
        
        if ($file) {
            $filePath = storage_path('logs');
            if ($folder) {
                $filePath .= '/' . Crypt::decrypt($folder);
            }
            $filePath .= '/' . Crypt::decrypt($file);
            
            if (file_exists($filePath)) {
                file_put_contents($filePath, '');
            }
        }
        
        return redirect()->back();
    }

    /**
     * Elimina un archivo de log
     */
    public function delete(Request $request)
    {
        $file = $request->get('del');
        $folder = $request->get('f');
        
        if ($file) {
            $filePath = storage_path('logs');
            if ($folder) {
                $filePath .= '/' . Crypt::decrypt($folder);
            }
            $filePath .= '/' . Crypt::decrypt($file);
            
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        
        return redirect()->back();
    }

    /**
     * Elimina todos los archivos de log
     */
    public function deleteAll(Request $request)
    {
        $folder = $request->get('f');
        $storage_path = storage_path('logs');
        
        if ($folder) {
            $storage_path .= '/' . Crypt::decrypt($folder);
        }

        try {
            $pattern = '/^' . preg_quote(config('logviewer.pattern', 'laravel-'), '/') . '/';
            $allFiles = File::allFiles($storage_path);
            
            foreach ($allFiles as $file) {
                if (preg_match($pattern, $file->getFilename())) {
                    unlink($file->getPathname());
                }
            }
        } catch (\Exception $e) {
            // Ignorar errores
        }
        
        return redirect()->back();
    }
}

