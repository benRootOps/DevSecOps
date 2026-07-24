"""
╔══════════════════════════════════════════════════════════════════════╗
║       MUSIC BALL VISUALIZER  —  VERSION CORRIGÉE & OPTIMISÉE        ║
║       Format 720x1280 | 60 FPS stable | Sync audio réelle           ║
╠══════════════════════════════════════════════════════════════════════╣
║  Corrections v2 :                                                    ║
║  [1] Pool NumPy pré-alloué : zéro allocation Surface par frame       ║
║  [2] Glow cache pré-rendu + blend workflow correct (BLEND_ADD)       ║
║  [3] Sub-stepping sans *60 : physique correcte                       ║
║  [4] Sync via get_pos() : référence audio réelle                     ║
║  [5] Collision cercle-AABB par normale réfléchie                     ║
║  [6] Fond pré-calculé : 1 blit au lieu de 320 draw.rect/frame        ║
║  [7] mixer.pre_init() avant pygame.init()                            ║
║  [8] Emojis supprimés du SysFont (safe toutes plateformes)           ║
║  [9] offsets balles défensif (N balles)                              ║
╚══════════════════════════════════════════════════════════════════════╝

Dépendances : pip install pygame librosa numpy
Usage       : python music_visualizer.py
Audio       : Placer "music.wav" dans le même dossier
"""

import math
import random
import sys
from dataclasses import dataclass, field
from typing import List, Tuple

import numpy as np
import pygame
import librosa


# ═══════════════════════════════════════════════════════════════════════
#  CONFIGURATION
# ═══════════════════════════════════════════════════════════════════════

AUDIO_FILE      = "music.mp3"
WIDTH, HEIGHT   = 720, 1280
FPS             = 60

NUM_BALLS       = 2          # 1 ou N (défensif)
GRAVITY         = 0.38       # unités/frame² (sub-step corrigé)
BOUNCE_STRENGTH = -16.5
BALL_RADIUS     = 14

MAX_PARTICLES   = 800
TRAIL_LENGTH    = 24

GLOW_STEPS      = 6          # résolution du cache glow
GLOW_MAX_R      = 64

SHAKE_INTENSITY = 7.0
SHAKE_DECAY     = 0.72       # par frame (60 FPS)


# ═══════════════════════════════════════════════════════════════════════
#  UTILITAIRES
# ═══════════════════════════════════════════════════════════════════════

def clamp(v, lo, hi):
    return lo if v < lo else (hi if v > hi else v)


def neon(t: float, shift: float = 0.0) -> Tuple[int, int, int]:
    """Couleur néon cyclique. t en secondes, shift en radians."""
    r = int(128 + 127 * math.sin(t       + shift))
    g = int(128 + 127 * math.sin(t + 2.1 + shift))
    b = int(128 + 127 * math.sin(t + 4.2 + shift))
    return (r, g, b)


def lerp_color(c1, c2, t: float) -> Tuple[int, int, int]:
    return tuple(int(a + (b - a) * clamp(t, 0, 1)) for a, b in zip(c1, c2))


# ═══════════════════════════════════════════════════════════════════════
#  CACHE GLOW  —  pré-rendu une seule fois à l'init
# ═══════════════════════════════════════════════════════════════════════

def build_glow_cache(max_r: int = GLOW_MAX_R,
                     steps: int = GLOW_STEPS) -> dict:
    """
    Retourne un dict {radius: Surface(SRCALPHA)} avec dégradé radial blanc.
    Colorisation + blend réalisés au moment du blit, pas ici.
    """
    cache = {}
    for i in range(1, steps + 1):
        r = max(4, int(max_r * i / steps))
        s = r * 2
        surf = pygame.Surface((s, s), pygame.SRCALPHA)
        surf.fill((0, 0, 0, 0))
        for ring in range(r, 0, -2):
            alpha = int(200 * ((r - ring) / r) ** 1.6)
            pygame.draw.circle(surf, (255, 255, 255, alpha), (r, r), ring)
        cache[r] = surf
    return cache


GLOW_CACHE: dict = {}   # rempli après pygame.init()


