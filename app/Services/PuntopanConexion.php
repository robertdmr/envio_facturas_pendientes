<?php

namespace App\Services;

use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Decide contra qué base de `puntopan` trabaja la aplicación.
 *
 * Estados posibles:
 * - REMOTO_OK:           el servidor remoto responde; se usa la conexión `puntopan`.
 * - LOCAL_APROBADO:      el operador aprobó (y la aprobación sigue vigente) usar
 *                        la copia local; la conexión `puntopan` se reescribe.
 * - REQUIERE_APROBACION: el remoto no responde y la copia local está disponible,
 *                        pero nadie ha aprobado todavía.
 * - NO_DISPONIBLE:       ni remoto ni copia local (o el respaldo está prohibido).
 *
 * Nunca se pasa a la copia local en silencio: siempre hace falta aprobación
 * explícita (con caducidad) o el escape hatch `forzado` para desarrollo/tests.
 * Hay dos aprobaciones del mismo operador: la del navegador (sesión) y la que
 * se extiende al encolar un envío, para que el worker de la cola —que no tiene
 * sesión— pueda ejecutarlo.
 */
class PuntopanConexion
{
    public const REMOTO_OK = 'remoto_ok';

    public const LOCAL_APROBADO = 'local_aprobado';

    public const REQUIERE_APROBACION = 'requiere_aprobacion';

    public const NO_DISPONIBLE = 'no_disponible';

    public const CLAVE_SESION = 'puntopan.local_aprobado_hasta';

    public const CLAVE_URL_PRETENDIDA = 'puntopan.url_pretendida';

    /**
     * Aprobación que alcanza a los procesos sin sesión (el worker de la cola).
     * Vive en el cache (store `database`) para que cruce procesos y caduque sola.
     */
    public const CLAVE_COLA = 'puntopan:aprobacion:cola';

    private const CLAVE_SONDEO_REMOTO = 'puntopan:sondeo:remoto';

    private const CLAVE_SONDEO_LOCAL = 'puntopan:sondeo:local';

    private const CLAVE_AVISO = 'puntopan:aviso:copia-local';

    public function __construct(private SondeadorTcp $sondeador) {}

    /**
     * Estado actual de la conexión.
     *
     * El remoto se comprueba **antes** que la aprobación: la copia local es un
     * respaldo, así que en cuanto el servidor remoto responde se vuelve a él
     * aunque quede aprobación vigente.
     */
    public function estado(): string
    {
        if ($this->forzado()) {
            return self::LOCAL_APROBADO;
        }

        if ($this->remotoDisponible()) {
            return self::REMOTO_OK;
        }

        if ($this->aprobado() && $this->localDisponible()) {
            return self::LOCAL_APROBADO;
        }

        if ($this->permitido() && $this->localDisponible()) {
            return self::REQUIERE_APROBACION;
        }

        return self::NO_DISPONIBLE;
    }

    /**
     * Punto de entrada único: resuelve el estado y, si toca, deja la conexión
     * `puntopan` apuntando a la copia local. Devuelve el estado resultante.
     */
    public function prepararConexion(): string
    {
        $estado = $this->estado();

        if ($estado === self::LOCAL_APROBADO) {
            $this->aplicarLocal();
        }

        return $estado;
    }

    /**
     * Sondeo TCP al servidor remoto (cacheado durante `ttl_sondeo`).
     */
    public function remotoDisponible(): bool
    {
        return (bool) Cache::remember(
            self::CLAVE_SONDEO_REMOTO,
            now()->addSeconds($this->ttlSondeo()),
            fn (): bool => $this->sondeador->disponible(
                $this->hostRemoto(),
                $this->puertoRemoto(),
                $this->timeoutSondeo(),
            ),
        );
    }

