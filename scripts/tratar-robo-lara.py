"""Trata o GIF do robô da Lara para a web.

Recorta o robô, troca o fundo preto por transparência (só o preto ligado às
bordas — os olhos e o sorriso, também pretos, ficam), limpa a franja escura das
bordas, reduz e grava WebP animado com alfa, mais PNGs estáticos.

Uso: python -I scripts/tratar-robo-lara.py <gif> <pasta-de-saida>
     python -I scripts/tratar-robo-lara.py <gif> <pasta-de-saida> --balao

Sem opção, gera o robô de corpo inteiro (estado vazio e avatares). Com --balao,
gera só o ícone do balão (o robô dentro do anel grená): lara-balao.webp e
lara-balao.png.
"""
import sys
from pathlib import Path

import numpy as np
from PIL import Image, ImageSequence

SRC, OUT = Path(sys.argv[1]), Path(sys.argv[2])
OUT.mkdir(parents=True, exist_ok=True)

BALAO = "--balao" in sys.argv
# Quadros: um a cada STEP. O GIF do balão tem 395 quadros de 30 ms; um a cada
# 3 (90 ms) ainda acena liso e deixa o arquivo pequeno.
STEP = 3 if BALAO else 2
DARK = 40         # abaixo disso (no canal mais claro) conta como fundo
PAD = 12

# Área preta fechada maior que isto (fração do quadro) também é fundo: o vão
# entre o braço que acena e o capacete. Olhos e boca são bem menores.
ENCLOSED_BG = 0.0005
# Medido no 1º quadro (320 px): vãos do braço com média 2,7 e 7,8; olhos 27–28.
BG_MEAN = 12.0
DEBUG = "--debug" in sys.argv

# 1) Caixa do robô somando todos os quadros (o braço que acena mexe). Duas
# passadas para não guardar centenas de quadros 1920x1080 na memória.
box, total, count = None, 0, 0
gif = Image.open(SRC)
for i, f in enumerate(ImageSequence.Iterator(gif)):
    total += f.info.get("duration", 0) or 30
    if i % STEP:
        continue
    count += 1
    a = np.asarray(f.convert("RGB"))
    ys, xs = np.nonzero(a.max(axis=2) > DARK)
    b = (xs.min(), ys.min(), xs.max(), ys.max())
    box = b if box is None else (min(box[0], b[0]), min(box[1], b[1]), max(box[2], b[2]), max(box[3], b[3]))
W, H = gif.size
box = (max(0, box[0] - PAD), max(0, box[1] - PAD), min(W, box[2] + PAD), min(H, box[3] + PAD))
# Mesma duração total do original, dividida pelos quadros que ficaram.
delay = round(total / count)
print(f"{count} quadros, {delay} ms cada, {total} ms no total; recorte {box}")

gif = Image.open(SRC)
frames = [f.convert("RGB").crop(box) for i, f in enumerate(ImageSequence.Iterator(gif)) if i % STEP == 0]


def dilate(m):
    d = m.copy()
    d[1:, :] |= m[:-1, :]
    d[:-1, :] |= m[1:, :]
    d[:, 1:] |= m[:, :-1]
    d[:, :-1] |= m[:, 1:]
    return d


def cutout(frame, height):
    f = frame
    w = round(f.width * height / f.height)
    f = f.resize((w, height), Image.LANCZOS)
    rgb = np.asarray(f).astype(np.float32)
    v = rgb.max(axis=2)
    dark = v < DARK

    # Fundo = escuro ligado à borda.
    bg = np.zeros_like(dark)
    bg[0, :], bg[-1, :], bg[:, 0], bg[:, -1] = dark[0, :], dark[-1, :], dark[:, 0], dark[:, -1]
    while True:
        nxt = dilate(bg) & dark
        if (nxt == bg).all():
            break
        bg = nxt

    # Regiões escuras que sobraram: as grandes viram fundo também. No balão o
    # vão do braço é o cinza de dentro do anel, e a regra apagaria a pupila.
    rest = dark & ~bg & (not BALAO)
    seen = np.zeros_like(rest)
    limit = ENCLOSED_BG * rest.size
    hh, ww = rest.shape
    for y0, x0 in zip(*np.nonzero(rest)):
        if seen[y0, x0]:
            continue
        stack, comp = [(y0, x0)], []
        seen[y0, x0] = True
        while stack:
            y, x = stack.pop()
            comp.append((y, x))
            for ny, nx in ((y + 1, x), (y - 1, x), (y, x + 1), (y, x - 1)):
                if 0 <= ny < hh and 0 <= nx < ww and rest[ny, nx] and not seen[ny, nx]:
                    seen[ny, nx] = True
                    stack.append((ny, nx))
        ys, xs = zip(*comp)
        mean_v = float(v[list(ys), list(xs)].mean())
        if DEBUG and len(comp) > 30:
            print(f"  região {len(comp)} px, média {mean_v:.1f}, em ({xs[0]},{ys[0]})")
        # O fundo do GIF é preto puro; pupila e boca são azul-marinho escuro.
        if len(comp) > limit and mean_v < BG_MEAN:
            bg[list(ys), list(xs)] = True

    alpha = np.where(bg, 0.0, 1.0)
    # Franja: pixels do robô encostados no fundo ficaram escurecidos pelo
    # antisserrilhado contra o preto. Vira alfa parcial e a cor é "desfeita".
    band = dilate(dilate(bg)) & ~bg
    a = np.clip((v - 8) / 110.0, 0.0, 1.0)
    alpha = np.where(band, np.minimum(alpha, a), alpha)
    safe = np.maximum(alpha, 1e-3)[..., None]
    rgb = np.where(band[..., None], np.clip(rgb / safe, 0, 255), rgb)

    out = np.dstack([rgb, alpha * 255]).astype(np.uint8)
    return Image.fromarray(out, "RGBA")


if BALAO:
    # Mostrado com 72 px de altura: 144 cobre tela de alta densidade.
    seq = [cutout(f, 144) for f in frames]
    path = OUT / "lara-balao.webp"
    seq[0].save(path, save_all=True, append_images=seq[1:], duration=delay, loop=0,
                quality=72, alpha_quality=85, method=6)
    seq[0].save(OUT / "lara-balao.png", optimize=True)
    for p in (path, OUT / "lara-balao.png"):
        print(p.name, f"{p.stat().st_size / 1024:.0f} KB", seq[0].size)
    sys.exit()

for height, quality in ((320, 75),):
    seq = [cutout(f, height) for f in frames]
    path = OUT / f"lara-robo-{height}.webp"
    seq[0].save(path, save_all=True, append_images=seq[1:], duration=delay, loop=0,
                quality=quality, alpha_quality=85, method=6)
    print(path.name, f"{path.stat().st_size / 1024:.0f} KB", seq[0].size)
    if height == 320:
        first = seq[0]
        first.save(OUT / "lara-robo.png", optimize=True)
        # Cabeça para o avatar redondo: só o capacete, sem a mão que acena
        # (que fica à esquerda, na altura do queixo).
        w, h = first.size
        head = first.crop((round(w * 0.27), 0, round(w * 0.95), round(h * 0.37)))
        side = max(head.size)
        sq = Image.new("RGBA", (side, side), (0, 0, 0, 0))
        sq.paste(head, ((side - head.width) // 2, (side - head.height) // 2))
        sq.resize((96, 96), Image.LANCZOS).save(OUT / "lara-robo-cabeca.png", optimize=True)

for p in sorted(OUT.iterdir()):
    print(p.name, f"{p.stat().st_size / 1024:.0f} KB")