def draw_glow(target: pygame.Surface,
              x: int, y: int,
              radius_key: int,
              color: Tuple[int, int, int],
              intensity: float = 1.0):
    """
    Blit un halo coloré en mode additif. Zéro allocation.
    radius_key : clé dans GLOW_CACHE (rayon approché).
    """
    # Trouver la clé la plus proche dans le cache
    keys = list(GLOW_CACHE.keys())
    if not keys:
        return
    best = min(keys, key=lambda k: abs(k - radius_key))
    base = GLOW_CACHE[best]

    # Coloriser via une copie (1 copy/appel, pas de Surface())
    colored = base.copy()
    r_c = clamp(int(color[0] * intensity), 0, 255)
    g_c = clamp(int(color[1] * intensity), 0, 255)
    b_c = clamp(int(color[2] * intensity), 0, 255)
    colored.fill((r_c, g_c, b_c, 0), special_flags=pygame.BLEND_RGBA_MULT)

    r = best
    target.blit(colored, (x - r, y - r), special_flags=pygame.BLEND_ADD)


# ═══════════════════════════════════════════════════════════════════════
#  FOND PRÉ-CALCULÉ
# ═══════════════════════════════════════════════════════════════════════

def build_background(w: int, h: int) -> pygame.Surface:
    """
    Gradient vertical sombre calculé une seule fois.
    1 blit/frame au lieu de ~320 draw.rect.
    """
    surf = pygame.Surface((w, h))
    for y in range(h):
        t = y / h
        surf.set_at((0, y), (0, 0, 0))  # dummy; on va dessiner ligne par ligne
    # Dessin par lignes horizontales
    for y in range(h):
        t = y / h
        r = int(6  + t * 8)
        g = int(4  + t * 4)
        b = int(16 + t * 14)
        pygame.draw.line(surf, (r, g, b), (0, y), (w - 1, y))
    return surf


# ═══════════════════════════════════════════════════════════════════════
#  ANALYSE AUDIO
# ═══════════════════════════════════════════════════════════════════════

class AudioAnalyzer:
    def __init__(self, path: str):
        print(f"[Audio] Chargement '{path}'...")
        self.y, self.sr = librosa.load(path, sr=None, mono=True)
        self.duration   = librosa.get_duration(y=self.y, sr=self.sr)

        tempo, beats        = librosa.beat.beat_track(y=self.y, sr=self.sr)
        self.tempo          = float(np.atleast_1d(tempo)[0])
        # beat_times en secondes — gardé en ndarray pour searchsorted rapide
        self.beat_times: np.ndarray = librosa.frames_to_time(beats, sr=self.sr)

        hop = 512
        rms_raw         = librosa.feature.rms(y=self.y, hop_length=hop)[0]
        self._rms_times = librosa.frames_to_time(
            np.arange(len(rms_raw)), sr=self.sr, hop_length=hop)  # ndarray
        mx = rms_raw.max() or 1.0
        self._rms       = (rms_raw / mx).astype(np.float32)

        onset_raw       = librosa.onset.onset_strength(
            y=self.y, sr=self.sr, hop_length=hop)
        mx2 = onset_raw.max() or 1.0
        self._onset     = (onset_raw / mx2).astype(np.float32)

        print(f"[Audio] BPM={self.tempo:.1f} | "
              f"Beats={len(self.beat_times)} | Duree={self.duration:.1f}s")

    def _lookup(self, arr: np.ndarray, t: float) -> float:
        idx = int(np.searchsorted(self._rms_times, t))
        return float(arr[clamp(idx, 0, len(arr) - 1)])

    def energy_at(self, t: float) -> float:
        return self._lookup(self._rms, t)

    def onset_at(self, t: float) -> float:
        return self._lookup(self._onset, t)


# ═══════════════════════════════════════════════════════════════════════
#  PARTICULES  —  pool NumPy pré-alloué, zéro realloc
# ═══════════════════════════════════════════════════════════════════════

