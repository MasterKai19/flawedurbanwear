"""Split approved front/back diptychs into web-ready gallery assets."""
from pathlib import Path
from PIL import Image
root = Path(__file__).resolve().parent.parent
source = root.parent / 'output'
for name in ('living', 'flawed', 'memories', 'grateful', 'miracle', 'journey'):
    folder = 'hoodie-mockups-v2' if name in ('grateful', 'miracle', 'journey') else 'clothing-mockups'
    with Image.open(source / folder / f'{name}-front-back.png') as original:
        original = original.convert('RGB')
        w, h = original.size
        for side, box in [('front', (0, 0, w//2, h)), ('back', (w//2, 0, w, h))]:
            photo = original.crop(box)
            if name == 'grateful' and side == 'front':
                # Approved correction: this hoodie has no drawstrings or eyelets.
                with Image.open(source / 'hoodie-mockups-v2/grateful-front-no-drawstrings.png') as correction:
                    photo = correction.convert('RGB')
                    photo.thumbnail((768, 1024), Image.Resampling.LANCZOS)
            stem = f'fu-{name}-mockup-v2-{side}'
            photo.save(root / 'assets/products' / f'{stem}.webp', quality=86, method=6)
            for size in (240, 640, 1280):
                small = photo.copy()
                small.thumbnail((size, size), Image.Resampling.LANCZOS)
                small.save(root / 'assets/optimized' / f'{stem}-{size}.webp', quality=80, method=6)
