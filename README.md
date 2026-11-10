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

En el primer inicio, el contenedor instala las dependencias, genera `APP_KEY` si falta y ejecuta las migraciones. La API queda disponible en `http://localhost:8000` por defecto. Puedes comprobarla en `http://localhost:8000/api/v1/health`.

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
| `SMARTLOG_API_KEY`, `LLM_DRIVER`, `OPENAI_API_KEY` | Clave de API y proveedor de análisis (`mock` u `openai`). La clave de OpenAI se necesita al usar ese proveedor. El Compose de producción exige que esté configurada. |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Conexión y credenciales de PostgreSQL. Dentro de Compose, el host es `db` y el puerto interno es `5432`. |
| `CACHE_STORE`, `REDIS_CLIENT`, `REDIS_HOST`, `REDIS_PORT` | Caché y conexión con Redis. Dentro de Compose, el host es `redis` y el puerto interno es `6379`. |
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

Esto elimina los contenedores y la red, pero conserva los volúmenes de PostgreSQL y Composer. `docker compose down -v` también elimina esos volúmenes y los datos de desarrollo.

## Producción

Crea `.env.production` a partir del ejemplo y configura sus valores antes de levantar los servicios:

```bash
[ -f .env.production ] || cp .env.production.example .env.production
```

Configura como mínimo `APP_KEY`, `APP_URL`, `SMARTLOG_API_KEY`, `OPENAI_API_KEY`, `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD`. Usa la URL pública real en `APP_URL`. Puedes generar un `APP_KEY` ejecutando:

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

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml ps
curl http://localhost:8080/api/v1/health
```

El puerto publicado de la aplicación es `APP_PORT` (por defecto `8080`). PostgreSQL y Redis solo están expuestos dentro de la red de Compose. Para detener producción sin borrar los datos:

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml down
```

No agregues `-v` salvo que quieras eliminar también el volumen de PostgreSQL.