    /**
     * Comprobación de la copia local: conexión real (es local, así que es barata)
     * porque no basta con que el puerto esté abierto, la base debe existir.
     */
    public function localDisponible(): bool
    {
        return (bool) Cache::remember(
            self::CLAVE_SONDEO_LOCAL,
            now()->addSeconds($this->ttlSondeo()),
            function (): bool {
                try {
                    DB::connection('puntopan_fallback')->getPdo();

                    return true;
                } catch (Throwable $e) {
                    Log::debug('puntopan: la copia local no está disponible.', [
                        'base' => $this->baseLocal(),
                        'mensaje' => $e->getMessage(),
                    ]);

                    return false;
                }
            },
        );
    }

    /**
     * Aprobación del navegador (o null si no hay sesión, no hay aprobación o caducó).
     */
    public function aprobadoHasta(): ?Carbon
    {
        $hasta = $this->sesion()?->get(self::CLAVE_SESION);

        return $hasta ? Carbon::createFromTimestamp((int) $hasta) : null;
    }

    public function aprobacionVigente(): bool
    {
        $hasta = $this->aprobadoHasta();

        return $hasta !== null && $hasta->isFuture();
    }

    /**
     * Aprobación que alcanza a los procesos sin sesión (el worker de la cola).
     */
    public function aprobadoParaLaColaHasta(): ?Carbon
    {
        $hasta = Cache::get(self::CLAVE_COLA);

        return $hasta ? Carbon::createFromTimestamp((int) $hasta) : null;
    }

    /**
     * Hay alguna aprobación vigente: la del navegador o la que habilita la cola.
     */
    public function aprobado(): bool
    {
        return $this->aprobacionVigente()
            || $this->aprobadoParaLaColaHasta()?->isFuture() === true;
    }

    /**
     * La conexión efectiva es la copia local (lo que muestra el banner).
     */
    public function usandoCopiaLocal(): bool
    {
        return $this->estado() === self::LOCAL_APROBADO;
    }

    /**
     * Hasta cuándo está aprobado el uso de la copia local, sea la aprobación del
     * navegador o la de la cola (la más lejana de las dos).
     */
    public function aprobacionEfectivaHasta(): ?Carbon
    {
        $sesion = $this->aprobadoHasta();
        $cola = $this->aprobadoParaLaColaHasta();

        if ($sesion === null) {
            return $cola;
        }

        if ($cola === null) {
            return $sesion;
        }

        return $sesion->greaterThan($cola) ? $sesion : $cola;
    }

    /**
     * Registra la aprobación del operador para su navegador. Devuelve false si no
     * hay sesión (consola, colas), donde este tipo de aprobación no aplica.
     */
    public function aprobar(): bool
    {
        $sesion = $this->sesion();

        if ($sesion === null) {
            return false;
        }

        $sesion->put(self::CLAVE_SESION, now()->addSeconds($this->ttlAprobacion())->getTimestamp());

        return true;
    }

    /**
     * Extiende la aprobación a los procesos sin sesión (el worker de la cola),
     * con la misma caducidad. Es lo que permite que un envío encolado desde la
     * copia local se pueda ejecutar.
     */
    public function aprobarParaLaCola(): Carbon
    {
        $hasta = now()->addSeconds($this->ttlAprobacion());

        Cache::put(self::CLAVE_COLA, $hasta->getTimestamp(), $hasta);

        return $hasta;
    }

    /**
     * Descarta la aprobación vigente (del navegador y de la cola) y devuelve la
     * conexión efectiva al remoto.
     */
    public function revocar(): void
    {
        $this->sesion()?->forget(self::CLAVE_SESION);

        Cache::forget(self::CLAVE_COLA);

        $remoto = config('database.connections.puntopan_remoto');

        // Solo se reconecta si de verdad estaba apuntando a la copia local: una
        // purga innecesaria descartaría transacciones abiertas (tests, jobs).
        if (config('database.connections.puntopan') !== $remoto) {
            config(['database.connections.puntopan' => $remoto]);

            DB::purge('puntopan');
        }
    }

