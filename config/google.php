<?php
return [
    /*
|----------------------------------------------------------------------------
| Google application name
|----------------------------------------------------------------------------
*/
    'application_name' => env('GOOGLE_APPLICATION_NAME', ''),
    /*
|----------------------------------------------------------------------------
| Google OAuth 2.0 access
|----------------------------------------------------------------------------
|
| Keys for OAuth 2.0 access, see the API console at
| https://developers.google.com/console
|
*/
    'client_id'        => env('GOOGLE_CLIENT_ID', ''),
    'client_secret'    => env('GOOGLE_CLIENT_SECRET', ''),
    'redirect_uri'     => env('GOOGLE_REDIRECT', ''),
    'scopes'           => [\Google_Service_Sheets::DRIVE, \Google_Service_Sheets::SPREADSHEETS],
    //    'scopes'           => [\Google_Service_Sheets::DRIVE_READONLY, \Google_Service_Sheets::SPREADSHEETS_READONLY],
    'access_type'      => 'offline',
    'approval_prompt'  => 'force',
    'prompt'           => 'consent', //"none", "consent", "select_account" default:none
    /*
|----------------------------------------------------------------------------
| Google developer key
|----------------------------------------------------------------------------
|
| Simple API access key, also from the API console. Ensure you get
| a Server key, and not a Browser key.
|
*/
    'developer_key'    => env('GOOGLE_DEVELOPER_KEY', ''),
    /*
|----------------------------------------------------------------------------
| Google service account
|----------------------------------------------------------------------------
|
| Set the credentials JSON's location to use assert credentials, otherwise
| app engine or compute engine will be used.
|
*/
    'service'          => [
        /*
| Enable service account auth or not.
*/
        'enable' => env('GOOGLE_SERVICE_ENABLED', false),
        /*
| Path to service account json file
*/
        'file' => base_path(env('GOOGLE_SERVICE_ACCOUNT_JSON_LOCATION', 'storage/credentials.json')),
    ],
    /*
|----------------------------------------------------------------------------
| Additional config for the Google Client
|----------------------------------------------------------------------------
|
| Set any additional config variables supported by the Google Client
| Details can be found here:
| https://github.com/google/google-api-php-client/blob/master/src/Google/Client.php
|
| NOTE: If client id is specified here, it will get over written by the one above.
|
*/
    'config'           => [],
    'post_spreadsheet_id' => env('POST_SPREADSHEET_ID'),
    'post_sheet_id'       => env('POST_SHEET_ID'),
    'post_sheet_status_doc_id' => env('POST_SHEET_STATUS_DOC_ID'),

    /*
    | Excel confirmación en Drive: oauth (Gmail / My Drive) | service_account (Workspace) | auto
    */
    'drive_excel_auth_mode' => env('GOOGLE_DRIVE_EXCEL_AUTH_MODE', 'oauth'),

    /*
    | OAuth de usuario para subir Excel (cuenta dueña de la carpeta raíz).
    | Ver docs/GOOGLE_DRIVE_EXCEL_OAUTH.md
    */
    'drive_oauth' => [
        'enabled' => env('GOOGLE_DRIVE_OAUTH_ENABLED', true),
        'client_id' => env('GOOGLE_CLIENT_ID', ''),
        'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),
        'redirect_uri' => env(
            'GOOGLE_DRIVE_OAUTH_REDIRECT_URI',
            rtrim((string) env('APP_URL', 'http://localhost:8001'), '/') . '/api/google/drive/oauth/callback'
        ),
        'token_file' => env('GOOGLE_DRIVE_OAUTH_TOKEN_FILE', 'google-drive-oauth-token.json'),
        'refresh_token' => env('GOOGLE_DRIVE_OAUTH_REFRESH_TOKEN', ''),
    ],

    /*
    | Carpeta raíz en Drive para Excel de confirmación.
    | Estructura: {root}/Consolidado-{carga}/{cliente}/{codigo_proveedor}/excel_confirmacion_*.xlsx
    */
    'drive_excel_confirmacion_root_folder_id' => env('GOOGLE_DRIVE_EXCEL_CONFIRMACION_ROOT_FOLDER_ID', ''),

    /** ID del Shared drive (unidad compartida). Opcional; solo con service_account en Workspace. */
    'drive_excel_confirmacion_shared_drive_id' => env('GOOGLE_DRIVE_EXCEL_CONFIRMACION_SHARED_DRIVE_ID', ''),

    /*
    | Carpeta raíz en Drive para Excel de seguimiento consolidado (cotizaciones).
    | Estructura: {root}/{numero_consolidado}/cotizaciones_#{carga}_{fecha}.xlsx
    */
    'drive_excel_seguimiento_consolidado_root_folder_id' => env('EXCEL_SEGUIMIENTO_CONSOLIDADO_ID', ''),
];
