"""
Tres en Raya (Tic-Tac-Toe) con Poda Alfa-Beta
Motor de IA invencible - Implementacion educativa
"""

import math
import time

# ─────────────────────────────────────────────
# PASO 1: REPRESENTACION DEL TABLERO
# ─────────────────────────────────────────────

def crear_tablero():
    """Crea un tablero 3x3 vacio representado como lista."""
    return [' '] * 9

def imprimir_tablero(tablero):
    """Muestra el tablero en consola con coordenadas."""
    print("\n  1   2   3")
    for fila in range(3):
        celdas = []
        for col in range(3):
            celdas.append(tablero[fila * 3 + col])
        print(f"{fila+1} {' | '.join(celdas)}")
        if fila < 2:
            print("  ---------")
    print()

LINEAS_GANADORAS = [
    (0, 1, 2), (3, 4, 5), (6, 7, 8),  # filas
    (0, 3, 6), (1, 4, 7), (2, 5, 8),  # columnas
    (0, 4, 8), (2, 4, 6)              # diagonales
]

def verificar_ganador(tablero, jugador):
    """Verifica si un jugador ha ganado."""
    for a, b, c in LINEAS_GANADORAS:
        if tablero[a] == tablero[b] == tablero[c] == jugador:
            return True
    return False

def es_empate(tablero):
    """Verifica si el tablero esta lleno sin ganador."""
    return ' ' not in tablero

def movimientos_posibles(tablero):
    """Retorna indices de celdas disponibles."""
    return [i for i, v in enumerate(tablero) if v == ' ']

def juego_terminado(tablero):
    """Verifica si el juego ha concluido."""
    return verificar_ganador(tablero, 'X') or \
           verificar_ganador(tablero, 'O') or \
           es_empate(tablero)

# ─────────────────────────────────────────────
# PASO 2: IMPLEMENTACION DE MINIMAX
# ─────────────────────────────────────────────

llamadas_minimax = 0

def minimax_puro(tablero, es_maximizador, profundidad=0):
    """
    Minimax recursivo sin poda.
    Retorna (puntuacion, movimiento_optimo)
    """
    global llamadas_minimax
    llamadas_minimax += 1

    if verificar_ganador(tablero, 'O'):
        return 10 - profundidad, -1
    if verificar_ganador(tablero, 'X'):
        return profundidad - 10, -1
    if es_empate(tablero):
        return 0, -1

    mejor_movimiento = -1

    if es_maximizador:
        mejor_puntuacion = -math.inf
        for mov in movimientos_posibles(tablero):
            tablero[mov] = 'O'
            puntuacion, _ = minimax_puro(tablero, False, profundidad + 1)
            tablero[mov] = ' '
            if puntuacion > mejor_puntuacion:
                mejor_puntuacion = puntuacion
                mejor_movimiento = mov
        return mejor_puntuacion, mejor_movimiento
    else:
        mejor_puntuacion = math.inf
        for mov in movimientos_posibles(tablero):
            tablero[mov] = 'X'
            puntuacion, _ = minimax_puro(tablero, True, profundidad + 1)
            tablero[mov] = ' '
            if puntuacion < mejor_puntuacion:
                mejor_puntuacion = puntuacion
                mejor_movimiento = mov
        return mejor_puntuacion, mejor_movimiento

# ─────────────────────────────────────────────
# PASO 3: MINIMAX CON PODA ALFA-BETA
# ─────────────────────────────────────────────

llamadas_alfabeta = 0
podas_alfa = 0
podas_beta = 0

def minimax_alfabeta(tablero, es_maximizador, alfa, beta, profundidad=0):
    """
    Minimax con Poda Alfa-Beta.
    alfa: mejor valor garantizado para el maximizador
    beta: mejor valor garantizado para el minimizador
    Retorna (puntuacion, movimiento_optimo)
    """
    global llamadas_alfabeta, podas_alfa, podas_beta
    llamadas_alfabeta += 1

    if verificar_ganador(tablero, 'O'):
        return 10 - profundidad, -1
    if verificar_ganador(tablero, 'X'):
        return profundidad - 10, -1
    if es_empate(tablero):
        return 0, -1

    mejor_movimiento = -1

    if es_maximizador:
        mejor_puntuacion = -math.inf
        for mov in movimientos_posibles(tablero):
            tablero[mov] = 'O'
            puntuacion, _ = minimax_alfabeta(tablero, False, alfa, beta, profundidad + 1)
            tablero[mov] = ' '
            if puntuacion > mejor_puntuacion:
                mejor_puntuacion = puntuacion
                mejor_movimiento = mov
            alfa = max(alfa, mejor_puntuacion)
            if beta <= alfa:          # ← PODA BETA
                podas_beta += 1
                break
        return mejor_puntuacion, mejor_movimiento
    else:
        mejor_puntuacion = math.inf
        for mov in movimientos_posibles(tablero):
            tablero[mov] = 'X'
            puntuacion, _ = minimax_alfabeta(tablero, True, alfa, beta, profundidad + 1)
            tablero[mov] = ' '
            if puntuacion < mejor_puntuacion:
                mejor_puntuacion = puntuacion
                mejor_movimiento = mov
            beta = min(beta, mejor_puntuacion)
            if beta <= alfa:          # ← PODA ALFA
                podas_alfa += 1
                break
        return mejor_puntuacion, mejor_movimiento

