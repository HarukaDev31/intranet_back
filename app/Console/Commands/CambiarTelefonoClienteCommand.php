<?php

namespace App\Console\Commands;

use App\Helpers\UserLookupHelper;
use App\Traits\DatabaseConnectionTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Busca un cliente de BD por teléfono, lista consolidados donde participó
 * y actualiza el teléfono en: clientes, users (portal) y cotizaciones asociadas.
 *
 * Ejemplos:
 *   php artisan clientes:cambiar-telefono 912705923 --dry-run
 *   php artisan clientes:cambiar-telefono 912705923 987654321
 *   php artisan clientes:cambiar-telefono 51912705923 51987654321 --force
 *   php artisan clientes:cambiar-telefono 912705923 987654321 --cliente-id=123
 */
class CambiarTelefonoClienteCommand extends Command
{
    use DatabaseConnectionTrait;

    protected $signature = 'clientes:cambiar-telefono
                            {telefono_actual : Teléfono actual (con o sin 51)}
                            {telefono_nuevo? : Teléfono nuevo (si se omite, solo muestra el impacto)}
                            {--cliente-id= : ID de clientes si hay varios matches}
                            {--dry-run : Solo listar impacto, no escribe}
                            {--force : Sin confirmación interactiva}
                            {--skip-cliente : No actualizar tabla clientes}
                            {--skip-usuario : No actualizar users.whatsapp/phone}
                            {--skip-cotizaciones : No actualizar contenedor_consolidado_cotizacion.telefono}';

    protected $description = 'Cambia el teléfono de un cliente BD + usuario portal + cotizaciones de consolidados';

