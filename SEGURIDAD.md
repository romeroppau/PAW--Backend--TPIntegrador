# Consideraciones de Seguridad — HuellaVet

Las medidas siguen el diagrama de arquitectura v3 (`docs/arquitectura/Arquitectura HuellaVet v3.pdf`). El sistema se despliega en tres plataformas gratuitas:

| Parte | Dónde corre |
|---|---|
| Frontend (SPA: HTML, CSS y JavaScript) | **Vercel**, que además reenvía todo lo que empieza con `/api/` a Render |
| API Gateway (Nginx) + los cinco módulos (Backend, Pacientes, Inventario, Adopciones y Reportes) | **Render**: un único servicio web, armado con Docker. Adentro hay un **contenedor** (una "caja" con su propio sistema operativo mínimo y los programas instalados) donde corren Nginx y un proceso PHP separado por módulo |
| Base de datos MySQL (única) | **Aiven**, plan gratuito |

Los servicios externos son MercadoPago y WhatsApp. Las tareas programadas las dispara **GitHub Actions**.

## Red
- **HTTPS/TLS** (TLS es el protocolo que cifra la conexión, y HTTPS es HTTP sobre TLS) en todo lo que viaja por internet:
  - entre el navegador y Vercel;
  - entre Vercel y Render;
  - entre los módulos y la base de Aiven.

  Los certificados los ponen y los renuevan Vercel y Render. Si alguien entra por `http://`, lo **redirigen a `https://`** (lo mandan automáticamente a la versión cifrada).
- **Recorrido de un pedido:**

  ```
  Navegador ──HTTPS──> Vercel ──HTTPS──> Render ──HTTP (dentro de Render)──> Nginx ──(127.0.0.1)──> PHP del módulo ──SSL──> MySQL en Aiven
  ```

  - Render **termina TLS** (tiene el certificado, descifra el tráfico HTTPS que llega y se lo pasa al contenedor).
  - **Dentro del contenedor, entre el API Gateway (Nginx) y los módulos, no se cifra.** Nginx le pasa el pedido al proceso PHP del módulo por `127.0.0.1` (localhost: la propia máquina, sin salir a ninguna red). Para interceptar ese tramo, alguien tendría que estar ya dentro del contenedor, así que cifrarlo no agrega protección.
- **API Gateway (Nginx)**: es la única puerta hacia los módulos. Los procesos PHP de los módulos escuchan solo en `127.0.0.1`, cada uno en su puerto (por ejemplo Backend en `127.0.0.1:9001` e Inventario en `127.0.0.1:9002`). Desde afuera del contenedor no se puede llegar a ellos.
- **Firewall** (filtro que decide qué conexiones de afuera se aceptan): lo maneja Render. El contenedor expone un único puerto, el de Nginx; todo lo demás queda cerrado.
- **CORS** (regla del navegador que impide que una página de un dominio lea respuestas de una API de otro dominio, salvo que la API lo permita): **no se habilita para ningún origen**.
  - El **origen** es la dirección del sitio desde el que se cargó la página que hace el pedido: protocolo, dominio y puerto, por ejemplo `https://huellavet.vercel.app`. No es la IP de un módulo.
  - La "API" son las direcciones `https://huellavet.vercel.app/api/...`. Vercel las reenvía a Render, y ahí el API Gateway (Nginx) las pasa a cada módulo. Por ejemplo, `/api/pacientes/...` va a Pacientes y `/api/tienda/...` va a Inventario.
  - Como el navegador pide la página y la API a la misma dirección (`huellavet.vercel.app`), para él es un solo sitio y no necesita CORS. Al no habilitarlo, el navegador bloquea por defecto a cualquier otro sitio.
  - **Ejemplo:**
    1. Ana inicia sesión en `https://huellavet.vercel.app`. Su navegador guarda la cookie con su JWT.
    2. En "Mis mascotas", el JavaScript del frontend pide `https://huellavet.vercel.app/api/pacientes/mascotas`. Vercel se lo pasa a Render, Nginx se lo pasa a Pacientes y Pacientes le devuelve sus mascotas. Todo bien.
    3. Sin cerrar sesión, Ana entra a otro sitio, `https://sitio-trucho.com`. Esa página tiene un JavaScript escondido que, desde el navegador de Ana, pide `https://huellavet.vercel.app/api/pacientes/mascotas`.
    4. **Sin CORS**, ese JavaScript podría leer la respuesta con los datos de Ana y mandárselos al dueño del sitio trucho.
    5. **Con CORS**, antes de dejar que la página lea la respuesta, el navegador se fija desde qué orígenes permite HuellaVet que se lean sus datos. HuellaVet no permite ningún origen ajeno. Como la página que pregunta es de `sitio-trucho.com`, el navegador la bloquea.
