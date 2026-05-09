<?php
// 1. INICIAR BUFFER Y ZONA HORARIA
ob_start(); 
date_default_timezone_set('America/Lima');

// ─────────────────────────────────────────────
// 2. LÓGICA DEL JUEGO Y MINIMAX
// ─────────────────────────────────────────────

define('LINEAS_GANADORAS', [
    [0,1,2], [3,4,5], [6,7,8],  // filas
    [0,3,6], [1,4,7], [2,5,8],  // columnas
    [0,4,8], [2,4,6]            // diagonales
]);

function verificarGanador(array $tablero, string $jugador): bool {
    foreach (LINEAS_GANADORAS as [$a, $b, $c]) {
        if ($tablero[$a] === $jugador && $tablero[$b] === $jugador && $tablero[$c] === $jugador) return true;
    }
    return false;
}

function lineasGanadoras(array $tablero, string $jugador): array {
    foreach (LINEAS_GANADORAS as $linea) {
        [$a, $b, $c] = $linea;
        if ($tablero[$a] === $jugador && $tablero[$b] === $jugador && $tablero[$c] === $jugador) return $linea;
    }
    return [];
}

function esEmpate(array $tablero): bool {
    return !in_array('', $tablero) && !verificarGanador($tablero, 'X') && !verificarGanador($tablero, 'O');
}

function movimientosPosibles(array $tablero): array {
    return array_keys(array_filter($tablero, fn($v) => $v === ''));
}

function juegoTerminado(array $tablero): bool {
    return verificarGanador($tablero, 'X') || verificarGanador($tablero, 'O') || esEmpate($tablero);
}

function minimax(array &$tablero, bool $esMaximizador, $alfa, $beta, int $profundidad, bool $usarPoda, int &$nodos, int &$podas): array {
    $nodos++;

    if (verificarGanador($tablero, 'O')) return ['score' => 10 - $profundidad, 'mov' => -1];
    if (verificarGanador($tablero, 'X')) return ['score' => $profundidad - 10, 'mov' => -1];
    if (esEmpate($tablero))              return ['score' => 0,               'mov' => -1];

    $mejorMov = -1;

    if ($esMaximizador) {
        $mejorScore = PHP_INT_MIN;
        foreach (movimientosPosibles($tablero) as $mov) {
            $tablero[$mov] = 'O';
            $res = minimax($tablero, false, $alfa, $beta, $profundidad + 1, $usarPoda, $nodos, $podas);
            $tablero[$mov] = '';
            if ($res['score'] > $mejorScore) { $mejorScore = $res['score']; $mejorMov = $mov; }
            $alfa = max($alfa, $mejorScore);
            if ($usarPoda && $beta <= $alfa) { $podas++; break; }
        }
        return ['score' => $mejorScore, 'mov' => $mejorMov];
    } else {
        $mejorScore = PHP_INT_MAX;
        foreach (movimientosPosibles($tablero) as $mov) {
            $tablero[$mov] = 'X';
            $res = minimax($tablero, true, $alfa, $beta, $profundidad + 1, $usarPoda, $nodos, $podas);
            $tablero[$mov] = '';
            if ($res['score'] < $mejorScore) { $mejorScore = $res['score']; $mejorMov = $mov; }
            $beta = min($beta, $mejorScore);
            if ($usarPoda && $beta <= $alfa) { $podas++; break; }
        }
        return ['score' => $mejorScore, 'mov' => $mejorMov];
    }
}

function obtenerMovimientoIA(array $tablero, bool $usarPoda): array {
    $nodos = 0; $podas = 0; $inicio = microtime(true);
    $res = minimax($tablero, true, PHP_INT_MIN, PHP_INT_MAX, 0, $usarPoda, $nodos, $podas);

    $nodosPuros = 0; $podasIgn = 0;
    if ($usarPoda) {
        $t2 = $tablero;
        minimax($t2, true, PHP_INT_MIN, PHP_INT_MAX, 0, false, $nodosPuros, $podasIgn);
    }

    return [
        'movimiento' => $res['mov'],
        'puntuacion' => $res['score'],
        'nodos'      => $nodos,
        'podas'      => $podas,
        'nodosPuros' => $usarPoda ? $nodosPuros : $nodos,
        'ahorrados'  => $usarPoda ? max(0, $nodosPuros - $nodos) : 0,
        'tiempoMs'   => round((microtime(true) - $inicio) * 1000, 2),
    ];
}

