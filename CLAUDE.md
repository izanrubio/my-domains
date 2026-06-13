# CLAUDE.md — Reglas del proyecto

Gestor de dominios. Backend Laravel 13 (API REST + Sanctum) en la raíz, frontend React 19 (Vite) en `/frontend`. NO se usa Inertia.

## Flujo de trabajo obligatorio

Después de terminar **cada tarea** que se te pida:

1. **Ejecuta los tests.**
   - Backend: `php artisan test`
   - Frontend (si la tarea tocó el front): el runner configurado (`npm run test` en `/frontend`).
2. **Si todos los tests pasan:**
   - `git add -A`
   - `git commit` con un mensaje claro y descriptivo de lo hecho (en imperativo, p. ej. "Add CloudflareService and DNS sync command").
   - `git push`
3. **Si falla algún test:**
   - Usa `/goal` para fijar el objetivo (dejar la suite en verde) y trabaja de forma iterativa hasta conseguirlo.
   - No pares hasta que TODOS los tests pasen, y solo entonces haz el commit y push del paso 2.

## Restricciones del flujo (importante)

- **Nunca** hagas que un test pase de forma falsa: no borres, comentes, vacíes ni debilites tests para forzar el verde. El objetivo es código correcto, no una suite trucada.
- Si un test falla por un motivo legítimo de diseño (un requisito ambiguo o un test mal planteado), **párate y pregúntame** antes de cambiarlo — no lo modifiques por tu cuenta.
- Si tras varios intentos razonables sigues bloqueado por algo externo (credenciales, un servicio caído, una dependencia que no instala), para, deja el trabajo en una rama o sin pushear, y explícame el bloqueo en vez de seguir en bucle.
- Cada tarea = un commit atómico. No acumules varios cambios sin relación en el mismo commit.

## Tests que debe haber

- Servicios (`CloudflareService`, `WhoisService`) con la API externa mockeada — nunca pegues llamadas reales en los tests.
- Endpoints API (feature tests) con autenticación Sanctum.
- Comandos artisan (`domains:sync`, `domains:check-expiry`).

## Convenciones

- PHP 8.3+, sigue las convenciones de Laravel. Form Requests para validación.
- Token de Cloudflare cifrado en `Setting`, nunca en texto plano ni en logs.
- Mensajes de commit en inglés, imperativo, una línea de resumen + cuerpo si hace falta.