- Las **rutas internas** `/internal/*` (endpoints que solo usan los módulos entre sí) quedan bloqueadas en el API Gateway (Nginx) para todo pedido que llegue de afuera: reciben `404`.
  - Nginx tiene dos entradas: la **pública**, la que recibe los pedidos de Render, que bloquea `/internal/*`; y una **interna**, que escucha solo en `127.0.0.1` y la usan los módulos para llamarse entre sí.
- La comunicación entre módulos se autentica con **tokens de servicio**.
  - Un token de servicio es una contraseña larga y aleatoria que identifica a un módulo, no a una persona.
  - No se obtiene de ningún servicio en tiempo de ejecución. El equipo lo genera una sola vez, por ejemplo con `openssl rand -hex 32`, y lo carga en las variables de entorno del módulo que llama y de los módulos que lo reciben (ver Sistema Operativo).
  - Cada módulo que hace llamadas internas tiene su propio token y lo envía en el header `X-Service-Token`.
  - El módulo destino verifica el token y que ese módulo tenga permiso para el endpoint. Si el token no es válido responde `401`, y si el módulo no tiene permiso, `403`.
  - Los endpoints internos no aceptan el **JWT** de un usuario (ver Aplicación), y los públicos no aceptan tokens de servicio.

  | Destino | Endpoint interno | Quién puede llamarlo |
  |---|---|---|
  | Inventario | `POST /internal/stock/descontar` | Pacientes |
  | Backend | `POST /internal/huellitas/acreditar` | Pacientes, Inventario, Adopciones |
  | Backend | `POST /internal/huellitas/reservar` | Inventario |
  | Backend | `POST /internal/notificaciones` | Pacientes, Adopciones, Reportes |
  | Pacientes | `POST /internal/mascotas/{id}/transferir` | Adopciones |

- Las llamadas internas entre módulos van por HTTP a la entrada interna de Nginx en `127.0.0.1`: no salen del contenedor.
- **Tareas programadas** (el motor de recomendaciones y los reintentos de operaciones pendientes, desde la Entrega 4): GitHub Actions las dispara llamando a `/api/tareas/...`.
  - Esas rutas exigen un **token de tareas** propio en el header `X-Task-Token`.
  - El token se guarda en los secretos de GitHub y en las variables de entorno de Render.
- **Webhook de MercadoPago** (aviso HTTP que los servidores de MercadoPago le mandan a una URL nuestra cuando cambia el estado de un pago).
  - Cualquiera podría llamar a esa URL con un falso "pago aprobado".
  - Por eso, antes de marcar un pago como aprobado, se verifica la firma del aviso y se consulta el pago directamente a la API de MercadoPago.

## Sistema Operativo
- El sistema operativo de las máquinas donde corre Render (actualizaciones, parches, firewall) lo mantiene Render.
- **Lo que nos toca a nosotros es el contenedor:**
  - se arma desde una imagen oficial de PHP y Nginx, y se vuelve a construir en cada despliegue para incluir los últimos parches;
  - solo tiene instalado lo necesario para correr Nginx y PHP.
