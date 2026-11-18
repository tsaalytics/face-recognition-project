export async function createKioskDetector(basePath) {
    const { FaceDetector, FilesetResolver } = await import('@mediapipe/tasks-vision');
    const files = await FilesetResolver.forVisionTasks(`${basePath}/wasm`);
    return FaceDetector.createFromOptions(files, {
        baseOptions: { modelAssetPath: `${basePath}/blaze_face_short_range.tflite`, delegate: 'CPU' },
        runningMode: 'VIDEO', minDetectionConfidence: 0.75,
    });
}
