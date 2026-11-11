from pathlib import Path
import tempfile
from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
OUTPUT = Path(tempfile.gettempdir()) / "tokyo-premium-webp"
OUTPUT.mkdir(parents=True, exist_ok=True)
folders = [ROOT / "assets" / "food", ROOT / "assets" / "generated"]
for folder in folders:
    for source in folder.glob("*.png"):
        with Image.open(source) as image:
            image = image.convert("RGB")
            image.thumbnail((1800, 1400), Image.Resampling.LANCZOS)
            target = OUTPUT / (source.stem + ".webp")
            image.save(target, "WEBP", quality=84, method=6)
            print(f"{source.name}: {source.stat().st_size:,} -> {target.stat().st_size:,} bytes")