- **Principio de mínimo privilegio** (cada programa tiene solo los permisos que necesita): dentro del contenedor, Nginx y cada uno de los cinco procesos PHP corren con su propio usuario, sin permisos de administrador (`root`).
- Cada módulo solo puede leer su propia carpeta.
- **Variables de entorno** (valores que el sistema le pasa a un programa al arrancar): los secretos de cada módulo se guardan así. Son la contraseña de MySQL, el token de servicio, las claves del JWT y la credencial de MercadoPago.
  - En Render se cargan desde su panel; en la computadora de cada integrante, desde un archivo **`.env`** (archivo de texto con esos mismos valores).
  - Nunca se suben al repositorio ni quedan dentro de la imagen del contenedor.
- Render borra el disco del contenedor en cada reinicio, así que no se guarda nada importante ahí: las imágenes subidas (mascotas, publicaciones y productos) van a la base de datos.

## Motor de Base de Datos
- Una única base MySQL, en Aiven. Cada módulo se conecta con su propio usuario de MySQL:
  - con escritura (`INSERT`, `UPDATE`, `DELETE`) solo sobre sus propias tablas;
  - con `SELECT` sobre las tablas de los demás módulos, para las lecturas directas que define la arquitectura;
  - Reportes solo tiene `SELECT`;
  - ningún módulo usa el usuario administrador de Aiven.
- La conexión entre los módulos y la base viaja por internet, así que **va siempre cifrada con SSL** (Aiven lo exige) y se verifica el certificado de Aiven.
- Si el plan gratuito lo permite, se activa el **filtro de IP** de Aiven, para que solo acepte conexiones desde las direcciones de Render.
- **Consultas parametrizadas** (los datos del usuario se pasan aparte de la sentencia SQL y nunca se interpretan como código) u ORM para evitar **inyección SQL** (meter código SQL dentro de un dato de entrada).
- **Transacciones** (varias operaciones que se guardan todas juntas o no se guarda ninguna) para lo que toca varias tablas. Por ejemplo, la consulta, sus ítems y su factura.
- Como la base es única, es el único punto de falla aceptado del diseño:
  - Aiven hace copias de seguridad automáticas;
  - además, una copia propia por semana (exportada con `mysqldump`), guardada fuera de Aiven y con acceso restringido;
  - se prueba al menos una vez que se pueda restaurar;
  - Aiven apaga la base gratuita si no se usa por un tiempo y la borra si queda apagada 180 días: se vigila el mail de aviso.

## Aplicación
- Contraseñas guardadas con hash **bcrypt** (algoritmo de hash pensado para contraseñas: es lento a propósito y agrega una sal aleatoria, así que es muy costoso adivinarlas por fuerza bruta).
  - Se usa con costo 12.
  - Nunca se guardan en texto plano ni con MD5 o SHA-1.
- Login:
  - el error es el mismo para email y para contraseña ("Email o contraseña incorrectos");
  - 5 intentos fallidos en 15 minutos bloquean el login de esa cuenta durante 15 minutos.
- Autenticación con **JWT** (JSON Web Token: un texto firmado que identifica al usuario que inició sesión; cualquiera puede leer su contenido, pero nadie puede modificarlo sin invalidar la firma).
  - Backend lo firma con su clave privada (RS256), y los otros cuatro módulos lo verifican con la clave pública.
  - Expira en 1 hora y solo lleva el `id` y el `rol`.
  - Se guarda en una **cookie `HttpOnly`, `Secure` y `SameSite=Strict`**: JavaScript no puede leerla, solo viaja por HTTPS y el navegador no la manda en pedidos que vienen de otros sitios. Esto último protege contra **CSRF** (que otro sitio haga acciones en HuellaVet usando la sesión del usuario).
  - La cookie queda asociada a `huellavet.vercel.app`, porque para el navegador la API está en esa misma dirección.
  - **Ejemplo:**
    1. La clienta Ana (id 15) inicia sesión. El pedido pasa por Vercel y por Nginx, y llega a Backend, que verifica su contraseña.
    2. Backend le devuelve un JWT firmado con su clave privada, cuyo contenido es `{"id": 15, "rol": "CLIENTE", "exp": <en 1 hora>}`. El navegador lo guarda en una cookie.
    3. Ana abre "Mis mascotas". El navegador pide `GET /api/pacientes/mascotas` y manda la cookie automáticamente.
    4. Pacientes verifica la firma con la clave pública de Backend, sin preguntarle nada a Backend. Lee que es el id 15 con rol CLIENTE y devuelve solo las mascotas de Ana.
    5. Si Ana modifica el token para decir `"id": 16`, la firma deja de coincidir y Pacientes lo rechaza.
    6. Si el proceso de Backend se cae, Pacientes puede seguir verificando el token, porque tiene la clave pública.
