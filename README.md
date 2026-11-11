# SmartLog Analyzer

API para recibir logs de microservicios y solicitar su análisis. La arquitectura y las decisiones técnicas están documentadas en [ARCHITECTURE.md](ARCHITECTURE.md).

## Requisitos

- Docker.

## Desarrollo

Desde la raíz del proyecto, crea el archivo de entorno si todavía no existe:

```bash
[ -f .env ] || cp .env.example .env
```

En `.env` puedes ajustar los puertos (`APP_PORT`, `DB_PORT`, `REDIS_PORT`) y elegir `LLM_DRIVER=mock` para trabajar sin una clave de OpenAI, o `LLM_DRIVER=openai` y configurar `OPENAI_API_KEY`.

Construye y levanta la aplicación, PostgreSQL y Redis:

```bash
docker compose up --build -d
docker compose ps
```

En el primer inicio, el contenedor instala las dependencias en `vendor/` dentro del proyecto, genera `APP_KEY` si falta y ejecuta las migraciones. Así el IDE también puede reconocer los tipos de Laravel. La API queda disponible en `http://localhost:8000` por defecto.

Para cargar los logs de ejemplo:

```bash
docker compose exec app php artisan db:seed
```

### Comprobar el estado de la API

Las rutas bajo `/api/v1` requieren una clave Bearer. Para comprobar `/health`, reemplaza `tu-clave-configurada` por el valor de `SMARTLOG_API_KEY` definido en `.env`:

```bash
curl -H "Authorization: Bearer tu-clave-configurada" http://localhost:8000/api/v1/health
```

La ruta ejecuta `SELECT 1` en PostgreSQL y `PING` en Redis. Devuelve `200 OK` con `{"status":"ok"}` si ambos servicios responden, o `503 Service Unavailable` con `{"status":"unavailable"}` si alguno falla.

### Analizar logs con OpenAI

El entorno usa `LLM_DRIVER=mock` por defecto. Para usar OpenAI, configura estas variables en `.env`:

```dotenv
LLM_DRIVER=openai
OPENAI_API_KEY=tu-clave-de-openai
```

Puedes ajustar `OPENAI_MODEL` (por defecto `gpt-4.1-mini`), `OPENAI_TIMEOUT` (30 segundos), `OPENAI_CONNECT_TIMEOUT` (5 segundos) y `OPENAI_MAX_OUTPUT_TOKENS` (1500). Después de cambiar `.env`, recrea el contenedor para que reciba las variables:

```bash
docker compose up -d --force-recreate app
```

Con logs ya cargados, envía sus IDs al endpoint de análisis. Reemplaza `tu-clave-configurada` por `SMARTLOG_API_KEY` y los IDs por los que existan en tu base:

```bash
curl -i -X POST http://localhost:8000/api/v1/logs/analyze \
  -H "Authorization: Bearer tu-clave-configurada" \
  -H "Content-Type: application/json" \
  -d '{"log_ids":[1,2,3]}'
```

Un análisis nuevo responde `201 Created` e incluye su ID, por ejemplo `{"id":42}`. Consúltalo con:

```bash
curl -H "Authorization: Bearer tu-clave-configurada" \
  http://localhost:8000/api/v1/analysis/42
```

La consulta devuelve el estado, el resultado y el error del análisis. 
- Si OpenAI falla o devuelve una respuesta inválida, el POST responde `502 Bad Gateway`. 
- Si se agota el tiempo de espera, responde `504 Gateway Timeout`.

En ambos casos devuelve `id` y `message`, y el análisis fallido se puede consultar por ese ID.

Las pruebas de la integración simulan las respuestas de OpenAI y no necesitan una clave real.

### Reutilizar análisis con Redis

Si repetís el POST con los mismos IDs, la API reutiliza un análisis completado mientras su referencia esté en Redis. `cache=true` es el valor predeterminado. El orden de los IDs no cambia la selección: `[1,2]` y `[2,1]` usan la misma referencia. Redis guarda solo el ID; el resultado completo permanece en PostgreSQL.

Para pedir un análisis nuevo aunque exista uno anterior, agregá `?cache=false`:

```bash
curl -i -X POST 'http://localhost:8000/api/v1/logs/analyze?cache=false' \
  -H "Authorization: Bearer tu-clave-configurada" \
  -H "Content-Type: application/json" \
  -d '{"log_ids":[1,2,3]}'
```

Con los mismos IDs, la secuencia esperada es:

| Pedido | Respuesta |
| --- | --- |
| Primer POST sin `cache` | `201 Created`, ID nuevo. |
| Repetirlo sin `cache` | `200 OK`, mismo ID. |
| POST con `?cache=false` | `201 Created`, otro ID. |
| Repetirlo sin `cache` | `200 OK`, el último ID. |

`ANALYSIS_CACHE_TTL` define cuánto dura la referencia en Redis (3600 segundos por defecto). Al vencer, se puede generar otro análisis sin borrar el historial en PostgreSQL. `ANALYSIS_LOCK_SECONDS` define la duración máxima del bloqueo que evita generar simultáneamente el mismo análisis (60 segundos por defecto).

Cada pedido, incluso si reutiliza un resultado, cuenta para los límites de solicitudes.

Si ya se está generando un análisis para esos logs, el POST devuelve `409 Conflict` y se puede reintentar. Si Redis falla antes de generar, devuelve `503 Service Unavailable`. Si falla la publicación de la referencia en Redis después de guardar el análisis en PostgreSQL, devuelve `201 Created` con el ID guardado.