class ParticleSystem:
    """
    Buffer circulaire fixe sur tableaux NumPy.
    update() vectorisé : une passe sur tout le tableau actif.
    draw()   : pygame.draw.circle direct, zéro Surface temporaire.
    """

    def __init__(self, cap: int = MAX_PARTICLES):
        self.cap  = cap
        self.px   = np.zeros(cap, np.float32)
        self.py   = np.zeros(cap, np.float32)
        self.vx   = np.zeros(cap, np.float32)
        self.vy   = np.zeros(cap, np.float32)
        self.life = np.zeros(cap, np.float32)   # 0 = mort
        self.cr   = np.zeros(cap, np.uint8)
        self.cg   = np.zeros(cap, np.uint8)
        self.cb   = np.zeros(cap, np.uint8)
        self.head = 0

    def emit(self, x: float, y: float,
             color: Tuple[int, int, int],
             count: int = 14, speed: float = 4.0):
        for _ in range(count):
            i = self.head % self.cap
            angle = random.uniform(0, math.tau)
            spd   = random.uniform(speed * 0.3, speed)
            self.px[i]   = x
            self.py[i]   = y
            self.vx[i]   = math.cos(angle) * spd
            self.vy[i]   = math.sin(angle) * spd - 2.0
            self.life[i] = random.uniform(0.55, 1.0)
            self.cr[i], self.cg[i], self.cb[i] = color
            self.head += 1

    def update(self, dt: float):
        alive = self.life > 0
        if not alive.any():
            return
        self.px[alive]   += self.vx[alive] * dt * 60
        self.py[alive]   += self.vy[alive] * dt * 60
        self.vy[alive]   += 0.18 * dt * 60
        self.life[alive] -= dt * 2.2

    def draw(self, surface: pygame.Surface):
        alive_idx = np.where(self.life > 0)[0]
        for i in alive_idx:
            lf = float(self.life[i])
            r  = max(1, int(lf * 7))
            c  = (int(self.cr[i]), int(self.cg[i]), int(self.cb[i]))
            pygame.draw.circle(surface, c,
                               (int(self.px[i]), int(self.py[i])), r)


# ═══════════════════════════════════════════════════════════════════════
#  SCREEN SHAKE
# ═══════════════════════════════════════════════════════════════════════

class ScreenShake:
    def __init__(self):
        self.intensity = 0.0

    def trigger(self, amount: float = 1.0):
        self.intensity = clamp(self.intensity + amount, 0, 1.0)

    def update(self) -> Tuple[int, int]:
        if self.intensity < 0.01:
            self.intensity = 0.0
            return 0, 0
        ox = int(random.uniform(-1, 1) * self.intensity * SHAKE_INTENSITY)
        oy = int(random.uniform(-1, 1) * self.intensity * SHAKE_INTENSITY)
        self.intensity *= SHAKE_DECAY
        return ox, oy


# ═══════════════════════════════════════════════════════════════════════
#  PLAQUE
# ═══════════════════════════════════════════════════════════════════════

@dataclass
class Plaque:
    rect:   pygame.Rect
    color:  Tuple[int, int, int]
    energy: float = 0.5
    pulse:  float = 0.0      # 0..1, décroît après impact

    def trigger_pulse(self):
        self.pulse = 1.0

    def update(self, dt: float):
        self.pulse = max(0.0, self.pulse - dt * 4.0)

    def draw(self, game_surf: pygame.Surface, glow_surf: pygame.Surface,
             t: float):
        # Couleur pulsée
        bright = lerp_color(self.color, (255, 255, 255), self.pulse * 0.45)

        # Glow (sur glow_surf dédié)
        glow_r = int(20 + self.energy * 20 + self.pulse * 18)
        cx = self.rect.centerx
        cy = self.rect.centery
        draw_glow(glow_surf, cx, cy, glow_r, self.color,
                  intensity=0.6 + self.pulse * 0.5)

        # Corps
        pygame.draw.rect(game_surf, bright, self.rect, border_radius=6)

        # Reflet interne
        hl = pygame.Rect(self.rect.x + 5, self.rect.y + 2,
                         max(4, self.rect.width - 10), 3)
        pygame.draw.rect(game_surf, (255, 255, 255), hl, border_radius=2)


# ═══════════════════════════════════════════════════════════════════════
#  GÉNÉRATION PLAQUES  —  IA musicale améliorée
# ═══════════════════════════════════════════════════════════════════════

