<?php

namespace App\Services\Google;

use Google\Client as GoogleClient;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Drive\Permission;
use Illuminate\Support\Facades\Log;

/**
 * Sube Excel de confirmación a Google Drive (carpetas por consolidado / cliente; opcional proveedor).
 *
 * Modo oauth (recomendado Gmail): cuenta de usuario con refresh token.
 * Modo service_account: Shared drive o carpeta compartida con SA (Workspace).
 */
class GoogleDriveExcelConfirmacionService
{
    /** @var Drive|null */
    private $drive;

    /** @var bool */
    private $usesOAuth = false;

    /** @var string */
    private $rootFolderId;

    /** @var string */
    private $sharedDriveId;

    /** @var GoogleDriveOAuthCredentials */
    private $oauthCredentials;

    public function __construct(GoogleDriveOAuthCredentials $oauthCredentials)
    {
        $this->oauthCredentials = $oauthCredentials;
        $this->rootFolderId = $this->resolveRootFolderId();
        $this->sharedDriveId = trim((string) config('google.drive_excel_confirmacion_shared_drive_id', ''));
    }

    /**
     * @return string
     */
    protected function resolveRootFolderId()
    {
        return trim((string) config('google.drive_excel_confirmacion_root_folder_id', ''));
    }

    public function isConfigured(): bool
    {
        if ($this->rootFolderId === '') {
            return false;
        }

        if ($this->shouldUseOAuth()) {
            return $this->oauthCredentials->isConfigured();
        }

        $credentials = config('google.service.file');

        return (bool) config('google.service.enable', false)
            && is_string($credentials)
            && is_file($credentials);
    }

