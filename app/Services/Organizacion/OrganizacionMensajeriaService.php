<?php

namespace App\Services\Organizacion;

use App\Models\CargaConsolidada\Contenedor;
use App\Models\CargaConsolidada\ConsolidadoDeliveryFormLima;
use App\Models\CargaConsolidada\ConsolidadoDeliveryFormProvince;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\Organizacion;
use App\Models\OrganizacionMensajeria;
use App\Models\OrganizacionPortal;
use App\Models\Pais;
use App\Models\PaisFlag;
use App\Traits\FileTrait;
use App\Traits\UsesObjectStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Flags de WhatsApp / rotulado por organización y assets (fotos paso 1-2 y dirección).
 *
 * Mapa de envíos del rotulado (API redis o Meta):
 * - welcomeV2 / pb_welcome_rotulado_v1 — bienvenida
 * - messageV2 — texto (nuevo proveedor)
 * - mediaV2 — fotos paso 1 y 2 (antes fijas en welcomeV2)
 * - mediaV2 / pb_rotulado_pdf_producto_v1 — PDF paso 3 (etiqueta consolidado)
 * - mediaV2 / pb_rotulado_almacen_china_img_v1 — imagen de dirección del almacén
 * - PDFs extra por tipo (calzado, ropa, etc.)
 */
class OrganizacionMensajeriaService
{
    use FileTrait;
    use UsesObjectStorage;

    const ID_ORGANIZACION_ADMIN = 1;

    const IMG_PASO1 = 'paso1';
    const IMG_PASO2 = 'paso2';
    const IMG_DIRECCION = 'direccion';

    /**
     * @param  int  $organizacionId
     * @return OrganizacionMensajeria
     */
    public function firstOrCreateFor($organizacionId)
    {
        $organizacionId = (int) $organizacionId;
        $row = OrganizacionMensajeria::query()->where('organizacion_id', $organizacionId)->first();
        if ($row) {
            return $row;
        }

        $esAdmin = $organizacionId === self::ID_ORGANIZACION_ADMIN;
        $payload = [
            'organizacion_id' => $organizacionId,
            'envios_habilitados' => $esAdmin,
            'rotulado_habilitado' => $esAdmin,
        ];
        if (Schema::hasColumn('organizacion_mensajeria', 'flujos')) {
            $payload['flujos'] = json_encode($this->flujosDefault($organizacionId));
        }

        return OrganizacionMensajeria::query()->create($payload);
    }

    /**
     * @return array<int, array{key: string, grupo: string, label: string}>
     */
    public function catalogoFlujos()
    {
        return [
            ['key' => 'rotulado', 'grupo' => 'Coordinación', 'label' => 'Rotulado'],
            ['key' => 'documentos', 'grupo' => 'Coordinación', 'label' => 'Documentos de aduana'],
            ['key' => 'datos_proveedor', 'grupo' => 'Coordinación', 'label' => 'Datos de proveedor'],
            ['key' => 'cbm_alerta', 'grupo' => 'Coordinación', 'label' => 'Alerta diferencia CBM'],
            ['key' => 'arrive_date', 'grupo' => 'Coordinación', 'label' => 'Aviso de arrive date'],
            ['key' => 'cambio_consolidado', 'grupo' => 'Coordinación', 'label' => 'Cambio de consolidado'],
            ['key' => 'inspeccion', 'grupo' => 'Almacén', 'label' => 'Inspección'],
            ['key' => 'entrega', 'grupo' => 'Entrega', 'label' => 'Formulario y cargo de entrega'],
            ['key' => 'cobranza', 'grupo' => 'Finanzas', 'label' => 'Cotización final / cobrando'],
            ['key' => 'reminder_pago', 'grupo' => 'Finanzas', 'label' => 'Recordatorio de pago'],
            ['key' => 'factura_guia', 'grupo' => 'Finanzas', 'label' => 'Factura y guía'],
            ['key' => 'contabilidad', 'grupo' => 'Finanzas', 'label' => 'Comprobantes y detracción'],
            ['key' => 'comprobante_form', 'grupo' => 'Finanzas', 'label' => 'Formulario de comprobante'],
            ['key' => 'calculadora', 'grupo' => 'Comercial', 'label' => 'Cotización calculadora'],
            ['key' => 'cotizacion_pdf', 'grupo' => 'Comercial', 'label' => 'PDF / contrato (ventas)'],
        ];
    }

    /**
     * @param  int  $organizacionId
     * @return array<string, bool>
     */
    public function flujosDefault($organizacionId)
    {
        $on = (int) $organizacionId === self::ID_ORGANIZACION_ADMIN;
        $out = array();
        foreach ($this->catalogoFlujos() as $item) {
            $out[$item['key']] = $on;
        }

        return $out;
    }

