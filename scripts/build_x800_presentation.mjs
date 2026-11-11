import fs from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { Presentation, PresentationFile } from "@oai/artifact-tool";

const workspaceDir = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const assetDir = path.join(workspaceDir, "assets", "presentation", "x800");
const buildDir = path.join(workspaceDir, ".codex-presentation-build");
const outputDir = path.join(workspaceDir, "output", "presentation");
const finalPptx = path.join(outputDir, "X800_Web_Security_Mapping_15_Slides.pptx");

await fs.mkdir(buildDir, { recursive: true });
await fs.mkdir(outputDir, { recursive: true });

const presentation = Presentation.create({
  slideSize: { width: 1280, height: 720 },
});

for (let index = 1; index <= 15; index += 1) {
  const slide = presentation.slides.add();
  slide.background.fill = "white";
  const imagePath = path.join(assetDir, `x800-slide-${String(index).padStart(2, "0")}.png`);
  const bytes = await fs.readFile(imagePath);
  const blob = bytes.buffer.slice(bytes.byteOffset, bytes.byteOffset + bytes.byteLength);
  slide.images.add({
    blob,
    contentType: "image/png",
    alt: `X.800 presentation slide ${index}`,
    fit: "cover",
    position: { left: 0, top: 0, width: 1280, height: 720 },
  });
}

for (const [index, slide] of presentation.slides.items.entries()) {
  const png = await presentation.export({ slide, format: "png", scale: 1 });
  await fs.writeFile(path.join(buildDir, `slide-${String(index + 1).padStart(2, "0")}.png`), new Uint8Array(await png.arrayBuffer()));
}

const pptx = await PresentationFile.exportPptx(presentation);
await pptx.save(path.join(buildDir, "candidate.pptx"));
console.log(JSON.stringify({ candidate: path.join(buildDir, "candidate.pptx"), final: finalPptx, slides: presentation.slides.items.length }));
