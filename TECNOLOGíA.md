# Tecnologías del backend — HuellaVet

Estas son las tecnologías que vamos a usar para construir los cinco módulos del backend (Backend, Pacientes, Inventario, Adopciones y Reportes), la base de datos y su conexión con los servicios externos.

Siguiendo el programa de la materia, el backend se programa con **PHP puro, sin frameworks**, y usamos librerías externas solo cuando realmente hacen falta.

## PHP

**Qué es:** el lenguaje de programación con el que se escriben los cinco módulos del backend. Recibe los pedidos de la página, aplica las reglas del sistema (por ejemplo, calcular una factura o validar un rescatista) y responde con los datos.

**Por qué lo elegimos:**
- Es el lenguaje que se enseña en la materia, así que contamos con las clases y el material de la cátedra como guía.
- Ninguno de los integrantes tiene experiencia previa en desarrollo web. Usar el lenguaje de la materia nos permite aprender sobre la marcha sin tener que estudiar además otro lenguaje por nuestra cuenta.
- Lo usamos "puro", sin frameworks, como pide la materia. Así entendemos cómo funciona cada parte en lugar de depender de herramientas que lo resuelven por nosotros.

## MySQL

**Qué es:** la base de datos donde se guarda toda la información del sistema: usuarios, mascotas, turnos, consultas, productos, facturas, adopciones y Huellitas. Está alojada en Aiven (ver "Plataformas de despliegue").

**Por qué la elegimos:**
- Es una base de datos relacional, es decir, guarda los datos en tablas relacionadas entre sí. Eso se ajusta a HuellaVet, donde casi todo está conectado: un cliente tiene mascotas, cada mascota tiene turnos, cada turno tiene una consulta y cada consulta tiene una factura.
- El grupo ya la usó en otras materias, así que no tenemos que aprender a manejarla desde cero.

## PDO

**Qué es:** la herramienta de PHP para conectarse a la base de datos y hacerle consultas (guardar, buscar, modificar y borrar datos).

**Por qué la elegimos:**
- Viene incluida con PHP, así que no hay que instalar nada aparte.
- Permite separar la consulta de los datos que escribe el usuario. Así se evita que alguien meta instrucciones dañinas para la base de datos dentro de un formulario, como pide el documento de seguridad.

## PHPUnit

**Qué es:** una herramienta para escribir pruebas automáticas del código. Cada prueba ejecuta una parte del sistema y verifica que el resultado sea el esperado. Por ejemplo, que una consulta con dos insumos genere una factura con el total correcto, o que un cliente no pueda ver las mascotas de otro.

**Por qué la elegimos:**
- PHP no trae una herramienta propia para hacer pruebas, y PHPUnit es la más conocida y usada para PHP.
- Nos permite volver a correr todas las pruebas después de cada cambio y detectar enseguida si rompimos algo que antes funcionaba.
- Solo se usa mientras desarrollamos. No forma parte del sistema que usa la veterinaria.

## Composer

**Qué es:** el programa que instala y mantiene actualizadas las herramientas externas de un proyecto PHP. En nuestro caso, se usa para instalar PHPUnit.

**Por qué lo elegimos:**
- Es la forma habitual de instalar PHPUnit. Con un solo comando, cada integrante del grupo tiene la misma versión.
- Permite revisar si alguna de las herramientas instaladas tiene problemas de seguridad conocidos (`composer audit`), como pide el documento de seguridad.

## Nginx

**Qué es:** el programa que funciona como puerta de entrada a los módulos (el API Gateway del diagrama de arquitectura). Recibe los pedidos que llegan a `/api/...` y se los pasa al módulo que corresponde. Por ejemplo, lo que empieza con `/api/pacientes/` va al módulo Pacientes. También bloquea desde afuera las rutas internas (`/internal/...`), que solo usan los módulos entre sí.

**Por qué lo elegimos:**
- PHP no sabe recibir por sí solo los pedidos que llegan desde internet. Necesita un programa adelante que los reciba y se los pase, y Nginx es uno de los más usados para eso.
- Con un solo programa resolvemos dos cosas: que PHP pueda atender pedidos y el reparto de esos pedidos entre los cinco módulos.
- Es liviano y su configuración es un archivo de texto simple.
- El cifrado HTTPS no lo hace Nginx: lo resuelven Vercel y Render (ver más abajo).

## Docker

**Qué es:** una herramienta que arma una "caja" (contenedor) con todo lo que necesita el backend para funcionar: un sistema operativo mínimo, Nginx, PHP y el código de los cinco módulos. Esa caja se describe en un archivo de texto (`Dockerfile`) y funciona igual en cualquier computadora.

