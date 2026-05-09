# 🎮 Tres en Raya con IA (Minimax + Alfa-Beta) y Detección Facial

Este proyecto es una implementación avanzada del clásico juego "Tres en Raya" (Tic-Tac-Toe), desarrollado como una aplicación web interactiva que enfrenta al jugador humano contra una Inteligencia Artificial **invencible**.

## ✨ Características Principales
* **Motor de IA Invencible:** Utiliza el algoritmo Minimax optimizado con Poda Alfa-Beta en el backend (PHP) para calcular el movimiento perfecto.
* **Métricas en Tiempo Real:** El sistema muestra cuántos nodos exploró la IA, cuántas podas realizó y el tiempo de cálculo exacto.
* **Detección Facial Integrada:** Utiliza `tracking.js` para escanear el rostro del jugador a través de la webcam en tiempo real antes de iniciar la partida.
* **Sistema de Auditoría (Capturas):** Una vez detectado el rostro y en cada jugada del usuario, el sistema toma silenciosamente una captura fotográfica (Base64) y la guarda localmente en el servidor PHP para verificar la identidad del jugador.
* **Interfaz UI/UX Moderna:** Diseño oscuro elegante con animaciones fluidas, retroalimentación visual en los turnos y diseño completamente responsivo.

## 🛠️ Tecnologías Utilizadas
* **Backend:** PHP puro.
* **Frontend:** HTML5, CSS3, JavaScript (ES6), Fetch API.
* **IA Visual:** Librería `tracking.js` para detección de rostros en el navegador vía WebRTC (`getUserMedia`).
* **Servidor Local recomendado:** XAMPP, WAMP o LAMP.

## 🚀 Instalación y Uso
1. Clona o descarga este repositorio.
2. Coloca el archivo `index.php` dentro del directorio público de tu servidor web local (ej. `htdocs` en XAMPP).
3. Inicia Apache.
4. Ingresa a `http://localhost/tu_carpeta/` desde el navegador.
5. Concede permisos de cámara al navegador.
6. ¡Asómate a la cámara para que te detecte y comienza a jugar!
*(Nota: Las capturas se guardarán automáticamente en una carpeta `/capturas` que PHP creará en el mismo directorio).*
