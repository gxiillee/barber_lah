# barber_lah
Github para la gestion de Barber la h
<p align="center">
  <img src="./banner de Hassan.jpg" alt="Banner de Introducción" width="100%">
</p>
=======
# 💈 Barbershop La H — Sistema de Gestión Web
### TFG · Ciclo Superior DAW (Desarrollo de Aplicaciones Web)

---

## 📋 Descripción del proyecto

Aplicación web completa para **Barbershop La H**, barbería de barrio ubicada en **C/ Miguel Servet 24, Zaragoza**.  
Propietario: **Hassan** · Instagram: [@barbershop_la_h](https://www.instagram.com/barbershop_la_h/)

El negocio actualmente utiliza **Booksy** para la gestión de reservas. Este proyecto desarrolla un sistema de gestión propio que incluye web pública, reservas online, panel de administración y notificaciones automáticas.

---

## 🎯 Objetivos

- Crear una web pública corporativa para la barbería
- Desarrollar un sistema de reservas online propio (sustituyendo Booksy)
- Implementar un panel de administración para gestionar citas, servicios y clientes
- Aplicar POO con herencia en PHP como requisito técnico del TFG
- Generar endpoints JSON para una posible futura API REST

---

## ⚙️ Stack tecnológico

| Capa | Tecnología |
|---|---|
| Backend | PHP 8+ — Orientación a Objetos (POO), sin frameworks |
| Base de datos | MySQL + PDO |
| Frontend | HTML5, CSS3, Bootstrap 5, JavaScript (fetch) |
| Email | PHPMailer + cuenta Gmail |
| Control de versiones | Git + GitHub |
| Servidor local | XAMPP |

---

## 🧱 Arquitectura de clases — Herencia PHP

### Jerarquía 1 — Usuarios del sistema

```
Usuario (clase base)
    ├── Cliente extends Usuario
    └── Administrador extends Usuario
```

- `Usuario` contiene los atributos comunes: `id`, `nombre`, `email`, `password`
- `Cliente` añade: `telefono`, puede reservar citas y dejar reseñas (con token)
- `Administrador` añade: acceso al panel, puede gestionar citas, servicios y reseñas

### Jerarquía 2 — Personas del negocio

```
Persona (clase base)
    ├── Cliente extends Persona
    └── Barbero extends Persona
```

- `Persona` contiene: `nombre`, `telefono`
- `Cliente` puede reservar y recibir notificaciones
- `Barbero` tiene horario y agenda propia

---

## 🛠️ Funcionalidades principales

### Parte pública (cualquier visitante)
- Página de inicio con presentación, servicios destacados y reseñas
- Listado completo de servicios con precio y duración
- Formulario de reserva online (servicio → fecha → hora disponible)
- Página de reseñas verificadas
- Página de contacto con mapa incrustado (C/ Miguel Servet 24)

### Panel de administración (solo Hassan)
- Login seguro con sesiones PHP
- Dashboard con citas del día y resumen semanal
- Gestión de citas: ver, confirmar, completar, cancelar
- Gestión de servicios: añadir, editar precio/duración, desactivar
- Gestión de reseñas: aprobar o eliminar antes de publicar

### Sistema de notificaciones y reseñas verificadas
1. Hassan marca una cita como **completada** desde el panel
2. El sistema genera un **token único** y lo guarda en la BBDD
3. Se envía un **email automático** al cliente (PHPMailer) con enlace a la reseña
4. El cliente accede a `resena.php?token=xxx` — si el token es válido y no usado → puede escribir su reseña
5. Hassan aprueba la reseña → aparece en la web pública
6. El token queda marcado como usado → nadie más puede usarlo

### Endpoints JSON (futura API REST)
- `api/servicios.php` → devuelve todos los servicios activos
- `api/disponibilidad.php?fecha=YYYY-MM-DD` → devuelve huecos libres
- `api/reservas.php` → devuelve citas del día (autenticado)

---

## ✂️ Servicios reales de la barbería

| Servicio | Precio | Duración |
|---|---|---|
| Corte caballero | 14 € | 30 min |
| Corte + barba | 20 € | 30 min |
| Corte niños (hasta 10 años) | 12 € | 30 min |
| Recorte de barba | 7 € | 30 min |
| Perfilar cejas | 5 € | 30 min |
| Diseño | 5 € | 30 min |

---

## 🗄️ Base de datos — Tablas

```sql
usuarios
    id, nombre, email, password, telefono, rol ENUM('admin','cliente'), created_at

servicios
    id, nombre, precio, duracion_min, activo

reservas
    id, id_cliente, id_servicio, fecha, hora,
    estado ENUM('pendiente','confirmada','completada','cancelada'),
    token_resena, resena_enviada, nota

resenas
    id, id_cliente, puntuacion, comentario, aprobada, fecha

barberos
    id, nombre, telefono, especialidad

horarios
    id, id_barbero, dia_semana, hora_inicio, hora_fin
```

---

## 📁 Estructura de archivos del proyecto

```
barberlah/
│
├── index.php
├── servicios.php
├── reservar.php
├── resenas.php
├── contacto.php
│
├── /admin/
│     ├── login.php
│     ├── dashboard.php
│     ├── citas.php
│     ├── servicios.php
│     └── resenas.php
│
├── /clases/
│     ├── Usuario.php
│     ├── Cliente.php
│     ├── Administrador.php
│     ├── Persona.php
│     ├── Barbero.php
│     └── Reserva.php
│
├── /api/
│     ├── servicios.php
│     ├── disponibilidad.php
│     └── reservas.php
│
├── /config/
│     └── database.php
│
└── /assets/
      ├── /css/
      ├── /js/
      └── /img/
```

---

## ✅ Estado del proyecto

### Fase 1 — Análisis y documentación
- [ ] Documento de análisis de la oportunidad
- [ ] Casos de uso
- [ ] Diagrama UML de clases
- [ ] Diagrama Entidad-Relación (E/R)
- [ ] Wireframes de las páginas

### Fase 2 — Base de datos
- [ ] Script SQL de creación de tablas
- [ ] Datos de prueba (seed)

### Fase 3 — Backend PHP
- [ ] Clases base y herencia (Usuario, Cliente, Administrador, Persona, Barbero)
- [ ] Conexión a BBDD con PDO
- [ ] Lógica de reservas y disponibilidad
- [ ] Sistema de tokens para reseñas
- [ ] Integración PHPMailer
- [ ] Endpoints JSON (api/)

### Fase 4 — Frontend público
- [ ] Página de inicio
- [ ] Página de servicios
- [ ] Formulario de reserva
- [ ] Página de reseñas
- [ ] Página de contacto

### Fase 5 — Panel de administración
- [ ] Login y control de sesión
- [ ] Dashboard
- [ ] Gestión de citas
- [ ] Gestión de servicios
- [ ] Gestión de reseñas

### Fase 6 — Pruebas y despliegue
- [ ] Pruebas funcionales
- [ ] Corrección de errores
- [ ] Despliegue en hosting

### Fase 7 — Documentación final
- [ ] Memoria del proyecto
- [ ] Preparación de la defensa

---

## 📝 Historial de cambios

| Fecha | Descripción |
|---|---|
| — | *Pendiente de primera entrada* |

---

## 👤 Autor

Proyecto de TFG — Ciclo Superior DAW  
Cliente real: **Barbershop La H** · Zaragoza
