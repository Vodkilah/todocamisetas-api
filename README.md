# TodoCamisetas API — Examen Transversal Final (Desarrollo Backend)

API RESTful en **PHP puro (sin frameworks)** para el caso **TodoCamisetas**:
gestión de inventario de camisetas, clientes B2B (90minutos, tdeportes),
tallas (relación N:M) y cálculo dinámico de `precio_final` según la
categoría del cliente.

---

## Tarea 1 — Arquitectura del proyecto

### 1.1 Estructura de directorios

```
todocamisetas/
├── docker-compose.yml          # Orquesta app (PHP+Apache), MySQL y Adminer
├── docker/
│   └── php/
│       └── Dockerfile          # Imagen PHP 8.2 + Apache + pdo_mysql
├── database/
│   └── schema.sql               # DDL + datos de prueba (seed)
├── public/                       # DocumentRoot del servidor web
│   ├── index.php                 # Front Controller: define TODAS las rutas
│   ├── autoload.php               # Autoloader PSR-4 manual (App\ -> /src)
│   └── .htaccess                  # Redirige todo a index.php (mod_rewrite)
├── src/
│   ├── Config/
│   │   └── Database.php          # Conexión PDO (Singleton)
│   ├── Models/
│   │   ├── Camiseta.php           # CRUD camisetas + tallas + precio_final
│   │   ├── Cliente.php            # CRUD clientes + camisetas por cliente
│   │   └── Talla.php              # CRUD catálogo de tallas
│   ├── Controllers/
│   │   ├── CamisetaController.php # Endpoints /api/camisetas[...]
│   │   ├── ClienteController.php  # Endpoints /api/clientes[...]
│   │   └── TallaController.php    # Endpoints /api/tallas[...]
│   ├── Http/
│   │   └── Response.php           # Helper para respuestas JSON estándar
│   └── Router/
│       └── Router.php             # Enrutador con expresiones regulares
├── postman/
│   └── TodoCamisetas.postman_collection.json
├── openapi.yaml                   # Documentación OpenAPI 3.0 (Swagger)
└── README.md                      # Este informe
```

### 1.2 Rol de cada componente

- **`public/index.php` (Front Controller / Router)**: único punto de
  entrada. Recibe toda solicitud HTTP (gracias a `.htaccess`), define las
  rutas con expresiones regulares y delega a los métodos estáticos de los
  controladores. Aquí también se configuran las cabeceras CORS.

- **`src/Router/Router.php`**: enrutador genérico. Registra rutas
  `(método, patrón regex, handler)`. En `resolve()` recorre las rutas, hace
  `preg_match` contra la URI solicitada y, si coincide el patrón **y** el
  método HTTP, invoca el controlador pasando los grupos capturados (`{id}`,
  `{tallaId}`, etc.) como parámetros. Si la URI coincide pero el método no,
  responde `405`; si no coincide ninguna ruta, responde `404`.

- **`src/Controllers/`**: contienen la lógica de cada endpoint. Cada
  controlador define métodos estáticos (`index`, `show`, `store`, `update`,
  `destroy`, y métodos adicionales para sub-recursos como `tallas*`). Aquí
  se **validan los datos obligatorios** del request, se devuelven los
  códigos HTTP correctos (200, 201, 404, 409, 422) y se delega el acceso a
  datos a los modelos.

- **`src/Models/`**: encapsulan **todo** el acceso a la base de datos
  mediante PDO y consultas preparadas (`all()`, `find()`, `create()`,
  `update()`, `delete()`, además de métodos específicos como
  `getTallas()`, `asignarTalla()`, `calcularPrecioFinal()`,
  `getCamisetas()` para el listado de camisetas por cliente). Ningún SQL
  vive en los controladores: esto da **modularidad** (se podría cambiar
  MySQL por otro motor modificando solo esta capa) y testabilidad.

- **`src/Config/Database.php`**: clase `Database` con patrón Singleton que
  entrega una única conexión `PDO`. Se eligió **PDO** porque:
  - Permite **consultas preparadas** (protección contra SQL Injection).
  - Es agnóstico al motor de base de datos.
  - Maneja errores como excepciones (`PDO::ERRMODE_EXCEPTION`), facilitando
    un manejo de errores centralizado.

- **`src/Http/Response.php`**: helper que centraliza el envío de JSON,
  garantizando que **toda** respuesta incluya
  `Content-Type: application/json` y un formato consistente
  (`{ "data": ... }` o `{ "error": ..., "errores": [...] }`).

