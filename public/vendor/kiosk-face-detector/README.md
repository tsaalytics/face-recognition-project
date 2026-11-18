# Local face-presence detector

This detector only locates a face in the browser to trigger recognition. It does not produce employee identities or replace InsightFace buffalo_m.

- Runtime: @mediapipe/tasks-vision 1.2.3, pinned in package-lock.json (Apache-2.0).
- Official model: https://storage.googleapis.com/mediapipe-models/face_detector/blaze_face_short_range/float16/1/blaze_face_short_range.tflite
- Model SHA-256: b4578f35940bf5a1a655214a1cce5cab13eba73c1297cd78e1a04c2380b0152f
- Google documentation: https://developers.google.com/edge/mediapipe/solutions/vision/face_detector?tab=Web

The model is included locally. npm run build and npm run dev copy the matching package's WASM files into the generated wasm/ subdirectory and verify the model checksum. Deploy the generated public/vendor/kiosk-face-detector/wasm directory together with public/build and the model. The generated WASM files are excluded from Git; install dependencies and run the build on a fresh checkout.

All runtime/model downloads for the kiosk browser come from the application's origin. Camera frames are processed in memory. The detector performs neither identity matching nor liveness detection.