- Control de acceso validado en cada módulo, no solo en el frontend:
  - **por rol:** solo el veterinario carga consultas, administra el stock, modera y aprueba rescatistas; solo un rescatista `APROBADO` publica en adopciones;
  - **por dueño:** un cliente solo ve sus mascotas, turnos, facturas y Huellitas; un rescatista solo ve las postulaciones de sus publicaciones.
- Validación de entradas en el servidor:
  - tipo, largo y rango de cada campo, y rechazo de los datos inválidos;
  - las imágenes solo pueden ser JPG, PNG o WEBP, de hasta 1 MB, y se guardan en la base;
  - los precios y totales siempre los calcula el servidor.
- Sanitización de salidas para prevenir **XSS** (que un usuario cargue código JavaScript en un texto y se ejecute en el navegador de otro):
  - el contenido escrito por usuarios (publicaciones, reseñas, postulaciones) se muestra escapado, nunca como HTML;
  - las respuestas no incluyen campos internos, como el hash de la contraseña o datos de contacto de otros usuarios;
  - los errores no muestran el stack trace ni el SQL.
- Llamadas internas:
  - cada una lleva un `idOperacion` único, para que un reintento desde `operacion_pendiente` no descuente stock ni acredite puntos dos veces;
  - el cuerpo se valida igual que en un endpoint público;
  - el timeout es de 3 segundos.
- Registro de auditoría:
  - accesos y modificaciones a fichas médicas;
  - acciones del veterinario: aprobar rescatistas, moderar, completar adopciones y ajustar stock;
  - logins fallidos y llamadas internas rechazadas;
  - nunca se registran contraseñas, tokens ni datos de pago.

## Ciclo de vida del software
- **Diseño:** decisiones de arquitectura descritas arriba: el API Gateway (Nginx) como única puerta a los módulos, tokens de servicio entre módulos, un usuario de MySQL por módulo y la tabla de operaciones pendientes.
- **Desarrollo:**
  - revisión de dependencias de terceros con `composer audit` (comando de PHP que compara las librerías del proyecto con una base de vulnerabilidades conocidas);
  - los secretos van en el `.env` de cada integrante y en las variables de entorno de Render, siempre fuera del repositorio, y hay un `.env.example` en el repo;
  - secretos distintos para desarrollo y para producción.
- **Testing:**
  - pruebas de control de acceso por rol y por dueño;
  - pruebas de inyección SQL y XSS;
  - pruebas de las llamadas internas sin token, con un token inválido y con un módulo sin permiso.
- **Despliegue:**
  - HTTPS de Vercel y Render;
  - módulos solo accesibles a través del API Gateway (Nginx);
  - conexión cifrada con la base;
  - **hardening del contenedor** (reducir lo que se puede atacar: imagen oficial y actualizada, solo los programas necesarios, cada programa con un usuario sin privilegios y un único puerto expuesto).
- **Mantenimiento:**
  - actualización de dependencias;
  - monitoreo de logs desde el panel de Render, en especial los logins fallidos y los rechazos de llamadas internas;
  - control de las copias de seguridad;
  - si un secreto se filtra, se cambia en el momento.
