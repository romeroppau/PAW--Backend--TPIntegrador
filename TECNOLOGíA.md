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

**Qué es:** la base de datos donde se guarda toda la información del sistema: usuarios, mascotas, turnos, consultas, productos, facturas, adopciones y Huellitas.

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

**Qué es:** el programa que funciona como puerta de entrada al sistema (el API Gateway del diagrama de arquitectura). Recibe todos los pedidos que llegan desde internet y se los pasa al módulo que corresponde. Por ejemplo, lo que empieza con `/api/pacientes/` va al módulo Pacientes. También se encarga de que la conexión con el navegador sea segura (HTTPS).

**Por qué lo elegimos:**
- PHP no sabe recibir por sí solo los pedidos que llegan desde internet. Necesita un programa que haga esa tarea, y Nginx es uno de los más usados para eso.
- Con un solo programa resolvemos tres cosas: la entrada única al sistema, el reparto de pedidos entre los cinco módulos y la conexión segura.
- Es liviano y su configuración es un archivo de texto simple.

## Cron

**Qué es:** la herramienta del sistema operativo del servidor que ejecuta tareas solas, en horarios fijos. En HuellaVet hay dos tareas así:
- el motor de recomendaciones, que se ejecuta una vez por día;
- el reintento de las operaciones pendientes entre módulos, que se ejecuta cada minuto.

**Por qué la elegimos:**
- PHP solo se ejecuta cuando alguien hace un pedido, así que necesitamos algo que lance estas tareas aunque nadie esté usando la página.
- Viene incluida en el sistema operativo del servidor, así que no hay que instalar nada.
- Es simple de configurar: se indica qué archivo PHP ejecutar y cada cuánto.

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
