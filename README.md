# files-browser (Docker + PHP + .env)

Este proyecto levanta Apache+PHP en Docker y lista el contenido de un directorio montado dentro del contenedor.

## Config
Edita `.env`:
- APP_MODE=LOCAL
- LOCAL_BASE_PATH=/mnt/windows_share

## Run (Windows / Linux)
En la raíz del proyecto:
- docker compose up -d --build

## URL
- http://localhost:8080

## Cómo probar sin Windows remoto
Pon archivos en `./data` (host). Esa carpeta se monta como `/mnt/windows_share` dentro del contenedor.

Cuando tengas el share real montado en un servidor Ubuntu, reemplaza el volumen `./data:/mnt/windows_share`
por `/mnt/windows_share:/mnt/windows_share`.