    /**
     * @param  mixed  $row
     * @return array<string, bool>
     */
    public function decodeFlujos($row)
    {
        $orgId = $row ? (int) $row->organizacion_id : self::ID_ORGANIZACION_ADMIN;
        $defaults = $this->flujosDefault($orgId);
        $decoded = array();
        $raw = $row ? $row->getAttribute('flujos') : null;
        if (is_array($raw)) {
            $decoded = $raw;
        } elseif (is_string($raw) && $raw !== '') {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                $decoded = $json;
            }
        }

        if ($decoded === array() && $row) {
            $legacyOn = (bool) $row->envios_habilitados;
            foreach ($defaults as $key => $unused) {
                $defaults[$key] = $legacyOn;
            }
            if (isset($row->rotulado_habilitado)) {
                $defaults['rotulado'] = (bool) $row->rotulado_habilitado;
            }

            return $defaults;
        }

        foreach ($defaults as $key => $fallback) {
            if (array_key_exists($key, $decoded)) {
                $defaults[$key] = $this->toBool($decoded[$key]);
            }
        }

        return $defaults;
    }

    /**
     * @param  int  $organizacionId
     * @param  string  $flujo
     * @return bool
     */
    public function flujoHabilitado($organizacionId, $flujo)
    {
        $organizacionId = (int) $organizacionId;
        $flujo = trim((string) $flujo);
        $esAdmin = $organizacionId === self::ID_ORGANIZACION_ADMIN;
        if ($flujo === '') {
            return $esAdmin;
        }
        if (!Schema::hasTable('organizacion_mensajeria')) {
            return $esAdmin;
        }

        $row = $this->firstOrCreateFor($organizacionId);
        $flujos = $this->decodeFlujos($row);

        if (!array_key_exists($flujo, $flujos)) {
            return true;
        }

        return (bool) $flujos[$flujo];
    }

    /**
     * @param  int  $organizacionId
     * @return bool
     */
    public function enviosHabilitados($organizacionId)
    {
        if (!Schema::hasTable('organizacion_mensajeria')) {
            return (int) $organizacionId === self::ID_ORGANIZACION_ADMIN;
        }

        $row = $this->firstOrCreateFor($organizacionId);
        $flujos = $this->decodeFlujos($row);

        return in_array(true, $flujos, true);
    }

    /**
     * Resuelve la org dueña del envío (cotización / contenedor / form), no la del usuario admin.
     *
     * @param  array<string, mixed>  $hints
     * @return int
     */
    public function resolverOrganizacionId(array $hints)
    {
        if (!empty($hints['organizacion_id']) && (int) $hints['organizacion_id'] > 0) {
            return (int) $hints['organizacion_id'];
        }

        $idCotizacion = $this->hintInt($hints, array('id_cotizacion', 'idCotizacion'));
        if ($idCotizacion > 0) {
            $org = Cotizacion::query()->where('id', $idCotizacion)->value('organizacion_id');
            if ($org) {
                return (int) $org;
            }
        }

        $idContenedor = $this->hintInt($hints, array('id_contenedor', 'idContenedor', 'idContainer', 'id_container'));
        if ($idContenedor > 0) {
            $org = Contenedor::query()->where('id', $idContenedor)->value('organizacion_id');
            if ($org) {
                return (int) $org;
            }
        }

        if (!empty($hints['phone'])) {
            $fromPhone = $this->organizacionIdDesdeTelefono($hints['phone']);
            if ($fromPhone > 0) {
                return $fromPhone;
            }
        }

        $idDelivery = $this->hintInt($hints, array('delivery_form_id', 'deliveryFormId'));
        if ($idDelivery > 0) {
            $fromForm = $this->organizacionIdDesdeDeliveryForm($idDelivery);
            if ($fromForm > 0) {
                return $fromForm;
            }
        }

        if (!empty($hints['auth_organizacion_id']) && (int) $hints['auth_organizacion_id'] > 0) {
            return (int) $hints['auth_organizacion_id'];
        }

        return self::ID_ORGANIZACION_ADMIN;
    }

    /**
     * @param  int  $formId
     * @return int
     */
    public function organizacionIdDesdeDeliveryForm($formId)
    {
        $formId = (int) $formId;
        $lima = ConsolidadoDeliveryFormLima::query()->find($formId);
        if ($lima) {
            return $this->orgDesdeFila($lima);
        }
        $provincia = ConsolidadoDeliveryFormProvince::query()->find($formId);
        if ($provincia) {
            return $this->orgDesdeFila($provincia);
        }

        return 0;
    }

    /**
     * @param  mixed  $fila
     * @return int
     */
    private function orgDesdeFila($fila)
    {
        $org = (int) $fila->getAttribute('organizacion_id');
        if ($org > 0) {
            return $org;
        }
        $idCot = (int) $fila->getAttribute('id_cotizacion');
        if ($idCot > 0) {
            $org = (int) Cotizacion::query()->where('id', $idCot)->value('organizacion_id');
            if ($org > 0) {
                return $org;
            }
        }
        $idCont = (int) $fila->getAttribute('id_contenedor');
        if ($idCont > 0) {
            return (int) Contenedor::query()->where('id', $idCont)->value('organizacion_id');
        }

        return 0;
    }

    /**
     * @param  string  $phone
     * @return int
     */
    private function organizacionIdDesdeTelefono($phone)
    {
        $digits = preg_replace('/[^0-9]/', '', (string) $phone);
        if (strlen($digits) < 8) {
            return 0;
        }
        $tail = substr($digits, -9);

        $org = Cotizacion::query()
            ->whereNotNull('organizacion_id')
            ->where('telefono', 'like', '%' . $tail . '%')
            ->orderByDesc('id')
            ->value('organizacion_id');

        return $org ? (int) $org : 0;
    }

    /**
     * @param  array<string, mixed>  $hints
     * @param  array<int, string>  $keys
     * @return int
     */
    private function hintInt(array $hints, array $keys)
    {
        foreach ($keys as $key) {
            if (!empty($hints[$key]) && (int) $hints[$key] > 0) {
                return (int) $hints[$key];
            }
        }

        return 0;
    }

    /**
     * @param  int  $organizacionId
     * @return bool
     */
    public function rotuladoHabilitado($organizacionId)
    {
        return $this->flujoHabilitado($organizacionId, 'rotulado');
    }

    /**
     * @param  int  $organizacionId
     * @param  array<string, mixed>  $input
     * @return OrganizacionMensajeria
     */
    public function syncFlags($organizacionId, array $input)
    {
        $row = $this->firstOrCreateFor($organizacionId);
        $flujos = $this->decodeFlujos($row);
        if (isset($input['flujos']) && is_array($input['flujos'])) {
            foreach ($this->catalogoFlujos() as $item) {
                $key = $item['key'];
                if (array_key_exists($key, $input['flujos'])) {
                    $flujos[$key] = $this->toBool($input['flujos'][$key]);
                }
            }
        }
        if (array_key_exists('rotulado_habilitado', $input) && (!isset($input['flujos']) || !is_array($input['flujos']) || !array_key_exists('rotulado', $input['flujos']))) {
            $flujos['rotulado'] = $this->toBool($input['rotulado_habilitado']);
        }
        $anyOn = in_array(true, $flujos, true);
        $row->envios_habilitados = $anyOn;
        $row->rotulado_habilitado = !empty($flujos['rotulado']);
        if (Schema::hasColumn('organizacion_mensajeria', 'flujos')) {
            $row->flujos = json_encode($flujos);
        }
        $row->save();

        return $row;
    }

    /**
     * @param  int  $organizacionId
     * @param  string  $slot  paso1|paso2|direccion
     * @param  UploadedFile  $file
     * @return OrganizacionMensajeria
     */
    public function storeImagen($organizacionId, $slot, UploadedFile $file)
    {
        $column = $this->columnForSlot($slot);
        if ($column === null) {
            throw new \InvalidArgumentException('Slot de imagen inválido');
        }

        $row = $this->firstOrCreateFor($organizacionId);
        $ext = strtolower((string) $file->getClientOriginalExtension());
        if ($ext === '') {
            $ext = 'jpg';
        }
        $filename = $slot . '_' . time() . '_' . uniqid() . '.' . $ext;
        $relative = $this->storageStoreUpload($file, 'organizacion/' . (int) $organizacionId . '/rotulado', $filename);
        $stored = $this->storageFinalizeCdnPath($relative);

        $row->setAttribute($column, $stored);
        $row->save();

        return $row;
    }

    /**
     * @param  int  $organizacionId
     * @param  string  $slot
     * @return string|null Ruta local para sendMedia
     */
    public function localPathImagen($organizacionId, $slot)
    {
        $column = $this->columnForSlot($slot);
        if ($column === null) {
            return null;
        }
        $row = OrganizacionMensajeria::query()->where('organizacion_id', (int) $organizacionId)->first();
        if (!$row) {
            return null;
        }
        $dbPath = $row->getAttribute($column);
        if (!$dbPath) {
            return null;
        }
        $uploadPath = $this->storageUploadPathFromDb($dbPath);
        if ($uploadPath === null || $uploadPath === '') {
            return null;
        }
        try {
            $local = $this->storageLocalPath($uploadPath);
            if ($local !== '' && is_file($local)) {
                return $local;
            }
        } catch (\Exception $e) {
            Log::warning('OrganizacionMensajeriaService: no se pudo materializar imagen', [
                'org' => $organizacionId,
                'slot' => $slot,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * @param  int  $organizacionId
     * @return array<string, mixed>
     */
    public function serializar($organizacionId)
    {
        $catalogo = $this->catalogoFlujos();
        $total = count($catalogo);
        if (!Schema::hasTable('organizacion_mensajeria')) {
            $esAdmin = (int) $organizacionId === self::ID_ORGANIZACION_ADMIN;
            $flujos = $this->flujosDefault($organizacionId);

            return [
                'envios_habilitados' => $esAdmin,
                'rotulado_habilitado' => $esAdmin,
                'flujos' => $flujos,
                'flujos_catalogo' => $catalogo,
                'flujos_activos' => $esAdmin ? $total : 0,
                'flujos_total' => $total,
                'img_rotulado_paso1_url' => null,
                'img_rotulado_paso2_url' => null,
                'img_rotulado_direccion_url' => null,
            ];
        }

        $row = $this->firstOrCreateFor($organizacionId);
        $flujos = $this->decodeFlujos($row);
        $activos = count(array_filter($flujos));

        return [
            'envios_habilitados' => $activos > 0,
            'rotulado_habilitado' => !empty($flujos['rotulado']),
            'flujos' => $flujos,
            'flujos_catalogo' => $catalogo,
            'flujos_activos' => $activos,
            'flujos_total' => $total,
            'img_rotulado_paso1_url' => $this->cdnStorageUrl($row->img_rotulado_paso1),
            'img_rotulado_paso2_url' => $this->cdnStorageUrl($row->img_rotulado_paso2),
            'img_rotulado_direccion_url' => $this->cdnStorageUrl($row->img_rotulado_direccion),
        ];
    }

    /**
     * Nombre comercial para el PDF de rotulado.
     *
     * @param  int  $organizacionId
     * @return string
     */
    public function companyRotulado($organizacionId)
    {
        $organizacionId = (int) $organizacionId;
        if ($organizacionId === self::ID_ORGANIZACION_ADMIN || $organizacionId <= 0) {
            return 'PRO MUNDO COMEX S.A.C';
        }

        $portal = OrganizacionPortal::query()->where('organizacion_id', $organizacionId)->first();
        $nombrePublico = $portal ? trim((string) $portal->nombre_publico) : '';
        if ($nombrePublico !== '') {
            return $nombrePublico;
        }

        $org = Organizacion::query()->find($organizacionId);

        return $org ? (string) $org->getAttribute('No_Organizacion') : '';
    }

    /**
     * País de destino del consolidado (id_pais del contenedor).
     *
     * @param  mixed  $contenedor
     * @return array{nombre: string, iso2: string, id_pais: int}
     */
    public function paisDesdeContenedor($contenedor)
    {
        $vacio = ['nombre' => '', 'iso2' => '', 'id_pais' => 0];
        if (!$contenedor) {
            return $vacio;
        }
        $idPais = (int) $contenedor->getAttribute('id_pais');
        if ($idPais <= 0) {
            return $vacio;
        }

        $nombre = '';
        $iso2 = '';
        $flag = PaisFlag::query()->where('id_pais', $idPais)->first();
        if ($flag) {
            $nombre = trim((string) $flag->getAttribute('nombre'));
            $iso2 = strtolower(trim((string) $flag->getAttribute('iso2')));
        }
        if ($nombre === '') {
            $pais = Pais::find($idPais);
            $nombre = $pais ? trim((string) $pais->getAttribute('No_Pais')) : '';
        }
        if ($iso2 === '' && $nombre !== '') {
            $iso2 = (string) (PaisFlag::isoDesdeNombre($nombre) ?: '');
        }

        return [
            'nombre' => $nombre,
            'iso2' => $iso2,
            'id_pais' => $idPais,
        ];
    }

    /**
     * @param  mixed  $value
     * @return bool
     */
    private function toBool($value)
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array($value, [1, '1', 'true', 'on', 'yes'], true);
    }

    /**
     * @param  string  $slot
     * @return string|null
     */
    private function columnForSlot($slot)
    {
        $map = [
            self::IMG_PASO1 => 'img_rotulado_paso1',
            self::IMG_PASO2 => 'img_rotulado_paso2',
            self::IMG_DIRECCION => 'img_rotulado_direccion',
        ];

        return isset($map[$slot]) ? $map[$slot] : null;
    }
}