# ─────────────────────────────────────────────
# PASO 4: FUNCION DE EVALUACION Y AGENTE
# ─────────────────────────────────────────────

def obtener_movimiento_ia(tablero, usar_poda=True):
    """
    El agente calcula el mejor movimiento.
    Retorna el indice optimo y metricas de eficiencia.
    """
    global llamadas_minimax, llamadas_alfabeta, podas_alfa, podas_beta

    llamadas_minimax = 0
    llamadas_alfabeta = 0
    podas_alfa = 0
    podas_beta = 0

    inicio = time.perf_counter()

    if usar_poda:
        _, mejor = minimax_alfabeta(tablero, True, -math.inf, math.inf)
        nodos = llamadas_alfabeta
        podas = podas_alfa + podas_beta
    else:
        _, mejor = minimax_puro(tablero, True)
        nodos = llamadas_minimax
        podas = 0

    tiempo = (time.perf_counter() - inicio) * 1000

    return mejor, nodos, podas, tiempo

# ─────────────────────────────────────────────
# PASO 5 y 6: COMPETENCIA Y METRICAS
# ─────────────────────────────────────────────

def jugar():
    """Bucle principal: Humano (X) vs IA (O)."""
    tablero = crear_tablero()
    usar_poda = True

    print("=" * 40)
    print("   TRES EN RAYA - MOTOR ALFA-BETA")
    print("=" * 40)
    print("Modo: [1] Alfa-Beta  [2] Minimax puro")
    op = input("Selecciona modo (Enter = Alfa-Beta): ").strip()
    if op == '2':
        usar_poda = False
        print(">> Modo: Minimax puro (sin poda)\n")
    else:
        print(">> Modo: Poda Alfa-Beta activa\n")

    turno_humano = True
    turno_num = 1
    stats_nodos = []
    stats_podas = []

    while not juego_terminado(tablero):
        imprimir_tablero(tablero)

        if turno_humano:
            print("Tu turno (X). Ingresa fila y columna (ej: 1 3):")
            while True:
                try:
                    entrada = input(">> ").strip().split()
                    fila, col = int(entrada[0]) - 1, int(entrada[1]) - 1
                    idx = fila * 3 + col
                    if 0 <= idx < 9 and tablero[idx] == ' ':
                        break
                    print("Celda invalida, intenta de nuevo.")
                except (ValueError, IndexError):
                    print("Formato: fila columna (ej: 2 1)")
            tablero[idx] = 'X'
            turno_humano = False
        else:
            print("IA calculando movimiento...")
            mov, nodos, podas, ms = obtener_movimiento_ia(tablero, usar_poda)
            tablero[mov] = 'O'
            stats_nodos.append(nodos)
            stats_podas.append(podas)
            fila_ia = mov // 3 + 1
            col_ia = mov % 3 + 1
            print(f"IA juega en [{fila_ia},{col_ia}] | "
                  f"Nodos: {nodos} | Podas: {podas} | Tiempo: {ms:.2f}ms")
            turno_humano = True
        turno_num += 1

    imprimir_tablero(tablero)

    if verificar_ganador(tablero, 'X'):
        print("¡Ganaste! (Esto es practicamente imposible)")
    elif verificar_ganador(tablero, 'O'):
        print("¡La IA gana! El agente es invencible.")
    else:
        print("¡Empate! Jugaste perfectamente.")

    if stats_nodos:
        print("\n--- METRICAS DE EFICIENCIA ---")
        print(f"Movimientos de IA:    {len(stats_nodos)}")
        print(f"Nodos totales:        {sum(stats_nodos)}")
        print(f"Promedio por turno:   {sum(stats_nodos)//len(stats_nodos)}")
        if usar_poda and sum(stats_nodos) > 0:
            print(f"Podas realizadas:     {sum(stats_podas)}")

if __name__ == '__main__':
    jugar()
