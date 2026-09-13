# Consideraciones de Seguridad — HuellaVet

**Entrega:** 1 / 4
**Fecha:** 31/07

## Red
- HTTPS/TLS obligatorio en todas las comunicaciones cliente-servidor y entre microservicios.
- CORS restringido a los orígenes del frontend propio.
- Firewall en el servidor: solo puertos necesarios expuestos (80/443).
- Comunicación entre microservicios (Inventario, Backend, Reportes, Pacientes, Frontend) autenticada con tokens internos, no expuesta públicamente sin control.

## Sistema Operativo
- Actualizaciones y parches de seguridad regulares en el servidor de despliegue.
- Principio de mínimo privilegio: cada servicio corre con un usuario de sistema propio, sin permisos de administrador.
- Deshabilitación de servicios y puertos no utilizados.

## Motor de Base de Datos
- Usuarios de base de datos separados por microservicio, con permisos limitados a las tablas que cada uno necesita.
- Consultas parametrizadas u ORM para evitar inyección SQL.
- Cifrado de campos sensibles en reposo: historial clínico, diagnósticos, datos personales.
- Backups periódicos con acceso restringido.

## Aplicación
- Contraseñas almacenadas con hash (bcrypt o argon2).
- Control de acceso por rol validado en backend, no solo en frontend.
- Validación y sanitización de input para prevenir XSS e inyección.
- Sesiones con cookies seguras (HttpOnly, Secure), expiración, protección CSRF.
- Registro de auditoría sobre accesos y modificaciones a fichas médicas.

## Ciclo de vida del software
- Diseño: decisiones de arquitectura descritas arriba.
- Desarrollo: revisión de dependencias de terceros, secretos fuera del repositorio.
- Testing: pruebas de control de acceso por rol, pruebas de inyección.
- Despliegue: HTTPS, firewall, hardening del servidor.
- Mantenimiento: actualización de dependencias, monitoreo de logs.