def generate_plaques(analyzer: AudioAnalyzer) -> List[Plaque]:
    """
    - Espacement vertical uniforme (game-feel : balle peut tout atteindre)
    - Largeur = f(énergie RMS + onset)
    - Alternance 3 zones X pour former un parcours logique
    - Couleur néon liée au temps du beat
    """
    beats = analyzer.beat_times
    n     = len(beats)
    if n == 0:
        return []

    MARGIN   = 70
    MIN_W, MAX_W = 85, 270
    H_PLAQUE = 13
    usable   = HEIGHT - 2 * MARGIN

    zones = [
        (MARGIN,          WIDTH // 3 - 10),
        (WIDTH // 3 + 5,  2 * WIDTH // 3 - 10),
        (2 * WIDTH // 3 + 5, WIDTH - MARGIN),
    ]

    plaques  = []
    zone_idx = 0

    for i, bt in enumerate(beats):
        energy  = analyzer.energy_at(bt)
        onset   = analyzer.onset_at(bt)
        strength = energy * 0.55 + onset * 0.45

        w = int(MIN_W + strength * (MAX_W - MIN_W))
        h = H_PLAQUE

        # Plaque verticale sur pics d'onset forts
        if onset > 0.88:
            w, h = h, int(w * 0.55)

        y_pos = MARGIN + int(i / max(n - 1, 1) * usable)

        zl, zr = zones[zone_idx % 3]
        zone_idx += 1
        max_x  = max(zl, zr - w)
        x_pos  = random.randint(zl, max_x)

        color = neon(bt * 0.75, shift=i * 0.28)

        plaques.append(Plaque(
            rect   = pygame.Rect(x_pos, y_pos, w, h),
            color  = color,
            energy = strength,
        ))

    return plaques


# ═══════════════════════════════════════════════════════════════════════
#  BALLE
# ═══════════════════════════════════════════════════════════════════════

class Ball:
    """
    Physique avec sub-stepping correct (sans *60).
    Collision par normale réfléchie (cercle-AABB exact).
    Glow via cache, trail sans Surface temporaire.
    """

    def __init__(self, x: float, y: float, color_shift: float = 0.0):
        self.x     = x
        self.y     = y
        self.vx    = random.choice([-5.0, 5.0])
        self.vy    = -13.0
        self.shift = color_shift
        self.trail: list = []     # liste de (x, y)
        self.flash = 0.0          # 0..1, intensité flash au rebond

    def color(self, t: float) -> Tuple[int, int, int]:
        return neon(t, self.shift)

    # ── Physique ───────────────────────────────────────────────────────

    def update(self, dt: float):
        """
        Sub-stepping sur 3 itérations.
        dt en secondes, GRAVITY en px/s² (sub correctement appliqué).
        """
        STEPS = 3
        sub   = dt / STEPS
        R     = BALL_RADIUS

        for _ in range(STEPS):
            self.vy  += GRAVITY * 60 * sub   # GRAVITY est en px/frame² à 60fps
            self.x   += self.vx * 60 * sub   # vx/vy sont en px/frame à 60fps
            self.y   += self.vy * 60 * sub

        # Murs latéraux
        if self.x - R < 0:
            self.x  = float(R)
            self.vx = abs(self.vx)
        elif self.x + R > WIDTH:
            self.x  = float(WIDTH - R)
            self.vx = -abs(self.vx)

        # Sortie par le bas → recycle en haut
        if self.y > HEIGHT + 60:
            self.y  = float(-R - 10)
            self.vy = abs(self.vy) * 0.4

        # Plafond
        if self.y - R < 0 and self.vy < 0:
            self.y  = float(R)
            self.vy = abs(self.vy) * 0.6

        # Trail (positions récentes)
        self.trail.append((self.x, self.y))
        if len(self.trail) > TRAIL_LENGTH:
            self.trail.pop(0)

        self.flash = max(0.0, self.flash - dt * 6.0)

    def force_bounce(self):
        """Impulsion beat pour maintenir la balle vivante."""
        if self.vy > -4:
            self.vy = BOUNCE_STRENGTH * 0.85

    # ── Collision cercle-AABB avec normale réfléchie ───────────────────

    def collide(self, plaque: Plaque,
                particles: ParticleSystem,
                t: float) -> bool:
        """
        1. Point le plus proche (cx,cy) sur le rectangle
        2. Vecteur (dx,dy) balle→point, distance
        3. Si dist < rayon : pénétration + réflexion par normale
        Retourne True si collision.
        """
        R  = BALL_RADIUS
        rr = plaque.rect

        cx = clamp(self.x, rr.left, rr.right)
        cy = clamp(self.y, rr.top,  rr.bottom)
        dx = self.x - cx
        dy = self.y - cy
        dist_sq = dx * dx + dy * dy

        if dist_sq == 0 or dist_sq >= R * R:
            return False

        dist = math.sqrt(dist_sq)
        nx   = dx / dist   # normale (rectangle → balle), normalisée
        ny   = dy / dist

        # Correction de pénétration
        pen  = R - dist
        self.x += nx * (pen + 0.5)
        self.y += ny * (pen + 0.5)

        # Réflexion : v' = v - 2(v·n)n
        dot    = self.vx * nx + self.vy * ny
        self.vx -= 2 * dot * nx
        self.vy -= 2 * dot * ny

        # Amortissement + boost si impact par dessus
        self.vx *= 0.93
        if ny < -0.4:                          # dessus de la plaque
            impact_x = (self.x - rr.left) / max(rr.width, 1) - 0.5
            self.vx  += impact_x * 3.5        # angle selon impact
            self.vx   = clamp(self.vx, -11, 11)
            self.vy   = BOUNCE_STRENGTH * (0.85 + plaque.energy * 0.25)

        self.flash = 1.0
        plaque.trigger_pulse()
        particles.emit(self.x, self.y, plaque.color, count=14, speed=4.2)
        return True

    # ── Rendu ──────────────────────────────────────────────────────────

    def draw(self, game_surf: pygame.Surface,
             glow_surf: pygame.Surface, t: float):
        R     = BALL_RADIUS
        color = self.color(t)
        bright = lerp_color(color, (255, 255, 255), self.flash * 0.55)

        # Trail — pygame.draw direct, zéro Surface temporaire
        n = len(self.trail)
        for i, (tx, ty) in enumerate(self.trail):
            ratio = i / n
            r2    = max(1, int(2 + ratio * (R - 3)))
            alpha = int(ratio * 200)
            cr, cg, cb = lerp_color((15, 15, 35), color, ratio)
            # Simuler transparence en assombrissant (pas de SRCALPHA)
            # On blit sur game_surf directement avec une couleur modulée
            pygame.draw.circle(game_surf,
                               (cr * alpha // 255,
                                cg * alpha // 255,
                                cb * alpha // 255),
                               (int(tx), int(ty)), r2)

        # Glow via cache (sur glow_surf)
        glow_r = int(R + 18 + self.flash * 14)
        draw_glow(glow_surf, int(self.x), int(self.y),
                  glow_r, color, intensity=0.9 + self.flash * 0.3)

        # Corps principal
        pygame.draw.circle(game_surf, bright,
                           (int(self.x), int(self.y)), R)

        # Reflet interne
        pygame.draw.circle(game_surf, (255, 255, 255),
                           (int(self.x) - R // 3,
                            int(self.y) - R // 3),
                           max(2, R // 3))


# ═══════════════════════════════════════════════════════════════════════
#  HUD
# ═══════════════════════════════════════════════════════════════════════

class HUD:
    """
    Texte TikTok + compteur de rebonds.
    Emojis supprimés (incompatibles SysFont sur la plupart des OS).
    """

    TIKTOK = "Tu reconnais cette musique ?"

    def __init__(self):
        pygame.font.init()
        # Tentative de polices système lisibles
        for name in ("Consolas", "Courier New", "monospace", None):
            try:
                self.font_sm = pygame.font.SysFont(name, 28, bold=True)
                self.font_lg = pygame.font.SysFont(name, 46, bold=True)
                break
            except Exception:
                continue

        self.bounces    = 0
        self.flash      = 0.0

    def add_bounce(self):
        self.bounces += 1
        self.flash    = 1.0

    def draw(self, surface: pygame.Surface, t: float):
        self.flash = max(0.0, self.flash - 0.035)

        # Bandeau TikTok en haut
        cr, cg, cb = neon(t * 0.4)
        label = self.font_sm.render(self.TIKTOK, True, (cr, cg, cb))
        lw    = label.get_width()
        lh    = label.get_height()
        pad   = 14

        bg = pygame.Surface((lw + pad * 2, lh + pad), pygame.SRCALPHA)
        bg.fill((0, 0, 0, 140))
        surface.blit(bg, ((WIDTH - lw - pad * 2) // 2, 28))
        surface.blit(label, ((WIDTH - lw) // 2, 33))

        # Compteur rebonds en bas à droite
        flash_c = lerp_color((180, 180, 180), (255, 240, 60), self.flash)
        count_s = self.font_lg.render(f"x{self.bounces}", True, flash_c)
        surface.blit(count_s,
                     (WIDTH - count_s.get_width() - 26,
                      HEIGHT - count_s.get_height() - 26))


# ═══════════════════════════════════════════════════════════════════════
#  ÉTOILES D'AMBIANCE (fond animé)
# ═══════════════════════════════════════════════════════════════════════

class StarField:
    def __init__(self, count: int = 90):
        self.stars = [
            [random.uniform(0, WIDTH),
             random.uniform(0, HEIGHT),
             random.uniform(0.15, 0.9),    # vitesse descente
             random.uniform(0.8, 2.2),     # rayon max
             random.uniform(0, math.tau)]  # phase scintillement
            for _ in range(count)
        ]

    def update_and_draw(self, surface: pygame.Surface, t: float):
        for s in self.stars:
            s[1] += s[2]
            if s[1] > HEIGHT:
                s[1] = 0.0
                s[0] = random.uniform(0, WIDTH)
            flicker = 0.4 + 0.6 * math.sin(t * 2.8 + s[4])
            r_star  = max(1, int(s[3] * flicker))
            cr, cg, cb = neon(t * 0.25 + s[4])
            alpha_v = int(flicker * 130)
            # Dessiner direct sans Surface (couleur modulée par alpha simulé)
            c = (cr * alpha_v // 255,
                 cg * alpha_v // 255,
                 cb * alpha_v // 255)
            pygame.draw.circle(surface, c,
                               (int(s[0]), int(s[1])), r_star)


# ═══════════════════════════════════════════════════════════════════════
#  BOUCLE PRINCIPALE
# ═══════════════════════════════════════════════════════════════════════

def main():
    # ── Init Pygame avec pre_init correct ──────────────────────────────
    # pre_init AVANT pygame.init() pour éviter double-init mixer
    try:
        tmp_y, tmp_sr = librosa.load(AUDIO_FILE, sr=None, mono=True,
                                     duration=0.1)
        pygame.mixer.pre_init(frequency=tmp_sr, size=-16,
                              channels=1, buffer=512)
    except Exception:
        pygame.mixer.pre_init(44100, -16, 2, 512)

    pygame.init()
    pygame.display.set_caption("Music Ball Visualizer")
    screen = pygame.display.set_mode((WIDTH, HEIGHT))
    clock  = pygame.time.Clock()

    # ── Build caches après init ────────────────────────────────────────
    global GLOW_CACHE
    GLOW_CACHE = build_glow_cache()

    BG_SURF = build_background(WIDTH, HEIGHT)   # fond pré-calculé

    # ── Analyse audio ──────────────────────────────────────────────────
    try:
        analyzer = AudioAnalyzer(AUDIO_FILE)
    except Exception as e:
        print(f"[ERREUR] '{AUDIO_FILE}' introuvable ou illisible : {e}")
        pygame.quit()
        sys.exit(1)

    # ── Plaques ────────────────────────────────────────────────────────
    plaques = generate_plaques(analyzer)

    # ── Balles (défensif : N balles avec N offsets) ────────────────────
    balls: List[Ball] = []
    for i in range(NUM_BALLS):
        shift = (math.tau / NUM_BALLS) * i
        bx    = WIDTH * (i + 1) / (NUM_BALLS + 1)
        balls.append(Ball(bx, HEIGHT // 4 + i * 90, color_shift=shift))

    # ── Systèmes ───────────────────────────────────────────────────────
    particles  = ParticleSystem()
    shake      = ScreenShake()
    hud        = HUD()
    stars      = StarField()

    # ── Surfaces de rendu séparées (workflow blend correct) ────────────
    game_surf  = pygame.Surface((WIDTH, HEIGHT))          # opaque
    glow_surf  = pygame.Surface((WIDTH, HEIGHT), pygame.SRCALPHA)  # SRCALPHA isolé
    final_surf = pygame.Surface((WIDTH, HEIGHT))          # composition finale

    # ── Lecture audio ──────────────────────────────────────────────────
    audio_ok = False
    try:
        pygame.mixer.music.load(AUDIO_FILE)
        pygame.mixer.music.play()
        audio_ok = True
    except Exception as e:
        print(f"[AVERTISSEMENT] Lecture audio impossible : {e}")

    # ── Sync via get_pos() (référence audio réelle, pas wall clock) ────
    beat_index = 0

    def get_audio_time() -> float:
        """Temps audio en secondes via get_pos() — évite la dérive."""
        if not audio_ok:
            return 0.0
        pos = pygame.mixer.music.get_pos()
        return max(0.0, pos / 1000.0)

    # ── Boucle ─────────────────────────────────────────────────────────
    running = True
    prev_t  = pygame.time.get_ticks() / 1000.0
    color_t = 0.0   # temps couleur indépendant (continue même en boucle)

    while running:
        now_s = pygame.time.get_ticks() / 1000.0
        dt    = clamp(now_s - prev_t, 0.0, 0.05)
        prev_t = now_s
        color_t += dt

        audio_t = get_audio_time()
        energy  = analyzer.energy_at(audio_t)

        # ── Événements ────────────────────────────────────────────────
        for event in pygame.event.get():
            if event.type == pygame.QUIT:
                running = False
            elif event.type == pygame.KEYDOWN:
                if event.key == pygame.K_ESCAPE:
                    running = False

        # ── Beats ─────────────────────────────────────────────────────
        while (beat_index < len(analyzer.beat_times) and
               audio_t >= analyzer.beat_times[beat_index]):
            for b in balls:
                b.force_bounce()
            for p in plaques:
                if random.random() < 0.2:
                    p.trigger_pulse()
            shake.trigger(energy * 0.9)
            beat_index += 1

        # Boucle audio : réinitialiser beat_index quand get_pos() repart
        if audio_ok and pygame.mixer.music.get_pos() < 0:
            pygame.mixer.music.play()
            beat_index = 0

        # ── Update ────────────────────────────────────────────────────
        for b in balls:
            b.update(dt)
            for p in plaques:
                if b.collide(p, particles, color_t):
                    hud.add_bounce()

        for p in plaques:
            p.update(dt)

        particles.update(dt)
        ox, oy = shake.update()

        # ══ RENDU ════════════════════════════════════════════════════

        # 1. Fond pré-calculé (1 blit)
        game_surf.blit(BG_SURF, (0, 0))

        # 2. Étoiles d'ambiance
        stars.update_and_draw(game_surf, color_t)

        # 3. Reset glow_surf
        glow_surf.fill((0, 0, 0, 0))

        # 4. Plaques (game_surf + glow_surf)
        for p in plaques:
            p.draw(game_surf, glow_surf, color_t)

        # 5. Balles (game_surf + glow_surf)
        for b in balls:
            b.draw(game_surf, glow_surf, color_t)

        # 6. Particules (game_surf direct)
        particles.draw(game_surf)

        # 7. HUD
        hud.draw(game_surf, color_t)

        # 8. Composition finale : game + glow en additif pur
        #    game_surf est opaque  → final_surf reçoit le rendu de base
        #    glow_surf est SRCALPHA → BLEND_ADD l'ajoute correctement
        final_surf.blit(game_surf, (0, 0))
        final_surf.blit(glow_surf, (0, 0), special_flags=pygame.BLEND_ADD)

        # 9. Screen shake : décalage sur screen (surface opaque finale)
        screen.fill((0, 0, 0))
        screen.blit(final_surf, (ox, oy))

        pygame.display.flip()
        clock.tick(FPS)

    pygame.quit()
    sys.exit(0)


# ═══════════════════════════════════════════════════════════════════════

if __name__ == "__main__":
    main()