    public function handle(): int
    {
        $this->setDatabaseConnection();

        $telefonoActualRaw = (string) $this->argument('telefono_actual');
        $telefonoNuevoRaw = $this->argument('telefono_nuevo');
        $telefonoActual = $this->normalizeLocalPhone($telefonoActualRaw);
        $telefonoNuevo = $telefonoNuevoRaw !== null
            ? $this->normalizeLocalPhone((string) $telefonoNuevoRaw)
            : null;

        if ($telefonoActual === '' || strlen($telefonoActual) < 8) {
            $this->error('Teléfono actual inválido tras normalizar: "' . $telefonoActualRaw . '"');

            return self::FAILURE;
        }

        if ($telefonoNuevoRaw !== null && ($telefonoNuevo === '' || strlen($telefonoNuevo) < 8)) {
            $this->error('Teléfono nuevo inválido tras normalizar: "' . $telefonoNuevoRaw . '"');

            return self::FAILURE;
        }

        if ($telefonoNuevo !== null && $telefonoNuevo === $telefonoActual) {
            $this->warn('El teléfono nuevo es igual al actual (normalizado). Nada que hacer.');

            return self::SUCCESS;
        }

        $this->info('Teléfono actual normalizado: ' . $telefonoActual);
        if ($telefonoNuevo !== null) {
            $this->info('Teléfono nuevo normalizado:  ' . $telefonoNuevo);
        }

        $clientes = $this->findClientesByPhone($telefonoActual);
        if ($clientes->isEmpty()) {
            $this->error('No se encontró ningún registro en `clientes` con ese teléfono.');

            return self::FAILURE;
        }

        if ($clientes->count() > 1) {
            $this->warn('Se encontraron varios clientes con ese teléfono:');
            $this->table(
                ['id', 'nombre', 'documento', 'correo', 'telefono'],
                $clientes->map(static function ($c) {
                    return [
                        $c->id,
                        $c->nombre,
                        $c->documento,
                        $c->correo,
                        $c->telefono,
                    ];
                })->all()
            );

            $clienteIdOpt = (int) $this->option('cliente-id');
            if ($clienteIdOpt <= 0) {
                $this->error('Usa --cliente-id=ID para elegir cuál actualizar.');

                return self::FAILURE;
            }

            $cliente = $clientes->firstWhere('id', $clienteIdOpt);
            if (!$cliente) {
                $this->error("El --cliente-id={$clienteIdOpt} no está entre los matches.");

                return self::FAILURE;
            }
        } else {
            $cliente = $clientes->first();
            $clienteIdOpt = (int) $this->option('cliente-id');
            if ($clienteIdOpt > 0 && (int) $cliente->id !== $clienteIdOpt) {
                $this->error("El cliente encontrado (id={$cliente->id}) no coincide con --cliente-id={$clienteIdOpt}.");

                return self::FAILURE;
            }
        }

        $this->newLine();
        $this->info('Cliente seleccionado');
        $this->table(
            ['id', 'nombre', 'documento', 'ruc', 'empresa', 'correo', 'telefono'],
            [[
                $cliente->id,
                $cliente->nombre,
                $cliente->documento,
                $cliente->ruc ?? '',
                $cliente->empresa ?? '',
                $cliente->correo,
                $cliente->telefono,
            ]]
        );

        $users = $this->findUsersForCliente($cliente, $telefonoActual);
        $this->newLine();
        $this->info('Usuario(s) portal asociados (users)');
        if ($users->isEmpty()) {
            $this->line('  (ninguno por correo / teléfono / DNI)');
        } else {
            $this->table(
                ['id', 'name', 'email', 'dni', 'whatsapp', 'phone'],
                $users->map(static function ($u) {
                    return [
                        $u->id,
                        trim(($u->name ?? '') . ' ' . ($u->lastname ?? '')),
                        $u->email,
                        $u->dni ?? '',
                        $u->whatsapp ?? '',
                        $u->phone ?? '',
                    ];
                })->all()
            );
        }

        $cotizaciones = $this->findCotizaciones($cliente, $telefonoActual);
        $this->newLine();
        $this->info('Cotizaciones / consolidados donde participó');
        if ($cotizaciones->isEmpty()) {
            $this->line('  (ninguna)');
        } else {
            $this->table(
                ['cotizacion_id', 'carga', 'contenedor_id', 'estado', 'telefono', 'fecha'],
                $cotizaciones->map(static function ($row) {
                    return [
                        $row->id,
                        $row->carga ?? '—',
                        $row->id_contenedor,
                        $row->estado ?? '',
                        $row->telefono ?? '',
                        $row->fecha ?? '',
                    ];
                })->all()
            );

            $cargas = $cotizaciones->pluck('carga')->filter()->unique()->values();
            $this->comment('Consolidados únicos: ' . ($cargas->isEmpty() ? '—' : $cargas->implode(', ')));
            $this->comment('Total cotizaciones: ' . $cotizaciones->count());
        }

        if ($telefonoNuevo === null) {
            $this->newLine();
            $this->comment('Solo consulta. Para aplicar: pasa el teléfono nuevo como 2.º argumento.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $doCliente = !(bool) $this->option('skip-cliente');
        $doUsuario = !(bool) $this->option('skip-usuario');
        $doCotizaciones = !(bool) $this->option('skip-cotizaciones');

        $this->newLine();
        $this->info('Plan de actualización → ' . $telefonoNuevo);
        $this->line('  clientes.id=' . $cliente->id . ': ' . ($doCliente ? 'SÍ' : 'omitido'));
        $this->line('  users (' . $users->count() . '): ' . ($doUsuario ? 'SÍ' : 'omitido'));
        $this->line('  cotizaciones (' . $cotizaciones->count() . '): ' . ($doCotizaciones ? 'SÍ' : 'omitido'));

        if ($dryRun) {
            $this->warn('DRY-RUN: no se escribió nada.');

            return self::SUCCESS;
        }

        if (!(bool) $this->option('force')) {
            if (!$this->confirm('¿Aplicar el cambio de teléfono?', false)) {
                $this->warn('Cancelado.');

                return self::SUCCESS;
            }
        }

        $updated = [
            'cliente' => 0,
            'users' => 0,
            'cotizaciones' => 0,
        ];

        DB::transaction(function () use (
            $cliente,
            $users,
            $cotizaciones,
            $telefonoActual,
            $telefonoNuevo,
            $doCliente,
            $doUsuario,
            $doCotizaciones,
            &$updated
        ) {
            if ($doCliente) {
                $updated['cliente'] = DB::table('clientes')
                    ->where('id', $cliente->id)
                    ->update([
                        'telefono' => $telefonoNuevo,
                        'updated_at' => now(),
                    ]);
            }

            if ($doUsuario && $users->isNotEmpty()) {
                foreach ($users as $user) {
                    $patch = [];
                    if ($this->phonesMatch((string) ($user->whatsapp ?? ''), $telefonoActual)) {
                        $patch['whatsapp'] = $telefonoNuevo;
                    }
                    if ($this->phonesMatch((string) ($user->phone ?? ''), $telefonoActual)) {
                        $patch['phone'] = $telefonoNuevo;
                    }
                    // Si no matcheó whatsapp/phone pero el user se encontró por correo/DNI, actualizar ambos si están vacíos o el actual.
                    if ($patch === []) {
                        $patch['whatsapp'] = $telefonoNuevo;
                        if (Schema::hasColumn('users', 'phone')) {
                            $patch['phone'] = $telefonoNuevo;
                        }
                    }
                    if ($patch !== []) {
                        if (Schema::hasColumn('users', 'updated_at')) {
                            $patch['updated_at'] = now();
                        }
                        $n = DB::table('users')->where('id', $user->id)->update($patch);
                        $updated['users'] += $n;
                    }
                }
            }

            if ($doCotizaciones && $cotizaciones->isNotEmpty()) {
                $ids = $cotizaciones->pluck('id')->all();
                $updated['cotizaciones'] = DB::table('contenedor_consolidado_cotizacion')
                    ->whereIn('id', $ids)
                    ->update(['telefono' => $telefonoNuevo]);
            }
        });

        Log::info('clientes:cambiar-telefono aplicado', [
            'cliente_id' => $cliente->id,
            'telefono_actual' => $telefonoActual,
            'telefono_nuevo' => $telefonoNuevo,
            'updated' => $updated,
            'user_ids' => $users->pluck('id')->all(),
            'cotizacion_ids' => $cotizaciones->pluck('id')->all(),
        ]);

        $this->newLine();
        $this->info('Listo.');
        $this->line('  clientes actualizados:     ' . $updated['cliente']);
        $this->line('  users actualizados:        ' . $updated['users']);
        $this->line('  cotizaciones actualizadas: ' . $updated['cotizaciones']);

        return self::SUCCESS;
    }

    /**
     * 9 dígitos locales (sin 51), coherente con BD clientes / PhoneHelper.
     */
    private function normalizeLocalPhone(string $raw): string
    {
        if (function_exists('normalizePhone')) {
            return (string) normalizePhone($raw);
        }

        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if (preg_match('/^51(\d{9})$/', $digits, $m)) {
            return $m[1];
        }
        if (preg_match('/^051(\d{9})$/', $digits, $m)) {
            return $m[1];
        }

        return $digits;
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function findClientesByPhone(string $localPhone)
    {
        $variations = $this->phoneSearchTokens($localPhone);

        return DB::table('clientes')
            ->where(function ($q) use ($variations) {
                $norm = $this->sqlDigitsExpr('telefono');
                foreach ($variations as $token) {
                    $q->orWhereRaw("{$norm} LIKE ?", ['%' . $token . '%']);
                }
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function findUsersForCliente(object $cliente, string $localPhone)
    {
        $byContact = UserLookupHelper::findUserByContact(
            $cliente->correo ?? null,
            $localPhone,
            $cliente->documento ?? null
        );

        $ids = [];
        if ($byContact) {
            $ids[] = (int) $byContact->id;
        }

        // Todos los users cuyo whatsapp/phone coincida (puede haber más de uno).
        $variations = $this->phoneSearchTokens($localPhone);
        $byPhone = DB::table('users')
            ->where(function ($q) use ($variations) {
                $wa = $this->sqlDigitsExpr('whatsapp');
                $ph = $this->sqlDigitsExpr('phone');
                foreach ($variations as $token) {
                    $q->orWhereRaw("{$wa} LIKE ?", ['%' . $token . '%'])
                        ->orWhereRaw("{$ph} LIKE ?", ['%' . $token . '%']);
                }
            })
            ->get();

        foreach ($byPhone as $u) {
            $ids[] = (int) $u->id;
        }

        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids === []) {
            return collect();
        }

        return DB::table('users')->whereIn('id', $ids)->orderBy('id')->get();
    }

    /**
     * Cotizaciones por id_cliente o por teléfono coincidente.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function findCotizaciones(object $cliente, string $localPhone)
    {
        $variations = $this->phoneSearchTokens($localPhone);

        $query = DB::table('contenedor_consolidado_cotizacion as CC')
            ->leftJoin('carga_consolidada_contenedor as C', 'C.id', '=', 'CC.id_contenedor')
            ->select([
                'CC.id',
                'CC.id_contenedor',
                'CC.id_cliente',
                'CC.telefono',
                'CC.estado',
                'CC.fecha',
                'CC.nombre',
                'C.carga',
            ])
            ->whereNull('CC.deleted_at')
            ->where(function ($q) use ($cliente, $variations) {
                $q->where('CC.id_cliente', $cliente->id)
                    ->orWhere(function ($q2) use ($cliente, $variations) {
                        $norm = $this->sqlDigitsExpr('CC.telefono');
                        $q2->where(function ($q3) use ($cliente) {
                            $q3->whereNull('CC.id_cliente')
                                ->orWhere('CC.id_cliente', $cliente->id);
                        });
                        $q2->where(function ($q3) use ($norm, $variations) {
                            foreach ($variations as $token) {
                                $q3->orWhereRaw("{$norm} LIKE ?", ['%' . $token . '%']);
                            }
                        });
                    });
            })
            ->orderByDesc('CC.id');

        return $query->get();
    }

    /**
     * @return array<int, string>
     */
    private function phoneSearchTokens(string $localPhone): array
    {
        $tokens = [$localPhone, '51' . $localPhone];
        if (function_exists('generatePhoneVariations')) {
            foreach (generatePhoneVariations($localPhone) as $v) {
                $digits = preg_replace('/\D+/', '', (string) $v) ?? '';
                if ($digits !== '') {
                    $tokens[] = $digits;
                }
            }
        }

        return array_values(array_unique($tokens));
    }

    private function phonesMatch(string $stored, string $localPhone): bool
    {
        if ($stored === '') {
            return false;
        }
        $digits = preg_replace('/\D+/', '', $stored) ?? '';
        if ($digits === '') {
            return false;
        }
        $digitsLocal = $this->normalizeLocalPhone($digits);

        return $digitsLocal === $localPhone
            || $digits === $localPhone
            || $digits === '51' . $localPhone;
    }

    private function sqlDigitsExpr(string $column): string
    {
        return 'REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(' . $column . ', " ", ""), "-", ""), "(", ""), ")", ""), "+", "")';
    }
}
