"""Generate small catalogue, thumbnail and detail WebP copies; keep originals."""
from pathlib import Path
from PIL import Image, ImageOps
root = Path(__file__).resolve().parent.parent
output = root / "assets/optimized"
output.mkdir(exist_ok=True)
for source in (root / "assets/products").glob("*.jpg"):
    with Image.open(source) as original:
        image = ImageOps.exif_transpose(original).convert("RGB")
        for width in (240, 640, 1280):
            scaled = image.copy()
            scaled.thumbnail((width, width), Image.Resampling.LANCZOS)
            scaled.save(output / f"{source.stem}-{width}.webp", "WEBP", quality=78, method=6)
