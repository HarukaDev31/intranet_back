<?php

namespace App\Services\PagoProveedor;

use App\Exceptions\PagoProveedorException;
use App\Models\PagoProveedor\PagoProveedorAbono;
use App\Models\PagoProveedor\PagoProveedorAbonoAplicacion;
use App\Models\PagoProveedor\PagoProveedorConfiguracion;
use App\Models\PagoProveedor\PagoProveedorDatoProveedor;
use App\Models\PagoProveedor\PagoProveedorMensaje;
use App\Models\PagoProveedor\PagoProveedorSolicitud;
use App\Models\PagoProveedor\PagoProveedorSolicitudProveedor;
use App\Traits\FileTrait;
use App\Traits\UsesObjectStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PagoProveedorEtapa1Service
{
    use FileTrait;
    use UsesObjectStorage;

    public function __construct(
        private readonly PagoProveedorHistorialService $historial,
        private readonly PagoProveedorAbonoEvaluador $evaluador,
        private readonly PagoProveedorCodigoSolicitud $codigos,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function detalle(int $solicitudId): array
    {
        $solicitud = $this->solicitud($solicitudId);

        return $this->presentar($solicitud);
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     * @return array<string, mixed>
     */
    public function guardarRmb(int $solicitudId, int $proveedorId, string $vendedor, string $wechat, array $actor): array
    {
        return DB::transaction(function () use ($solicitudId, $proveedorId, $vendedor, $wechat, $actor) {
            $proveedor = $this->proveedorEtapa1($solicitudId, $proveedorId);
            if ($proveedor->moneda !== 'RMB') {
                throw new PagoProveedorException('El vendedor y el WeChat son solo para pagos en yuanes.');
            }
            $datos = $this->datosDe($proveedor);
            $datos->vendedor_nombre = trim($vendedor);
            $datos->wechat = PagoProveedorDatosReglas::wechat($wechat);
            if ($datos->vendedor_nombre === '') {
                throw new PagoProveedorException('Falta el nombre del vendedor.');
            }
            $datos->estado = 'recibido';
            $datos->origen = 'ventas';
            $datos->save();
            $this->historial->registrar('solicitud', $solicitudId, 'Datos del proveedor', $actor, $proveedor->code_supplier);
            $this->intentarAvanzar($this->solicitudDe($proveedor), $actor);

            return $this->presentar($this->recargar($this->solicitudDe($proveedor)));
        });
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     * @return array<string, mixed>
     */
    public function enviarFormulario(int $solicitudId, int $proveedorId, array $actor): array
    {
        return DB::transaction(function () use ($solicitudId, $proveedorId, $actor) {
            $proveedor = $this->proveedorEtapa1($solicitudId, $proveedorId);
            $this->exigirTt($proveedor);
            $datos = $this->datosDe($proveedor);
            if ($datos->estado === 'recibido' && !$datos->correccion_solicitada) {
                throw new PagoProveedorException('Los datos ya fueron autorizados. Para corregirlos, solicita una nueva validación.');
            }
            if ($datos->estado === 'pendiente') {
                $datos->estado = 'enviado';
            }
            $datos->save();
            $enlace = $this->enlace($datos);
            $texto = $this->textoFormulario($proveedor, $enlace, $datos->estado === 'por_autorizar');
            $this->guardarMensaje($proveedor, 'pedir_datos', $texto, $actor);
            $this->historial->registrar('solicitud', $solicitudId, 'Formulario T-T enviado', $actor, $proveedor->code_supplier);

            return [
                'enlace' => $enlace,
                'texto' => $texto,
                'nota' => 'Quedó la vista previa. El envío real por WhatsApp se habilita con la plantilla de Meta.',
                'solicitud' => $this->presentar($this->recargar($this->solicitudDe($proveedor))),
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     * @return array<string, mixed>
     */
    public function llenarPorVentas(int $solicitudId, int $proveedorId, array $payload, array $actor): array
    {
        return DB::transaction(function () use ($solicitudId, $proveedorId, $payload, $actor) {
            $proveedor = $this->proveedorEtapa1($solicitudId, $proveedorId);
            $this->exigirTt($proveedor);
            $datos = $this->datosDe($proveedor);
            if ($datos->estado === 'recibido') {
                throw new PagoProveedorException('Los datos ya están autorizados. Solicita una validación para corregirlos.');
            }
            $this->aplicarDatosBancarios($datos, $payload);
            $datos->estado = 'por_autorizar';
            $datos->origen = 'ventas';
            $datos->autorizado_at = null;
            $datos->save();
            $this->historial->registrar('solicitud', $solicitudId, 'T-T llenado por Ventas', $actor, 'Quedó por autorizar. ' . $proveedor->code_supplier);

            return $this->presentar($this->recargar($this->solicitudDe($proveedor)));
        });
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     * @return array<string, mixed>
     */
    public function solicitarValidacion(int $solicitudId, int $proveedorId, array $actor): array
    {
        return DB::transaction(function () use ($solicitudId, $proveedorId, $actor) {
            $proveedor = $this->proveedorEtapa1($solicitudId, $proveedorId);
            $datos = $this->datosDe($proveedor);
            if ($datos->estado !== 'recibido') {
                throw new PagoProveedorException('Solo se pide una nueva validación cuando el cliente ya autorizó los datos.');
            }
            $datos->correccion_solicitada = true;
            $datos->save();
            $enlace = $this->enlace($datos);
            $texto = "Hola, " . ($this->solicitudDe($proveedor)->cliente_nombre ?: 'cliente') . " 🙋🏻‍♀️\n|\nNecesitamos que revises los datos bancarios de " . $proveedor->code_supplier . ".\n" . $enlace;
            $this->guardarMensaje($proveedor, 'validacion_tt', $texto, $actor);
            $this->historial->registrar('solicitud', $solicitudId, 'Validación de datos T-T', $actor, $proveedor->code_supplier);

            return [
                'enlace' => $enlace,
                'texto' => $texto,
                'solicitud' => $this->presentar($this->recargar($this->solicitudDe($proveedor))),
            ];
        });
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     * @return array<string, mixed>
     */
    public function anularProveedor(int $solicitudId, int $proveedorId, array $actor): array
    {
        return DB::transaction(function () use ($solicitudId, $proveedorId, $actor) {
            $proveedor = $this->proveedorEtapa1($solicitudId, $proveedorId);
            $proveedor->estado = 'anulado';
            $proveedor->save();
            $this->historial->registrar('solicitud', $solicitudId, 'Proveedor anulado', $actor, $proveedor->code_supplier);

            return $this->presentar($this->recargar($this->solicitudDe($proveedor)));
        });
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     * @return array<string, mixed>
     */
    public function vistaPreviaPedirDatos(int $solicitudId, array $actor): array
    {
        return DB::transaction(function () use ($solicitudId, $actor) {
            $solicitud = $this->solicitud($solicitudId, true);
            $bloques = [];
            $primero = null;
            foreach ($solicitud->proveedores as $proveedor) {
                if ($proveedor->estado === 'anulado' || (int) $proveedor->etapa !== 1) {
                    continue;
                }
                $primero ??= $proveedor;
                if ($proveedor->moneda === 'RMB') {
                    $datos = $proveedor->datos;
                    if ($datos && trim((string) $datos->vendedor_nombre) !== '' && trim((string) $datos->wechat) !== '') {
                        continue;
                    }
                    $bloques[] = "✅ Proveedor: {$proveedor->code_supplier} | {$proveedor->producto}\n📝 Datos: nombre del vendedor y N.° de WeChat";
                    continue;
                }
                $datos = $this->datosDe($proveedor);
                if ($datos->estado === 'recibido' && !$datos->correccion_solicitada) {
                    continue;
                }
                if ($datos->estado === 'pendiente') {
                    $datos->estado = 'enviado';
                    $datos->save();
                }
                $bloques[] = "✅ Proveedor: {$proveedor->code_supplier} | {$proveedor->producto}\n📝 Datos: {$this->enlace($datos)}";
            }
            if ($bloques === [] || !$primero) {
                throw new PagoProveedorException('Los proveedores de esta solicitud ya tienen los datos.');
            }
            $nombre = $solicitud->cliente_nombre ?: 'cliente';
            $texto = "Hola, {$nombre} 🙋🏻‍♀️\n|\n" . implode("\n", $bloques) . "\nCon estos datos continuamos con el proceso.";
            $this->guardarMensaje($primero, 'pedir_datos', $texto, $actor);
            $this->historial->registrar('solicitud', $solicitudId, 'Pedido de datos', $actor, null);

            return [
                'texto' => $texto,
                'nota' => 'Quedó la vista previa. El envío real por WhatsApp se habilita con la plantilla de Meta.',
                'solicitud' => $this->presentar($this->recargar($solicitud)),
            ];
        });
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     * @return array<string, mixed>
     */
    public function vistaPreviaCuentas(int $solicitudId, array $actor): array
    {
        return DB::transaction(function () use ($solicitudId, $actor) {
            $solicitud = $this->solicitud($solicitudId);
            $lineas = [];
            $total = 0.0;
            $primero = null;
            foreach ($solicitud->proveedores as $proveedor) {
                if ($proveedor->estado === 'anulado') {
                    continue;
                }
                $primero ??= $proveedor;
                $saldo = $this->saldo($proveedor);
                if ($saldo <= 0) {
                    continue;
                }
                $lineas[] = $proveedor->code_supplier . ' | ' . $proveedor->producto;
                $total += $saldo;
            }
            if ($lineas === [] || !$primero) {
                throw new PagoProveedorException('Ya no hay saldo por depositar.');
            }
            $nombre = $solicitud->cliente_nombre ?: 'cliente';
            $texto = "Hola, {$nombre} 🙋🏻‍♀️\n|\n👆🏻\nConcepto: Pago a proveedor\n"
                . implode("\n", $lineas)
                . "\nTotal a depositar: $" . number_format($total, 2, '.', ',');
            $this->guardarMensaje($primero, 'cuentas', $texto, $actor);

            return [
                'texto' => $texto,
                'nota' => 'La imagen oficial de las cuentas va antes de este texto. Los números de cuenta no se escriben en el mensaje.',
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     * @return array<string, mixed>
     */
    public function registrarAbono(int $solicitudId, array $payload, ?UploadedFile $voucher, array $actor, ?int $abonoId = null): array
    {
        return DB::transaction(function () use ($solicitudId, $payload, $voucher, $actor, $abonoId) {
            $solicitud = $this->solicitud($solicitudId, true);
            $abono = null;
            if ($abonoId) {
                $abono = $this->abonoEditable($solicitud, $abonoId);
            }

            $saldos = [];
            foreach ($solicitud->proveedores as $proveedor) {
                if ($proveedor->estado === 'anulado' || (int) $proveedor->etapa !== 1) {
                    continue;
                }
                $saldos[$proveedor->id] = $this->saldo($proveedor, $abono?->id);
            }

            $aplicaciones = $this->mapaAplicaciones($payload['aplicaciones'] ?? []);
            $evaluacion = $this->evaluador->evaluar((float) $payload['importe'], $aplicaciones, $saldos);
            $hayCuota2 = $solicitud->proveedores->contains(fn ($proveedor) => $proveedor->modalidad_abono === 'fraccionado');
            $repetida = PagoProveedorAbono::query()
                ->where('numero_operacion', $payload['numero_operacion'])
                ->when($abono, fn ($q) => $q->where('id', '!=', $abono->id))
                ->where('estado', '!=', 'anulado')
                ->exists();

            if ($voucher) {
                $ruta = $this->storageFinalizeCdnPath($this->storageStoreUpload(
                    $voucher,
                    'pago-proveedor/vouchers',
                    'voucher-' . $solicitudId . '-' . now()->format('YmdHis') . '.' . strtolower($voucher->getClientOriginalExtension() ?: 'pdf')
                ));
                $nombreVoucher = $voucher->getClientOriginalName();
            } elseif ($abono) {
                $ruta = $abono->voucher_path;
                $nombreVoucher = $abono->voucher_nombre;
            } else {
                throw new PagoProveedorException('Falta el comprobante.');
            }

            $datosAbono = [
                'importe' => round((float) $payload['importe'], 2),
                'fecha_pago' => $payload['fecha_pago'],
                'numero_operacion' => trim((string) $payload['numero_operacion']),
                'banco' => $payload['banco'],
                'interbancaria' => (bool) ($payload['interbancaria'] ?? false),
                'voucher_path' => $ruta,
                'voucher_nombre' => $nombreVoucher,
                'estado' => 'en_validacion',
                'excedente' => $evaluacion['excedente'],
                'excedente_decision' => $this->evaluador->decisionExcedente($evaluacion['excedente'], $hayCuota2),
                'operacion_repetida' => $repetida,
                'usuario_id' => $actor['id'],
            ];

            if ($abono) {
                $abono->fill($datosAbono);
                $abono->save();
                $abono->aplicaciones()->delete();
            } else {
                $abono = $solicitud->abonos()->create($datosAbono);
            }

            foreach ($evaluacion['aplicaciones'] as $proveedorId => $monto) {
                $abono->aplicaciones()->create([
                    'solicitud_proveedor_id' => $proveedorId,
                    'monto' => $monto,
                ]);
            }

            $this->historial->registrar('solicitud', $solicitudId, $abonoId ? 'Abono editado' : 'Abono registrado', $actor, $abono->numero_operacion);
            $this->intentarAvanzar($this->recargar($solicitud, ['proveedores.datos', 'proveedores.aplicaciones.abono']), $actor);

            $presentado = $this->presentar($this->recargar($solicitud));

            return [
                'solicitud' => $presentado,
                'aviso_operacion_repetida' => $repetida
                    ? 'Ese número de operación ya está registrado. Puedes continuar.'
                    : null,
            ];
        });
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     * @return array<string, mixed>
     */
    public function anularAbono(int $solicitudId, int $abonoId, array $actor): array
    {
        return DB::transaction(function () use ($solicitudId, $abonoId, $actor) {
            $solicitud = $this->solicitud($solicitudId, true);
            $abono = $this->abonoEditable($solicitud, $abonoId);
            $abono->estado = 'anulado';
            $abono->save();
            $this->historial->registrar('solicitud', $solicitudId, 'Abono anulado', $actor, $abono->numero_operacion);

            return $this->presentar($this->recargar($solicitud));
        });
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     * @return array<string, mixed>
     */
    public function decidirExcedente(int $solicitudId, int $abonoId, string $decision, array $actor): array
    {
        return DB::transaction(function () use ($solicitudId, $abonoId, $decision, $actor) {
            $solicitud = $this->solicitud($solicitudId);
            $abono = $solicitud->abonos()->where('id', $abonoId)->first();
            if (!$abono || $abono->estado === 'anulado') {
                throw new PagoProveedorException('El abono no existe.', 404);
            }
            if ($abono->excedente_decision !== 'por_consultar') {
                throw new PagoProveedorException('Este excedente ya tiene una decisión.');
            }
            $hayCuota2 = $solicitud->proveedores->contains(fn ($proveedor) => $proveedor->modalidad_abono === 'fraccionado');
            $permitidas = $hayCuota2 ? ['aplicado_cuota', 'reembolso_solicitado'] : ['reembolso_solicitado'];
            if (!in_array($decision, $permitidas, true)) {
                throw new PagoProveedorException('Esa decisión no aplica a este excedente.');
            }
            $abono->excedente_decision = $decision;
            $abono->save();
            $this->historial->registrar('solicitud', $solicitudId, 'Excedente', $actor, $decision);

            return $this->presentar($this->recargar($solicitud));
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function verFormularioPublico(string $token): array
    {
        $datos = $this->datosPorToken($token);
        $proveedor = $datos->proveedor;

        return [
            'proveedor' => $proveedor->code_supplier,
            'nombre_proveedor' => $proveedor->nombre_proveedor,
            'producto' => $proveedor->producto,
            'proforma_nombre' => $proveedor->proforma_nombre,
            'proforma_url' => $proveedor->proforma_path ? $this->cdnStorageUrl($proveedor->proforma_path) : null,
            'estado' => $datos->estado,
            'correccion_solicitada' => $datos->correccion_solicitada,
            'bloqueado' => $datos->estado === 'recibido' && !$datos->correccion_solicitada,
            'puede_editar' => $datos->correccion_solicitada || $datos->estado !== 'recibido',
            'datos' => $this->datosPublicos($datos),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function guardarFormularioPublico(string $token, array $payload): array
    {
        return DB::transaction(function () use ($token, $payload) {
            $datos = $this->datosPorToken($token);
            if ($datos->estado === 'recibido' && !$datos->correccion_solicitada) {
                throw new PagoProveedorException('Estos datos ya fueron autorizados.');
            }
            if (empty($payload['autorizo'])) {
                throw new PagoProveedorException('Falta la autorización para usar estos datos en el pago.');
            }
            $proveedor = $datos->proveedor;
            if ((int) $proveedor->etapa !== 1 || $proveedor->estado === 'anulado') {
                throw new PagoProveedorException('Este formulario ya no admite cambios.');
            }
            $this->aplicarDatosBancarios($datos, $payload);
            $datos->estado = 'recibido';
            $datos->origen = $datos->origen === 'ventas' ? 'ventas' : 'cliente';
            $datos->correccion_solicitada = false;
            $datos->autorizado_at = now();
            $datos->save();
            $actor = ['id' => null, 'nombre' => 'Cliente', 'area' => 'Cliente'];
            $this->historial->registrar('solicitud', $proveedor->solicitud_id, 'T-T autorizado', $actor, $proveedor->code_supplier);
            $this->intentarAvanzar($this->solicitudDe($proveedor), $actor);

            return ['estado' => 'recibido', 'mensaje' => 'Autorizamos los datos. Ventas ya puede continuar.'];
        });
    }

    /**
     * @param  array<int, string>  $with
     */
    private function recargar(PagoProveedorSolicitud $solicitud, array $with = ['proveedores.datos', 'proveedores.aplicaciones.abono', 'abonos.aplicaciones', 'cotizacion']): PagoProveedorSolicitud
    {
        $fresca = $solicitud->fresh($with);
        if (!$fresca instanceof PagoProveedorSolicitud) {
            throw new PagoProveedorException('La solicitud no existe.', 404);
        }

        return $fresca;
    }

    private function solicitudDe(PagoProveedorSolicitudProveedor $proveedor): PagoProveedorSolicitud
    {
        $solicitud = $proveedor->solicitud;
        if (!$solicitud instanceof PagoProveedorSolicitud) {
            throw new PagoProveedorException('La solicitud no existe.', 404);
        }

        return $solicitud;
    }

    private function solicitud(int $id, bool $bloquear = false): PagoProveedorSolicitud
    {
        $query = PagoProveedorSolicitud::query()->with(['proveedores.datos', 'proveedores.aplicaciones.abono', 'abonos.aplicaciones', 'cotizacion']);
        if ($bloquear) {
            $query->lockForUpdate();
        }
        $solicitud = $query->find($id);
        if (!$solicitud) {
            throw new PagoProveedorException('La solicitud no existe.', 404);
        }

        return $solicitud;
    }

    private function proveedorEtapa1(int $solicitudId, int $proveedorId): PagoProveedorSolicitudProveedor
    {
        $solicitud = $this->solicitud($solicitudId, true);
        $proveedor = $solicitud->proveedores->firstWhere('id', $proveedorId);
        if (!$proveedor) {
            throw new PagoProveedorException('El proveedor no pertenece a esta solicitud.', 404);
        }
        if ($proveedor->estado === 'anulado') {
            throw new PagoProveedorException('Este proveedor está anulado.');
        }
        if ((int) $proveedor->etapa !== 1) {
            throw new PagoProveedorException('Los datos y los abonos de la etapa 1 ya no se editan.');
        }
        $proveedor->setRelation('solicitud', $solicitud);

        return $proveedor;
    }

    private function exigirTt(PagoProveedorSolicitudProveedor $proveedor): void
    {
        if ($proveedor->moneda !== 'USD' || $proveedor->tipo_pago !== 'tt') {
            throw new PagoProveedorException('El formulario T-T es solo para pagos en dólares.');
        }
    }

    private function datosDe(PagoProveedorSolicitudProveedor $proveedor): PagoProveedorDatoProveedor
    {
        $datos = $proveedor->datos;
        if ($datos) {
            return $datos;
        }

        return $proveedor->datos()->create([
            'estado' => 'pendiente',
            'token' => Str::random(48),
        ]);
    }

    private function datosPorToken(string $token): PagoProveedorDatoProveedor
    {
        $datos = PagoProveedorDatoProveedor::query()->with('proveedor.solicitud')->where('token', $token)->first();
        if (!$datos || !$datos->proveedor) {
            throw new PagoProveedorException('El enlace no es válido.', 404);
        }

        return $datos;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function aplicarDatosBancarios(PagoProveedorDatoProveedor $datos, array $payload): void
    {
        foreach (['beneficiario_nombre', 'beneficiario_cuenta', 'beneficiario_direccion', 'beneficiario_pais', 'beneficiario_ciudad', 'banco_nombre', 'banco_direccion', 'banco_pais', 'banco_ciudad'] as $campo) {
            if (trim((string) ($payload[$campo] ?? '')) === '') {
                throw new PagoProveedorException('Faltan datos del beneficiario o del banco de destino.');
            }
        }
        $tipo = (string) ($payload['banco_tipo_codigo'] ?? '');
        if (!in_array($tipo, ['SWIFT', 'ABA', 'CHIPS', 'Sort Code', 'Otro'], true)) {
            throw new PagoProveedorException('El tipo de código del banco no es válido.');
        }
        $datos->fill([
            'beneficiario_nombre' => trim((string) $payload['beneficiario_nombre']),
            'beneficiario_cuenta' => trim((string) $payload['beneficiario_cuenta']),
            'beneficiario_direccion' => trim((string) $payload['beneficiario_direccion']),
            'beneficiario_pais' => trim((string) $payload['beneficiario_pais']),
            'beneficiario_ciudad' => trim((string) $payload['beneficiario_ciudad']),
            'banco_tipo_codigo' => $tipo,
            'banco_codigo' => PagoProveedorDatosReglas::codigoBanco($tipo, (string) ($payload['banco_codigo'] ?? '')),
            'banco_nombre' => trim((string) $payload['banco_nombre']),
            'banco_direccion' => trim((string) $payload['banco_direccion']),
            'banco_pais' => trim((string) $payload['banco_pais']),
            'banco_ciudad' => trim((string) $payload['banco_ciudad']),
            'usa_intermediario' => (bool) ($payload['usa_intermediario'] ?? false),
        ]);
        if ($datos->usa_intermediario) {
            $tipoInter = (string) ($payload['inter_tipo_codigo'] ?? '');
            if (!in_array($tipoInter, ['SWIFT', 'ABA', 'CHIPS', 'Sort Code', 'Otro'], true)) {
                throw new PagoProveedorException('El tipo de código del banco intermediario no es válido.');
            }
            foreach (['inter_nombre', 'inter_direccion', 'inter_pais', 'inter_ciudad'] as $campo) {
                if (trim((string) ($payload[$campo] ?? '')) === '') {
                    throw new PagoProveedorException('Faltan datos del banco intermediario.');
                }
            }
            $datos->inter_tipo_codigo = $tipoInter;
            $datos->inter_codigo = PagoProveedorDatosReglas::codigoBanco($tipoInter, (string) ($payload['inter_codigo'] ?? ''));
            $datos->inter_nombre = trim((string) $payload['inter_nombre']);
            $datos->inter_direccion = trim((string) $payload['inter_direccion']);
            $datos->inter_pais = trim((string) $payload['inter_pais']);
            $datos->inter_ciudad = trim((string) $payload['inter_ciudad']);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>|string  $aplicaciones
     * @return array<int, float>
     */
    private function mapaAplicaciones(array|string $aplicaciones): array
    {
        if (is_string($aplicaciones)) {
            $aplicaciones = json_decode($aplicaciones, true) ?: [];
        }
        $mapa = [];
        foreach ($aplicaciones as $fila) {
            $mapa[(int) ($fila['solicitud_proveedor_id'] ?? 0)] = (float) ($fila['monto'] ?? 0);
        }

        return $mapa;
    }

    private function saldo(PagoProveedorSolicitudProveedor $proveedor, ?int $exceptoAbonoId = null): float
    {
        $abonado = 0.0;
        foreach ($proveedor->aplicaciones as $aplicacion) {
            $abono = $aplicacion->abono;
            if (!$abono || $abono->estado === 'anulado') {
                continue;
            }
            if ($exceptoAbonoId && (int) $abono->id === $exceptoAbonoId) {
                continue;
            }
            $abonado += (float) $aplicacion->monto;
        }

        return round(max(0, $this->requerido($proveedor) - $abonado), 2);
    }

    private function requerido(PagoProveedorSolicitudProveedor $proveedor): float
    {
        $cuotas = $proveedor->resumen_json['cuotas'] ?? [];
        $indice = max(0, ((int) $proveedor->cuota_actual) - 1);
        if (isset($cuotas[$indice]['total'])) {
            return round((float) $cuotas[$indice]['total'], 2);
        }

        return round((float) $proveedor->total_monto, 2);
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    private function intentarAvanzar(PagoProveedorSolicitud $solicitud, array $actor): void
    {
        $solicitud->load(['proveedores.datos', 'proveedores.aplicaciones.abono']);
        foreach ($solicitud->proveedores as $proveedor) {
            if ($proveedor->estado === 'anulado' || (int) $proveedor->etapa !== 1) {
                continue;
            }
            if (!$this->datosListos($proveedor) || $this->saldo($proveedor) > 0.009) {
                continue;
            }
            if (!$proveedor->codigo_solicitud) {
                $proveedor->codigo_solicitud = $this->codigos->armar($proveedor->moneda, $this->siguienteCorrelativo());
            }
            $proveedor->etapa = 2;
            $proveedor->save();
            $this->historial->registrar('solicitud', $solicitud->id, 'Pasó a validación', $actor, $proveedor->code_supplier);
        }
    }

    private function datosListos(PagoProveedorSolicitudProveedor $proveedor): bool
    {
        $datos = $proveedor->datos;
        if (!$datos) {
            return false;
        }
        if ($proveedor->moneda === 'RMB') {
            return trim((string) $datos->vendedor_nombre) !== '' && trim((string) $datos->wechat) !== '';
        }

        return $datos->estado === 'recibido';
    }

    private function siguienteCorrelativo(): int
    {
        $fila = PagoProveedorConfiguracion::query()->where('clave', 'correlativo_solicitud')->lockForUpdate()->first();
        if (!$fila) {
            $fila = PagoProveedorConfiguracion::query()->create([
                'clave' => 'correlativo_solicitud',
                'valor' => ['n' => 0],
            ]);
            $fila = PagoProveedorConfiguracion::query()->whereKey($fila->id)->lockForUpdate()->first();
        }
        $siguiente = ((int) ($fila->valor['n'] ?? 0)) + 1;
        $fila->valor = ['n' => $siguiente];
        $fila->save();

        return $siguiente;
    }

    private function abonoEditable(PagoProveedorSolicitud $solicitud, int $abonoId): PagoProveedorAbono
    {
        $abono = $solicitud->abonos->firstWhere('id', $abonoId);
        if (!$abono) {
            throw new PagoProveedorException('El abono no existe.', 404);
        }
        if ($abono->estado !== 'en_validacion') {
            throw new PagoProveedorException('Solo se edita o anula un abono que sigue en validación.');
        }
        $avanzados = $abono->aplicaciones->contains(function ($aplicacion) use ($solicitud) {
            $proveedor = $solicitud->proveedores->firstWhere('id', $aplicacion->solicitud_proveedor_id);

            return $proveedor && (int) $proveedor->etapa > 1;
        });
        if ($avanzados) {
            throw new PagoProveedorException('Un proveedor de este abono ya pasó a validación.');
        }

        return $abono;
    }

    private function enlace(PagoProveedorDatoProveedor $datos): string
    {
        return rtrim((string) config('pago_proveedor.front_url'), '/') . '/pago-proveedor/formulario-tt/' . $datos->token;
    }

    private function textoFormulario(PagoProveedorSolicitudProveedor $proveedor, string $enlace, bool $porAutorizar): string
    {
        $nombre = $this->solicitudDe($proveedor)->cliente_nombre ?: 'cliente';
        $pedido = $porAutorizar
            ? 'Revisa y autoriza los datos bancarios que registramos para el pago.'
            : 'Completa el formulario bancario para pagarle a tu proveedor.';

        return "Hola, {$nombre} 🙋🏻‍♀️\n|\nProveedor: {$proveedor->code_supplier}\n{$proveedor->producto}\n{$pedido}\n{$enlace}";
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    private function guardarMensaje(PagoProveedorSolicitudProveedor $proveedor, string $tipo, string $texto, array $actor): void
    {
        PagoProveedorMensaje::query()->create([
            'solicitud_id' => $proveedor->solicitud_id,
            'tipo' => $tipo,
            'destinatario' => $this->solicitudDe($proveedor)->cliente_whatsapp,
            'cuerpo' => $texto,
            'usuario_id' => $actor['id'],
            'created_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentar(PagoProveedorSolicitud $solicitud): array
    {
        $solicitud->loadMissing(['proveedores.datos', 'proveedores.aplicaciones.abono', 'abonos.aplicaciones', 'cotizacion']);
        $proveedores = [];
        $cotizado = 0.0;
        $abonado = 0.0;
        $pendiente = 0.0;
        $excedente = 0.0;

        foreach ($solicitud->proveedores as $proveedor) {
            if ($proveedor->estado === 'anulado') {
                $proveedores[] = $this->filaProveedor($proveedor, 0, 0);
                continue;
            }
            $requerido = $this->requerido($proveedor);
            $pagado = round($requerido - $this->saldo($proveedor), 2);
            $cotizado += (float) $proveedor->total_monto;
            $abonado += $pagado;
            $pendiente += $this->saldo($proveedor);
            $proveedores[] = $this->filaProveedor($proveedor, $requerido, $pagado);
        }
        foreach ($solicitud->abonos as $abono) {
            if ($abono->estado !== 'anulado') {
                $excedente += (float) $abono->excedente;
            }
        }

        return [
            'id' => $solicitud->id,
            'numero_orden' => $solicitud->numero_orden,
            'cotizacion_id' => $solicitud->cotizacion_id,
            'cotizacion_codigo' => $solicitud->cotizacion?->codigo,
            'cod_cotizacion_cc' => $solicitud->cod_cotizacion_cc,
            'cliente_nombre' => $solicitud->cliente_nombre,
            'cliente_documento' => $solicitud->cliente_documento,
            'cliente_whatsapp' => $solicitud->cliente_whatsapp,
            'cliente_correo' => $solicitud->cliente_correo,
            'consolidado' => $solicitud->consolidado,
            'tipo_cambio' => $solicitud->tipo_cambio,
            'creado_en' => optional($solicitud->created_at)->toIso8601String(),
            'total_cotizado' => round($cotizado, 2),
            'total_abonado' => round($abonado, 2),
            'saldo_pendiente' => round($pendiente, 2),
            'excedente' => round($excedente, 2),
            'proveedores' => $proveedores,
            'abonos' => $solicitud->abonos->map(fn (PagoProveedorAbono $abono) => $this->filaAbono($solicitud, $abono))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function filaProveedor(PagoProveedorSolicitudProveedor $proveedor, float $requerido, float $pagado): array
    {
        $datos = $proveedor->datos;
        $titulo = $proveedor->nombre_proveedor
            ? $proveedor->code_supplier . ' · ' . $proveedor->nombre_proveedor
            : ($proveedor->code_supplier ?: 'Proveedor ' . $proveedor->orden);

        return [
            'id' => $proveedor->id,
            'orden' => $proveedor->orden,
            'titulo' => $titulo,
            'code_supplier' => $proveedor->code_supplier,
            'nombre_proveedor' => $proveedor->nombre_proveedor,
            'producto' => $proveedor->producto,
            'moneda' => $proveedor->moneda,
            'tipo_pago' => $proveedor->tipo_pago,
            'valor_carga_usd' => $proveedor->valor_carga_usd,
            'modalidad_abono' => $proveedor->modalidad_abono,
            'modalidad_pago' => $proveedor->modalidad_pago,
            'bancaria_monto' => $proveedor->bancaria_monto,
            'administrativa_monto' => $proveedor->administrativa_monto,
            'total_monto' => $proveedor->total_monto,
            'etiqueta_bancaria' => $proveedor->resumen_json['etiqueta_bancaria'] ?? null,
            'importe_requerido' => $requerido,
            'importe_abonado' => $pagado,
            'saldo' => $proveedor->estado === 'anulado' ? 0 : $this->saldo($proveedor),
            'proforma_nombre' => $proveedor->proforma_nombre,
            'proforma_url' => $proveedor->proforma_path ? $this->cdnStorageUrl($proveedor->proforma_path) : null,
            'etapa' => $proveedor->etapa,
            'etapa_nombre' => $this->nombreEtapa((int) $proveedor->etapa),
            'estado' => $proveedor->estado,
            'estado_nombre' => $proveedor->estado === 'anulado' ? 'Anulado' : 'En proceso',
            'codigo_solicitud' => $proveedor->codigo_solicitud,
            'puede_editar_etapa1' => (int) $proveedor->etapa === 1 && $proveedor->estado !== 'anulado',
            'datos' => $datos ? [
                'estado' => $datos->estado,
                'origen' => $datos->origen,
                'vendedor_nombre' => $datos->vendedor_nombre,
                'wechat' => $datos->wechat,
                'correccion_solicitada' => $datos->correccion_solicitada,
                'beneficiario_nombre' => $datos->beneficiario_nombre,
                'banco_nombre' => $datos->banco_nombre,
                'banco_codigo' => $datos->banco_codigo,
            ] : [
                'estado' => 'pendiente',
                'origen' => null,
                'vendedor_nombre' => null,
                'wechat' => null,
                'correccion_solicitada' => false,
                'beneficiario_nombre' => null,
                'banco_nombre' => null,
                'banco_codigo' => null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function filaAbono(PagoProveedorSolicitud $solicitud, PagoProveedorAbono $abono): array
    {
        $avanzado = $abono->aplicaciones->contains(function ($aplicacion) use ($solicitud) {
            $proveedor = $solicitud->proveedores->firstWhere('id', $aplicacion->solicitud_proveedor_id);

            return $proveedor && (int) $proveedor->etapa > 1;
        });

        return [
            'id' => $abono->id,
            'importe' => $abono->importe,
            'fecha_pago' => optional($abono->fecha_pago)->format('Y-m-d'),
            'numero_operacion' => $abono->numero_operacion,
            'banco' => $abono->banco,
            'interbancaria' => $abono->interbancaria,
            'voucher_nombre' => $abono->voucher_nombre,
            'voucher_url' => $this->cdnStorageUrl($abono->voucher_path),
            'estado' => $abono->estado,
            'estado_nombre' => $this->nombreEstadoAbono($abono->estado),
            'excedente' => $abono->excedente,
            'excedente_decision' => $abono->excedente_decision,
            'operacion_repetida' => $abono->operacion_repetida,
            'puede_editar' => $abono->estado === 'en_validacion' && !$avanzado,
            'aplicaciones' => $abono->aplicaciones->map(fn (PagoProveedorAbonoAplicacion $aplicacion) => [
                'solicitud_proveedor_id' => $aplicacion->solicitud_proveedor_id,
                'monto' => $aplicacion->monto,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function datosPublicos(PagoProveedorDatoProveedor $datos): array
    {
        return [
            'beneficiario_nombre' => $datos->beneficiario_nombre,
            'beneficiario_cuenta' => $datos->beneficiario_cuenta,
            'beneficiario_direccion' => $datos->beneficiario_direccion,
            'beneficiario_pais' => $datos->beneficiario_pais,
            'beneficiario_ciudad' => $datos->beneficiario_ciudad,
            'banco_tipo_codigo' => $datos->banco_tipo_codigo,
            'banco_codigo' => $datos->banco_codigo,
            'banco_nombre' => $datos->banco_nombre,
            'banco_direccion' => $datos->banco_direccion,
            'banco_pais' => $datos->banco_pais,
            'banco_ciudad' => $datos->banco_ciudad,
            'usa_intermediario' => $datos->usa_intermediario,
            'inter_tipo_codigo' => $datos->inter_tipo_codigo,
            'inter_codigo' => $datos->inter_codigo,
            'inter_nombre' => $datos->inter_nombre,
            'inter_direccion' => $datos->inter_direccion,
            'inter_pais' => $datos->inter_pais,
            'inter_ciudad' => $datos->inter_ciudad,
        ];
    }

    private function nombreEtapa(int $etapa): string
    {
        return [
            1 => 'Solicitud y datos de pago',
            2 => 'Validación de pago',
            3 => 'Ejecución de pago',
            4 => 'Confirmación',
        ][$etapa] ?? 'Solicitud y datos de pago';
    }

    private function nombreEstadoAbono(string $estado): string
    {
        return [
            'en_validacion' => 'En validación',
            'validado' => 'Validado',
            'observado' => 'Observado',
            'anulado' => 'Anulado',
        ][$estado] ?? $estado;
    }
}