- **`database/schema.sql`**: define las tablas, llaves foráneas (con
  `ON DELETE CASCADE` para `camiseta_tallas` y `ON DELETE RESTRICT` para
  `pedidos`) y datos de prueba (2 clientes, 3 camisetas, tallas y pedidos).

- **`docker-compose.yml` + `docker/php/Dockerfile`**: levantan 3 servicios:
  `app` (PHP 8.2 + Apache, con `pdo_mysql` habilitado y `mod_rewrite`
  activo), `db` (MySQL 8, carga `schema.sql` automáticamente al iniciar) y
  `adminer` (administración visual de la BD en `http://localhost:8081`).

> No existen "Vistas" en el sentido tradicional MVC, ya que se trata de una
> API que solo retorna JSON (no HTML). Por eso no hay carpeta `views`.

### 1.3 Modelo de datos

```
┌──────────────┐        ┌──────────────────┐        ┌────────────┐
│   clientes    │        │     pedidos       │        │  camisetas  │
├──────────────┤        ├──────────────────┤        ├────────────┤
│ id (PK)       │1      *│ id (PK)            │*      1│ id (PK)     │
│ nombre_comercial│◄──────┤ cliente_id (FK)    ├──────►│ titulo      │
│ rut           │        │ camiseta_id (FK)   │        │ club        │
│ direccion     │        │ cantidad           │        │ pais        │
│ categoria     │        │ created_at         │        │ tipo        │
│ contacto_nombre│       └──────────────────┘        │ color       │
│ contacto_email│                                      │ precio      │
│ porcentaje_oferta│                                    │ precio_oferta│
└──────────────┘                                      │ detalles    │
                                                        │ codigo_producto│
                                                        └─────┬──────┘
                                                              │1
                                                              │
                                                              │*
                                                  ┌────────────────────┐
                                                  │  camiseta_tallas    │
                                                  ├────────────────────┤
                                                  │ id (PK)             │
                                                  │ camiseta_id (FK) ───┤ ON DELETE CASCADE
                                                  │ talla_id (FK)    ───┤ ON DELETE CASCADE
                                                  │ stock               │
                                                  └─────────┬──────────┘
                                                             │*
                                                             │1
                                                       ┌──────────┐
                                                       │  tallas   │
                                                       ├──────────┤
                                                       │ id (PK)   │
                                                       │ nombre    │
                                                       └──────────┘
```

**Relaciones:**
- `camisetas` ⟷ `tallas`: **muchos a muchos** vía `camiseta_tallas`
  (incluye `stock` por talla/camiseta). `ON DELETE CASCADE` en ambas FKs:
  si se borra una camiseta o una talla, se eliminan automáticamente sus
  relaciones.
- `clientes` ⟷ `camisetas`: **muchos a muchos** vía `pedidos` (incluye
  `cantidad`). Esta tabla permite implementar "listar camisetas por
  cliente" (Tarea 5) y, con `ON DELETE RESTRICT`, **impide eliminar** un
  cliente o camiseta que tenga pedidos asociados (regla de integridad
  pedida en la Tarea 5).

---

## Tarea 2 — Enrutamiento (PHP puro, expresiones regulares)

Todas las rutas se definen en `public/index.php` y son resueltas por
`src/Router/Router.php`. El método HTTP se obtiene con
`$_SERVER['REQUEST_METHOD']` y la URI con `$_SERVER['REQUEST_URI']`.

