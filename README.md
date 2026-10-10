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

## Cómo correrlo

> Como el backend todavía está en etapa de diseño: este repositorio contiene la documentación y el código se irá incorporando durante la cursada. Estos pasos describen cómo se va a levantar el módulo según el stack definido en [TECNOLOGíA.md](./TECNOLOGíA.md), pero tener en cuenta que se va a ir modificando y ajustando pasos a medida que se agregue el código.

### Pasos

1. Clonar el repositorio:

```bash
   git clone https://github.com/romeroppau/PAW--Backend--TPIntegrador.git
   cd PAW--Backend--TPIntegrador
```

2. Instalar las dependencias de desarrollo:

```bash
   composer install
```

3. Crear la base de datos en MySQL:

```sql
   CREATE DATABASE huellavet CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

4. Configurar la conexión a la base de datos (host, puerto, nombre de la base, usuario y contraseña). Las credenciales no se suben al repositorio.

5. Levantar el módulo con el servidor integrado de PHP, escuchando solo en localhost como indica [SEGURIDAD.md](./SEGURIDAD.md):

```bash
   php -S 127.0.0.1:3001
```

6. Para el sistema completo, levantar cada módulo en su puerto y configurar Nginx para que reenvíe `/api/...` al módulo correspondiente.

### Pruebas

```bash
./vendor/bin/phpunit
```

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
* Incorporar un espacio destinado a publicaciones de adopción.
* Generar reportes sobre ventas, turnos e inventario.
* Implementar beneficios y descuentos para los clientes.
* Integrar WhatsApp como medio de comunicación para la solicitud y coordinación de turnos.

---

## Descripción de la aplicación

**HuellaVet** es una aplicación web destinada a una veterinaria que integra funcionalidades de gestión y atención al cliente.

El sistema contempla dos roles principales:

### Cliente

El cliente podrá:

* Registrarse e iniciar sesión.
* Gestionar sus mascotas.
* Consultar la ficha e historia clínica de sus mascotas.
* Consultar turnos próximos y anteriores.
* Solicitar turnos mediante WhatsApp.
* Consultar y comprar productos del catálogo.
* Seleccionar el retiro de productos en el local.
* Consultar sus facturas.
* Acceder a beneficios y descuentos.
* Publicar y consultar publicaciones de adopción.

### Veterinario / Administrador

El veterinario o administrador podrá:

* Gestionar pacientes y sus fichas clínicas.
* Registrar diagnósticos e insumos utilizados durante las consultas.
* Gestionar turnos.
* Gestionar productos del catálogo.
* Administrar el stock.
* Gestionar los insumos internos de la veterinaria.
* Registrar movimientos de inventario.
* Gestionar facturación.
* Moderar publicaciones de adopción.
* Consultar reportes de ventas, turnos e inventario.

---

## Funcionalidades principales

Las principales funcionalidades contempladas para el proyecto son:

* **Gestión de pacientes:** administración de clientes, mascotas, fichas e historias clínicas.
* **Gestión de turnos:** solicitud y coordinación de turnos mediante WhatsApp, junto con la visualización de turnos próximos y anteriores.
* **Historias clínicas:** registro de diagnósticos e insumos utilizados durante las consultas.
* **Facturación:** generación de facturas asociadas a consultas y compras.
* **Tienda:** catálogo de productos, compras y retiro en el local.
* **Inventario e insumos:** gestión de productos, insumos internos, stock y movimientos de inventario.
* **Adopciones:** espacio para publicar y consultar animales en adopción, con moderación por parte del administrador.
* **Reportes:** información sobre ventas, turnos atendidos, stock y movimientos de inventario.
* **Beneficios:** descuentos para los clientes.
* **Comunicación:** integración con WhatsApp para la solicitud y coordinación de turnos.

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

Incluye las diferentes interfaces del sitio público, panel del cliente y panel del veterinario/administrador.

[Repositorio Frontend](https://github.com/romeroppau/PAW--Frontend--TPIntegrador.git)

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

### Reportes

Repositorio destinado al desarrollo de los reportes de la aplicación.

Contempla información relacionada con ventas, turnos atendidos, stock y movimientos de inventario.

[Repositorio Reportes](https://github.com/romeroppau/PAW--Reportes--TPIntegrador.git)

> Las funcionalidades de HuellaVet no se encuentran necesariamente limitadas a un único repositorio. Algunas funcionalidades requieren la interacción entre diferentes componentes del sistema, como el Frontend, Backend y los módulos específicos.

---

## Tecnologías y herramientas

Para el desarrollo del proyecto se utilizarán las tecnologías y herramientas abordadas durante la asignatura.

### Frontend

* HTML5
* CSS
* JavaScript

### Backend

La tecnología utilizada para el desarrollo del Backend se encuentra documentada en el repositorio correspondiente.

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

## Trabajo Práctico Integrador

Este proyecto corresponde al **Trabajo Práctico Final Integrador de Conocimientos** de la asignatura Programación en Ambiente Web.

El desarrollo del proyecto se realizará de manera incremental durante la cursada, incorporando progresivamente las funcionalidades definidas para la aplicación.
