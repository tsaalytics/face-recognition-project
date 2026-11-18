import { cpSync, mkdirSync, readFileSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { fileURLToPath } from 'node:url';
const source = fileURLToPath(new URL('../node_modules/@mediapipe/tasks-vision/wasm/', import.meta.url));
const target = fileURLToPath(new URL('../public/vendor/kiosk-face-detector/wasm/', import.meta.url));
const model = readFileSync(new URL('../public/vendor/kiosk-face-detector/blaze_face_short_range.tflite', import.meta.url));
if (createHash('sha256').update(model).digest('hex') !== 'b4578f35940bf5a1a655214a1cce5cab13eba73c1297cd78e1a04c2380b0152f') {
    throw new Error('Kiosk face detector model is missing or does not match the pinned official asset.');
}
mkdirSync(target, { recursive: true });
cpSync(source, target, { recursive: true });