## Puertos, red y variables de entorno

| Servicio | Puerto del contenedor | Desarrollo: puerto publicado en el host | Producción |
| --- | ---: | ---: | --- |
| Aplicación | `80` | `APP_PORT` (por defecto `8000`) | `APP_PORT` (por defecto `8080`) |
| PostgreSQL | `5432` | `DB_PORT` (por defecto `5432`) → `5432` | Solo red interna, `5432` |
| Redis | `6379` | `REDIS_PORT` (por defecto `6379`) → `6379` | Solo red interna, `6379` |

Compose crea una red privada para cada entorno: `smartlog-dev_default` y `smartlog-prod_default`. Los contenedores se encuentran por el nombre del servicio. La aplicación se conecta a PostgreSQL en `db:5432` y a Redis en `redis:6379`. En desarrollo, `DB_PORT` y `REDIS_PORT` cambian el puerto publicado en el host. No cambian los puertos internos. En producción, PostgreSQL y Redis no publican puertos al host.

Los ejemplos de configuración están en [`.env.example`](.env.example) y [`.env.production.example`](.env.production.example). Sus variables se agrupan así:

| Variables | Uso |
| --- | --- |
| `APP_NAME`, `APP_ENV`, `APP_DEBUG`, `APP_KEY`, `APP_URL`, `APP_PORT` | Nombre, entorno, modo de depuración, clave de Laravel, URL y puerto web. `APP_URL` debe coincidir con el puerto publicado. En desarrollo `APP_KEY` se genera al iniciar. En producción se configura antes de levantar los servicios. |
| `APP_LOCALE`, `APP_FALLBACK_LOCALE`, `APP_FAKER_LOCALE` | Idioma de la aplicación y de los datos de prueba. |
| `LOG_CHANNEL`, `LOG_STACK`, `LOG_LEVEL` | Destino y nivel de detalle de los logs. `LOG_STACK` se usa en desarrollo. |
| `SMARTLOG_API_KEY`, `API_RATE_LIMIT_PER_MINUTE`, `LOG_ANALYSIS_RATE_LIMIT_PER_MINUTE` | Clave de API y límites por IP: 60 solicitudes generales y 10 análisis por minuto por defecto. |
| `LLM_DRIVER`, `OPENAI_API_KEY` | Proveedor de análisis (`mock` u `openai`). La clave de OpenAI se necesita al usar ese proveedor. |
| `OPENAI_MODEL`, `OPENAI_TIMEOUT`, `OPENAI_CONNECT_TIMEOUT`, `OPENAI_MAX_OUTPUT_TOKENS` | Modelo y límites de tiempo y de salida de OpenAI. |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Conexión y credenciales de PostgreSQL. Dentro de Compose, el host es `db` y el puerto interno es `5432`. |
| `CACHE_STORE`, `REDIS_CLIENT`, `REDIS_HOST`, `REDIS_PORT` | Caché y conexión con Redis. Dentro de Compose, el host es `redis` y el puerto interno es `6379`. |
| `ANALYSIS_CACHE_TTL`, `ANALYSIS_LOCK_SECONDS` | Duración en segundos de la referencia reutilizable y del bloqueo del análisis: 3600 y 60 por defecto. |
| `SESSION_DRIVER`, `FILESYSTEM_DISK`, `QUEUE_CONNECTION` | Almacenamiento de sesiones, archivos y trabajos en cola. |

Para ver los logs de la aplicación:

```bash
docker compose logs -f app
```

Para ejecutar las pruebas:

```bash
docker compose exec app php artisan test
```

Para detener el entorno:

```bash
docker compose down
```

Esto elimina los contenedores y la red, pero conserva el volumen de PostgreSQL. `vendor/` permanece en el proyecto local y Git lo ignora. `docker compose down -v` también elimina el volumen y los datos de desarrollo.

## Producción

Crea `.env.production` a partir del ejemplo y configura sus valores antes de levantar los servicios:

```bash
[ -f .env.production ] || cp .env.production.example .env.production
```

Configura como mínimo `APP_KEY`, `APP_URL`, `SMARTLOG_API_KEY`, `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD`. Si usas `LLM_DRIVER=openai`, configura también `OPENAI_API_KEY`. Usa la URL pública real en `APP_URL`. Puedes generar un `APP_KEY` ejecutando:

```bash
docker run --rm php:8.5-cli php -r 'echo "base64:".base64_encode(random_bytes(32)).PHP_EOL;'
```

Copia el valor que imprime en `APP_KEY`. No subas `.env.production` al repositorio.

Compose no carga `.env.production` automáticamente. Pasa el archivo explícitamente en cada comando. Ejecuta las migraciones antes de iniciar o actualizar la aplicación:

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml run --build --rm app php artisan migrate --path=db/migrations --force
docker compose --env-file .env.production -f docker-compose.prod.yml up -d
```

Comprueba los servicios y la ruta de salud:

En el siguiente comando, reemplaza `tu-clave-configurada` por el valor de `SMARTLOG_API_KEY` definido en `.env.production`.

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml ps
curl -H "Authorization: Bearer tu-clave-configurada" http://localhost:8080/api/v1/health
```

El puerto publicado de la aplicación es `APP_PORT` (por defecto `8080`). PostgreSQL y Redis solo están expuestos dentro de la red de Compose. Para detener producción sin borrar los datos:

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml down
```

No agregues `-v` salvo que quieras eliminar también el volumen de PostgreSQL.
