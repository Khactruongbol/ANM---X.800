import path from "node:path";
import fs from "node:fs/promises";
import { fileURLToPath, pathToFileURL } from "node:url";

const workspaceDir = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const skillDir = "C:/Users/User/.codex/plugins/cache/openai-primary-runtime/presentations/26.1105.12137/skills/presentations";
const stagingDir = path.join(workspaceDir, ".codex-finalizer");
const candidatePath = path.join(workspaceDir, ".codex-presentation-build", "candidate.pptx");
const finalPath = path.join(workspaceDir, "output", "presentation", "X800_Web_Security_Mapping_15_Slides.pptx");
const receiptPath = path.join(stagingDir, "X800_Web_Security_Mapping_15_Slides.validation.json");
await fs.mkdir(stagingDir, { recursive: true });

const { finalizePresentation } = await import(pathToFileURL(path.join(skillDir, "container_tools", "artifact_tool_utils.mjs")).href);
const result = await finalizePresentation({
  explicitTotalSlideCount: 15,
  requiredNativeTableOwnerSlides: [],
  requiredNativeChartOwnerSlides: [],
  workspaceDir,
  candidatePath,
  finalPath,
  pythonExecutable: "C:/Program Files/WindowsApps/OpenAI.CodexPrimaryRuntime.v26-1105-1297-0_26.1105.1297.0_x64__3k8sg7r9htsxt/dependencies/python/python.exe",
  integrityValidatorPath: path.join(skillDir, "container_tools", "inspect_presentation_package_integrity.py"),
  layoutValidatorPath: path.join(skillDir, "container_tools", "inspect_presentation_layout_geometry.py"),
  layoutArgs: ["--expected-slide-size-emu", "12192000,6858000", "--validate-heading-fit"],
  requiredNativeTableOwnerSlides: [],
  fontPolicy: { basis: "design", families: ["Arial"] },
  verifyArtifactToolImport: false,
  receiptPath,
});
console.log(JSON.stringify(result));
