import fs from 'fs';
import { buildScene, toGLB } from 'openskp';

const [, , inputPath, outputPath] = process.argv;

if (!inputPath || !outputPath) {
    console.error('Usage: node convert-skp-to-glb.mjs <input.skp> <output.glb>');
    process.exit(1);
}

if (!fs.existsSync(inputPath)) {
    console.error('Input file not found:', inputPath);
    process.exit(1);
}

try {
    const buffer = fs.readFileSync(inputPath);
    const scene = buildScene(buffer);
    const glb = toGLB(scene);
    fs.writeFileSync(outputPath, Buffer.from(glb));
    console.log(`Successfully converted ${inputPath} to ${outputPath} (${glb.length} bytes)`);
    process.exit(0);
} catch (err) {
    console.error('Conversion error:', err?.message || err);
    process.exit(1);
}