    /**
     * Apunta la conexión `puntopan` a la copia local. A partir de aquí los
     * modelos (`Factura`, `DetalleFactura`, `Cliente`, `Fecdc`) y cualquier
     * `DB::connection('puntopan')` leen del MySQL local sin cambiar una línea.
     *
     * Es idempotente a propósito: llamarla de nuevo cuando ya está aplicada no
     * reconecta, así que no descarta transacciones en curso.
     */
    public function aplicarLocal(): void
    {
        $local = config('database.connections.puntopan_fallback');

        if (config('database.connections.puntopan') !== $local) {
            config(['database.connections.puntopan' => $local]);

            DB::purge('puntopan');
        }

        $this->avisarUsoDeCopiaLocal();
    }

    public function olvidarSondeo(): void
    {
        Cache::forget(self::CLAVE_SONDEO_REMOTO);
        Cache::forget(self::CLAVE_SONDEO_LOCAL);
    }

    /**
     * Datos para la pantalla de confirmación y el banner.
     */
    public function resumen(): array
    {
        return [
            'estado' => $this->estado(),
            'host_remoto' => $this->hostRemoto(),
            'puerto_remoto' => $this->puertoRemoto(),
            'host_local' => $this->hostLocal(),
            'puerto_local' => $this->puertoLocal(),
            'base_local' => $this->baseLocal(),
            'permitido' => $this->permitido(),
            'forzado' => $this->forzado(),
            'ttl_aprobacion' => $this->ttlAprobacion(),
            'aprobado_hasta' => $this->aprobadoHasta(),
            'aprobado_cola_hasta' => $this->aprobadoParaLaColaHasta(),
        ];
    }

    public function permitido(): bool
    {
        return (bool) config('database.puntopan_respaldo.permitido', true);
    }

    public function forzado(): bool
    {
        return (bool) config('database.puntopan_respaldo.forzado', false);
    }

    public function hostRemoto(): string
    {
        return (string) config('database.connections.puntopan_remoto.host', '');
    }

    public function puertoRemoto(): int
    {
        return (int) config('database.connections.puntopan_remoto.port', 3306);
    }

    public function hostLocal(): string
    {
        return (string) config('database.connections.puntopan_fallback.host', '127.0.0.1');
    }

    public function puertoLocal(): int
    {
        return (int) config('database.connections.puntopan_fallback.port', 3306);
    }

    public function baseLocal(): string
    {
        return (string) config('database.connections.puntopan_fallback.database', 'puntopan');
    }

    private function ttlAprobacion(): int
    {
        return max(60, (int) config('database.puntopan_respaldo.ttl_aprobacion', 3600));
    }

    private function ttlSondeo(): int
    {
        return max(1, (int) config('database.puntopan_respaldo.ttl_sondeo', 30));
    }

    private function timeoutSondeo(): int
    {
        return max(1, (int) config('database.puntopan_respaldo.timeout_sondeo', 2));
    }

    /**
     * La sesión no existe en consola ni en colas; ahí solo puede haber
     * aprobación del operador si alguien la extendió a la cola al encolar
     * (o si el respaldo está `forzado`).
     */
    private function sesion(): ?Session
    {
        try {
            return app('session')->driver();
        } catch (Throwable) {
            return null;
        }
    }

    private function avisarUsoDeCopiaLocal(): void
    {
        $vigencia = now()->addSeconds($this->ttlSondeo());

        if (! Cache::add(self::CLAVE_AVISO, true, $vigencia)) {
            return;
        }

        Log::warning('puntopan: servidor remoto no disponible; se usa la copia local.', [
            'remoto' => $this->hostRemoto().':'.$this->puertoRemoto(),
            'local' => $this->hostLocal().':'.$this->puertoLocal(),
            'base' => $this->baseLocal(),
            'aprobado_hasta' => $this->aprobadoHasta()?->toDateTimeString(),
            'forzado' => $this->forzado(),
        ]);
    }
}