// ─────────────────────────────────────────────
// 3. LECTURA DE DATOS Y ENRUTAMIENTO API
// ─────────────────────────────────────────────

$inputJSON = file_get_contents('php://input');
$data = json_decode($inputJSON, true);
$action = $_GET['action'] ?? '';

// --- RUTA: GUARDAR CAPTURA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_capture') {
    if (isset($data['image'])) {
        $img = str_replace(['data:image/png;base64,', ' '], ['', '+'], $data['image']);
        $fileData = base64_decode($img);
        
        $dir = __DIR__ . '/capturas';
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
        
        $fileName = $dir . '/jugada_' . date('Ymd_His') . '_' . uniqid() . '.png';
        file_put_contents($fileName, $fileData);
        
        ob_clean();
        echo json_encode(['success' => true, 'path' => $fileName]);
        exit;
    }
}

// --- RUTA: MOVIMIENTO DE IA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'ia_move') {
    if (!isset($data['tablero']) || !is_array($data['tablero'])) {
        ob_clean();
        echo json_encode(['error' => 'Tablero invalido']);
        exit;
    }

    $tablero  = array_map('strval', $data['tablero']);
    $usarPoda = $data['usarPoda'] ?? true;

    if (juegoTerminado($tablero)) {
        ob_clean();
        echo json_encode(['error' => 'Juego terminado']);
        exit;
    }

    $resultado = obtenerMovimientoIA($tablero, $usarPoda);
    $tablero[$resultado['movimiento']] = 'O';

    $estado = 'jugando';
    $lineaGanadora = [];
    if (verificarGanador($tablero, 'O')) {
        $estado = 'ia_gana';
        $lineaGanadora = lineasGanadoras($tablero, 'O');
    } elseif (esEmpate($tablero)) {
        $estado = 'empate';
    }

    ob_clean(); 
    header('Content-Type: application/json');
    echo json_encode([
        'tablero'      => $tablero,
        'movimiento'   => $resultado['movimiento'],
        'estado'       => $estado,
        'lineaGanadora'=> $lineaGanadora,
        'metricas'     => [
            'nodos'      => $resultado['nodos'],
            'podas'      => $resultado['podas'],
            'nodosPuros' => $resultado['nodosPuros'],
            'ahorrados'  => $resultado['ahorrados'],
            'tiempoMs'   => $resultado['tiempoMs'],
            'eficiencia' => $resultado['nodosPuros'] > 0
                ? round($resultado['ahorrados'] / $resultado['nodosPuros'] * 100) . '%'
                : '0%',
        ],
    ]);
    exit;
}

// ─────────────────────────────────────────────
// 4. INTERFAZ WEB HTML
// ─────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tres en Raya · Motor Alfa-Beta</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/tracking.js/1.1.3/tracking-min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/tracking.js/1.1.3/data/face-min.js"></script>