**Por qué la elegimos:**
- Render necesita recibir el backend armado de esta forma para poder ejecutarlo.
- Con el mismo `Dockerfile` cada integrante levanta el proyecto en su computadora, más una base MySQL local, con Docker Compose. Así lo que funciona en una máquina funciona en todas y en Render.
- Es gratis. Es una herramienta y no una librería del código, pero pedimos el aval docente igual.

## GitHub Actions

**Qué es:** el servicio de GitHub que ejecuta tareas solas, en horarios fijos. En HuellaVet hay tres tareas así:
- el motor de recomendaciones, que se ejecuta una vez por día (desde la Entrega 4);
- el reintento de las operaciones pendientes entre módulos (desde la Entrega 4);
- una visita a `/api/salud` cada 14 minutos, para que Render y Aiven no apaguen el sistema por falta de uso.

**Por qué la elegimos:**
- PHP solo se ejecuta cuando alguien hace un pedido, así que necesitamos algo que lance estas tareas aunque nadie esté usando la página.
- El plan gratuito de Render no permite programar tareas. GitHub Actions es gratis para repositorios públicos y ya usamos GitHub para el código.
- Cada tarea es un archivo de texto en el repositorio que dice qué dirección llamar y cada cuánto. La llamada lleva un token secreto, guardado en GitHub.

## Plataformas de despliegue

Las tres son gratuitas y no piden tarjeta de crédito.

### Vercel

**Qué es:** la plataforma donde se publica el frontend (las páginas HTML, CSS y JavaScript). Además reenvía a Render todos los pedidos que empiezan con `/api/`.

**Por qué la elegimos:**
- Las páginas siempre están disponibles y cargan rápido, aunque el backend esté dormido o caído. Así el inicio, la información de contacto y el botón de WhatsApp para pedir turno siguen funcionando.
- Gracias al reenvío de `/api/`, el navegador ve la página y la API en una misma dirección. Así la cookie de sesión funciona sin cambios y no hace falta habilitar CORS (ver `SEGURIDAD.md`).
- Pone el HTTPS solo y publica cada cambio que se sube a GitHub. El grupo ya la conoce.

### Render

**Qué es:** la plataforma donde corre el backend: un único servicio web con el contenedor de Docker, donde están Nginx y los cinco módulos.

**Por qué la elegimos:**
- Su plan gratuito ejecuta contenedores de Docker, que es lo que necesita PHP con Nginx. Vercel solo corre PHP con un complemento no oficial, y Railway ya no tiene plan gratuito.
- Pone el HTTPS solo y vuelve a desplegar cada cambio que se sube a GitHub.
- Usamos un solo servicio porque el plan gratuito da 750 horas por mes para toda la cuenta: alcanza para un servicio prendido todo el mes, no para cinco. Dentro del contenedor, cada módulo sigue siendo un proceso separado: si uno falla, los demás siguen atendiendo.
- **Limitación:** si pasan 15 minutos sin visitas, Render lo apaga y la siguiente visita tarda alrededor de un minuto en responder. La visita automática de GitHub Actions lo evita.

### Aiven

**Qué es:** la plataforma donde está la base de datos MySQL.

**Por qué la elegimos:**
- Es MySQL de verdad, la base que elegimos, y su plan gratuito no pide tarjeta.
- Trae copias de seguridad automáticas y exige conexión cifrada, que es lo que pide el documento de seguridad.
- **Limitaciones:** tiene 1 GB de espacio, que alcanza para el proyecto con imágenes chicas. Además, apaga la base si no se usa por un tiempo, avisa por mail antes y se vuelve a prender desde su panel.

## Servicios externos

Son servicios de otras empresas a los que el backend se conecta. No los programamos nosotros: usamos lo que ofrecen.

### MercadoPago

**Qué es:** el servicio que cobra las compras de la tienda. El cliente paga en la página de MercadoPago, y MercadoPago le avisa al módulo Inventario si el pago fue aprobado o rechazado.

**Por qué lo elegimos:**
- Es el medio de pago online más usado en Argentina, así que la mayoría de los clientes ya lo conoce y lo usa.
- Nos evita manejar datos de tarjetas, que es algo delicado y riesgoso. Esos datos los maneja MercadoPago.
- Tiene un entorno de prueba para simular pagos sin usar dinero real mientras desarrollamos.

### WhatsApp (API de WhatsApp Business)

**Qué es:** el servicio que permite que el sistema mande mensajes de WhatsApp automáticamente, sin que una persona los escriba. El módulo Backend lo usa para enviar recordatorios de vacunas, avisos de recompra, promociones y los controles de adopción.

**Por qué lo elegimos:**
- La veterinaria ya coordina los turnos con sus clientes por WhatsApp, así que los avisos les llegan por el mismo canal que ya usan todos los días.
- Un mensaje de WhatsApp se lee mucho más que un correo electrónico.