    /**
     * Sube un Excel general a nivel cliente (sin carpeta por proveedor).
     *
     * @return string|null URL pública edit?usp=sharing
     */
    public function uploadForCliente(
        string $cargaCode,
        string $nombreCliente,
        string $localPath,
        string $fileName
    ): ?string {
        if (!$this->isConfigured() || !is_file($localPath)) {
            return null;
        }

        try {
            $this->bootDrive();

            $consolidadoFolderId = $this->ensureFolder($this->rootFolderId, 'Consolidado-' . $this->sanitizeName($cargaCode));
            $clienteFolderId = $this->ensureFolder($consolidadoFolderId, $this->sanitizeName($nombreCliente));

            return $this->uploadOrReplace($clienteFolderId, $localPath, $fileName);
        } catch (\Throwable $e) {
            Log::error('GoogleDriveExcelConfirmacionService: fallo al subir Excel general', [
                'auth' => $this->usesOAuth ? 'oauth' : 'service_account',
                'carga' => $cargaCode,
                'cliente' => $nombreCliente,
                'file' => $fileName,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return string|null URL pública edit?usp=sharing
     */
    public function uploadForProveedor(
        string $cargaCode,
        string $nombreCliente,
        string $codeSupplier,
        string $localPath,
        string $fileName
    ): ?string {
        if (!$this->isConfigured() || !is_file($localPath)) {
            return null;
        }

        try {
            $this->bootDrive();

            $consolidadoFolderId = $this->ensureFolder($this->rootFolderId, 'Consolidado-' . $this->sanitizeName($cargaCode));
            $clienteFolderId = $this->ensureFolder($consolidadoFolderId, $this->sanitizeName($nombreCliente));
            $proveedorFolderId = $this->ensureFolder($clienteFolderId, $this->sanitizeName($codeSupplier));

            return $this->uploadOrReplace($proveedorFolderId, $localPath, $fileName);
        } catch (\Throwable $e) {
            Log::error('GoogleDriveExcelConfirmacionService: fallo al subir Excel', [
                'auth' => $this->usesOAuth ? 'oauth' : 'service_account',
                'carga' => $cargaCode,
                'proveedor' => $codeSupplier,
                'file' => $fileName,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function shouldUseOAuth(): bool
    {
        $mode = strtolower(trim((string) config('google.drive_excel_auth_mode', 'oauth')));

        if ($mode === 'service_account') {
            return false;
        }

        if ($mode === 'oauth') {
            return true;
        }

        // auto: OAuth si hay credenciales OAuth; si no, service account
        if ($this->oauthCredentials->isConfigured()) {
            return true;
        }

        return false;
    }

    protected function bootDrive(): void
    {
        if ($this->drive !== null) {
            return;
        }

        $this->usesOAuth = $this->shouldUseOAuth();

        if ($this->usesOAuth) {
            $client = $this->oauthCredentials->createAuthenticatedClient();
        } else {
            $client = new GoogleClient();
            $client->setAuthConfig(config('google.service.file'));
            $client->addScope(Drive::DRIVE);
        }

        $this->drive = new Drive($client);
    }

    protected function resetDrive(): void
    {
        $this->drive = null;
    }

    protected function ensureFolder(string $parentId, string $name): string
    {
        $existingId = $this->findFolderId($parentId, $name);
        if ($existingId !== null) {
            return $existingId;
        }

        $meta = new DriveFile([
            'name' => $name,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents' => [$parentId],
        ]);

        $folder = $this->drive->files->create($meta, $this->driveWriteParams(['fields' => 'id']));

        return (string) $folder->getId();
    }

    private function findFolderId(string $parentId, string $name): ?string
    {
        $results = $this->drive->files->listFiles(array_merge($this->driveListParams(), [
            'q' => $this->buildNameInParentQuery($name, $parentId, 'application/vnd.google-apps.folder'),
            'fields' => 'files(id)',
            'pageSize' => 1,
        ]));

        $files = $results->getFiles();

        return !empty($files) ? (string) $files[0]->getId() : null;
    }

    /**
     * @param string|null $preferFileId Si ya está vinculado, actualiza ese ID (evita duplicados por nombre).
     */
    protected function uploadOrReplace(
        string $folderId,
        string $localPath,
        string $fileName,
        ?string $preferFileId = null
    ): string {
        $mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        $content = file_get_contents($localPath);
        if ($content === false) {
            throw new \RuntimeException('No se pudo leer el archivo local: ' . $localPath);
        }

        $preferFileId = trim((string) $preferFileId);
        $mode = 'create';
        $fileId = null;

        // 1) Preferir el file_id ya vinculado (mismo link que ve el usuario).
        if ($preferFileId !== '') {
            $metaInfo = $this->getDriveFileMeta($preferFileId);
            if ($metaInfo !== null) {
                $currentMime = (string) ($metaInfo['mimeType'] ?? '');
                // Si lo convirtieron a Google Sheets, el update binario xlsx no aplica a ese ID.
                if ($currentMime === 'application/vnd.google-apps.spreadsheet') {
                    Log::warning('GoogleDriveExcelConfirmacionService: file_id es Google Sheets; se reemplaza por xlsx nuevo', [
                        'file_id' => $preferFileId,
                        'file' => $fileName,
                        'modifiedTime' => $metaInfo['modifiedTime'] ?? null,
                    ]);
                } else {
                    try {
                        $meta = new DriveFile(['name' => $fileName]);
                        $file = $this->drive->files->update($preferFileId, $meta, $this->driveWriteParams([
                            'data' => $content,
                            'mimeType' => $mime,
                            'uploadType' => 'multipart',
                            'fields' => 'id,modifiedTime,mimeType,size',
                        ]));
                        $fileId = (string) $file->getId();
                        $mode = 'update_by_id';
                        Log::info('GoogleDriveExcelConfirmacionService: Excel actualizado por file_id', [
                            'file_id' => $fileId,
                            'file' => $fileName,
                            'modifiedTime' => $file->getModifiedTime(),
                            'size' => $file->getSize(),
                            'before_modifiedTime' => $metaInfo['modifiedTime'] ?? null,
                        ]);
                    } catch (\Throwable $e) {
                        Log::warning('GoogleDriveExcelConfirmacionService: update por file_id falló', [
                            'file_id' => $preferFileId,
                            'file' => $fileName,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            } else {
                Log::warning('GoogleDriveExcelConfirmacionService: file_id vinculado no existe en Drive', [
                    'file_id' => $preferFileId,
                    'file' => $fileName,
                ]);
            }
        }

        // 2) Fallback: mismo nombre en la carpeta (xlsx nativo, el más reciente).
        if ($fileId === null) {
            $byNameId = $this->findXlsxFileId($folderId, $fileName);
            if ($byNameId !== null) {
                $meta = new DriveFile(['name' => $fileName]);
                $file = $this->drive->files->update($byNameId, $meta, $this->driveWriteParams([
                    'data' => $content,
                    'mimeType' => $mime,
                    'uploadType' => 'multipart',
                    'fields' => 'id,modifiedTime,size',
                ]));
                $fileId = (string) $file->getId();
                $mode = 'update_by_name';
                Log::info('GoogleDriveExcelConfirmacionService: Excel actualizado por nombre', [
                    'file_id' => $fileId,
                    'file' => $fileName,
                    'modifiedTime' => $file->getModifiedTime(),
                    'size' => $file->getSize(),
                ]);
            } else {
                $meta = new DriveFile([
                    'name' => $fileName,
                    'parents' => [$folderId],
                ]);
                $file = $this->drive->files->create($meta, $this->driveWriteParams([
                    'data' => $content,
                    'mimeType' => $mime,
                    'uploadType' => 'multipart',
                    'fields' => 'id,modifiedTime,size',
                ]));
                $fileId = (string) $file->getId();
                $mode = 'create';
                Log::info('GoogleDriveExcelConfirmacionService: Excel creado en Drive', [
                    'file_id' => $fileId,
                    'file' => $fileName,
                    'modifiedTime' => $file->getModifiedTime(),
                    'size' => $file->getSize(),
                    'prefer_file_id' => $preferFileId !== '' ? $preferFileId : null,
                ]);
            }
        }

        $this->ensureAnyoneWriter($fileId);

        Log::info('GoogleDriveExcelConfirmacionService: uploadOrReplace OK', [
            'mode' => $mode,
            'file_id' => $fileId,
            'file' => $fileName,
        ]);

        return 'https://drive.google.com/file/d/' . $fileId . '/edit?usp=sharing';
    }

    /**
     * @return array{id?:string,mimeType?:string,modifiedTime?:string,name?:string}|null
     */
    private function getDriveFileMeta(string $fileId): ?array
    {
        try {
            $file = $this->drive->files->get($fileId, $this->driveListParams() + [
                'fields' => 'id,name,mimeType,modifiedTime,size,trashed',
            ]);
            if ($file->getTrashed()) {
                return null;
            }

            return [
                'id' => (string) $file->getId(),
                'name' => (string) $file->getName(),
                'mimeType' => (string) $file->getMimeType(),
                'modifiedTime' => (string) $file->getModifiedTime(),
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Descarga un archivo de Drive por ID a una ruta local.
     */
    public function downloadFileByIdToPath(string $fileId, string $destPath): bool
    {
        $fileId = trim($fileId);
        if ($fileId === '' || $destPath === '') {
            return false;
        }

        if (!$this->isConfigured()) {
            return false;
        }

        try {
            $this->bootDrive();

            $response = $this->drive->files->get($fileId, array_merge($this->driveListParams(), [
                'alt' => 'media',
            ]));

            if (is_string($response)) {
                $content = $response;
            } elseif (is_object($response) && method_exists($response, 'getBody')) {
                $content = $response->getBody()->getContents();
            } else {
                $content = (string) $response;
            }
            if ($content === '' || $content === false) {
                return false;
            }

            $dir = dirname($destPath);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }

            return file_put_contents($destPath, $content) !== false;
        } catch (\Throwable $e) {
            Log::error('GoogleDriveExcelConfirmacionService: fallo al descargar archivo', [
                'file_id' => $fileId,
                'dest' => $destPath,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function findFileId(string $folderId, string $fileName): ?string
    {
        $results = $this->drive->files->listFiles(array_merge($this->driveListParams(), [
            'q' => $this->buildNameInParentQuery($fileName, $folderId, null),
            'fields' => 'files(id)',
            'pageSize' => 1,
            'orderBy' => 'modifiedTime desc',
        ]));

        $files = $results->getFiles();

        return !empty($files) ? (string) $files[0]->getId() : null;
    }

    /**
     * Busca solo xlsx nativo (ignora copias convertidas a Google Sheets con el mismo nombre).
     */
    private function findXlsxFileId(string $folderId, string $fileName): ?string
    {
        $mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        $results = $this->drive->files->listFiles(array_merge($this->driveListParams(), [
            'q' => $this->buildNameInParentQuery($fileName, $folderId, $mime),
            'fields' => 'files(id,modifiedTime)',
            'pageSize' => 1,
            'orderBy' => 'modifiedTime desc',
        ]));

        $files = $results->getFiles();

        return !empty($files) ? (string) $files[0]->getId() : null;
    }

    private function buildNameInParentQuery(string $name, string $parentId, ?string $mimeType): string
    {
        // Drive query: solo escapar \ y ' dentro del literal entre comillas (# es válido literal).
        $escapedName = str_replace(
            ['\\', "'"],
            ['\\\\', "\\'"],
            $name
        );
        $query = sprintf("name='%s' and '%s' in parents and trashed=false", $escapedName, $parentId);

        if ($mimeType !== null) {
            $query .= " and mimeType='{$mimeType}'";
        }

        return $query;
    }

    private function ensureAnyoneWriter(string $fileId): void
    {
        try {
            $list = $this->drive->permissions->listPermissions(
                $fileId,
                array_merge($this->driveWriteParams(), ['fields' => 'permissions(id,type,role)'])
            );

            foreach ($list->getPermissions() as $perm) {
                if ($perm->getType() === 'anyone') {
                    if ($perm->getRole() !== 'writer') {
                        $this->drive->permissions->update(
                            $fileId,
                            (string) $perm->getId(),
                            new Permission(['role' => 'writer']),
                            $this->driveWriteParams()
                        );
                    }

                    return;
                }
            }

            $permission = new Permission([
                'type' => 'anyone',
                'role' => 'writer',
            ]);
            $this->drive->permissions->create($fileId, $permission, $this->driveWriteParams());
        } catch (\Throwable $e) {
            Log::debug('GoogleDriveExcelConfirmacionService: permiso Drive editable', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function driveWriteParams(array $extra = []): array
    {
        $params = $extra;

        if (!$this->usesOAuth || $this->sharedDriveId !== '') {
            $params['supportsAllDrives'] = true;
        }

        return $params;
    }

    /**
     * @return array<string, mixed>
     */
    private function driveListParams(): array
    {
        $params = [];

        if (!$this->usesOAuth || $this->sharedDriveId !== '') {
            $params['supportsAllDrives'] = true;
            $params['includeItemsFromAllDrives'] = true;
        }

        if ($this->sharedDriveId !== '') {
            $params['driveId'] = $this->sharedDriveId;
            $params['corpora'] = 'drive';
        }

        return $params;
    }

    protected function sanitizeName(string $name): string
    {
        $name = preg_replace('/[\/\\\\:*?"<>|]/', '-', trim($name));

        return $name !== '' ? $name : 'sin-nombre';
    }

    /**
     * @param string $fileId
     * @return bool
     */
    protected function deleteDriveFileById($fileId)
    {
        $this->bootDrive();

        try {
            $this->drive->files->delete((string) $fileId, $this->driveWriteParams());

            return true;
        } catch (\Throwable $e) {
            if ($this->isDriveNotFoundError($e)) {
                return true;
            }

            throw $e;
        }
    }

    /**
     * @param string $parentId
     * @param string|null $mimeType
     * @return array<int, array{id: string, name: string, mimeType: string|null}>
     */
    protected function listDriveChildren($parentId, $mimeType = null)
    {
        $this->bootDrive();

        $items = [];
        $pageToken = null;

        do {
            $params = array_merge($this->driveListParams(), [
                'q' => $this->buildParentQuery($parentId, $mimeType),
                'fields' => 'nextPageToken, files(id, name, mimeType)',
                'pageSize' => 200,
            ]);

            if ($pageToken !== null) {
                $params['pageToken'] = $pageToken;
            }

            $results = $this->drive->files->listFiles($params);

            foreach ($results->getFiles() as $file) {
                $items[] = [
                    'id' => (string) $file->getId(),
                    'name' => (string) $file->getName(),
                    'mimeType' => $file->getMimeType(),
                ];
            }

            $pageToken = $results->getNextPageToken();
        } while ($pageToken !== null);

        return $items;
    }

    /**
     * @param string $parentId
     * @param string|null $mimeType
     * @return string
     */
    protected function buildParentQuery($parentId, $mimeType = null)
    {
        $query = sprintf("'%s' in parents and trashed=false", $parentId);

        if ($mimeType !== null) {
            $query .= " and mimeType='{$mimeType}'";
        }

        return $query;
    }

    /**
     * @param \Throwable $e
     * @return bool
     */
    protected function isDriveNotFoundError(\Throwable $e)
    {
        if ((int) $e->getCode() === 404) {
            return true;
        }

        $message = strtolower($e->getMessage());

        return strpos($message, 'not found') !== false || strpos($message, 'file not found') !== false;
    }
}