<style>
  @import url('https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;700;800&display=swap');
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Syne',sans-serif;background:#0f0f11;color:#e8e6f0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem}
  .container{max-width:760px;width:100%}
  h1{font-size:2.4rem;font-weight:800;text-align:center;letter-spacing:-2px;margin-bottom:4px}
  .sub{text-align:center;font-family:'Space Mono',monospace;font-size:11px;color:#888;letter-spacing:2px;text-transform:uppercase;margin-bottom:2rem}
  .layout{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem}
  @media(max-width:560px){.layout{grid-template-columns:1fr}}
  .card{background:#18181c;border:1px solid #2a2a30;border-radius:14px;padding:1.25rem; display: flex; flex-direction: column; align-items: center;}
  .card h2{font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#666;margin-bottom:1rem; width: 100%;}
  
  /* CÁMARA WEB - TAMAÑO AMPLIADO */
  .camera-wrapper { width: 100%; display: flex; justify-content: center; margin-bottom: 1.5rem; position: relative; }
  .camera-container {
      width: 200px; /* <--- CÁMARA MÁS GRANDE AQUÍ */
      height: 200px; /* <--- CÁMARA MÁS GRANDE AQUÍ */
      border-radius: 50%; overflow: hidden;
      border: 4px solid #2a2a30; box-shadow: 0 4px 15px rgba(0,0,0,0.6);
      transition: all 0.3s ease; position: relative; background: #111;
  }
  .camera-container video { width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1); }
  
  /* Estados de la cámara */
  .camera-container.scanning { border-color: #e6a15c; box-shadow: 0 0 25px rgba(230, 161, 92, 0.4); animation: pulse 1.5s infinite; }
  .camera-container.flash-green { box-shadow: 0 0 50px #4caf50; border-color: #4caf50; }
  .camera-container.turn-user { border-color: #4a90d9; box-shadow: 0 0 25px rgba(74, 144, 217, 0.4); }
  .camera-container.turn-ai { border-color: #d96a4a; filter: grayscale(70%) sepia(30%) hue-rotate(-50deg); opacity: 0.8; }
  .camera-container.flash { box-shadow: 0 0 50px #ffffff; border-color: #ffffff; }

  @keyframes pulse { 0% { opacity: 0.8; transform: scale(0.98); } 50% { opacity: 1; transform: scale(1.02); } 100% { opacity: 0.8; transform: scale(0.98); } }
  
  .mode-row{display:flex;gap:8px;margin-bottom:1rem; width: 100%;}
  .mode-btn{flex:1;padding:8px;font-family:'Space Mono',monospace;font-size:10px;border:1px solid #333;border-radius:8px;background:transparent;color:#888;cursor:pointer;transition:all 0.15s}
  .mode-btn.active{background:#1a3a5c;color:#60a8e8;border-color:#2a5080}
  .scores{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:1rem; width: 100%;}
  .sc{background:#111;border-radius:8px;padding:10px;text-align:center}
  .sc .lbl{font-size:10px;color:#555;letter-spacing:1px;text-transform:uppercase}
  .sc .val{font-size:1.8rem;font-weight:800;line-height:1}
  .sc-x .val{color:#4a90d9}.sc-d .val{color:#666}.sc-o .val{color:#d96a4a}
  .board{display:grid;grid-template-columns:repeat(3,1fr);gap:6px; width: 100%;}
  .cell{aspect-ratio:1;background:#111;border:1px solid #2a2a30;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:800;cursor:pointer;transition:all 0.12s;user-select:none}
  .cell:hover:not(.taken){background:#1e1e24;border-color:#444}
  .cell.x{color:#4a90d9}.cell.o{color:#d96a4a}
  .cell.win{background:#1a3a20!important;border-color:#2a6a30!important}
  .cell.pop{animation:pop 0.2s ease}
  @keyframes pop{0%{transform:scale(0.6)}65%{transform:scale(1.12)}100%{transform:scale(1)}}
  
  .status{text-align:center;font-family:'Space Mono',monospace;font-size:13px; font-weight: bold; margin-top:10px;min-height:20px;color:#888; width: 100%; transition: color 0.3s;}
  .status.success { color: #4caf50; }
  .status.scanning { color: #e6a15c; }

  .btn-row{display:flex;gap:8px;margin-top:10px; width: 100%;}
  button.act{flex:1;padding:9px;font-family:'Syne',sans-serif;font-size:13px;font-weight:700;border:1px solid #333;border-radius:8px;background:transparent;color:#ccc;cursor:pointer;transition:all 0.12s}
  button.act:hover{background:#222}
  button.act.prim{background:#1a3a5c;color:#60a8e8;border-color:#2a5080}
  
  .metrics-card { width: 100%; align-items: flex-start; }
  .metric{display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #222;font-family:'Space Mono',monospace;font-size:11px; width: 100%;}
  .metric:last-child{border-bottom:none}
  .metric .k{color:#555}
  .metric .v{color:#e8e6f0;font-weight:700}
  .metric .v.hi{color:#4caf82}
  .log{max-height:200px;overflow-y:auto;margin-top:4px; width: 100%;}
  .log-item{font-family:'Space Mono',monospace;font-size:10px;padding:4px 0;border-bottom:1px solid #1a1a1f;display:flex;gap:8px;color:#666}
  .log-item .num{color:#444;width:16px;flex-shrink:0}
  .log-item.ai .txt{color:#d96a4a}
  .turn{display:flex;align-items:center;gap:8px;justify-content:center;margin-bottom:8px;font-size:13px;font-weight:700; width: 100%;}
  .dot{width:10px;height:10px;border-radius:50%}
  .dot-x{background:#4a90d9}.dot-o{background:#d96a4a}
</style>
</head>
<body>
<div class="container">
  <h1>Tres en Raya IA</h1>
  <div class="sub">PHP Backend · Cámara Web · Capturas</div>

  <div class="layout">
    <div>
      <div class="card">
        <div class="camera-wrapper">
            <div class="camera-container" id="cameraBox">
                <video id="webcam" autoplay playsinline></video>
            </div>
        </div>

        <div class="scores">
          <div class="sc sc-x"><div class="lbl">Humano X</div><div class="val" id="sc-x">0</div></div>
          <div class="sc sc-d"><div class="lbl">Empate</div><div class="val" id="sc-d">0</div></div>
          <div class="sc sc-o"><div class="lbl">IA O</div><div class="val" id="sc-o">0</div></div>
        </div>
        <div class="mode-row">
          <button class="mode-btn active" id="btn-ab" onclick="setMode('ab')">Alfa-Beta</button>
          <button class="mode-btn" id="btn-mm" onclick="setMode('mm')">Minimax puro</button>
        </div>
        <div class="turn" id="turn-ind">
          <div class="dot dot-x" id="turn-dot"></div>
          <span id="turn-txt">Esperando jugador...</span>
        </div>
        <div class="board" id="board">
          <?php for($i=0;$i<9;$i++): ?>
          <div class="cell" data-i="<?=$i?>" onclick="humanMove(<?=$i?>)"></div>
          <?php endfor; ?>
        </div>
        <div class="status" id="status">Iniciando cámara...</div>
        <div class="btn-row">
          <button class="act" onclick="reset()">Nueva partida</button>
          <button class="act prim" onclick="iaFirst()">IA primero</button>
        </div>
      </div>
    </div>

    <div style="display:flex;flex-direction:column;gap:1.5rem">
      <div class="card metrics-card">
        <h2>Métricas del Backend (PHP)</h2>
        <div class="metric"><span class="k">Nodos explorados</span><span class="v" id="m-nodos">0</span></div>
        <div class="metric"><span class="k">Podas realizadas</span><span class="v hi" id="m-podas">0</span></div>
        <div class="metric"><span class="k">Nodos ahorrados</span><span class="v hi" id="m-ahorrados">0</span></div>
        <div class="metric"><span class="k">Eficiencia poda</span><span class="v" id="m-eff">—</span></div>
        <div class="metric"><span class="k">Tiempo cálculo PHP</span><span class="v" id="m-tiempo">—</span></div>
        <div class="metric"><span class="k">Modo activo</span><span class="v" id="m-modo">Alfa-Beta</span></div>
      </div>
      <div class="card metrics-card">
        <h2>Registro de movimientos</h2>
        <div class="log" id="log"></div>
      </div>
    </div>
  </div>
</div>

<script>
let tablero = Array(9).fill('');
let turnoHumano = true;
let juegoTerminado = false;
let modo = 'ab';
let scores = {x:0,o:0,d:0};
let logItems = [];
let numMov = 0;

let rostroDetectado = false; 
let trackerTask = null; // Guardará la tarea de tracking
const baseUrl = window.location.pathname;

// ─────────────────────────────────────────────
// INICIALIZAR CÁMARA Y DETECCIÓN FACIAL REAL
// ─────────────────────────────────────────────
async function initWebcam() {
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ video: true });
        const videoEl = document.getElementById('webcam');
        videoEl.srcObject = stream;
        
        // Cuando el video empiece a reproducirse, encendemos el detector de rostros
        videoEl.onloadedmetadata = () => {
            iniciarEscaneoFacial();
        };

    } catch (err) {
        console.error("Error cámara:", err);
        document.getElementById('status').textContent = 'Cámara no detectada. Puedes jugar sin fotos.';
        rostroDetectado = true; // Liberar bloqueo
        setTurn(true);
    }
}
window.addEventListener('load', initWebcam);

function iniciarEscaneoFacial() {
    const camBox = document.getElementById('cameraBox');
    const statusEl = document.getElementById('status');
    
    camBox.className = 'camera-container scanning';
    statusEl.className = 'status scanning';
    statusEl.textContent = 'Buscando tu rostro... asómate a la cámara 👀';

    // Configurar Tracking.js para detectar caras
    const tracker = new tracking.ObjectTracker('face');
    tracker.setInitialScale(4);
    tracker.setStepSize(2);
    tracker.setEdgesDensity(0.1);

    // Adjuntar el tracker a nuestro elemento de video
    trackerTask = tracking.track('#webcam', tracker);

    // Evento que se dispara continuamente buscando caras
    tracker.on('track', function(event) {
        // Si event.data tiene algo, significa que encontró al menos un rostro
        if (event.data.length > 0 && !rostroDetectado) {
            rostroDetectado = true;
            
            // DETENEMOS EL TRACKER para que tu PC no explote de calor calculando caras
            trackerTask.stop();
            
            camBox.className = 'camera-container flash-green';
            statusEl.className = 'status success';
            statusEl.textContent = '¡Rostro detectado! Bienvenido, vamos a jugar 🎮';
            
            // Tomamos la captura inicial y la mandamos a PHP
            capturarRostro();
            
            // Habilitamos el juego tras unos segundos
            setTimeout(() => {
                statusEl.className = 'status';
                statusEl.textContent = 'Haz clic en una celda para tu primer movimiento';
                setTurn(true); 
            }, 2500);
        }
    });
}

function capturarRostro() {
    const video = document.getElementById('webcam');
    if (video.videoWidth > 0 && video.videoHeight > 0) {
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        const ctx = canvas.getContext('2d');
        
        ctx.translate(canvas.width, 0);
        ctx.scale(-1, 1);
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        
        const dataURI = canvas.toDataURL('image/png');
        
        const camBox = document.getElementById('cameraBox');
        camBox.classList.add('flash');
        setTimeout(() => { camBox.classList.remove('flash'); }, 150);

        fetch(baseUrl + '?action=save_capture', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ image: dataURI })
        }).catch(err => console.error("Error guardando captura:", err));
    }
}

// ─────────────────────────────────────────────
// LÓGICA DEL FRONTEND (JUEGO)
// ─────────────────────────────────────────────

function renderBoard() {
  document.querySelectorAll('.cell').forEach((c,i) => {
    c.textContent = tablero[i] === 'X' ? 'X' : tablero[i] === 'O' ? 'O' : '';
    c.className = 'cell' + (tablero[i] ? ' taken ' + tablero[i].toLowerCase() : '');
  });
}

function setTurn(human) {
  document.getElementById('turn-dot').className = 'dot ' + (human ? 'dot-x' : 'dot-o');
  document.getElementById('turn-txt').textContent = human ? 'Tu turno (X)' : 'IA calculando en PHP...';
  
  if (rostroDetectado) {
      const camBox = document.getElementById('cameraBox');
      camBox.className = human ? 'camera-container turn-user' : 'camera-container turn-ai';
  }
}

function addLog(txt, isAi=false) {
  numMov++;
  logItems.unshift({num: numMov, txt, isAi});
  if (logItems.length > 20) logItems.pop();
  document.getElementById('log').innerHTML = logItems.map(l =>
    `<div class="log-item${l.isAi?' ai':''}"><span class="num">${l.num}</span><span class="txt">${l.txt}</span></div>`
  ).join('');
}

function updateMetrics(m) {
  document.getElementById('m-nodos').textContent = m.nodos;
  document.getElementById('m-podas').textContent = m.podas;
  document.getElementById('m-ahorrados').textContent = m.ahorrados;
  document.getElementById('m-eff').textContent = modo === 'ab' ? m.eficiencia : 'N/A';
  document.getElementById('m-tiempo').textContent = m.tiempoMs + ' ms';
}

function updateScores() {
  document.getElementById('sc-x').textContent = scores.x;
  document.getElementById('sc-d').textContent = scores.d;
  document.getElementById('sc-o').textContent = scores.o;
}

async function humanMove(i) {
  if (juegoTerminado || !turnoHumano || tablero[i] || !rostroDetectado) return;
  
  tablero[i] = 'X';
  renderBoard();
  document.querySelectorAll('.cell')[i].classList.add('pop');
  addLog(`Humano → celda ${i+1}`);

  capturarRostro();

  if (checkLocal('X')) { endGame('human_wins'); return; }
  if (tablero.every(c => c !== '')) { endGame('empate'); return; }

  turnoHumano = false;
  setTurn(false);
  await iaMove();
}

async function iaMove() {
  await new Promise(r => setTimeout(r, 600)); 

  try {
      const resp = await fetch(baseUrl + '?action=ia_move', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({tablero, usarPoda: modo === 'ab'})
      });
      
      const textResponse = await resp.text();
      let data;
      try {
          data = JSON.parse(textResponse);
      } catch (err) {
          console.error("Respuesta rota de PHP:", textResponse);
          document.getElementById('status').textContent = "Error interno de PHP. Abre F12 (Consola).";
          return;
      }
      
      tablero = data.tablero;
      renderBoard();
      
      const c = document.querySelectorAll('.cell')[data.movimiento];
      c.classList.add('pop');
      updateMetrics(data.metricas);
      const m = data.metricas;
      addLog(`PHP IA (${modo==='ab'?'Alfa-Beta':'Minimax'}) → celda ${data.movimiento+1} | nodos:${m.nodos}`, true);

      if (data.estado === 'ia_gana') {
        data.lineaGanadora.forEach(i => document.querySelectorAll('.cell')[i].classList.add('win'));
        endGame('ia_wins'); return;
      }
      if (data.estado === 'empate') { endGame('empate'); return; }

      turnoHumano = true;
      setTurn(true);
      document.getElementById('status').textContent = '';
  } catch (error) {
      console.error("Error de Red:", error);
      document.getElementById('status').textContent = "Fallo de red en servidor local.";
  }
}

function checkLocal(jugador) {
  const lineas = [[0,1,2],[3,4,5],[6,7,8],[0,3,6],[1,4,7],[2,5,8],[0,4,8],[2,4,6]];
  return lineas.some(([a,b,c]) => tablero[a]===jugador && tablero[b]===jugador && tablero[c]===jugador);
}

function endGame(tipo) {
  juegoTerminado = true;
  document.getElementById('cameraBox').className = 'camera-container';
  
  const statusEl = document.getElementById('status');
  statusEl.className = 'status';
  if (tipo === 'ia_wins') { statusEl.textContent = '¡La IA (PHP) gana!'; scores.o++; }
  else if (tipo === 'human_wins') { statusEl.textContent = '¡Ganaste!'; scores.x++; }
  else { statusEl.textContent = '¡Empate!'; scores.d++; }
  
  updateScores();
}

function reset() {
  if (!rostroDetectado) return;
  tablero = Array(9).fill('');
  juegoTerminado = false;
  turnoHumano = true;
  numMov = 0;
  renderBoard();
  document.getElementById('status').textContent = 'Haz clic en una celda para comenzar';
  setTurn(true);
}

async function iaFirst() {
  if (!rostroDetectado) return;
  reset();
  turnoHumano = false;
  setTurn(false);
  await iaMove();
}

function setMode(m) {
  modo = m;
  document.getElementById('btn-ab').className = 'mode-btn' + (m==='ab'?' active':'');
  document.getElementById('btn-mm').className = 'mode-btn' + (m==='mm'?' active':'');
  document.getElementById('m-modo').textContent = m==='ab'?'Alfa-Beta':'Minimax puro';
  reset();
}

renderBoard();
</script>
</body>
</html>