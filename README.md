# HuellaVet — Backend

Repositorio Backend del Trabajo Práctico Integrador de **Programación en Ambiente Web (PAW)** — UNLu, 2026 — Grupo **La 25**.

## Autores

| Integrante | Legajo |
| --- | --- |
| Ana Paula Romero | 195388 |
| Maria Trinidad Lopez | 197958 |
| Valentino Aimale | 197961 |
| Cristian Tomás Anito | 158887 |

## Documentación
- [Seguridad](./SEGURIDAD.md)
- [Contrato de la API de autenticación](./docs/api/contrato.md)

## Cómo correrlo

Este repo es el módulo **Backend**: registro, inicio de sesión y sesión de los usuarios (clientes, rescatistas y el veterinario). Los endpoints están en el [contrato de la API](./docs/api/contrato.md).

El módulo no se levanta solo: corre adentro del contenedor que arma el repo **ApiGateway**, junto con Nginx, el código común (`comun/`) y los demás módulos. Por eso todo se levanta desde ApiGateway, con Docker.

### Qué hace falta

- [Docker Desktop](https://www.docker.com/products/docker-desktop/)
- PHP 8 y [Composer](https://getcomposer.org/), solo para correr las pruebas

### Levantar el sistema

1. Clonar ApiGateway **con los submódulos** (así trae también este repo, en la carpeta `Backend/`):

   ```bash
   git clone --recurse-submodules https://github.com/romeroppau/PAW--ApiGateway--TPIntegrador.git
   cd PAW--ApiGateway--TPIntegrador
   ```

2. Copiar el archivo de ejemplo de las variables y completarlo:

   ```bash
   cp .env.example .env
   ```

   El `.env` tiene las contraseñas y las claves, así que **nunca se sube** al repositorio. Las claves del JWT se generan así (en una carpeta que no sea de ningún repo), y las dos líneas de `claves.env` se copian al `.env`:

   ```bash
   openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:2048 -out jwt_privada.pem
   openssl pkey -in jwt_privada.pem -pubout -out jwt_publica.pem
   printf 'JWT_PRIVATE_KEY="%s"\n' "$(awk 'NF {printf "%s\\n", $0}' jwt_privada.pem)" > claves.env
   printf 'JWT_PUBLIC_KEY="%s"\n' "$(awk 'NF {printf "%s\\n", $0}' jwt_publica.pem)" >> claves.env
   ```

3. Levantar los contenedores (la aplicación y MySQL):

   ```bash
   docker compose up -d --build
   ```

4. Crear las tablas, los usuarios de MySQL de cada módulo y sus permisos:

   ```bash
   docker compose --profile tools run --rm migrator
   ```

5. Crear al veterinario. No se registra por la web: se carga con un script. Se cambian los datos de ejemplo por los reales:

   ```bash
   docker compose exec app sh -c 'DB_USER="$BACKEND_DB_USER" DB_PASSWORD="$BACKEND_DB_PASSWORD" VET_NOMBRE="Nombre del veterinario" VET_EMAIL="vet@huellavet.com" VET_PASSWORD="una-contraseña-segura" php /app/Backend/scripts/crear_veterinario.php'
   ```

   Si el veterinario ya existe, el script no hace nada, así que se puede correr más de una vez.

6. Comprobar que el módulo responde:

   ```bash
   curl http://localhost:10000/api/backend/salud
   ```

   Tiene que devolver `{"estado":"funciona","modulo":"Backend"}`.

> **En Render:** si nadie entró en los últimos 15 minutos, el servicio se apaga y la primera visita tarda alrededor de un minuto en responder. Conviene abrir la página unos minutos antes de mostrarla.

### Pruebas

Las pruebas automáticas usan PHPUnit. Desde la carpeta `Backend/` (adentro de ApiGateway):

```bash
composer install
vendor/bin/phpunit --testdox
```

`composer install` se corre una sola vez: instala PHPUnit en la carpeta `vendor/`, que tampoco se sube al repositorio.

Si se trabaja con este repo suelto (fuera de ApiGateway), hay que indicar dónde está la carpeta `comun/` de ApiGateway:

```bash
COMUN_DIR="ruta/a/PAW--ApiGateway--TPIntegrador/comun" vendor/bin/phpunit --testdox
```

Hoy hay pruebas del JWT (que el token que crea Backend lo acepte la clase `Auth` de `comun/`, y que rechace tokens alterados, vencidos o firmados con otra clave) y de las validaciones del registro.

### Cómo está organizado el código

| Archivo | Qué hace |
| --- | --- |
| `src/Index.php` | Punto de entrada: carga el código, registra las rutas y responde 500 si algo falla de forma inesperada |
| `src/AuthControlador.php` | Un método por endpoint: registros, login, `/yo`, logout y salud |
| `src/Jwt.php` | Crea el JWT de la sesión, firmado con la clave privada |
| `src/Sesion.php` | Arma la sesión: el código CSRF, el JWT y la cookie |
| `src/ValidadorRegistro.php` | Valida los datos de los registros |
| `src/UsuarioRepositorio.php` | Todas las consultas a la tabla `usuario`, y el bloqueo por intentos fallidos |
| `src/Cliente.php`, `src/Veterinario.php`, `src/Rescatista.php` | Los tres tipos de usuario, como en el diagrama de clases |
| `scripts/crear_veterinario.php` | Script para cargar al veterinario |
| `tests/` | Las pruebas automáticas |

La tabla `usuario` se crea con la migración `base/001_backend_usuario.sql`, que está en el repo ApiGateway.

---

## Introducción

Este repositorio forma parte del desarrollo del **Trabajo Práctico Final Integrador de Conocimientos** de la asignatura **Programación en Ambiente Web (PAW)** de la Universidad Nacional de Luján.

El proyecto consiste en el diseño y desarrollo de **HuellaVet**, una aplicación web orientada a la gestión integral de una veterinaria, desarrollada por el grupo **La 25**.

La aplicación busca centralizar y facilitar la gestión de pacientes, historias clínicas, turnos, productos, inventario, ventas, facturación y adopciones, ofreciendo funcionalidades diferenciadas para clientes y veterinarios/administradores.

El proyecto será desarrollado de manera incremental durante la cursada, incorporando progresivamente las distintas funcionalidades definidas para la aplicación.

---

## Objetivo

El objetivo principal del proyecto es desarrollar una **aplicación web funcional para la gestión integral de una veterinaria**, accesible desde dispositivos móviles y de escritorio.

HuellaVet busca centralizar información y procesos de la veterinaria, permitiendo mejorar la gestión de pacientes, consultas, productos, inventario y ventas, además de brindar a los clientes un espacio para consultar y administrar la información relacionada con sus mascotas.

Entre los principales objetivos se encuentran:

* Centralizar las historias clínicas de las mascotas.
* Permitir a los clientes consultar y administrar la información de sus mascotas.
* Gestionar turnos y consultas veterinarias.
* Registrar diagnósticos e insumos utilizados durante las consultas.
* Generar facturas asociadas a las consultas y compras.
* Gestionar productos y stock.
* Controlar los insumos internos de la veterinaria.
* Permitir la venta de productos con retiro en el local.
* Gestionar el proceso de adopción, desde la publicación por rescatistas validados hasta el chequeo veterinario obligatorio.
* Generar reportes sobre ventas, turnos e inventario.
* Generar recomendaciones automáticas a partir de los datos del sistema.
* Implementar el programa de puntos Huellitas para los clientes.
* Integrar WhatsApp como medio de comunicación para la solicitud y coordinación de turnos, recordatorios y promociones.

---

## Descripción de la aplicación

**HuellaVet** es una aplicación web destinada a una veterinaria que integra funcionalidades de gestión y atención al cliente.

El sistema contempla tres roles principales:

### Cliente

El cliente podrá:

* Registrarse e iniciar sesión.
* Gestionar sus mascotas.
* Consultar la ficha, historia clínica y calendario sanitario de sus mascotas.
* Consultar turnos próximos y anteriores.
* Solicitar turnos mediante WhatsApp.
* Consultar y comprar productos del catálogo, con recomendaciones según sus mascotas.
* Seleccionar el retiro de productos en el local.
* Consultar sus facturas.
* Sumar y canjear puntos Huellitas.
* Postularse para adoptar y seguir el estado de sus postulaciones.
* Recibir notificaciones: recordatorios de vacunas, avisos de recompra y promociones.

### Rescatista

El rescatista podrá:

* Registrarse y quedar pendiente hasta que el veterinario lo apruebe.
* Publicar animales en adopción, con foto, descripción, historial clínico y estado. Es el único rol que puede publicar en adopciones.
* Revisar las postulaciones de cada animal y elegir al adoptante.

### Veterinario / Administrador

El veterinario o administrador podrá:

* Gestionar pacientes y sus fichas clínicas.
* Registrar diagnósticos e insumos utilizados durante las consultas.
* Registrar en la agenda los turnos coordinados por WhatsApp.
* Gestionar productos del catálogo, con fecha de vencimiento.
* Administrar el stock.
* Gestionar los insumos internos de la veterinaria.
* Registrar movimientos de inventario.
* Gestionar facturación.
* Validar rescatistas.
* Moderar publicaciones de adopción y cerrar las adopciones tras el chequeo obligatorio.
* Consultar reportes de ventas, turnos e inventario.

---

## Funcionalidades principales

Las principales funcionalidades contempladas para el proyecto son:

* **Gestión de pacientes:** administración de clientes, mascotas, fichas e historias clínicas.
* **Gestión de turnos:** solicitud y coordinación de turnos mediante WhatsApp, junto con la visualización de turnos próximos y anteriores.
* **Historias clínicas:** registro de diagnósticos e insumos utilizados durante las consultas.
* **Facturación:** generación de facturas asociadas a consultas y compras.
* **Tienda:** catálogo de productos, pago por la web y retiro en el local.
* **Inventario e insumos:** gestión de productos, insumos internos, stock y movimientos de inventario.
* **Adopciones:** proceso con estados (publicada, con postulantes, adoptante elegido, turno de chequeo y vacunación, adopción finalizada). Solo publican los rescatistas validados, el chequeo en la veterinaria es obligatorio y, al finalizar, la ficha del animal se transfiere al adoptante.
* **Reportes:** información sobre ventas, turnos atendidos, stock y movimientos de inventario.
* **Motor de recomendaciones:** descuentos por vencimiento, promociones para franjas con pocos turnos, aviso de recompra de alimento, calendario sanitario, aviso de stock bajo y productos recomendados según la mascota.
* **Huellitas:** puntos que el cliente suma por registrarse, tener las vacunas al día, comprar, publicar reseñas y adoptar, y que canjea por descuentos en la tienda o en consultas.
* **Comunicación:** integración con WhatsApp para la solicitud y coordinación de turnos, recordatorios y promociones.

---

## Sitemap

La estructura general de navegación de **HuellaVet** fue definida mediante un **Sitemap**, que permite organizar las diferentes secciones y funcionalidades de la aplicación.

### Sitemap en Figma

[Ver Sitemap de HuellaVet en Figma](https://www.figma.com/design/zA9il7mruFQYbrXAkV1DKs/SiteMap-Integrador-veterinaria?node-id=0-1&t=muumkX4FHkep3MTu-1)

---

## Organización de los repositorios

El desarrollo del proyecto se encuentra dividido en diferentes repositorios de GitHub, con el objetivo de organizar las distintas responsabilidades de la aplicación.

### Frontend

Repositorio destinado al desarrollo de la interfaz de usuario y las vistas de la aplicación.

Incluye las diferentes interfaces del sitio público, panel del cliente, panel del rescatista y panel del veterinario/administrador.

[Repositorio Frontend](https://github.com/romeroppau/PAW--Frontend--TPIntegrador.git)

### ApiGateway

Repositorio destinado a la configuración del API Gateway (Nginx), la única entrada pública al sistema.

Recibe los pedidos del Frontend, los deriva al módulo correspondiente según la URL, maneja la conexión HTTPS y bloquea desde afuera las rutas internas entre módulos.

[Repositorio ApiGateway](https://github.com/romeroppau/PAW--ApiGateway--TPIntegrador.git)

### Backend

Repositorio destinado al desarrollo del lado servidor y a la implementación de la lógica necesaria para el funcionamiento de la aplicación.

En este repositorio se encuentran **los archivos correspondientes al desarrollo del Backend**.

[Repositorio Backend](https://github.com/romeroppau/PAW--Backend--TPIntegrador.git)

### Pacientes

Repositorio destinado a las funcionalidades relacionadas con pacientes, mascotas e historias clínicas.

Incluye la gestión de clientes, mascotas, fichas clínicas, historias clínicas, diagnósticos e insumos utilizados durante las consultas.

[Repositorio Pacientes](https://github.com/romeroppau/PAW--Pacientes--TPIntegrador.git)

### Inventario

Repositorio destinado a la gestión de productos, insumos y movimientos de inventario.

Incluye el control de stock, productos, insumos internos y movimientos relacionados con el inventario.

[Repositorio Inventario](https://github.com/romeroppau/PAW--Inventario--TPIntegrador.git)

### Adopciones

Repositorio destinado a las funcionalidades relacionadas con el proceso de adopción.

Incluye la publicación de animales por parte de rescatistas validados, la moderación, las postulaciones, la elección del adoptante, el chequeo veterinario obligatorio y el cierre de la adopción.

[Repositorio Adopciones](https://github.com/romeroppau/PAW--Adopciones--TPIntegrador.git)

### Reportes

Repositorio destinado al desarrollo de los reportes de la aplicación.

Contempla información relacionada con ventas, turnos atendidos, stock y movimientos de inventario, además del motor de recomendaciones.

[Repositorio Reportes](https://github.com/romeroppau/PAW--Reportes--TPIntegrador.git)
---

## Tecnologías y herramientas

Para el desarrollo del proyecto se utilizarán las tecnologías y herramientas abordadas durante la asignatura.

### Frontend

* HTML5
* CSS
* JavaScript

### Backend

* PHP (sin frameworks)
* MySQL y PDO
* Composer y PHPUnit
* Nginx como API Gateway

El detalle y la justificación de cada tecnología están en [TECNOLOGíA.md](./TECNOLOGíA.md).

### Herramientas

* Git
* GitHub
* Figma

El proyecto deberá cumplir con los estándares establecidos por la **W3C**, contemplando compatibilidad entre navegadores, diseño multipantalla y comportamiento responsivo.

---

## Seguridad

La seguridad será considerada durante las diferentes etapas del desarrollo y en las distintas capas de la aplicación.

Se tendrán en cuenta aspectos como:

* Autenticación de usuarios.
* Gestión de roles y permisos.
* Validación de datos ingresados.
* Control de acceso a las funcionalidades administrativas.
* Protección de la información almacenada.

Las medidas de seguridad serán ampliadas y documentadas a medida que avance el desarrollo del proyecto.

---

## Control de versiones

El proyecto utiliza **Git** como herramienta de control de versiones y **GitHub** como plataforma para el almacenamiento y trabajo colaborativo.

El desarrollo se encuentra organizado en diferentes repositorios correspondientes a las distintas áreas de la aplicación.

Para cada entrega se generará el **tag correspondiente en los repositorios**, de acuerdo con las pautas establecidas por la asignatura.

---

## Equipo

**Grupo: La 25**

**Proyecto: HuellaVet — Gestión Integral de Veterinaria**

**Asignatura:** Programación en Ambiente Web (PAW)

**Universidad Nacional de Luján — 2026**

---