| # | Expresión regular | Método | Propósito |
|---|---|---|---|
| 1 | `#^/api/health$#` | GET | Healthcheck de la API |
| 2 | `#^/api/camisetas$#` | GET | Listar camisetas (opcional `?cliente_id=`) |
| 3 | `#^/api/camisetas$#` | POST | Crear una camiseta |
| 4 | `#^/api/camisetas/([0-9]+)$#` | GET | Detalle de camiseta (tallas + precio_final), opcional `?cliente_id=` |
| 5 | `#^/api/camisetas/([0-9]+)$#` | PUT | Actualizar camiseta (parcial) |
| 6 | `#^/api/camisetas/([0-9]+)$#` | DELETE | Eliminar camiseta (rechaza si tiene pedidos) |
| 7 | `#^/api/camisetas/([0-9]+)/tallas$#` | GET | Listar tallas + stock de una camiseta |
| 8 | `#^/api/camisetas/([0-9]+)/tallas$#` | POST | Asociar talla (nueva o existente) con stock a una camiseta |
| 9 | `#^/api/camisetas/([0-9]+)/tallas/([0-9]+)$#` | PUT | Actualizar stock de una talla de la camiseta |
| 10 | `#^/api/camisetas/([0-9]+)/tallas/([0-9]+)$#` | DELETE | Desvincular una talla de la camiseta |
| 11 | `#^/api/clientes$#` | GET | Listar clientes |
| 12 | `#^/api/clientes$#` | POST | Crear cliente |
| 13 | `#^/api/clientes/([0-9]+)$#` | GET | Detalle de cliente |
| 14 | `#^/api/clientes/([0-9]+)$#` | PUT | Actualizar cliente (parcial) |
| 15 | `#^/api/clientes/([0-9]+)$#` | DELETE | Eliminar cliente (rechaza si tiene pedidos) |
| 16 | `#^/api/clientes/([0-9]+)/camisetas$#` | GET | Listar camisetas pedidas por un cliente, con `precio_final` |
| 17 | `#^/api/tallas$#` | GET | Listar catálogo de tallas |
| 18 | `#^/api/tallas$#` | POST | Crear talla en el catálogo |
| 19 | `#^/api/tallas/([0-9]+)$#` | GET | Detalle de talla |
| 20 | `#^/api/tallas/([0-9]+)$#` | PUT | Actualizar nombre de talla |
| 21 | `#^/api/tallas/([0-9]+)$#` | DELETE | Eliminar talla (cascada en `camiseta_tallas`) |

Si la URI coincide con un patrón pero el método HTTP no, se responde
**405 Method Not Allowed**. Si ninguna ruta coincide, **404 Not Found**.

### Validaciones por endpoint (resumen)

- **POST/PUT camisetas**: `titulo`, `club`, `pais`, `tipo`, `color`,
  `precio`, `codigo_producto` son obligatorios al crear; `precio` y
  `precio_oferta` deben ser numéricos ≥ 0; `codigo_producto` único
  (formato alfanumérico 3–30 chars).
- **POST/PUT clientes**: `nombre_comercial`, `rut`, `direccion`,
  `contacto_nombre`, `contacto_email` obligatorios al crear;
  `categoria` ∈ {Regular, Preferencial}; `contacto_email` formato válido;
  `porcentaje_oferta` entre 0 y 100; `rut` único.
- **POST/PUT tallas (camiseta)**: requiere `nombre` o `talla_id`, y
  `stock` entero ≥ 0.
- **DELETE camisetas/clientes**: rechazados con `409` si existen
  registros relacionados en `pedidos`.

---

## Regla de negocio: `precio_final`

Implementada en `Camiseta::calcularPrecioFinal()`:

- Si el cliente consultante (`?cliente_id=`) es de categoría
  **Preferencial** (ej. `90minutos`) **y** la camiseta tiene
  `precio_oferta` definido → `precio_final = precio_oferta`.
- En cualquier otro caso (cliente **Regular** como `tdeportes`, sin
  `cliente_id`, o sin `precio_oferta`) → `precio_final = precio`.

---

## Cómo ejecutar el proyecto

```bash
docker-compose up -d --build
```

- API: `http://localhost:8080/api/health`
- **Swagger UI**: `http://localhost:8082` (carga automáticamente
  `openapi.yaml`; permite usar "Try it out" para ejecutar cada endpoint
  contra la API real — útil para el video de la entrega)
- Adminer (BD): `http://localhost:8081` (sistema: MySQL, servidor: `db`,
  usuario: `todocamisetas_user`, contraseña: `todocamisetas_pass`,
  base de datos: `todocamisetas`)
- La base de datos se inicializa automáticamente con `database/schema.sql`
  (incluye datos de prueba: clientes `90minutos` (id=1, Preferencial) y
  `tdeportes` (id=2, Regular), 3 camisetas y sus tallas/pedidos).

### Probar la API

Importar `postman/TodoCamisetas.postman_collection.json` en Postman, o
usar `curl`:

```bash
curl http://localhost:8080/api/camisetas
curl "http://localhost:8080/api/camisetas/1?cliente_id=1"
curl http://localhost:8080/api/clientes/1/camisetas
```

La documentación completa de endpoints (request/response/errores) está en
`openapi.yaml` (importable en Swagger Editor / Swagger UI).

---

## Equipo

- **Nombre del equipo:** _(completar)_
- **Integrantes:** Javier Arroyo _(agregar resto si aplica)_
