<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="icon" type="image/x-icon" sizes="32x32" href="../assets/img/Icon.ico">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link rel="stylesheet" href="./stylelogin.css" />
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <title>PharmaSystem</title>
  <style>
    /* Overlay de bloqueo estilo claro PharmaSystem */

    * {
      font-family: "Poppins", sans-serif;
    }

    .lockout-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.4);
      z-index: 10000;
      display: flex;
      justify-content: center;
      align-items: center;
      backdrop-filter: blur(6px);
      animation: fadeInLockout 0.5s ease;
      transition: all 0.3s ease;
    }

    @keyframes fadeInLockout {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    .lockout-card {
      background: rgba(255, 255, 255, 0.98);
      border-radius: 34px;
      padding: 40px 30px;
      text-align: center;
      max-width: 340px;
      width: 90%;
      box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
      border: 1px solid rgba(46, 204, 113, 0.2);
      animation: slideUpLockout 0.6s ease;
      transition: transform 0.3s ease;
    }

    @keyframes slideUpLockout {
      from { opacity: 0; transform: translateY(30px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .lockout-icon {
      font-size: 70px;
      color: #27ae60;
      margin-bottom: 20px;
    }

    .lockout-title {
      font-size: 24px;
      font-weight: 600;
      color: #27ae60;
      margin-bottom: 15px;
    }

    .lockout-message {
      font-size: 16px;
      color: #555;
      margin-bottom: 25px;
      line-height: 1.5;
    }

    .lockout-timer {
      background: rgba(46, 204, 113, 0.08);
      border-radius: 30px;
      padding: 15px 20px;
      margin-bottom: 25px;
      border: 1px solid rgba(46, 204, 113, 0.15);
      transition: all 0.3s ease;
      max-height: 300px;
      opacity: 1;
      transform: translateY(0);
      overflow: hidden;
      transition: all 0.4s ease;
    }

    .lockout-timer.hidden {
      max-height: 0;
      opacity: 0;
      transform: translateY(-10px);
      padding: 0;
      margin: 0;
    }

    .timer-circle {
      width: 120px;
      height: 120px;
      margin: 0 auto 15px;
      position: relative;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .timer-svg {
      transform: rotate(-90deg);
    }

    .timer-circle-bg {
      stroke: rgba(46, 204, 113, 0.2);
    }

    .timer-circle-fill {
      stroke: #27ae60;
      stroke-dasharray: 339.292;
      stroke-dashoffset: 0;
      transition: stroke-dashoffset 1s linear;
    }

    .timer-text {
      position: absolute;
      font-size: 32px;
      font-weight: 700;
      color: #27ae60;
    }

    .timer-label {
      font-size: 14px;
      color: #888;
      text-transform: uppercase;
      letter-spacing: 1px;
      font-weight: 500;
    }

    .timer-number {
      font-size: 48px;
      font-weight: 700;
      color: #27ae60;
    }

    .lockout-footer {
      font-size: 13px;
      color: #aaa;
      margin-top: 20px;
    }

    .btn-try-again {
      background: #27ae60;
      border: none;
      color: #fff;
      padding: 12px 24px;
      border-radius: 30px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      margin-top: 10px;
      font-family: "Poppins", sans-serif;
      box-shadow: 0 2px 8px rgba(39, 174, 96, 0.3);
    }

    .btn-try-again i {
      transition: transform 0.3s ease;
    }

    .btn-try-again.active i {
      transform: rotate(180deg);
    }

    .btn-try-again:hover {
      background: #2ecc71;
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(39, 174, 96, 0.4);
    }

    .btn-try-again:active {
      transform: scale(0.98);
    }

    /* Oscurecer el formulario cuando está bloqueado - más suave */
    .container.blurred {
      filter: blur(3px);
      pointer-events: none;
      opacity: 0.6;
      transition: all 0.3s ease;
    }
  </style>
</head>

<body>
  <div class="container" id="mainContainer">
    <div class="forms-container">
      <div class="signin-signup">

        <form action="../backend/login.php" method="POST" class="sign-in-form" id="loginForm">
          <h2 class="title" style="color: #27ae60;">Iniciar Sesión</h2>

          <div class="input-field">
            <i class="fas fa-user-md"></i>
            <input
              type="text"
              name="user"
              id="username"
              placeholder="Nombre de Usuario"
              autocomplete="off" />
          </div>

          <div class="input-field password-field">
            <i class="fas fa-pills"></i>
            <input
              type="password"
              name="pass"
              id="pass"
              placeholder="Contraseña"
              autocomplete="off" />

            <i class="fas fa-eye toggle-password" id="togglePassword"></i>
          </div>

          <input type="submit" value="Ingresar" class="btn solid" />
        </form>
      </div>
    </div>

    <div class="panels-container">
      <div class="panel left-panel">
        <div class="content">
          <h3>PharmaSystem</h3>
          <p>
            Optimiza hoy mismo el control de tu inventario, ventas y recetas médicas con nuestra herramienta especializada.
          </p>
        </div>
        <img src="../assets/img/vecteezy_medical-drugs-pill-medical-pills-bottle-pills-capsule_4462047.svg" class="image" alt="Bienvenida" />
      </div>
    </div>
  </div>

  <!-- Overlay de bloqueo (oculto inicialmente) -->
  <div id="lockoutOverlay" class="lockout-overlay" style="display: none;">
    <div class="lockout-card">
      <div class="lockout-icon">
        <i class="fas fa-shield-alt"></i>
      </div>
      <div class="lockout-title" id="lockoutTitle">Usuario Bloqueado</div>
      <div class="lockout-message" id="lockoutMessage">
        Demasiados intentos fallidos.<br>
        Intente nuevamente en:
      </div>
      <div class="lockout-timer" id="lockoutTimer">
        <div class="timer-circle">
          <svg class="timer-svg" width="120" height="120" viewBox="0 0 120 120">
            <circle class="timer-circle-bg" cx="60" cy="60" r="54" fill="none" stroke-width="6" />
            <circle class="timer-circle-fill" id="timerCircle" cx="60" cy="60" r="54" fill="none" stroke-width="6" stroke-linecap="round" />
          </svg>
          <div class="timer-text" id="timerText">00:00</div>
        </div>
        <div class="timer-label" id="timerLabel">TIEMPO RESTANTE</div>
      </div>
      <button class="btn-try-again" id="tryAgainBtn">
        <i class="fas fa-eye-slash" style="margin-right: 8px;"></i>
        Ocultar tiempo
      </button>
      <div class="lockout-footer">
        <i class="fas fa-leaf"></i> PharmaSystem Security
      </div>
    </div>
  </div>

  <script>
    // Toggle de contraseña
    const passwordInput = document.getElementById('pass');
    const togglePassword = document.getElementById('togglePassword');

    togglePassword.addEventListener('click', function() {
      this.classList.add('animate');
      setTimeout(() => {
        this.classList.remove('animate');
      }, 250);

      if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        this.classList.remove('fa-eye');
        this.classList.add('fa-eye-slash');
        this.classList.add('active');
      } else {
        passwordInput.type = 'password';
        this.classList.remove('fa-eye-slash');
        this.classList.add('fa-eye');
        this.classList.remove('active');
      }
    });
  </script>

  <script>
    // Variables para el cronómetro
    let timerInterval = null;
    let currentLockoutType = null;
    let currentRemainingSeconds = 0;
    let currentTotalTime = 0;
    let timerVisible = true;
    let currentUsername = '';

    // Actualizar el círculo de progreso
    function updateProgressCircle(totalSeconds, remainingSeconds, tipo) {
      const circle = document.getElementById('timerCircle');
      if (!circle) return;

      const circumference = 2 * Math.PI * 54;
      let percentage;

      if (tipo === 'corto') {
        percentage = remainingSeconds / totalSeconds;
      } else if (tipo === 'medio') {
        percentage = remainingSeconds / 300;
      } else {
        percentage = remainingSeconds / 900;
      }

      const dashoffset = circumference * (1 - percentage);
      circle.style.strokeDasharray = circumference;
      circle.style.strokeDashoffset = dashoffset;
    }

    function updateTimerDisplay(seconds, tipo) {
      const timerText = document.getElementById('timerText');
      if (tipo === 'corto') {
        timerText.innerHTML = `${seconds}s`;
      } else {
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = seconds % 60;
        timerText.innerHTML = `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
      }
    }

    // Mostrar/ocultar el temporizador
    function toggleTimerVisibility() {
      const timerDiv = document.getElementById('lockoutTimer');
      const btn = document.getElementById('tryAgainBtn');

      if (timerVisible) {
        timerDiv.classList.add('hidden');
        btn.classList.remove('active');
        btn.innerHTML = '<i class="fas fa-eye" style="margin-right: 8px;"></i> Mostrar tiempo';
        timerVisible = false;
      } else {
        timerDiv.classList.remove('hidden');
        btn.classList.add('active');
        btn.innerHTML = '<i class="fas fa-eye-slash" style="margin-right: 8px;"></i> Ocultar tiempo';
        timerVisible = true;
      }
    }

    // Mostrar overlay de bloqueo
    function showLockout(tipo, tiempoRestante, usuario) {
      const overlay = document.getElementById('lockoutOverlay');
      const mainContainer = document.getElementById('mainContainer');
      const lockoutTitle = document.getElementById('lockoutTitle');
      const lockoutMessage = document.getElementById('lockoutMessage');
      const btn = document.getElementById('tryAgainBtn');

      currentLockoutType = tipo;
      currentRemainingSeconds = tiempoRestante;
      currentTotalTime = tipo === 'corto' ? 60 : (tipo === 'medio' ? 300 : 900);
      currentUsername = usuario;

      // Configurar mensajes según tipo de bloqueo
      if (tipo === 'permanente') {
        lockoutTitle.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Usuario bloqueado';
        lockoutMessage.innerHTML = 'Demasiados intentos fallidos.<br>Contacte al administrador del sistema.';
        document.querySelector('.lockout-timer').style.display = 'none';
        btn.style.display = 'none';
        overlay.style.display = 'flex';
        mainContainer.classList.add('blurred');
        return;
      }

      lockoutTitle.innerHTML = '<i class="fas fa-clock"></i> Usuario Bloqueado';
      lockoutMessage.innerHTML = `Usuario "${usuario}" desactivado.<br>Intente nuevamente en:`;

      // Configurar botón
      btn.innerHTML = '<i class="fas fa-eye-slash" style="margin-right: 8px;"></i> Ocultar tiempo';
      timerVisible = true;

      overlay.style.display = 'flex';
      mainContainer.classList.add('blurred');

      // Mostrar el temporizador
      const timerDiv = document.getElementById('lockoutTimer');
      timerDiv.classList.remove('hidden');

      // Actualizar cronómetro
      updateTimerDisplay(currentRemainingSeconds, tipo);
      updateProgressCircle(currentTotalTime, currentRemainingSeconds, tipo);

      // Iniciar cuenta regresiva
      if (timerInterval) clearInterval(timerInterval);

      timerInterval = setInterval(() => {
        if (currentRemainingSeconds <= 1) {
          clearInterval(timerInterval);
          window.location.reload();
        } else {
          currentRemainingSeconds--;
          updateTimerDisplay(currentRemainingSeconds, tipo);
          updateProgressCircle(currentTotalTime, currentRemainingSeconds, tipo);
        }
      }, 1000);
    }

    // Verificar estado de bloqueo al cargar la página (por USUARIO)
    async function checkLockoutStatus() {
      const username = document.getElementById('username').value.trim();
      if (!username) return;
      
      try {
        const response = await fetch('../backend/login.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
          },
          body: `check_lockout=1&user=${encodeURIComponent(username)}`
        });

        const data = await response.json();

        if (data.bloqueado === true && data.tiempo_restante && data.tiempo_restante > 0) {
          showLockout(data.tipo, data.tiempo_restante, username);
        } else {
          const overlay = document.getElementById('lockoutOverlay');
          const mainContainer = document.getElementById('mainContainer');
          overlay.style.display = 'none';
          mainContainer.classList.remove('blurred');
          if (timerInterval) clearInterval(timerInterval);
        }
      } catch (error) {
        console.error('Error verificando bloqueo:', error);
      }
    }

    // Sincronizar con el servidor (actualizar tiempo restante)
    async function syncLockoutTime() {
      const username = document.getElementById('username').value.trim();
      if (!username) return;
      
      try {
        const response = await fetch('../backend/login.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
          },
          body: `check_lockout=1&user=${encodeURIComponent(username)}`
        });

        const data = await response.json();

        if (data.bloqueado === true && data.tiempo_restante && data.tiempo_restante > 0) {
          if (currentRemainingSeconds !== data.tiempo_restante) {
            currentRemainingSeconds = data.tiempo_restante;
            updateTimerDisplay(currentRemainingSeconds, currentLockoutType);
            updateProgressCircle(currentTotalTime, currentRemainingSeconds, currentLockoutType);

            // Mostrar notificación visual de sincronización
            const btn = document.getElementById('tryAgainBtn');
            btn.innerHTML = '<i class="fas fa-check" style="margin-right: 8px;"></i> Sincronizado!';
            setTimeout(() => {
              if (timerVisible) {
                btn.innerHTML = '<i class="fas fa-eye-slash" style="margin-right: 8px;"></i> Ocultar tiempo';
              } else {
                btn.innerHTML = '<i class="fas fa-eye" style="margin-right: 8px;"></i> Mostrar tiempo';
              }
            }, 1500);
          }
        } else if (data.bloqueado === false) {
          window.location.reload();
        }
      } catch (error) {
        console.error('Error sincronizando:', error);
      }
    }

    // Verificar al cargar la página
    document.addEventListener('DOMContentLoaded', () => {
      const urlParams = new URLSearchParams(window.location.search);
      const errorMessage = urlParams.get('error');
      const usernameInput = document.getElementById('username');

      // Verificar bloqueo al perder foco del campo usuario
      usernameInput.addEventListener('blur', () => checkLockoutStatus());
      
      // Si el usuario cambia mientras hay overlay, ocultarlo
      usernameInput.addEventListener('input', () => {
        const overlay = document.getElementById('lockoutOverlay');
        if (overlay.style.display === 'flex') {
          overlay.style.display = 'none';
          document.getElementById('mainContainer').classList.remove('blurred');
          if (timerInterval) clearInterval(timerInterval);
        }
      });

      // 🔥 CONFIGURAR BOTÓN
      const btn = document.getElementById('tryAgainBtn');
      btn.addEventListener('click', () => {
        const overlay = document.getElementById('lockoutOverlay');
        if (overlay.style.display === 'flex') {
          toggleTimerVisibility();
        }
      });

      // 🔁 SINCRONIZACIÓN CADA 10 SEGUNDOS
      setInterval(() => {
        const overlay = document.getElementById('lockoutOverlay');
        if (overlay.style.display === 'flex' && currentLockoutType !== 'permanente') {
          syncLockoutTime();
        }
      }, 10000);

      // 🔥 SI HAY ERROR → MOSTRAR ALERTA
      if (errorMessage) {
        const decodedMessage = decodeURIComponent(errorMessage);

        Swal.fire({
          icon: 'error',
          title: 'Error de Acceso',
          text: decodedMessage,
          confirmButtonText: 'Entendido',
          confirmButtonColor: '#212529',
          focusConfirm: false,
          didOpen: () => {
            const confirmBtn = document.querySelector('.swal2-confirm');
            if (confirmBtn) confirmBtn.focus();
          },
          willClose: () => {
            if (usernameInput) {
              setTimeout(() => {
                usernameInput.focus();
                usernameInput.select();
              }, 100);
            }
          }
        });

        // Limpiar URL
        const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
        window.history.replaceState({ path: newUrl }, '', newUrl);
      } else {
        // 🔥 AUTOFOCUS NORMAL
        if (usernameInput) {
          setTimeout(() => {
            usernameInput.focus();
            usernameInput.select();
          }, 200);
        }
      }
    });

    // Enter para mover el foco
    const userField = document.querySelector('input[name="user"]');
    const passField = document.querySelector('input[name="pass"]');

    if (userField) {
      userField.addEventListener('keydown', function(event) {
        if (event.key === 'Enter') {
          event.preventDefault();
          if (passField) passField.focus();
        }
      });
    }

    // Exponer funciones globalmente
    window.checkLockoutStatus = checkLockoutStatus;
  </script>

</body>

</html>